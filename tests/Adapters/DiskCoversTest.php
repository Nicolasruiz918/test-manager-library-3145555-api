<?php
declare(strict_types=1);
namespace Tests\Adapters;
use App\Library\Adapters\Outbound\Storage\DiskCovers;
use App\Library\Domain\DomainError;
use PHPUnit\Framework\TestCase;
final class DiskCoversTest extends TestCase
{
    private string $directory;
    protected function setUp(): void { $this->directory = sys_get_temp_dir().'/library-cover-test-'.bin2hex(random_bytes(8)); mkdir($this->directory, 0700); }
    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $file) { unlink($file); }
        rmdir($this->directory); parent::tearDown();
    }
    private function png(int $size): string
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6X8AAAAASUVORK5CYII=');
        $path = $this->directory.'/upload';
        file_put_contents($path, str_pad($bytes, $size, "\0"));
        return $path;
    }
    public function test_two_mebibyte_png_is_stored_as_an_opaque_key_and_can_be_removed(): void
    {
        $storage = new DiskCovers($this->directory); $key = $storage->store($this->png(2097152));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key);
        $read = $storage->read($key);
        $this->assertSame('image/png', $read['mime']); $this->assertSame(2097152, strlen($read['bytes']));
        $storage->remove($key);
        $this->expectException(DomainError::class); $storage->read($key);
    }
    public function test_three_mebibyte_image_is_rejected(): void
    {
        try { (new DiskCovers($this->directory))->store($this->png(3145728)); $this->fail('Oversized cover accepted'); }
        catch (DomainError $error) { $this->assertSame('VALIDATION_ERROR', $error->errorCode); }
    }
    public function test_extension_cannot_disguise_nonimage_and_keys_cannot_traverse_paths(): void
    {
        $path = $this->directory.'/fake.png'; file_put_contents($path, '<script>invalid image</script>');
        $storage = new DiskCovers($this->directory);
        try { $storage->store($path); $this->fail('Nonimage accepted'); }
        catch (DomainError $error) { $this->assertSame('VALIDATION_ERROR', $error->errorCode); }
        $this->expectException(DomainError::class); $storage->read('../fake.png');
    }
}
