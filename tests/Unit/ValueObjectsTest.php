<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Library\Domain\DomainError;
use App\Library\Domain\ValueObjects\{DateRange, Isbn, Quantity};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function test_isbn_requires_digits_without_an_unspecified_checksum_rule(): void
    {
        $this->assertSame('0000000000000', (new Isbn('0000000000000'))->value);
        $this->assertSame('9780307474728', (new Isbn('9780307474728'))->value);
    }

    public static function invalidIsbns(): array
    {
        return [[''], ['123456789012'], ['12345678901234'], ['978-0307474728'], ['123456789012x'], [' 9780307474728'], ["9780307474728\n"], ['１２３４５６７８９０１２３']];
    }

    #[DataProvider('invalidIsbns')]
    public function test_invalid_isbn_is_rejected(string $value): void
    {
        $this->expectException(DomainError::class);
        new Isbn($value);
    }

    public function test_positive_quantity(): void
    {
        $this->assertSame(1, (new Quantity(1))->value);
        $this->assertSame(9, (new Quantity(9))->value);
    }

    public static function invalidQuantities(): array { return [[0], [-1], [-100]]; }

    #[DataProvider('invalidQuantities')]
    public function test_zero_and_negative_quantities_are_rejected(int $value): void
    {
        $this->expectException(DomainError::class);
        new Quantity($value);
    }

    public function test_same_day_range_is_inclusive_in_utc(): void
    {
        $range = new DateRange('2026-10-14', '2026-10-14');
        $this->assertTrue($range->contains(new DateTimeImmutable('2026-10-14T00:00:00Z')));
        $this->assertTrue($range->contains(new DateTimeImmutable('2026-10-14T23:59:59.999999Z')));
        $this->assertFalse($range->contains(new DateTimeImmutable('2026-10-15T00:00:00Z')));
        $this->assertFalse($range->contains(new DateTimeImmutable('2026-10-13T23:59:59Z')));
        $this->assertSame('UTC', $range->start->getTimezone()->getName());
    }

    public function test_reversed_range_keeps_its_business_code(): void
    {
        try {
            new DateRange('2026-10-15', '2026-10-14');
            $this->fail('Reversed range accepted');
        } catch (DomainError $error) {
            $this->assertSame('INVALID_DATE_RANGE', $error->errorCode);
        }
    }

    public function test_invalid_calendar_date_does_not_roll_into_the_next_month(): void
    {
        $this->expectException(DomainError::class);
        new DateRange('2026-02-30', '2026-03-01');
    }
}
