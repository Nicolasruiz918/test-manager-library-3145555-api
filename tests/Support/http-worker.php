<?php
declare(strict_types=1);
$job = json_decode(trim(fgets(STDIN)), true, 512, JSON_THROW_ON_ERROR);
echo "READY\n"; fflush(STDOUT); fgets(STDIN);
$context = stream_context_create(['http' => ['method' => 'POST', 'header' => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer '.$job['token']], 'content' => json_encode($job['body']), 'ignore_errors' => true, 'timeout' => 20]]);
$content = file_get_contents($job['url'], false, $context);
$headers = http_get_last_response_headers();
preg_match('/HTTP\/\S+ (\d+)/', $headers[0], $match);
echo json_encode(['status' => (int) $match[1], 'body' => json_decode($content, true)], JSON_THROW_ON_ERROR)."\n";
