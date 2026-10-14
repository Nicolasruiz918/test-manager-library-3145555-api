<?php
declare(strict_types=1);
namespace Tests\Architecture;
use PhpParser\{Node, NodeFinder, NodeTraverser, ParserFactory};
use PhpParser\NodeVisitor\NameResolver;
final class DependencyRules
{
    public static function violations(string $source, string $path): array
    {
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        $traverser = new NodeTraverser(new NameResolver()); $nodes = $traverser->traverse($nodes);
        $finder = new NodeFinder(); $errors = [];
        $domain = str_contains($path, '/Library/Domain/');
        $application = str_contains($path, '/Library/Application/');
        if ($domain || $application) {
            foreach ($finder->findInstanceOf($nodes, Node\Name\FullyQualified::class) as $name) {
                $reference = $name->toString();
                if (str_starts_with($reference, 'App\\Library\\Domain\\')) { continue; }
                if ($application && str_starts_with($reference, 'App\\Library\\Application\\')) { continue; }
                if (in_array(strtolower($reference), ['self', 'static', 'parent'], true)) { continue; }
                if ((class_exists($reference, false) || interface_exists($reference, false)) && (new \ReflectionClass($reference))->isInternal()) { continue; }
                $errors[] = $path.': forbidden dependency '.$reference;
            }
            // An unused import must still fail the architectural contract.
            foreach ($finder->findInstanceOf($nodes, Node\Stmt\Use_::class) as $use) {
                foreach ($use->uses as $import) {
                    $reference = $import->name->toString();
                    if (str_starts_with($reference, 'App\\Library\\Domain\\') || ($application && str_starts_with($reference, 'App\\Library\\Application\\'))) { continue; }
                    if ((class_exists($reference, false) || interface_exists($reference, false)) && (new \ReflectionClass($reference))->isInternal()) { continue; }
                    $errors[] = $path.': forbidden import '.$reference;
                }
            }
            foreach ($finder->findInstanceOf($nodes, Node\Stmt\GroupUse::class) as $use) {
                foreach ($use->uses as $import) {
                    $reference = $use->prefix->toString().'\\'.$import->name->toString();
                    if (str_starts_with($reference, 'App\\Library\\Domain\\') || ($application && str_starts_with($reference, 'App\\Library\\Application\\'))) { continue; }
                    $errors[] = $path.': forbidden grouped import '.$reference;
                }
            }
        }
        foreach ($finder->findInstanceOf($nodes, Node\Expr\New_::class) as $new) {
            if ($new->class instanceof Node\Name && str_starts_with($new->class->toString(), 'App\\Library\\Adapters\\') && !str_ends_with($path, '/Providers/LibraryServiceProvider.php')) {
                // Technical adapter exceptions are values, not infrastructure construction.
                if (is_a($new->class->toString(), \Throwable::class, true)) { continue; }
                $errors[] = $path.': adapter constructed outside the composition point';
            }
        }
        if (!str_ends_with($path, '/Providers/LibraryServiceProvider.php')) {
            foreach ($finder->findInstanceOf($nodes, Node\Expr\MethodCall::class) as $call) {
                if (!$call->name instanceof Node\Identifier || !in_array($call->name->toString(), ['bind', 'singleton', 'scoped', 'instance', 'alias'], true)) { continue; }
                foreach ($finder->findInstanceOf($call->args, Node\Expr\ClassConstFetch::class) as $reference) {
                    if ($reference->class instanceof Node\Name && str_starts_with($reference->class->toString(), 'App\\Library\\Application\\Ports\\')) { $errors[] = $path.': port binding outside the composition point'; }
                }
            }
        }
        return array_values(array_unique($errors));
    }
}
