<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{IdentifierGenerator, PasswordHasher, UserRepository};
use App\Library\Domain\{DomainError, User};

final readonly class CreateLibrarianService
{
    public function __construct(private UserRepository $users, private PasswordHasher $passwords, private IdentifierGenerator $ids) {}

    public function execute(string $username, #[\SensitiveParameter] string $password, string $role): User
    {
        if ($password === '') {
            throw new DomainError('VALIDATION_ERROR', 'La contraseña es obligatoria.');
        }
        // Validate the requested role before checking for an existing account.
        $user = User::librarian($this->ids->next(), $username, $this->passwords->hash($password), $role);
        if ($this->users->findByUsername($user->username)) {
            throw new DomainError('USERNAME_TAKEN', 'El nombre de usuario ya está registrado.');
        }
        $this->users->addUser($user);
        return $user;
    }
}
