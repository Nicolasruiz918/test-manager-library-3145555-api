<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared("ALTER TABLE library.book ADD CONSTRAINT ck_book_total_positive CHECK (total_copies >= 1)");
        DB::unprepared("ALTER TABLE library.\"user\" ADD CONSTRAINT ck_user_role CHECK (role IN ('admin', 'librarian'))");
    }
    public function down(): void { throw new LogicException('Required business invariants are not silently removed.'); }
};
