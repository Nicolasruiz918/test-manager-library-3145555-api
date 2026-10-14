<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Domain\User;
interface TokenIssuer { public function issue(User $user): array; }
