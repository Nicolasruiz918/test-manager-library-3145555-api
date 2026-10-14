<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Library\Domain\{DomainError, Member, User};
use PHPUnit\Framework\TestCase;

final class IdentityTest extends TestCase
{
    public function test_username_is_normalized_without_changing_member_names(): void
    {
        $user = User::librarian('user', 'Ana ', 'fake-test-hash', 'librarian');
        $this->assertSame('ana', $user->username);
        $this->assertSame('Ángela Ruiz', (new Member('member', 'Ángela Ruiz', '001'))->fullName);
        $this->assertSame('001', (new Member('member', 'Ana', '001'))->documentNumber);
        $this->assertSame(['id' => 'user', 'username' => 'ana', 'role' => 'librarian'], json_decode(json_encode($user), true));
    }

    public function test_runtime_creation_cannot_grant_admin(): void
    {
        $this->expectException(DomainError::class);
        User::librarian('user', 'admin', 'fake-test-hash', 'admin');
    }

    public function test_unknown_role_is_rejected_on_hydration(): void
    {
        $this->expectException(DomainError::class);
        new User('user', 'name', 'fake-test-hash', 'owner');
    }

    public function test_blank_member_document_is_rejected(): void
    {
        $this->expectException(DomainError::class);
        new Member('member', 'Ana', '  ');
    }
}
