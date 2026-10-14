<?php
declare(strict_types=1);
namespace Tests\Architecture;
require_once __DIR__.'/DependencyRules.php';
use PHPUnit\Framework\TestCase;
final class ArchitectureTest extends TestCase
{
    public function test_domain_application_and_composition_dependencies(): void
    {
        $root = dirname(__DIR__, 2).'/app'; $errors = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                array_push($errors, ...DependencyRules::violations(file_get_contents($file->getPathname()), str_replace('\\', '/', $file->getPathname())));
            }
        }
        $this->assertSame([], $errors, implode("\n", $errors));
    }
    public function test_checker_rejects_an_unused_framework_import_in_domain(): void
    {
        $source = '<?php namespace App\\Library\\Domain; use Illuminate\\Database\\Eloquent\\Model; final class Probe {}';
        $this->assertNotEmpty(DependencyRules::violations($source, '/app/Library/Domain/Probe.php'));
    }
    public function test_checker_rejects_an_adapter_dependency_in_application(): void
    {
        $source = '<?php namespace App\\Library\\Application; final class Probe { public function go(\\App\\Library\\Adapters\\Outbound\\Persistence\\PgBookRepository $repo): void {} }';
        $this->assertNotEmpty(DependencyRules::violations($source, '/app/Library/Application/Probe.php'));
    }
    public function test_checker_rejects_grouped_imports_and_port_bindings_outside_composition(): void
    {
        $source = '<?php namespace App\\Library\\Domain; use Illuminate\\Database\\{Eloquent\\Model};';
        $this->assertNotEmpty(DependencyRules::violations($source, '/app/Library/Domain/Probe.php'));
        $source = '<?php $app->bind(\\App\\Library\\Application\\Ports\\Clock::class, \\App\\Library\\Adapters\\Outbound\\Runtime\\SystemClock::class);';
        $this->assertNotEmpty(DependencyRules::violations($source, '/app/Library/Adapters/Inbound/Http/Probe.php'));
    }
    public function test_checker_rejects_adapter_construction_outside_composition(): void
    {
        $source = '<?php namespace App\\Library\\Adapters\\Inbound\\Http; use App\\Library\\Adapters\\Outbound\\Runtime\\SystemClock; new SystemClock();';
        $this->assertNotEmpty(DependencyRules::violations($source, '/app/Library/Adapters/Inbound/Http/Probe.php'));
        $this->assertSame([], DependencyRules::violations($source, '/app/Providers/LibraryServiceProvider.php'));
    }
}
