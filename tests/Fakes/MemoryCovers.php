<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\CoverStorage;
use App\Library\Domain\DomainError;
final class MemoryCovers implements CoverStorage
{
    public array $files = [];
    private int $sequence = 0;
    public function store(string $localPath): string { $key = 'cover-'.++$this->sequence; $this->files[$key] = $localPath; return $key; }
    public function read(string $key): array { return ['bytes' => $this->files[$key] ?? throw new DomainError('NOT_FOUND', 'No se encontró la portada.'), 'mime' => 'image/png']; }
    public function remove(string $key): void { unset($this->files[$key]); }
}
