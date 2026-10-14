<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE library.category (
    id uuid PRIMARY KEY,
    name varchar(120) NOT NULL UNIQUE
);
CREATE TABLE library.book (
    id uuid PRIMARY KEY,
    title varchar(250) NOT NULL,
    author varchar(200) NOT NULL,
    isbn char(13) NOT NULL,
    category_id uuid NOT NULL REFERENCES library.category(id) ON DELETE RESTRICT,
    total_copies integer NOT NULL,
    available_copies integer NOT NULL,
    cover_key varchar(512) NULL,
    deleted_at timestamptz NULL,
    CONSTRAINT ck_book_available_range CHECK (available_copies >= 0 AND available_copies <= total_copies)
);
CREATE TABLE library.member (
    id uuid PRIMARY KEY,
    full_name varchar(200) NOT NULL,
    document_number varchar(30) NOT NULL UNIQUE
);
CREATE TABLE library."user" (
    id uuid PRIMARY KEY,
    username varchar(120) NOT NULL UNIQUE,
    password_hash varchar(512) NOT NULL,
    role varchar(40) NOT NULL
);
CREATE TABLE library.loan (
    id uuid PRIMARY KEY,
    member_id uuid NOT NULL REFERENCES library.member(id) ON DELETE RESTRICT,
    loaned_by_user_id uuid NOT NULL REFERENCES library."user"(id) ON DELETE RESTRICT,
    loaned_at timestamptz NOT NULL
);
CREATE TABLE library.loan_item (
    id uuid PRIMARY KEY,
    loan_id uuid NOT NULL REFERENCES library.loan(id) ON DELETE CASCADE,
    book_id uuid NOT NULL REFERENCES library.book(id) ON DELETE RESTRICT,
    book_title varchar(250) NOT NULL,
    isbn char(13) NOT NULL,
    category_name varchar(120) NOT NULL,
    due_at timestamptz NOT NULL,
    returned_at timestamptz NULL,
    UNIQUE (loan_id, book_id)
);
CREATE UNIQUE INDEX uq_book_active_isbn ON library.book (isbn) WHERE deleted_at IS NULL;
CREATE INDEX ix_book_active_category_title ON library.book (category_id, title) WHERE deleted_at IS NULL;
CREATE INDEX ix_loan_loaned_at ON library.loan (loaned_at);
CREATE INDEX ix_loan_item_book ON library.loan_item (book_id);
CREATE INDEX ix_loan_item_pending_due ON library.loan_item (due_at) WHERE returned_at IS NULL;
SQL);
        DB::table('library.category')->insert([
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Tecnología'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Historia'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Infantil'],
        ]);
    }

    public function down(): void
    {
        throw new LogicException('Initial business schema is not destructively rolled back.');
    }
};
