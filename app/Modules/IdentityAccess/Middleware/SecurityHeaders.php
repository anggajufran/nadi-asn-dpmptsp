<?php
namespace App\Modules\IdentityAccess\Middleware;
use Closure;
class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store');
            $response->headers->set('X-Robots-Tag', 'noindex');
        }
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        return $response;
    }
}
