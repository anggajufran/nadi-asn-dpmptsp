<?php
namespace Tests\Laravel;
use Tests\TestCase;
use App\Modules\Personnel\Services\{PersonnelClock, EmployeeProjection};
use App\Modules\Governance\Services\ChangeService;
class PersonnelClockTest extends TestCase
{
    public function test_service_follows_months_and_age_follows_birthday(): void
    {
        $this->assertSame(
            [13, 1, '2026-09'],
            PersonnelClock::service(13, 1, '2026-09', '2026-09-30'),
        );
        $this->assertSame(
            [13, 2, '2026-10'],
            PersonnelClock::service(13, 1, '2026-09', '2026-10-01'),
        );
        $this->assertSame(
            [15, 11, '2029-07'],
            PersonnelClock::service(13, 1, '2026-09', '2029-07-01'),
        );
        $this->assertSame(
            [14, 0, '2027-08'],
            PersonnelClock::service(13, 1, '2026-09', '2027-08-01'),
        );
        $this->assertSame(
            [null, null, '2026-09'],
            PersonnelClock::service(null, null, '2026-09', '2027-08-01'),
        );
        $this->assertSame(29, PersonnelClock::age('1996-09-17', '2026-09-16'));
        $this->assertSame(30, PersonnelClock::age('1996-09-17', '2026-09-17'));
        $this->assertNull(PersonnelClock::age(null, '2026-09-16'));
    }
    public function test_service_correction_keeps_the_month_when_the_form_was_loaded(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01'));
        $this->references();
        $u = $this->user('SUPER_ADMIN');
        $e = $this->employee($u);
        $p = app(ChangeService::class)->submit($u, [
            'employeeId' => $e->id,
            'revision' => 1,
            'changes' => [
                'serviceYears' => 13,
                'serviceMonths' => 11,
                'serviceReference' => '2026-09',
            ],
            'reason' => 'Koreksi masa kerja per September',
        ]);
        app(ChangeService::class)->decide($u, $p->id, 'APPROVED', 'Telah diperiksa');
        $row = app(EmployeeProjection::class)->project($e->fresh(), $u);
        $this->assertSame(14, $row['serviceYears']);
        $this->assertSame(0, $row['serviceMonths']);
    }
    public function test_approval_after_month_change_does_not_double_count_service(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-10'));
        $this->references();
        $u = $this->user('SUPER_ADMIN');
        $e = $this->employee($u);
        $e->assignments()->update([
            'service_years' => 13,
            'service_months' => 1,
            'service_reference' => '2026-09',
        ]);
        $row = app(EmployeeProjection::class)->project($e->fresh(), $u);
        $this->assertSame(2, $row['serviceMonths']);
        $p = app(ChangeService::class)->submit($u, [
            'employeeId' => $e->id,
            'revision' => 1,
            'changes' => ['name' => 'Nama diperbaiki'],
            'reason' => 'Sesuai dokumen sumber',
        ]);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10'));
        app(ChangeService::class)->decide($u, $p->id, 'APPROVED', 'Telah diperiksa');
        $row = app(EmployeeProjection::class)->project($e->fresh(), $u);
        $this->assertSame(13, $row['serviceYears']);
        $this->assertSame(3, $row['serviceMonths']);
        $again = app(EmployeeProjection::class)->project($e->fresh(), $u);
        $this->assertSame($row['serviceMonths'], $again['serviceMonths']);
    }
}
