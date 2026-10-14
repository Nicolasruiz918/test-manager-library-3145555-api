<?php
declare(strict_types=1);
namespace Tests\Adapters;
use App\Library\Adapters\Outbound\Security\{ArgonPasswords, JwtTokens};
use App\Library\Domain\{DomainError, User};
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
final class SecurityTest extends TestCase
{
    protected function tearDown(): void { JWT::$timestamp = null; parent::tearDown(); }
    public function test_argon_hashes_are_salted_and_do_not_truncate_long_passwords(): void
    {
        $passwords = new ArgonPasswords(); $plain = str_repeat('a', 90).' end ';
        $hash = $passwords->hash($plain);
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertNotSame($hash, $passwords->hash($plain));
        $this->assertTrue($passwords->verify($plain, $hash));
        $this->assertFalse($passwords->verify(str_repeat('a', 90).' other ', $hash));
        $this->assertFalse($passwords->verify(trim($plain), $hash));
        $this->assertFalse($passwords->verify('absent', ''));
    }
    public function test_token_has_only_required_identity_and_real_eight_hour_expiry(): void
    {
        $secret = bin2hex(random_bytes(32)); $tokens = new JwtTokens($secret);
        $user = new User('11111111-1111-4111-8111-111111111111', 'ana', 'fake-test-hash', 'librarian');
        $response = $tokens->issue($user);
        $claims = json_decode(base64_decode(strtr(explode('.', $response['access_token'])[1], '-_', '+/')), true);
        $this->assertSame(['sub', 'role', 'iat', 'exp'], array_keys($claims));
        $this->assertSame(28800, $claims['exp'] - $claims['iat']);
        $this->assertLessThanOrEqual(1, abs(time() - $claims['iat']));
        $this->assertSame(['sub' => $user->id, 'role' => 'librarian'], $tokens->verify($response['access_token']));
        JWT::$timestamp = $claims['exp'];
        $this->expectException(DomainError::class); $tokens->verify($response['access_token']);
    }
    public function test_wrong_secret_wrong_algorithm_and_forged_roles_are_rejected(): void
    {
        $secret = bin2hex(random_bytes(32)); $tokens = new JwtTokens($secret);
        $payload = ['sub' => '11111111-1111-4111-8111-111111111111', 'role' => 'librarian', 'iat' => time(), 'exp' => time() + 28800];
        $invalid = [
            JWT::encode($payload, bin2hex(random_bytes(32)), 'HS256'),
            JWT::encode($payload, $secret, 'HS384'),
            JWT::encode(array_replace($payload, ['role' => 'owner']), $secret, 'HS256'),
            'malformed',
        ];
        foreach ($invalid as $token) {
            try { $tokens->verify($token); $this->fail('Invalid token accepted'); }
            catch (DomainError $error) { $this->assertSame('INVALID_CREDENTIALS', $error->errorCode); }
        }
    }
    public function test_no_default_or_short_signing_secret_is_accepted(): void
    {
        $this->expectException(\LogicException::class); new JwtTokens('');
    }
}
