<?php
namespace Tests\Laravel;
use Tests\TestCase;
use App\Modules\References\Services\UnitOrder;
class UnitOrderTest extends TestCase
{
    public function test_requested_order_is_stable_and_unknown_units_follow(): void
    {
        $labels = json_decode(file_get_contents(resource_path('js/domain/unit-order.json')), true);
        $rows = array_map(fn($label) => ['label' => $label, 'value' => 1], array_reverse($labels));
        $rows[] = ['label' => 'Unit Baru', 'value' => 2];
        $this->assertSame([...$labels, 'Unit Baru'], array_column(UnitOrder::sort($rows), 'label'));
    }
    public function test_login_does_not_accept_email(): void
    {
        $u = $this->user();
        $this->postJson('/api/auth/login', [
            'email' => $u->email,
            'password' => 'KataSandiUji-12345',
        ])->assertUnprocessable();
    }
}
