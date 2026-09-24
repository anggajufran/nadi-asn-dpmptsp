<?php
use Illuminate\Support\Facades\{Route, DB};
use Illuminate\Http\Request;
use App\Modules\Personnel\Http\EmployeeController;
use App\Modules\Governance\Http\ChangeController;
use App\Modules\Analytics\Http\DashboardController;
use App\Modules\Rules\Services\CalculationGate;
use App\Modules\Audit\Models\AuditEvent;
Route::get('employees', [EmployeeController::class, 'index']);
Route::get('v1/internal/employees', [EmployeeController::class, 'index']);
Route::get('dashboard', [DashboardController::class, 'internal']);
Route::get('proposals', [ChangeController::class, 'index']);
Route::post('proposals', [ChangeController::class, 'store']);
Route::post('proposals/{id}/decide', [ChangeController::class, 'decide'])->whereUuid('id');
Route::get(
    'rules',
    fn(CalculationGate $gate) => response()->json(
        array_map(fn($kind) => $gate->explain($kind), ['DUK', 'KP', 'KGB']),
    ),
);
Route::get('audit', function (Request $r) {
    abort_unless($r->user()->canWrite(), 403);
    return response()->json(
        AuditEvent::latest('created_at')->limit(100)->get()->map(
            fn($e) => [
                'id' => $e->id,
                'actor' => $e->actor_name,
                'action' => $e->action,
                'subject' => $e->subject,
                'createdAt' => $e->created_at->toIso8601String(),
            ],
        ),
    );
});
Route::get('imports', [\App\Modules\Imports\Http\ImportController::class, 'index']);
Route::post('imports', [\App\Modules\Imports\Http\ImportController::class, 'store']);
Route::get('imports/{batch}', [
    \App\Modules\Imports\Http\ImportController::class,
    'show',
])->whereUuid('batch');
Route::post('imports/{batch}/validate', [
    \App\Modules\Imports\Http\ImportController::class,
    'validate',
])->whereUuid('batch');
Route::post('imports/{batch}/confirm', [
    \App\Modules\Imports\Http\ImportController::class,
    'confirm',
])->whereUuid('batch');
Route::post('exports', [\App\Modules\SnapshotsReports\Http\ExportController::class, 'store']);
Route::get('exports/{export}', [
    \App\Modules\SnapshotsReports\Http\ExportController::class,
    'show',
])->whereUuid('export');
Route::get('exports/{export}/download', [
    \App\Modules\SnapshotsReports\Http\ExportController::class,
    'download',
])->whereUuid('export');

Route::get('exports', [\App\Modules\SnapshotsReports\Http\ExportController::class, 'index']);

Route::get('units', [\App\Modules\References\Http\UnitController::class, 'index']);
Route::post('units', [\App\Modules\References\Http\UnitController::class, 'store']);
Route::patch('units/{id}', [
    \App\Modules\References\Http\UnitController::class,
    'update',
])->whereUuid('id');

Route::post('proposals/batch', [ChangeController::class, 'batch']);

Route::get('duk', [\App\Modules\Duk\Http\ManualDukController::class, 'index']);
Route::post('duk/order', [\App\Modules\Duk\Http\ManualDukController::class, 'save']);
Route::get('accounts', [\App\Modules\IdentityAccess\Http\AccountController::class, 'index']);
Route::post('accounts', [\App\Modules\IdentityAccess\Http\AccountController::class, 'store']);
Route::post('accounts/{id}/activation', [
    \App\Modules\IdentityAccess\Http\AccountController::class,
    'activation',
])->whereUuid('id');
Route::post('accounts/{id}/disable', [
    \App\Modules\IdentityAccess\Http\AccountController::class,
    'disable',
])->whereUuid('id');

Route::get('radar/{kind}', [\App\Modules\Radar\Http\RadarController::class, 'index'])->whereIn(
    'kind',
    ['kp', 'kgb'],
);
Route::post('radar/{kind}/{id}', [\App\Modules\Radar\Http\RadarController::class, 'save'])
    ->whereIn('kind', ['kp', 'kgb'])
    ->whereUuid('id');
