<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
interface PasswordHasher
{
    public function hash(#[\SensitiveParameter] string $password): string;
    public function verify(#[\SensitiveParameter] string $password, string $hash): bool;
}
