<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\UserRepository;
use App\Library\Domain\User;
final class PgUserRepository extends PgGateway implements UserRepository
{
    private function hydrate(?object $row): ?User { return $row ? new User($row->id, $row->username, $row->password_hash, $row->role) : null; }
    public function findUser(string $id): ?User { return $this->hydrate($this->db->table('library.user')->where('id', $id)->first()); }
    public function findByUsername(string $username): ?User { return $this->hydrate($this->db->table('library.user')->where('username', $username)->first()); }
    public function addUser(User $user): void
    {
        $this->insertUnique('library.user', ['id' => $user->id, 'username' => $user->username, 'password_hash' => $user->passwordHash(), 'role' => $user->role], 'user_username_key', 'USERNAME_TAKEN', 'El nombre de usuario ya está registrado.');
    }
}
