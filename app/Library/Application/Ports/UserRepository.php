<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Domain\User;
interface UserRepository
{
    public function findUser(string $id): ?User;
    public function findByUsername(string $username): ?User;
    public function addUser(User $user): void;
}
