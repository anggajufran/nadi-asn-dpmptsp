<?php
namespace Tests\Laravel;
use Tests\TestCase;
use Illuminate\Support\Facades\Route;
class HttpSecurityTest extends TestCase
{
    public function test_unexpected_errors_never_reveal_internal_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/security-test-error', function () {
            throw new \RuntimeException('PRIVATE_DATABASE_PASSWORD /private/server/path');
        });
        $r = $this->getJson('/api/security-test-error')
            ->assertStatus(500)
            ->assertJson(['message' => 'Terjadi gangguan pada server. Silakan coba kembali.'])
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringNotContainsString('PRIVATE_DATABASE_PASSWORD', $r->getContent());
        $this->assertStringNotContainsString('/private/server/path', $r->getContent());
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }
    public function test_forwarding_headers_from_untrusted_clients_are_ignored(): void
    {
        config(['trustedproxy.proxies' => []]);
        Route::get(
            '/api/security-test-origin',
            fn() => response()->json([
                'ip' => request()->ip(),
                'secure' => request()->secure(),
            ]),
        );
        $this->withHeaders(['X-Forwarded-For' => '198.51.100.99', 'X-Forwarded-Proto' => 'https'])
            ->getJson('/api/security-test-origin')
            ->assertJson(['ip' => '127.0.0.1', 'secure' => false]);
    }
    public function test_sql_payload_is_treated_as_search_text(): void
    {
        $u = $this->user();
        $this->employee($u);
        $this->fullSession($u)
            ->getJson('/api/employees?search=' . urlencode("' OR 1=1; DROP TABLE employees; --"))
            ->assertOk()
            ->assertJsonPath('total', 0);
        $this->assertDatabaseCount('employees', 1);
    }
    public function test_script_url_and_unapproved_fields_cannot_be_saved(): void
    {
        $this->references();
        $u = $this->user();
        $e = $this->employee($u);
        foreach ([['driveUrl' => 'javascript:alert(1)'], ['role' => 'SUPER_ADMIN']] as $changes) {
            $this->fullSession($u)
                ->postJson('/api/proposals', [
                    'employeeId' => $e->id,
                    'revision' => 1,
                    'changes' => $changes,
                    'reason' => 'Pengujian keamanan input',
                ])
                ->assertStatus(422);
        }
        $this->assertDatabaseCount('change_requests', 0);
    }
}
