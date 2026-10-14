<?php

declare(strict_types=1);

namespace App\Library\Domain\ValueObjects;

use App\Library\Domain\DomainError;

final readonly class Isbn
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A[0-9]{13}\z/', $value) !== 1) {
            throw new DomainError('VALIDATION_ERROR', 'El ISBN debe contener exactamente 13 dígitos.', [['field' => 'isbn', 'issue' => 'se requieren 13 dígitos']]);
        }
    }
}
