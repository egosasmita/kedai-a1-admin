<?php

namespace Tests\Unit;

use App\Services\AttendanceShiftService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class AttendanceShiftServiceTest extends TestCase
{
    public function test_it_applies_the_grace_period_to_an_overnight_shift(): void
    {
        $service = new AttendanceShiftService;
        $shift = (object) ['starts_at' => '23:00:00', 'ends_at' => '07:00:00'];
        $onTime = Carbon::parse('2026-10-29 23:15:00', 'Asia/Jakarta');
        $late = Carbon::parse('2026-10-29 23:16:00', 'Asia/Jakarta');
        $onTimeWindow = $service->checkInWindow($shift, $onTime);
        $lateWindow = $service->checkInWindow($shift, $late);

        $this->assertNotNull($onTimeWindow);
        $this->assertSame(0, $service->lateMinutes($onTimeWindow, $onTime));
        $this->assertSame('2026-10-29', $service->attendanceDate($shift, $onTime));
        $this->assertNotNull($lateWindow);
        $this->assertSame(16, $service->lateMinutes($lateWindow, $late));
        $this->assertNull($service->checkInWindow($shift, Carbon::parse('2026-10-29 22:44:59', 'Asia/Jakarta')));
        $this->assertNotNull($service->checkOutWindow($shift, Carbon::parse('2026-10-30 07:15:00', 'Asia/Jakarta')));
        $this->assertNull($service->checkOutWindow($shift, Carbon::parse('2026-10-30 07:15:01', 'Asia/Jakarta')));
    }
}
