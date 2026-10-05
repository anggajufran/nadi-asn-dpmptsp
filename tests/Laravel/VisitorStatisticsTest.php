<?php
namespace Tests\Laravel;
use Tests\TestCase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Modules\Analytics\Services\VisitorStatistics;
class VisitorStatisticsTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }
    public function test_reloads_and_multiple_tabs_count_once_but_different_browsers_count_separately(): void
    {
        CarbonImmutable::setTestNow('2026-10-01T02:00:00+08:00');
        $service = app(VisitorStatistics::class);
        $visitor = (string) Str::uuid();
        $service->record($visitor);
        $service->record($visitor);
        $service->record((string) Str::uuid());
        $this->assertSame(2, $service->read()['today']);
        $this->assertSame(2, $service->read()['online']);
        $this->assertDatabaseCount('visitor_presences', 2);
        $serialized = json_encode(DB::table('visitor_presences')->get());
        $this->assertStringNotContainsString($visitor, $serialized);
    }
    public function test_wita_midnight_counts_new_day_without_double_counting_online(): void
    {
        $visitor = (string) Str::uuid();
        $service = app(VisitorStatistics::class);
        CarbonImmutable::setTestNow('2026-09-30T15:59:00Z');
        $service->record($visitor);
        CarbonImmutable::setTestNow('2026-09-30T16:01:00Z');
        $service->record($visitor);
        $stats = $service->read();
        $this->assertSame(1, $stats['today']);
        $this->assertSame(1, $stats['yesterday']);
        $this->assertSame(1, $stats['month']);
        $this->assertSame(2, $stats['week']);
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['online']);
        CarbonImmutable::setTestNow('2026-10-01T00:06:00+08:00');
        $this->assertSame(0, $service->read()['online']);
    }
    public function test_calendar_periods_exclude_prior_week_month_and_year_and_future_rows(): void
    {
        CarbonImmutable::setTestNow('2027-01-04T09:00:00+08:00'); // Monday
        DB::table('visitor_daily_totals')->insert([
            ['visit_date' => '2026-12-31', 'visitors' => 7],
            ['visit_date' => '2027-01-01', 'visitors' => 3],
            ['visit_date' => '2027-01-03', 'visitors' => 2],
            ['visit_date' => '2027-01-04', 'visitors' => 4],
            ['visit_date' => '2027-01-05', 'visitors' => 100],
        ]);
        $stats = app(VisitorStatistics::class)->read();
        $this->assertSame(4, $stats['today']);
        $this->assertSame(2, $stats['yesterday']);
        $this->assertSame(4, $stats['week']);
        $this->assertSame(9, $stats['month']);
        $this->assertSame(9, $stats['year']);
        $this->assertSame(16, $stats['total']);
    }
    public function test_presence_retention_preserves_totals_and_daily_deduplication(): void
    {
        $service = app(VisitorStatistics::class);
        CarbonImmutable::setTestNow('2026-09-29T12:00:00+08:00');
        $service->record((string) Str::uuid());
        CarbonImmutable::setTestNow('2026-09-30T12:00:00+08:00');
        $visitor = (string) Str::uuid();
        $service->record($visitor);
        CarbonImmutable::setTestNow('2026-10-01T12:00:00+08:00');
        $service->record($visitor);
        $this->assertSame(1, $service->prune());
        $service->record($visitor);
        $this->assertSame(3, $service->read()['total']);
        $this->assertDatabaseCount('visitor_presences', 2);
    }
    public function test_public_endpoint_exposes_only_aggregate_counts_and_cookie_is_secure(): void
    {
        CarbonImmutable::setTestNow('2026-10-01T12:00:00+08:00');
        config(['session.secure' => true]);
        $this->withHeader('Origin', config('nadi.frontend_url'));
        $r = $this->withHeader('User-Agent', 'Mozilla/5.0')->postJson(
            '/api/public/visitors/heartbeat',
            [],
        );
        $r->assertOk()->assertJsonPath('today', 1)->assertJsonPath('demo', false);
        $cookie = collect($r->headers->getCookies())->first(
            fn($c) => $c->getName() === 'nadi_visitor',
        );
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $keys = array_keys($r->json());
        $this->assertSame(
            [
                'today',
                'yesterday',
                'week',
                'month',
                'year',
                'total',
                'online',
                'trackingSince',
                'updatedAt',
                'timezone',
                'onlineWindowMinutes',
                'demo',
            ],
            $keys,
        );
        $this->getJson('/api/public/visitors')->assertOk()->assertJsonPath('total', 1);
    }
    public function test_invalid_payload_and_bot_do_not_inflate_counts(): void
    {
        $this->withHeader('Origin', config('nadi.frontend_url'));
        $this->postJson('/api/public/visitors/heartbeat', ['nip' => 'not-accepted'])->assertStatus(
            422,
        );
        $this->withHeader('User-Agent', 'Googlebot')
            ->postJson('/api/public/visitors/heartbeat', [])
            ->assertOk()
            ->assertJsonPath('total', 0);
        $this->getJson('/api/public/visitors')->assertOk()->assertJsonPath('total', 0);
        $this->assertDatabaseCount('visitor_daily_totals', 0);
    }
    public function test_foreign_missing_and_null_origin_are_rejected(): void
    {
        foreach (
            [
                null,
                'null',
                'https://attacker.example',
                'https://nadiasndpmptspsulteng.online.attacker.example',
            ]
            as $origin
        ) {
            $this->flushHeaders();
            if ($origin !== null) {
                $this->withHeader('Origin', $origin);
            }
            $this->postJson('/api/public/visitors/heartbeat', [])->assertForbidden();
        }
        $this->assertDatabaseCount('visitor_daily_totals', 0);
    }
    public function test_heartbeat_uses_encrypted_cookie_without_creating_or_refreshing_login_session(): void
    {
        $this->withHeaders([
            'Origin' => config('nadi.frontend_url'),
            'User-Agent' => 'Mozilla/5.0',
        ]);
        $first = $this->postJson('/api/public/visitors/heartbeat', [])->assertOk();
        $first->assertCookieMissing('nadi_session')->assertCookieMissing('XSRF-TOKEN');
        $cookie = collect($first->headers->getCookies())->first(
            fn($c) => $c->getName() === 'nadi_visitor',
        );
        $this->assertFalse(Str::isUuid($cookie->getValue()));
        $this->withCredentials()
            ->withUnencryptedCookie('nadi_visitor', $cookie->getValue())
            ->postJson('/api/public/visitors/heartbeat', [])
            ->assertOk()
            ->assertJsonPath('total', 1);
        $this->getJson('/api/public/visitors')->assertOk()->assertCookieMissing('nadi_session');
        $route = app('router')
            ->getRoutes()
            ->match(\Illuminate\Http\Request::create('/api/public/visitors/heartbeat', 'POST'));
        $this->assertNotContains(
            \Illuminate\Session\Middleware\StartSession::class,
            app('router')->gatherRouteMiddleware($route),
        );
    }
}
