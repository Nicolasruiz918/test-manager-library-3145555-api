<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
interface IdentifierGenerator { public function next(): string; }
