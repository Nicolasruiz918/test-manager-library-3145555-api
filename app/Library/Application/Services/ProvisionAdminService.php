<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{IdentifierGenerator, PasswordHasher, UserRepository};
use App\Library\Domain\User;
final readonly class ProvisionAdminService
{
    public function __construct(private UserRepository $users, private PasswordHasher $passwords, private IdentifierGenerator $ids) {}
    public function execute(string $username, #[\SensitiveParameter] string $password): User
    {
        if (trim($username) === '' || $password === '') { throw new \LogicException('ADMIN_USERNAME and ADMIN_PASSWORD are required.'); }
        $existing = $this->users->findByUsername(User::normalizeUsername($username));
        if ($existing) {
            if ($existing->role !== 'admin' || !$this->passwords->verify($password, $existing->passwordHash())) {
                throw new \LogicException('Configured administrator conflicts with an existing account; no account was changed.');
            }
            return $existing;
        }
        $user = new User($this->ids->next(), $username, $this->passwords->hash($password), 'admin');
        $this->users->addUser($user);
        return $user;
    }
}
