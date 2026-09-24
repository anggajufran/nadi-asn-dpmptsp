<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Modules\IdentityAccess\Http\AuthController;
Route::prefix('api')->group(function () {
    Route::get('activation', [
        \App\Modules\IdentityAccess\Http\ActivationController::class,
        'show',
    ])->middleware('throttle:login');
    Route::post('activation', [
        \App\Modules\IdentityAccess\Http\ActivationController::class,
        'store',
    ])->middleware('throttle:login');
    Route::post('auth/recovery', [
        \App\Modules\IdentityAccess\Http\RecoveryController::class,
        'store',
    ])->middleware('throttle:recovery');
    Route::get('csrf', fn() => response()->json(['ok' => true]));
    Route::get('session', [AuthController::class, 'session']);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/totp', [AuthController::class, 'totp'])->middleware('throttle:login');
    Route::post('logout', [AuthController::class, 'logout']);
    require __DIR__ . '/api_public.php';
    Route::middleware(['full-session', 'throttle:internal'])->group(
        fn() => require __DIR__ . '/api_internal.php',
    );
});
Route::get('/{path?}', function () {
    abort_unless(is_file(base_path('dist/index.html')), 503, 'Frontend belum dibangun.');
    return response()->file(base_path('dist/index.html'), [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
})->where('path', '^(?!api(?:/|$)).*');
