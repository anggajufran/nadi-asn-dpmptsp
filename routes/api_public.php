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
