<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = \Tests\Support\IsolatedAgendaQa::boot();
$app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (\Throwable $e) use ($argv) {
    file_put_contents($argv[2].'.error.json', json_encode(['type' => get_class($e), 'message' => $e->getMessage()]));
});
$input = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
\Illuminate\Support\Facades\Auth::loginUsingId($input['actor']);
file_put_contents($argv[2].'.ready', 'ready');
$deadline = microtime(true) + 15;
while (!file_exists($input['release'])) {
    if (microtime(true) > $deadline) { throw new RuntimeException('QA barrier timeout.'); }
    usleep(5000);
}
$request = \Illuminate\Http\Request::create($input['path'], 'POST', [], [], [],
    ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], json_encode($input['payload']));
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
file_put_contents($argv[2], json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)]));
$kernel->terminate($request, $response);
