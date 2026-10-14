<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Library\Application\Ports\{IdentifierGenerator, TokenIssuer, UserRepository};
use App\Library\Application\Services\{CreateBookService, CreateMemberService};
use App\Library\Domain\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\{InputStream, Process};
use Tests\TestCase;
final class LiveHttpTest extends TestCase
{
    public function test_live_http_last_copy_and_real_multipart_put(): void
    {
        if (getenv('DB_DATABASE') !== 'manager_library_test') { $this->markTestSkipped('Use the disposable PostgreSQL test stack.'); }
        $this->assertSame(0, $this->app->make(Kernel::class)->call('library:migrate'));
        $secret = bin2hex(random_bytes(32)); config(['library.jwt_secret' => $secret]);
        $ids = $this->app->make(IdentifierGenerator::class);
        $users = $this->app->make(UserRepository::class); $tokens = $this->app->make(TokenIssuer::class);
        $admin = new User($ids->next(), 'admin', 'fake-test-hash', 'admin'); $users->addUser($admin);
        $adminToken = $tokens->issue($admin)['access_token'];
        $operators = [];
        foreach (['one', 'two'] as $name) { $user = new User($ids->next(), $name, 'fake-test-hash', 'librarian'); $users->addUser($user); $operators[] = $tokens->issue($user)['access_token']; }
        $book = $this->app->make(CreateBookService::class)->execute('Last copy', 'Author', '0000000000001', '11111111-1111-4111-8111-111111111111', 1);
        $members = [$this->app->make(CreateMemberService::class)->execute('One', '001'), $this->app->make(CreateMemberService::class)->execute('Two', '002')];
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); $address = stream_socket_get_name($socket, false); fclose($socket);
        $url = 'http://'.$address;
        $directory = sys_get_temp_dir().'/library-live-covers-'.bin2hex(random_bytes(8)); mkdir($directory, 0700);
        $server = new Process([PHP_BINARY, '-d', 'upload_max_filesize=8M', '-d', 'post_max_size=10M', '-S', $address, '-t', public_path(), public_path('index.php')], base_path(), ['PHP_CLI_SERVER_WORKERS' => '4', 'JWT_SECRET' => $secret, 'COVER_DIRECTORY' => $directory], null, null);
        $workers = [];
        try {
            $server->start(); $ready = false;
            for ($i = 0; $i < 100; ++$i) {
                if (@file_get_contents($url.'/health') === '{"status":"ok"}') { $ready = true; break; }
                usleep(50000);
            }
            $this->assertTrue($ready, $server->getErrorOutput());
            $inputs = [];
            foreach ($members as $index => $member) {
                $input = new InputStream();
                $input->write(json_encode(['url' => $url.'/api/v1/loans', 'token' => $operators[$index], 'body' => ['member_id' => $member->id, 'items' => [['book_id' => $book->id]]]])."\n");
                $worker = new Process([PHP_BINARY, base_path('tests/Support/http-worker.php')], base_path(), null, $input, 30); $worker->start();
                $workers[] = $worker; $inputs[] = $input;
            }
            foreach ($workers as $worker) { if (!str_contains($worker->getOutput(), 'READY')) { $worker->waitUntil(static fn () => str_contains($worker->getOutput(), 'READY')); } }
            foreach ($inputs as $input) { $input->write("GO\n"); $input->close(); }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait(); $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                $lines = explode("\n", trim($worker->getOutput())); $results[] = json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR);
            }
            usort($results, static fn ($a, $b) => $a['status'] <=> $b['status']);
            $this->assertSame([201, 409], array_column($results, 'status'));
            $this->assertSame('BOOK_NOT_AVAILABLE', $results[1]['body']['code']);
            $this->assertSame(1, DB::table('library.loan')->count()); $this->assertSame(0, DB::table('library.book')->where('id', $book->id)->value('available_copies'));
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6X8AAAAASUVORK5CYII=');
            foreach ([[2097152, 200], [3145728, 422]] as [$size, $expected]) {
                $boundary = 'LibraryBoundary'.bin2hex(random_bytes(8));
                $body = '--'.$boundary."\r\nContent-Disposition: form-data; name=\"file\"; filename=\"cover.png\"\r\nContent-Type: image/png\r\n\r\n".str_pad($png, $size, "\0")."\r\n--".$boundary."--\r\n";
                $context = stream_context_create(['http' => ['method' => 'PUT', 'header' => ['Content-Type: multipart/form-data; boundary='.$boundary, 'Accept: application/json', 'Authorization: Bearer '.$adminToken], 'content' => $body, 'ignore_errors' => true, 'timeout' => 15]]);
                $response = file_get_contents($url.'/api/v1/books/'.$book->id.'/cover', false, $context); $headers = http_get_last_response_headers();
                preg_match('/HTTP\/\S+ (\d+)/', $headers[0], $match);
                $this->assertSame($expected, (int) $match[1], $response);
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                if ($expected === 200) { $this->assertNotNull($data['cover_key']); } else { $this->assertSame('VALIDATION_ERROR', $data['code']); }
            }
            $this->assertStringNotContainsString($secret, $server->getOutput().$server->getErrorOutput());
            $this->assertStringNotContainsString($adminToken, $server->getOutput().$server->getErrorOutput());
        } finally {
            foreach ($workers as $worker) { if ($worker->isRunning()) { $worker->stop(1); } }
            $server->stop(1);
            foreach (glob($directory.'/*') as $file) { unlink($file); } rmdir($directory);
            DB::unprepared('DROP SCHEMA IF EXISTS library CASCADE');
        }
    }
}
