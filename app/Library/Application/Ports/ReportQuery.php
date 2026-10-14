<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Domain\ValueObjects\DateRange;
interface ReportQuery { public function mostBorrowed(DateRange $range): array; }
