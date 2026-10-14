<?php

declare(strict_types=1);

namespace App\Library\Domain;

use JsonSerializable;

final readonly class User implements JsonSerializable
{
    public string $username;

    public function __construct(public string $id, string $username, private string $passwordHash, public string $role)
    {
        $this->username = self::normalizeUsername($username);
        if ($this->username === '' || mb_strlen($this->username) > 120 || $passwordHash === '' || strlen($passwordHash) > 512 || !in_array($role, ['admin', 'librarian'], true)) {
            throw new DomainError('VALIDATION_ERROR', 'Los datos del usuario no son válidos.');
        }
    }

    public static function normalizeUsername(string $username): string { return mb_strtolower(trim($username), 'UTF-8'); }

    public static function librarian(string $id, string $username, string $hash, string $requestedRole): self
    {
        if ($requestedRole !== 'librarian') {
            throw new DomainError('VALIDATION_ERROR', 'Solo se puede crear el rol librarian.');
        }
        return new self($id, $username, $hash, 'librarian');
    }

    public function passwordHash(): string { return $this->passwordHash; }

    public function jsonSerialize(): array { return ['id' => $this->id, 'username' => $this->username, 'role' => $this->role]; }
}
