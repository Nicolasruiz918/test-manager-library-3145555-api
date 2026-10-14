<?php

declare(strict_types=1);

namespace App\Library\Domain;

use RuntimeException;

/** A business failure, independent of HTTP and persistence. */
final class DomainError extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
