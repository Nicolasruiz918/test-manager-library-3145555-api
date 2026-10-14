<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
interface TokenVerifier
{
    /** @return array{sub:string,role:string} */
    public function verify(#[\SensitiveParameter] string $token): array;
}
