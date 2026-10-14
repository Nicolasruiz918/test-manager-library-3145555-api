<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Application\Ports\{TokenVerifier, UserRepository};
use App\Library\Domain\DomainError;
use Closure;
use Illuminate\Http\Request;
final readonly class Authenticate
{
    public function __construct(private TokenVerifier $tokens, private UserRepository $users) {}
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) { throw new DomainError('INVALID_CREDENTIALS', 'Debes iniciar sesión.'); }
        $claims = $this->tokens->verify($token);
        $user = $this->users->findUser($claims['sub']);
        if (!$user || $user->role !== $claims['role']) { throw new DomainError('INVALID_CREDENTIALS', 'La sesión no es válida o ha vencido.'); }
        $request->attributes->set('library_user', $user);
        return $next($request);
    }
}
