<?php
// Emits operational counts only; never employee identities or credentials.
try {
    require '/var/www/vendor/autoload.php';
    $app = require '/var/www/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $db = Illuminate\Support\Facades\DB::class;
    $db::selectOne('SELECT 1');
    $expiry = $db::table('cache')->where('key', 'nadi_scheduler_heartbeat')->value('expiration');
    $oldest = $db::table('jobs')->min('created_at');
    echo json_encode([
        'database' => true,
        'scheduler_age' => $expiry ? time() - ((int) $expiry - 300) : null,
        'queue_oldest_age' => $oldest ? time() - (int) $oldest : 0,
        'failed_jobs' => $db::table('failed_jobs')->count(),
        'production_ready' => (bool) config('nadi.production_ready'),
        'debug_off' => !config('app.debug'),
        'cookie_secure' => (bool) config('session.secure'),
    ]);
} catch (Throwable $error) {
    echo json_encode(['database' => false]);
    exit(1);
}
