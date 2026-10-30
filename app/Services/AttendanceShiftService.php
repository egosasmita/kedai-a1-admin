<?php

namespace App\Services;

use Carbon\Carbon;

class AttendanceShiftService
{
    /**
     * @return array{start: Carbon, end: Carbon}|null
     */
    public function checkInWindow(object $shift, Carbon $now): ?array
    {
        foreach ($this->occurrences($shift, $now) as $window) {
            if ($now->getTimestamp() >= $window['start']->copy()->subMinutes(15)->getTimestamp()
                && $now->getTimestamp() < $window['end']->getTimestamp()) {
                return $window;
            }
        }

        return null;
    }

    /**
     * @return array{start: Carbon, end: Carbon}|null
     */
    public function checkOutWindow(object $shift, Carbon $now): ?array
    {
        foreach ($this->occurrences($shift, $now) as $window) {
            if ($now->getTimestamp() >= $window['start']->getTimestamp()
                && $now->getTimestamp() <= $window['end']->copy()->addMinutes(15)->getTimestamp()) {
                return $window;
            }
        }

        return null;
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $window
     */
    public function lateMinutes(array $window, Carbon $checkInAt): int
    {
        $lateThreshold = $window['start']->copy()->addMinutes(15);

        return $checkInAt->getTimestamp() > $lateThreshold->getTimestamp()
            ? (int) ceil($window['start']->diffInSeconds($checkInAt) / 60)
            : 0;
    }

    /**
     * @return string Y-m-d attendance date corresponding to the shift occurrence.
     */
    public function attendanceDate(object $shift, Carbon $now): string
    {
        $window = $this->checkInWindow($shift, $now) ?? $this->checkOutWindow($shift, $now);

        return $window === null ? $now->toDateString() : $window['start']->toDateString();
    }

    /**
     * @return list<array{start: Carbon, end: Carbon}>
     */
    private function occurrences(object $shift, Carbon $now): array
    {
        $windows = [];

        foreach ([-1, 0, 1] as $dayOffset) {
            $start = Carbon::parse(
                $now->copy()->addDays($dayOffset)->toDateString().' '.$shift->starts_at,
                $now->timezone,
            );
            $end = Carbon::parse(
                $start->toDateString().' '.$shift->ends_at,
                $now->timezone,
            );

            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }

            $windows[] = ['start' => $start, 'end' => $end];
        }

        return $windows;
    }
}
