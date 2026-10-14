<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\PasswordHasher;
/** Test double only. Production uses its own slow password-hashing adapter. */
final class FakePasswords implements PasswordHasher
{
    public function hash(#[\SensitiveParameter] string $password): string { return 'test-only:'.hash('sha256', $password); }
    public function verify(#[\SensitiveParameter] string $password, string $hash): bool { return hash_equals($this->hash($password), $hash); }
}
