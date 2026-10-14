<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{UserRepository, PasswordHasher, TokenIssuer};
use App\Library\Domain\{DomainError, User};

final readonly class LoginService
{
    public function __construct(private UserRepository $users, private PasswordHasher $passwords, private TokenIssuer $tokens) {}

    public function execute(string $username, #[\SensitiveParameter] string $password): array
    {
        $user = $this->users->findByUsername(User::normalizeUsername($username));
        // The adapter verifies against a dummy hash when the account does not exist.
        $valid = $this->passwords->verify($password, $user?->passwordHash() ?? '');
        if (!$user || !$valid) {
            throw new DomainError('INVALID_CREDENTIALS', 'Usuario o contraseña incorrectos.');
        }
        return $this->tokens->issue($user);
    }
}
