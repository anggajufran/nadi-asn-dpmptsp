<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for(
            'login',
            fn($request) => [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(30)->by(
                    hash(
                        'sha256',
                        strtolower((string) $request->input('nip', $request->input('email'))) .
                            '|' .
                            $request->ip(),
                    ),
                ),
            ],
        );
        RateLimiter::for(
            'recovery',
            fn($request) => [
                Limit::perMinute(3)->by($request->ip()),
                Limit::perHour(5)->by(
                    hash('sha256', strtolower((string) $request->input('email'))),
                ),
            ],
        );
        RateLimiter::for('visitors', fn($request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('public', fn($request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for(
            'internal',
            fn($request) => Limit::perMinute(120)->by($request->user()?->id ?? $request->ip()),
        );
    }
}
