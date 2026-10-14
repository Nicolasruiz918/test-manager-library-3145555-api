<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('DB_DATABASE') !== 'manager_library_test') {
            $this->markTestSkipped('Run with compose.test.yaml and its disposable PostgreSQL database.');
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (getenv('DB_DATABASE') === 'manager_library_test') {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        parent::tearDown();
    }

    private function migrate(): void
    {
        $this->assertSame(0, $this->app->make(Kernel::class)->call('library:migrate'));
    }

    public function test_exact_six_tables_thirty_columns_and_no_defaults(): void
    {
        $this->assertSame([], DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema IN ('library', 'public')"));
        $this->migrate();
        $rows = DB::select("SELECT table_name, column_name, data_type, character_maximum_length, is_nullable, column_default FROM information_schema.columns WHERE table_schema = 'library' ORDER BY table_name, ordinal_position");
        $expected = [
            'book' => ['id:uuid', 'title:character varying:250', 'author:character varying:200', 'isbn:character:13', 'category_id:uuid', 'total_copies:integer', 'available_copies:integer', 'cover_key:character varying:512:nullable', 'deleted_at:timestamp with time zone:nullable'],
            'category' => ['id:uuid', 'name:character varying:120'],
            'loan' => ['id:uuid', 'member_id:uuid', 'loaned_by_user_id:uuid', 'loaned_at:timestamp with time zone'],
            'loan_item' => ['id:uuid', 'loan_id:uuid', 'book_id:uuid', 'book_title:character varying:250', 'isbn:character:13', 'category_name:character varying:120', 'due_at:timestamp with time zone', 'returned_at:timestamp with time zone:nullable'],
            'member' => ['id:uuid', 'full_name:character varying:200', 'document_number:character varying:30'],
            'user' => ['id:uuid', 'username:character varying:120', 'password_hash:character varying:512', 'role:character varying:40'],
        ];
        $actual = [];
        foreach ($rows as $row) {
            $this->assertNull($row->column_default);
            $actual[$row->table_name][] = $row->column_name.':'.$row->data_type
                .($row->character_maximum_length === null ? '' : ':'.$row->character_maximum_length)
                .($row->is_nullable === 'YES' ? ':nullable' : '');
        }
        $this->assertCount(30, $rows);
        $this->assertSame($expected, $actual);
        $this->assertSame([], DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'"));
        $this->assertSame('UTC', DB::selectOne('SHOW timezone')->TimeZone);
    }

    public function test_category_values_are_exact_and_migrations_are_repeatable(): void
    {
        $this->migrate();
        $before = DB::select("SELECT obj_description(oid, 'pg_namespace') AS metadata FROM pg_namespace WHERE nspname = 'library'");
        $this->migrate();
        $this->assertEquals($before, DB::select("SELECT obj_description(oid, 'pg_namespace') AS metadata FROM pg_namespace WHERE nspname = 'library'"));
        $this->assertSame([
            '11111111-1111-4111-8111-111111111111' => 'Novela',
            '22222222-2222-4222-8222-222222222222' => 'Ciencia',
            '33333333-3333-4333-8333-333333333333' => 'Tecnología',
            '44444444-4444-4444-8444-444444444444' => 'Historia',
            '55555555-5555-4555-8555-555555555555' => 'Infantil',
        ], DB::table('library.category')->orderBy('id')->pluck('name', 'id')->all());
        $this->assertSame(['2026_10_14_000001_create_library', '2026_10_14_000002_enforce_total_and_role'], $this->app['migration.repository']->getRan());
    }

    public function test_foreign_keys_indexes_and_specified_checks_are_present(): void
    {
        $this->migrate();
        $constraints = DB::select("SELECT c.conrelid::regclass::text AS relation, c.conname, c.contype, pg_get_constraintdef(c.oid) AS definition FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace WHERE n.nspname = 'library'");
        $foreignKeys = array_values(array_filter($constraints, static fn ($c) => $c->contype === 'f'));
        $this->assertCount(5, $foreignKeys);
        $this->assertCount(1, array_filter($foreignKeys, static fn ($c) => str_contains($c->definition, 'ON DELETE CASCADE')));
        $this->assertCount(4, array_filter($foreignKeys, static fn ($c) => str_contains($c->definition, 'ON DELETE RESTRICT')));
        $this->assertCount(6, array_filter($constraints, static fn ($c) => $c->contype === 'p'));
        $checks = array_values(array_filter($constraints, static fn ($c) => $c->contype === 'c'));
        $names = array_column($checks, 'conname'); sort($names);
        $this->assertSame(['ck_book_available_range', 'ck_book_total_positive', 'ck_user_role'], $names);
        $indexes = DB::select("SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = 'library'");
        $this->assertCount(15, $indexes);
        $definitions = array_column($indexes, 'indexdef', 'indexname');
        $this->assertStringContainsString('UNIQUE INDEX', $definitions['uq_book_active_isbn']);
        $this->assertStringContainsString('WHERE (deleted_at IS NULL)', $definitions['uq_book_active_isbn']);
        $this->assertStringContainsString('(category_id, title)', $definitions['ix_book_active_category_title']);
        $this->assertStringContainsString('(loaned_at)', $definitions['ix_loan_loaned_at']);
        $this->assertStringContainsString('(book_id)', $definitions['ix_loan_item_book']);
        $this->assertStringContainsString('WHERE (returned_at IS NULL)', $definitions['ix_loan_item_pending_due']);
    }

    public function test_rollback_restores_both_tables_and_history(): void
    {
        DB::beginTransaction();
        $this->migrate();
        DB::rollBack();
        $this->assertFalse($this->app['migration.repository']->repositoryExists());
        $this->assertSame([], DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'library'"));
        $this->migrate();
        $this->assertCount(5, DB::table('library.category')->get());
    }
}
