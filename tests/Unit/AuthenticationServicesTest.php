<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Library\Application\Services\{CreateLibrarianService, LoginService};
use App\Library\Domain\DomainError;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\{FakePasswords, FakeTokens, MemoryUsers, SequenceIds};

final class AuthenticationServicesTest extends TestCase
{
    public function test_create_and_login_preserve_password_spaces_and_eight_hour_contract(): void
    {
        $users = new MemoryUsers();
        $passwords = new FakePasswords();
        $password = ' secret with spaces ';
        $created = (new CreateLibrarianService($users, $passwords, new SequenceIds()))->execute(' Ana ', $password, 'librarian');
        $this->assertSame('ana', $created->username);
        $this->assertNotSame($password, $created->passwordHash());
        $this->assertStringNotContainsString($password, json_encode($created));
        $response = (new LoginService($users, $passwords, new FakeTokens()))->execute('ANA', $password);
        $this->assertSame(28800, $response['expires_in']);
        $this->assertSame('Bearer', $response['token_type']);
        $this->assertSame('librarian', $response['role']);
    }

    public function test_invalid_credentials_do_not_reveal_which_field_was_wrong(): void
    {
        $users = new MemoryUsers();
        $passwords = new FakePasswords();
        (new CreateLibrarianService($users, $passwords, new SequenceIds()))->execute('ana', 'value', 'librarian');
        $login = new LoginService($users, $passwords, new FakeTokens());
        $messages = [];
        foreach ([['ana', 'wrong'], ['missing', 'value']] as [$name, $password]) {
            try { $login->execute($name, $password); $this->fail('Invalid credentials accepted'); }
            catch (DomainError $error) { $this->assertSame('INVALID_CREDENTIALS', $error->errorCode); $messages[] = $error->getMessage(); }
        }
        $this->assertSame($messages[0], $messages[1]);
    }

    public function test_duplicate_normalized_username_fails(): void
    {
        $create = new CreateLibrarianService(new MemoryUsers(), new FakePasswords(), new SequenceIds());
        $create->execute('Ana', 'value', 'librarian');
        try { $create->execute(' ana ', 'value', 'librarian'); $this->fail('Duplicate accepted'); }
        catch (DomainError $error) { $this->assertSame('USERNAME_TAKEN', $error->errorCode); }
    }

    public function test_admin_role_is_rejected_without_persisting_user(): void
    {
        $users = new MemoryUsers();
        try { (new CreateLibrarianService($users, new FakePasswords(), new SequenceIds()))->execute('admin', 'value', 'admin'); $this->fail('Admin created'); }
        catch (DomainError $error) { $this->assertSame('VALIDATION_ERROR', $error->errorCode); $this->assertNull($users->findByUsername('admin')); }
    }
}
