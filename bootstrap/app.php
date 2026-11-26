<?php

use Illuminate\Foundation\Application;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . "/../routes/web.php",
        commands: __DIR__ . "/../routes/console.php",
        health: "/up",
    )
    ->withMiddleware(function (Illuminate\Foundation\Configuration\Middleware $middleware): void {
        $middleware->web(
            append: [
                App\Http\Middleware\SearchIndexing::class,
                App\Http\Middleware\EnsureAccountIsActive::class,
            ],
        );
    })
    ->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) {
            return \App\Support\SafeErrors::render($exception, $request);
        });
    })
    ->create();
if (PHP_OS_FAMILY === "Windows") {
    $app->addAbsoluteCachePathPrefix(substr(__DIR__, 0, 3));
    $app->addAbsoluteCachePathPrefix(str_replace("\\", "/", substr(__DIR__, 0, 3)));
}
return $app;
