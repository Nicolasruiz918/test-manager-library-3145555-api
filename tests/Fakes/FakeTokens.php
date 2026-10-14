<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\TokenIssuer;
use App\Library\Domain\User;
final class FakeTokens implements TokenIssuer
{
    public function issue(User $user): array { return ['access_token' => 'fake-token', 'token_type' => 'Bearer', 'expires_in' => 28800, 'role' => $user->role]; }
}
