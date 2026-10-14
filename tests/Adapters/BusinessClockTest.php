<?php
declare(strict_types=1);
namespace Tests\Adapters;
use App\Library\Adapters\Outbound\Runtime\BusinessClock;
use App\Library\Adapters\Outbound\Security\JwtTokens;
use App\Library\Domain\User;
use PHPUnit\Framework\TestCase;
final class BusinessClockTest extends TestCase
{
    public function test_demo_clock_does_not_backdate_jwt(): void
    {
        $clock = new BusinessClock('demo', '2026-01-01T00:00:00Z');
        $this->assertSame('2026-01-01', $clock->now()->format('Y-m-d'));
        $tokens = new JwtTokens(bin2hex(random_bytes(32)));
        $token = $tokens->issue(new User('11111111-1111-4111-8111-111111111111', 'test', 'fake-test-hash', 'admin'))['access_token'];
        $claims = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
        $this->assertLessThanOrEqual(1, abs(time() - $claims['iat']));
        $this->assertSame('admin', $tokens->verify($token)['role']);
        $this->assertLessThanOrEqual(1, abs(time() - (new BusinessClock('demo'))->now()->getTimestamp()));
    }
    public function test_clock_override_is_rejected_outside_isolated_demo_or_testing(): void
    {
        $this->expectException(\LogicException::class); new BusinessClock('production', '2026-01-01T00:00:00Z');
    }
}
