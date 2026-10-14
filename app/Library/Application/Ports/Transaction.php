<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
interface Transaction { public function run(callable $operation): mixed; }
