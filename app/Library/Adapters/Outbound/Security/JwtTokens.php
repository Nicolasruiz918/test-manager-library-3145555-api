<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Security;
use App\Library\Application\Ports\{TokenIssuer, TokenVerifier};
use App\Library\Domain\{DomainError, User};
use Firebase\JWT\{JWT, Key};
use Throwable;
final readonly class JwtTokens implements TokenIssuer, TokenVerifier
{
    public function __construct(#[\SensitiveParameter] private string $secret)
    {
        if (strlen($secret) < 32) { throw new \LogicException('JWT_SECRET must contain at least 32 bytes.'); }
    }
    public function issue(User $user): array
    {
        $now = time();
        $token = JWT::encode(['sub' => $user->id, 'role' => $user->role, 'iat' => $now, 'exp' => $now + 28800], $this->secret, 'HS256');
        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => 28800, 'role' => $user->role];
    }
    public function verify(#[\SensitiveParameter] string $token): array
    {
        try {
            $claims = JWT::decode($token, new Key($this->secret, 'HS256'));
            if (!is_string($claims->sub ?? null) || !preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $claims->sub)
                || !in_array($claims->role ?? null, ['admin', 'librarian'], true) || !is_int($claims->iat ?? null) || !is_int($claims->exp ?? null) || $claims->exp - $claims->iat !== 28800) {
                throw new \UnexpectedValueException('Invalid token claims.');
            }
            return ['sub' => $claims->sub, 'role' => $claims->role];
        } catch (Throwable) { throw new DomainError('INVALID_CREDENTIALS', 'La sesión no es válida o ha vencido.'); }
    }
}
