<?php

declare(strict_types=1);

namespace App\Library\Domain\ValueObjects;

use App\Library\Domain\DomainError;

/** Positive totals only; available copies can legitimately be zero. */
final readonly class Quantity
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new DomainError('VALIDATION_ERROR', 'El total de ejemplares debe ser al menos uno.', [['field' => 'total_copies', 'issue' => 'debe ser mayor que cero']]);
        }
    }
}
