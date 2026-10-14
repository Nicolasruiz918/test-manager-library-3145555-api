<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
interface CoverStorage
{
    /** Validate content and store under a generated opaque key. */
    public function store(string $localPath): string;
    /** @return array{bytes:string,mime:string} */
    public function read(string $key): array;
    public function remove(string $key): void;
}
