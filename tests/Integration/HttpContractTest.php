<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Library\Application\Ports\{Clock, IdentifierGenerator, PasswordHasher, TokenIssuer, UserRepository};
use App\Library\Domain\User;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Log};
use Tests\Fakes\FrozenClock;
final class HttpContractTest extends DatabaseTestCase
{
    private array $adminHeaders;
    private FrozenClock $clock;
    private string $password;
    protected function setUp(): void
    {
        parent::setUp();
        config(['library.jwt_secret' => bin2hex(random_bytes(32))]);
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-10-01T12:00:00Z'));
        $this->app->instance(Clock::class, $this->clock);
        $this->password = ' test '.bin2hex(random_bytes(12)).' ';
        $admin = new User($this->app->make(IdentifierGenerator::class)->next(), 'admin', $this->app->make(PasswordHasher::class)->hash($this->password), 'admin');
        $this->app->make(UserRepository::class)->addUser($admin);
        $this->adminHeaders = ['Authorization' => 'Bearer '.$this->app->make(TokenIssuer::class)->issue($admin)['access_token']];
    }
    private function error($response, int $status, string $code): void
    {
        $response->assertStatus($status)->assertJsonPath('code', $code)->assertJsonStructure(['code', 'message', 'details']);
        $this->assertSame(['code', 'message', 'details'], array_keys($response->json()));
        $this->assertIsArray($response->json('details'));
        foreach (['password_hash', 'SQLSTATE', '/app/', 'stack trace', $this->password] as $private) { $this->assertStringNotContainsString($private, $response->getContent()); }
    }
    private function createBook(int $isbn, int $total = 4): array
    {
        return $this->postJson('/api/v1/books', ['title' => 'Title '.$isbn, 'author' => 'Author', 'isbn' => str_pad((string) $isbn, 13, '0', STR_PAD_LEFT), 'category_id' => '11111111-1111-4111-8111-111111111111', 'total_copies' => $total], $this->adminHeaders)->assertCreated()->json();
    }
    private function member(string $document): array
    {
        return $this->postJson('/api/v1/members', ['full_name' => 'Ana Ruiz', 'document_number' => $document], $this->adminHeaders)->assertCreated()->json();
    }
    private function lend(string $member, array $books)
    {
        return $this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => array_map(static fn ($id) => ['book_id' => $id], $books)], $this->adminHeaders);
    }
    public function test_every_business_error_code_and_primary_lending_flow(): void
    {
        $this->error($this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'wrong']), 401, 'INVALID_CREDENTIALS');
        $login = $this->postJson('/api/v1/auth/login', ['username' => ' ADMIN ', 'password' => $this->password])->assertOk()->assertJsonPath('expires_in', 28800)->assertJsonPath('role', 'admin');
        $this->assertStringNotContainsString($this->password, $login->getContent());
        $userBody = ['username' => 'Ana ', 'password' => $this->password, 'role' => 'librarian'];
        $created = $this->postJson('/api/v1/users', $userBody, $this->adminHeaders)->assertCreated()->assertJsonPath('username', 'ana');
        $this->assertSame(['id', 'username', 'role'], array_keys($created->json()));
        $this->error($this->postJson('/api/v1/users', $userBody, $this->adminHeaders), 409, 'USERNAME_TAKEN');
        $this->error($this->postJson('/api/v1/users', array_replace($userBody, ['role' => 'admin']), $this->adminHeaders), 422, 'VALIDATION_ERROR');
        $librarianLogin = $this->postJson('/api/v1/auth/login', ['username' => 'ana', 'password' => $this->password])->assertOk()->json();
        $librarian = ['Authorization' => 'Bearer '.$librarianLogin['access_token']];
        $missing = '99999999-9999-4999-8999-999999999999';
        $this->error($this->deleteJson('/api/v1/books/'.$missing, [], $librarian), 403, 'FORBIDDEN_ROLE');
        $this->error($this->getJson('/api/v1/books/'.$missing, $this->adminHeaders), 404, 'NOT_FOUND');
        $categories = $this->getJson('/api/v1/categories', $librarian)->assertOk()->json('items'); $this->assertCount(5, $categories);
        $a = $this->createBook(1, 1); $b = $this->createBook(2); $c = $this->createBook(3); $d = $this->createBook(4);
        $duplicate = ['title' => 'Duplicate', 'author' => 'Author', 'isbn' => $a['isbn'], 'category_id' => $a['category']['id'], 'total_copies' => 1];
        $this->error($this->postJson('/api/v1/books', $duplicate, $this->adminHeaders), 409, 'ISBN_TAKEN');
        $member = $this->member('001'); $other = $this->member('002');
        $this->error($this->postJson('/api/v1/members', ['full_name' => 'Other', 'document_number' => '001'], $librarian), 409, 'DOCUMENT_TAKEN');
        $this->getJson('/api/v1/members?q=001&page=1&size=1', $librarian)->assertOk()->assertJsonPath('total', 1);
        $this->error($this->lend($member['id'], [$a['id'], $a['id']]), 422, 'DUPLICATE_BOOK_IN_LOAN');
        $loan = $this->lend($member['id'], [$a['id'], $b['id']])->assertCreated()->assertJsonPath('status', 'open')->json();
        $this->error($this->deleteJson('/api/v1/books/'.$a['id'], [], $this->adminHeaders), 409, 'BOOK_HAS_OPEN_LOANS');
        $this->error($this->lend($other['id'], [$a['id']]), 409, 'BOOK_NOT_AVAILABLE');
        $this->error($this->lend($member['id'], [$c['id'], $d['id']]), 422, 'MEMBER_LIMIT_EXCEEDED');
        $this->getJson('/api/v1/members/'.$member['id'], $librarian)->assertOk()->assertJsonPath('active_loans', 2);
        $this->clock->instant = new DateTimeImmutable('2026-10-16T12:00:00Z');
        $this->error($this->lend($member['id'], [$c['id']]), 409, 'MEMBER_HAS_OVERDUE');
        $this->getJson('/api/v1/loans/overdue', $librarian)->assertOk()->assertJsonPath('total', 2)->assertJsonPath('items.0.days_late', 1);
        $this->getJson('/api/v1/loans?from=2026-10-01&to=2026-10-01&status=overdue&member_id='.$member['id'], $librarian)->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/loans/'.$loan['id'], $librarian)->assertOk()->assertJsonPath('status', 'overdue');
        foreach ($loan['items'] as $item) {
            $route = '/api/v1/loans/'.$loan['id'].'/items/'.$item['id'].'/return';
            $this->postJson($route, [], $librarian)->assertOk()->assertJsonPath('id', $item['id']);
        }
        $route = '/api/v1/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return';
        $this->error($this->postJson($route, [], $librarian), 409, 'ITEM_ALREADY_RETURNED');
        $this->getJson('/api/v1/loans/'.$loan['id'], $librarian)->assertOk()->assertJsonPath('status', 'closed');
        $this->error($this->getJson('/api/v1/reports/most-borrowed?from=2026-10-02&to=2026-10-01', $this->adminHeaders), 422, 'INVALID_DATE_RANGE');
        $report = $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-01', $this->adminHeaders)->assertOk()->json();
        $this->assertCount(2, $report['rows']); $this->assertSame(1, $report['rows'][0]['times_borrowed']);
        $this->putJson('/api/v1/books/'.$a['id'], ['title' => 'Renamed', 'author' => 'Author', 'category_id' => $a['category']['id'], 'total_copies' => 2], $this->adminHeaders)->assertOk()->assertJsonPath('available_copies', 2);
        $this->getJson('/api/v1/books?q=renamed&size=1', $librarian)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.title', 'Renamed');
        $this->assertSame($report, $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-01', $this->adminHeaders)->json());
        $this->deleteJson('/api/v1/books/'.$a['id'], [], $this->adminHeaders)->assertNoContent();
        $this->error($this->getJson('/api/v1/books/'.$a['id'], $this->adminHeaders), 404, 'NOT_FOUND');
    }
    public function test_all_protected_routes_reject_missing_tokens_and_restricted_routes_reject_librarians_first(): void
    {
        $id = '99999999-9999-4999-8999-999999999999';
        $routes = [['POST','users'], ['GET','categories'], ['GET','books'], ['GET',"books/$id"], ['POST','books'], ['PUT',"books/$id"], ['DELETE',"books/$id"], ['PUT',"books/$id/cover"], ['GET',"books/$id/cover"], ['DELETE',"books/$id/cover"], ['GET','members'], ['POST','members'], ['GET',"members/$id"], ['GET','loans'], ['POST','loans'], ['GET',"loans/$id"], ['GET','loans/overdue'], ['POST',"loans/$id/items/$id/return"], ['GET','reports/most-borrowed']];
        foreach ($routes as [$method, $route]) { $this->error($this->json($method, '/api/v1/'.$route, []), 401, 'INVALID_CREDENTIALS'); }
        $user = new User($this->app->make(IdentifierGenerator::class)->next(), 'librarian', 'fake-test-hash', 'librarian'); $this->app->make(UserRepository::class)->addUser($user);
        $headers = ['Authorization' => 'Bearer '.$this->app->make(TokenIssuer::class)->issue($user)['access_token']];
        foreach ([['POST','users'], ['POST','books'], ['PUT',"books/$id"], ['DELETE',"books/$id"], ['PUT',"books/$id/cover"], ['DELETE',"books/$id/cover"], ['GET','reports/most-borrowed']] as [$method, $route]) {
            $this->error($this->json($method, '/api/v1/'.$route, [], $headers), 403, 'FORBIDDEN_ROLE');
        }
    }
    public function test_cover_binary_routes_and_three_mebibyte_validation_use_common_errors(): void
    {
        $directory = sys_get_temp_dir().'/library-http-covers-'.bin2hex(random_bytes(8)); mkdir($directory, 0700); config(['library.cover_directory' => $directory]);
        try {
            $book = $this->createBook(1); $this->assertNull($book['cover_key']); $route = '/api/v1/books/'.$book['id'].'/cover';
            $this->error($this->getJson($route, $this->adminHeaders), 404, 'NOT_FOUND');
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6X8AAAAASUVORK5CYII=');
            $path = $directory.'/fixture'; file_put_contents($path, str_pad($png, 2097152, "\0"));
            $server = ['HTTP_AUTHORIZATION' => $this->adminHeaders['Authorization'], 'HTTP_ACCEPT' => 'application/json'];
            $upload = $this->call('PUT', $route, [], [], ['file' => new UploadedFile($path, 'cover.png', 'image/png', null, true)], $server)->assertOk()->json();
            $this->assertNotEmpty($upload['cover_key']);
            $binary = $this->get($route, $this->adminHeaders)->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->assertSame(2097152, strlen($binary->getContent()));
            file_put_contents($path, str_pad($png, 3145728, "\0")); clearstatcache();
            $this->error($this->call('PUT', $route, [], [], ['file' => new UploadedFile($path, 'cover.png', 'image/png', null, true)], $server), 422, 'VALIDATION_ERROR');
            $this->deleteJson($route, [], $this->adminHeaders)->assertNoContent();
            $this->getJson('/api/v1/books/'.$book['id'], $this->adminHeaders)->assertOk()->assertJsonPath('cover_key', null);
        } finally { foreach (glob($directory.'/*') as $file) { unlink($file); } rmdir($directory); }
    }
    public function test_invalid_transport_pagination_cors_and_errors_do_not_leak_secrets(): void
    {
        $this->error($this->getJson('/api/v1/books?size=51', $this->adminHeaders), 422, 'VALIDATION_ERROR');
        $this->error($this->getJson('/api/v1/books/not-a-uuid', $this->adminHeaders), 422, 'VALIDATION_ERROR');
        $this->error($this->call('POST', '/api/v1/auth/login', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{bad'), 422, 'VALIDATION_ERROR');
        $this->options('/api/v1/books', [], ['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'GET', 'Access-Control-Request-Headers' => 'authorization'])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $cors = $this->getJson('/api/v1/health', ['Origin' => 'https://untrusted.example'])->assertOk();
        $this->assertNotSame('https://untrusted.example', $cors->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $cors->headers->get('Access-Control-Allow-Origin'));
        // Inspect the logger's records, including an unexpected exception whose text is sensitive.
        $handler = new \Monolog\Handler\TestHandler(); Log::getLogger()->pushHandler($handler);
        $sensitive = $this->password;
        \Illuminate\Support\Facades\Route::get('/api/v1/test-only-failure', static fn () => throw new \RuntimeException('password_hash '.$sensitive));
        $this->error($this->getJson('/api/v1/test-only-failure'), 500, 'INTERNAL_ERROR');
        $logged = json_encode($handler->getRecords());
        $this->assertStringNotContainsString($this->password, $logged); $this->assertStringNotContainsString('password_hash', $logged);
        Log::getLogger()->popHandler();
    }
}
