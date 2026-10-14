<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Storage;
use App\Library\Application\Ports\CoverStorage;
use App\Library\Domain\DomainError;
use finfo;
final readonly class DiskCovers implements CoverStorage
{
    public function __construct(private string $directory) {}
    public function store(string $localPath): string
    {
        if (!is_file($localPath) || !is_readable($localPath) || filesize($localPath) > 2097152) { throw new DomainError('VALIDATION_ERROR', 'La portada debe ser JPG o PNG y no superar 2 MB.', [['field' => 'file', 'issue' => 'tamaño o archivo no válido']]); }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($localPath);
        $image = @getimagesize($localPath);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true) || !$image || ($image['mime'] ?? null) !== $mime) { throw new DomainError('VALIDATION_ERROR', 'La portada debe contener una imagen JPG o PNG válida.', [['field' => 'file', 'issue' => 'tipo no válido']]); }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) { throw new \RuntimeException('Cover directory unavailable.'); }
        $key = bin2hex(random_bytes(32));
        $target = $this->directory.DIRECTORY_SEPARATOR.$key;
        if (!copy($localPath, $target)) { throw new \RuntimeException('Cover write failed.'); }
        chmod($target, 0600);
        return $key;
    }
    private function path(string $key): string
    {
        if (!preg_match('/\A[0-9a-f]{64}\z/', $key)) { throw new DomainError('NOT_FOUND', 'No se encontró la portada.'); }
        return $this->directory.DIRECTORY_SEPARATOR.$key;
    }
    public function read(string $key): array
    {
        $path = $this->path($key);
        if (!is_file($path)) { throw new DomainError('NOT_FOUND', 'No se encontró la portada.'); }
        $bytes = file_get_contents($path);
        if ($bytes === false) { throw new \RuntimeException('Cover read failed.'); }
        return ['bytes' => $bytes, 'mime' => (new finfo(FILEINFO_MIME_TYPE))->file($path)];
    }
    public function remove(string $key): void
    {
        $path = $this->path($key);
        // Cleanup follows a committed reference change; a failed deletion must not
        // turn that successful business operation into a misleading failure.
        if (is_file($path) && !@unlink($path)) { error_log('Cover cleanup failed for an unreferenced opaque object.'); }
    }
}
