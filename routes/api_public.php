<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Analytics\Http\DashboardController;
Route::get('public/statistics', [DashboardController::class, 'public'])->middleware(
    'throttle:public',
);
Route::get('v1/public/dashboard', [DashboardController::class, 'public'])->middleware(
    'throttle:public',
);

Route::get('public/units', [\App\Modules\References\Http\UnitController::class, 'index']);

// Visitor counts are independent of authentication sessions and never renew idle login.
Route::withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
])->group(function () {
    Route::get('public/visitors', [
        \App\Modules\Analytics\Http\VisitorController::class,
        'index',
    ])->middleware('throttle:public');
    Route::post('public/visitors/heartbeat', [
        \App\Modules\Analytics\Http\VisitorController::class,
        'heartbeat',
    ])->middleware('throttle:visitors');
});
