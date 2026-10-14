<?php
declare(strict_types=1);
namespace App\Library\Application;
use App\Library\Domain\DomainError;
final readonly class Pagination
{
    public function __construct(public int $page = 1, public int $size = 20)
    {
        if ($page < 1 || $size < 1 || $size > 50 || $page > intdiv(PHP_INT_MAX, $size)) {
            throw new DomainError('VALIDATION_ERROR', 'La página debe ser positiva y el tamaño debe estar entre 1 y 50.');
        }
    }
    public function offset(): int { return ($this->page - 1) * $this->size; }
}
