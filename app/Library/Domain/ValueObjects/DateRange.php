<?php

declare(strict_types=1);

namespace App\Library\Domain\ValueObjects;

use App\Library\Domain\DomainError;
use DateTimeImmutable;
use DateTimeZone;

final readonly class DateRange
{
    public DateTimeImmutable $start;
    public DateTimeImmutable $endExclusive;

    public function __construct(public string $from, public string $to)
    {
        $this->start = self::date($from, 'from');
        $end = self::date($to, 'to');
        if ($end < $this->start) {
            throw new DomainError('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.');
        }
        $this->endExclusive = $end->modify('+1 day');
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        return $instant >= $this->start && $instant < $this->endExclusive;
    }

    private static function date(string $value, string $field): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new DomainError('VALIDATION_ERROR', 'La fecha debe ser válida y usar el formato AAAA-MM-DD.', [['field' => $field, 'issue' => 'fecha inválida']]);
        }
        return $date;
    }
}
