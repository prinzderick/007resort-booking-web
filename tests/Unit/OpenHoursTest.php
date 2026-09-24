<?php

namespace Tests\Unit;

use App\Support\OpenHours;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class OpenHoursTest extends TestCase
{
    private function hours(array $over = []): array
    {
        $week = [];
        foreach (['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'] as $d) {
            $week[] = ['day' => $d, 'open' => '07:00', 'close' => '23:00', 'closed' => $d === 'SUN'];
        }

        return $over + ['weekly' => $week, 'holidays' => []];
    }

    public function test_open_now_uses_the_lagos_wall_clock_not_utc(): void
    {
        // 2026-09-24 is a Thursday. 21:30 UTC is 22:30 in Lagos (UTC+1): still open.
        $s = OpenHours::status($this->hours(), CarbonImmutable::parse('2026-09-24 21:30:00', 'UTC')->setTimezone('Africa/Lagos'));
        $this->assertTrue($s['open']);
        $this->assertSame('Open now, until 11pm', $s['label']);
        // 22:30 UTC is 23:30 Lagos: closed, opens tomorrow 7am
        $s = OpenHours::status($this->hours(), CarbonImmutable::parse('2026-09-24 22:30:00', 'UTC')->setTimezone('Africa/Lagos'));
        $this->assertFalse($s['open']);
        $this->assertSame('Closed, opens tomorrow 7am', $s['label']);
    }

    public function test_before_opening_and_closed_days_and_holidays(): void
    {
        $lagos = fn (string $t) => CarbonImmutable::parse($t, 'Africa/Lagos');
        $this->assertSame('Closed, opens 7am', OpenHours::status($this->hours(), $lagos('2026-09-24 05:00'))['label']);
        // Saturday night, Sunday closed: next opening is Monday
        $this->assertSame('Closed, opens Monday 7am', OpenHours::status($this->hours(), $lagos('2026-09-26 23:30'))['label']);
        // holiday override: Thursday closed
        $h = $this->hours(['holidays' => [['date' => '2026-09-24', 'label' => 'Holiday', 'closed' => true]]]);
        $this->assertFalse(OpenHours::status($h, $lagos('2026-09-24 12:00'))['open']);
        // holiday with special hours
        $h = $this->hours(['holidays' => [['date' => '2026-09-24', 'open' => '10:00', 'close' => '18:00', 'closed' => false]]]);
        $this->assertTrue(OpenHours::status($h, $lagos('2026-09-24 12:00'))['open']);
        $this->assertFalse(OpenHours::status($h, $lagos('2026-09-24 19:00'))['open']);
    }

    public function test_unknown_hours_and_formatting(): void
    {
        $this->assertFalse(OpenHours::status([], CarbonImmutable::now())['known']);
        $this->assertSame('midnight', OpenHours::fmt('23:59'));
        $this->assertSame('7:30am', OpenHours::fmt('07:30'));
        $this->assertSame('12pm', OpenHours::fmt('12:00'));
    }
}
