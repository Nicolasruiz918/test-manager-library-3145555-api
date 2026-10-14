<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Library\Application\Ports\{IdentifierGenerator, UserRepository};
use App\Library\Domain\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
final class StartupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('DB_DATABASE') !== 'manager_library_test') { $this->markTestSkipped('Use the disposable PostgreSQL test stack.'); }
        DB::beginTransaction();
        config(['library.jwt_secret' => bin2hex(random_bytes(32)), 'library.admin_username' => 'Admin ', 'library.admin_password' => ' secret '.bin2hex(random_bytes(12)).' ']);
    }
    protected function tearDown(): void
    {
        if (getenv('DB_DATABASE') === 'manager_library_test') { while (DB::transactionLevel() > 0) { DB::rollBack(); } }
        parent::tearDown();
    }
    public function test_empty_database_can_install_login_and_repeat_without_changing_identity_or_hash(): void
    {
        $this->assertSame([], DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema IN ('library','public')"));
        $kernel = $this->app->make(Kernel::class);
        $this->assertSame(0, $kernel->call('library:install'));
        $user = (array) DB::table('library.user')->first();
        $this->assertSame('admin', $user['username']); $this->assertSame('admin', $user['role']);
        $this->assertSame(0, $kernel->call('library:install'));
        $this->assertSame($user, (array) DB::table('library.user')->first()); $this->assertSame(1, DB::table('library.user')->count());
        $response = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => config('library.admin_password')])->assertOk()->assertJsonPath('role', 'admin');
        $this->assertStringNotContainsString(config('library.admin_password'), $response->getContent().$kernel->output());
        $this->assertStringNotContainsString($user['password_hash'], $response->getContent().$kernel->output());
    }
    public function test_existing_librarian_is_never_promoted_by_startup(): void
    {
        $kernel = $this->app->make(Kernel::class); $this->assertSame(0, $kernel->call('library:migrate'));
        $user = new User($this->app->make(IdentifierGenerator::class)->next(), 'admin', 'fake-test-hash', 'librarian');
        $this->app->make(UserRepository::class)->addUser($user);
        $this->assertSame(1, $kernel->call('library:install'));
        $this->assertSame('librarian', DB::table('library.user')->where('id', $user->id)->value('role'));
    }
    public function test_missing_credentials_do_not_create_tables(): void
    {
        config(['library.admin_password' => '']);
        $this->assertSame(1, $this->app->make(Kernel::class)->call('library:install'));
        $this->assertSame([], DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema IN ('library','public')"));
    }
}
