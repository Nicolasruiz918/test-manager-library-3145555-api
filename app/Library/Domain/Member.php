<?php

declare(strict_types=1);

namespace App\Library\Domain;

final readonly class Member
{
    public function __construct(public string $id, public string $fullName, public string $documentNumber)
    {
        if (trim($fullName) === '' || mb_strlen($fullName) > 200 || trim($documentNumber) === '' || mb_strlen($documentNumber) > 30) {
            throw new DomainError('VALIDATION_ERROR', 'Revisa el nombre y el documento del socio.');
        }
    }
}
