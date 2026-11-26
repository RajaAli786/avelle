<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function check($condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
    echo "PASS $label\n";
}
function renderError(Throwable $error, bool $json = false)
{
    $request = Request::create('/account/orders/sample-reference', 'GET');
    if ($json) $request->headers->set('Accept', 'application/json');
    app()->instance('request', $request);
    app('url')->setRequest($request);
    return app(ExceptionHandler::class)->render($request, $error);
}
function noLeaks($response): bool
{
    foreach (['SQLSTATE', 'secret-database-name', 'private-user@example.test', 'TOP_SECRET_VALUE',
        'QueryException', 'vendor/laravel', 'Exception trace', 'password_column'] as $secret) {
        if (str_contains($response->getContent(), $secret)) return false;
    }
    return true;
}

$connection = new PDOException('SQLSTATE[HY000] [2002] TOP_SECRET_VALUE secret-database-name');
$connection->errorInfo = ['HY000', 2002, 'Connection refused'];
$query = new QueryException('mysql', 'select password_column from users where email = ?', ['private-user@example.test'], $connection);
DB::enableQueryLog();
foreach ([false, true] as $debug) {
    config(['app.debug' => $debug]);
    $response = renderError($query);
    check($response->getStatusCode() === 503 && str_contains($response->getContent(), 'We will be back shortly.'), 'Database outage renders a friendly 503 with debug ' . ($debug ? 'on' : 'off'));
    check(noLeaks($response), 'HTML database error never exposes diagnostics');
    $json = renderError($query, true);
    $body = json_decode($json->getContent(), true);
    check($json->getStatusCode() === 503 && array_keys($body) === ['message', 'status'] && noLeaks($json), 'API database error returns only safe fields');
}
$syntax = new PDOException('TOP_SECRET_VALUE password_column');
$syntax->errorInfo = ['42S02', 1146, 'secret-database-name'];
check(renderError(new QueryException('mysql', 'select password_column', [], $syntax))->getStatusCode() === 500, 'SQL programming error remains 500 rather than a false outage');
$notFound = renderError(new NotFoundHttpException('TOP_SECRET_VALUE'));
check($notFound->getStatusCode() === 404 && str_contains($notFound->getContent(), 'This page slipped away.') && noLeaks($notFound), 'Unknown page renders a safe 404');
foreach ([403, 419, 429, 500, 503] as $status) {
    $response = renderError(new HttpException($status, 'TOP_SECRET_VALUE'));
    check($response->getStatusCode() === $status && noLeaks($response), 'Safe page preserves HTTP ' . $status);
}
$method = renderError(new MethodNotAllowedHttpException(['POST'], 'TOP_SECRET_VALUE'));
check($method->getStatusCode() === 405 && $method->headers->get('Allow') === 'POST', 'Method-not-allowed response retains its Allow header');
$response = renderError(new RuntimeException('TOP_SECRET_VALUE vendor/laravel password_column'));
check($response->getStatusCode() === 500 && noLeaks($response), 'Unexpected exceptions use a generic 500 even if debug is enabled');
check(str_contains($response->headers->get('Cache-Control'), 'no-store') && $response->headers->get('X-Robots-Tag') === 'noindex, nofollow', 'Error responses cannot be cached or indexed');
$validation = renderError(ValidationException::withMessages(['email' => 'Please enter a valid email.']), true);
check($validation->getStatusCode() === 422 && isset(json_decode($validation->getContent(), true)['errors']['email']), 'Normal validation feedback remains intact');
check(DB::getQueryLog() === [], 'Error rendering never queries the database');

// Exercise the real HTTP kernel with a refused test connection. This never stops or
// modifies the owner's MySQL instance and never touches any customer records.
config(['app.debug' => false, 'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => 1,
    'database.connections.mysql.options' => [PDO::ATTR_TIMEOUT => 1], 'session.driver' => 'array']);
DB::purge('mysql');
$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$request = Request::create('http://localhost/', 'GET');
$liveResponse = $kernel->handle($request);
check($liveResponse->getStatusCode() === 503 && noLeaks($liveResponse), 'Full web request with unavailable MySQL returns safe 503');
$request = Request::create('http://localhost/a-definitely-missing-page-for-error-testing', 'GET');
$missing = $kernel->handle($request);
check($missing->getStatusCode() === 404 && noLeaks($missing), 'Full missing-page request returns safe 404');
echo "Safe error checks passed. No database records or running services were changed.\n";
