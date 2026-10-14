<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Security;
use App\Library\Application\Ports\PasswordHasher;
final class ArgonPasswords implements PasswordHasher
{
    private ?string $dummy = null;
    public function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
    }
    public function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        if ($hash === '') { $this->dummy ??= $this->hash(bin2hex(random_bytes(32))); $hash = $this->dummy; }
        return password_verify($password, $hash);
    }
}
