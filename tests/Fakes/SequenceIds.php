<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\IdentifierGenerator;
final class SequenceIds implements IdentifierGenerator
{
    private int $value = 0;
    public function next(): string { return sprintf('00000000-0000-4000-8000-%012d', ++$this->value); }
}
