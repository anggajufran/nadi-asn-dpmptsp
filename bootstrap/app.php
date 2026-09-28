<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Modules\IdentityAccess\Middleware\RequireFullSession;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['full-session' => RequireFullSession::class]);
        $middleware->web(append: [App\Modules\IdentityAccess\Middleware\SecurityHeaders::class]);
        $middleware->trustProxies(
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn($request) => $request->is('api/*'));
        $exceptions->respond(function ($response) {
            if (request()->is('api/*')) {
                if ($response->getStatusCode() === 500 && !config('app.debug')) {
                    $response = response()->json(
                        ['message' => 'Terjadi gangguan pada server. Silakan coba kembali.'],
                        500,
                    );
                }
                $response->headers->set('Cache-Control', 'no-store');
                $response->headers->set('X-Content-Type-Options', 'nosniff');
                $response->headers->set('X-Frame-Options', 'DENY');
            }
            return $response;
        });
    })
    ->create();
