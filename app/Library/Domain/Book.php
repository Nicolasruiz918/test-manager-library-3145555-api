<?php

declare(strict_types=1);

namespace App\Library\Domain;

use App\Library\Domain\ValueObjects\{Isbn, Quantity};
use DateTimeImmutable;

final class Book
{
    public private(set) string $title;
    public private(set) string $author;
    public private(set) string $categoryId;
    public private(set) string $categoryName;
    public private(set) int $totalCopies;
    public private(set) int $availableCopies;
    public private(set) ?string $coverKey;
    public private(set) ?DateTimeImmutable $deletedAt;

    public function __construct(
        public readonly string $id,
        string $title,
        string $author,
        public readonly Isbn $isbn,
        string $categoryId,
        string $categoryName,
        Quantity $totalCopies,
        int $availableCopies,
        ?string $coverKey = null,
        ?DateTimeImmutable $deletedAt = null,
    ) {
        self::validateText($title, $author);
        if ($availableCopies < 0 || $availableCopies > $totalCopies->value) {
            throw new DomainError('VALIDATION_ERROR', 'Los ejemplares disponibles deben estar entre cero y el total.');
        }
        $this->title = trim($title);
        $this->author = $author;
        $this->categoryId = $categoryId;
        $this->categoryName = $categoryName;
        $this->totalCopies = $totalCopies->value;
        $this->availableCopies = $availableCopies;
        $this->setCoverKey($coverKey);
        $this->deletedAt = $deletedAt;
    }

    public function withdraw(): void
    {
        $this->requireActive();
        if ($this->availableCopies === 0) {
            throw new DomainError('BOOK_NOT_AVAILABLE', 'No quedan ejemplares disponibles de «'.$this->title.'».');
        }
        --$this->availableCopies;
    }

    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new DomainError('VALIDATION_ERROR', 'La devolución superaría el total de ejemplares.');
        }
        ++$this->availableCopies;
    }

    public function revise(string $title, string $author, string $categoryId, string $categoryName, Quantity $total): void
    {
        $this->requireActive();
        self::validateText($title, $author);
        $available = $this->availableCopies + ($total->value - $this->totalCopies);
        if ($available < 0) {
            throw new DomainError('VALIDATION_ERROR', 'El total no puede ser menor que los ejemplares prestados.', [['field' => 'total_copies', 'issue' => 'hay ejemplares sin devolver']]);
        }
        $this->title = trim($title);
        $this->author = $author;
        $this->categoryId = $categoryId;
        $this->categoryName = $categoryName;
        $this->totalCopies = $total->value;
        $this->availableCopies = $available;
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        $this->requireActive();
        if ($this->availableCopies !== $this->totalCopies) {
            throw new DomainError('BOOK_HAS_OPEN_LOANS', 'El libro tiene ejemplares sin devolver.');
        }
        $this->deletedAt = $now;
    }

    public function setCoverKey(?string $key): void
    {
        if ($key !== null && (trim($key) === '' || mb_strlen($key) > 512)) {
            throw new DomainError('VALIDATION_ERROR', 'La clave de portada no es válida.');
        }
        $this->coverKey = $key;
    }

    private function requireActive(): void
    {
        if ($this->deletedAt !== null) {
            throw new DomainError('NOT_FOUND', 'No se encontró el libro.');
        }
    }

    private static function validateText(string $title, string $author): void
    {
        if (trim($title) === '' || mb_strlen(trim($title)) > 250 || mb_strlen($author) > 200) {
            throw new DomainError('VALIDATION_ERROR', 'Revisa el título y el autor del libro.');
        }
    }
}
