<?php
declare(strict_types=1);
namespace Tests\Integration;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('DB_DATABASE') !== 'manager_library_test') { $this->markTestSkipped('Use the disposable PostgreSQL test stack.'); }
        DB::beginTransaction();
        $this->assertSame(0, $this->app->make(Kernel::class)->call('library:migrate'));
    }
    protected function tearDown(): void
    {
        if (getenv('DB_DATABASE') === 'manager_library_test') { while (DB::transactionLevel() > 0) { DB::rollBack(); } }
        parent::tearDown();
    }
}
