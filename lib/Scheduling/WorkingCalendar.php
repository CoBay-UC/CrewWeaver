<?php

declare(strict_types=1);

namespace CrewWeaver\Scheduling;

use DateTimeImmutable;
use DateTimeZone;

class WorkingCalendar
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected string $_mode;
    protected DateTimeZone $_timezone;
    protected array $_workingWeekdays;
    protected array $_holidays;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(string $mode = 'calendar', string $timezone = 'UTC', array $workingWeekdays = [1, 2, 3, 4, 5], array $holidays = [])
    {
        $this->_mode = $mode;
        $this->_timezone = new DateTimeZone($timezone);
        $this->_workingWeekdays = $workingWeekdays;
        $this->_holidays = array_fill_keys($holidays, true);
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _isWorkingDay(DateTimeImmutable $date): bool
    {
        return in_array((int) $date->format('N'), $this->_workingWeekdays, true)
            && !isset($this->_holidays[$date->format('Y-m-d')]);
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' 00:00:00', $this->_timezone);
    }

    public function addDays(DateTimeImmutable $start, int $days): DateTimeImmutable
    {
        if ($this->_mode === 'calendar') {
            return $start->modify(($days >= 0 ? '+' : '') . $days . ' days');
        }
        $current = $start;
        $remaining = abs($days);
        $direction = $days >= 0 ? '+1 day' : '-1 day';
        while ($remaining > 0) {
            $current = $current->modify($direction);
            if ($this->_isWorkingDay($current)) {
                $remaining--;
            }
        }
        return $current;
    }
}
