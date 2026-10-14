<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\UserRepository;
use App\Library\Domain\{DomainError, User};
final class MemoryUsers implements UserRepository
{
    private array $users = [];
    public function findUser(string $id): ?User { return $this->users[$id] ?? null; }
    public function findByUsername(string $username): ?User
    {
        foreach ($this->users as $user) { if ($user->username === $username) { return $user; } }
        return null;
    }
    public function addUser(User $user): void
    {
        if ($this->findByUsername($user->username)) { throw new DomainError('USERNAME_TAKEN', 'El nombre de usuario ya está registrado.'); }
        $this->users[$user->id] = $user;
    }
}
