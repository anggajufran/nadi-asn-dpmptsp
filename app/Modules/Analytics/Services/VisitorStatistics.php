<?php
namespace App\Modules\Analytics\Services;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
class VisitorStatistics
{
    public const TIMEZONE = 'Asia/Makassar';
    public function record(string $visitor): void
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $date = $now->toDateString();
        $key = (string) config('app.key');
        $daily = hash_hmac('sha256', $date . '|' . $visitor, $key);
        $online = hash_hmac('sha256', 'online|' . $visitor, $key);
        DB::transaction(function () use ($now, $date, $daily, $online) {
            // ON CONFLICT makes concurrent tabs/heartbeats count only once per day.
            $inserted = DB::table('visitor_presences')->insertOrIgnore([
                'visit_date' => $date,
                'daily_hash' => $daily,
                'online_hash' => $online,
                'last_seen' => $now,
            ]);
            if ($inserted) {
                DB::statement(
                    'INSERT INTO visitor_daily_totals (visit_date, visitors) VALUES (?, 1) ' .
                        'ON CONFLICT (visit_date) DO UPDATE SET visitors = visitor_daily_totals.visitors + 1',
                    [$date],
                );
            } else {
                DB::table('visitor_presences')
                    ->where('visit_date', $date)
                    ->where('daily_hash', $daily)
                    ->update(['last_seen' => $now]);
            }
        }, 3);
    }
    public function read(): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $today = $now->toDateString();
        $totals = DB::table('visitor_daily_totals')
            ->where('visit_date', '<=', $today)
            ->selectRaw(
                'COALESCE(SUM(visitors) FILTER (WHERE visit_date = ?), 0) AS today, ' .
                    'COALESCE(SUM(visitors) FILTER (WHERE visit_date = ?), 0) AS yesterday, ' .
                    'COALESCE(SUM(visitors) FILTER (WHERE visit_date >= ?), 0) AS week, ' .
                    'COALESCE(SUM(visitors) FILTER (WHERE visit_date >= ?), 0) AS month, ' .
                    'COALESCE(SUM(visitors) FILTER (WHERE visit_date >= ?), 0) AS year, ' .
                    'COALESCE(SUM(visitors), 0) AS total, MIN(visit_date) AS tracking_since',
                [
                    $today,
                    $now->subDay()->toDateString(),
                    $now->startOfWeek(1)->toDateString(),
                    $now->startOfMonth()->toDateString(),
                    $now->startOfYear()->toDateString(),
                ],
            )
            ->first();
        $result = [];
        foreach (['today', 'yesterday', 'week', 'month', 'year', 'total'] as $name) {
            $result[$name] = (int) $totals->$name;
        }
        $result['online'] = DB::table('visitor_presences')
            ->where('last_seen', '>', $now->subMinutes(5))
            ->where('last_seen', '<=', $now)
            ->distinct()
            ->count('online_hash');
        return $result + [
            'trackingSince' => $totals->tracking_since,
            'updatedAt' => $now->toIso8601String(),
            'timezone' => self::TIMEZONE,
            'onlineWindowMinutes' => 5,
            'demo' => false,
        ];
    }
    public function prune(): int
    {
        // Keep today's/yesterday's daily deduplication only; totals have no identifiers.
        return DB::table('visitor_presences')
            ->where(
                'visit_date',
                '<',
                CarbonImmutable::now(self::TIMEZONE)->subDay()->toDateString(),
            )
            ->delete();
    }
}
