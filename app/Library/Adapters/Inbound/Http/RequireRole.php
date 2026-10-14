<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Domain\DomainError;
use Closure;
use Illuminate\Http\Request;
final class RequireRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        if ($request->attributes->get('library_user')?->role !== $role) { throw new DomainError('FORBIDDEN_ROLE', 'Tu rol no permite esta operación.'); }
        return $next($request);
    }
}
