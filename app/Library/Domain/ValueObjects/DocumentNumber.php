<?php

declare(strict_types=1);

namespace App\Library\Domain\ValueObjects;

use App\Library\Domain\DomainError;

final readonly class DocumentNumber
{
    public function __construct(public string $value)
    {
        if (trim($value) === '' || mb_strlen($value) > 30) {
            throw new DomainError('VALIDATION_ERROR', 'Revisa el nombre y el documento del socio.');
        }
    }
}
