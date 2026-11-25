<?php

declare(strict_types=1);

namespace CrewWeaver\Tests\Unit;

use CrewWeaver\Scheduling\ScheduleEngine;
use CrewWeaver\Scheduling\WorkingCalendar;
use DomainException;
use PHPUnit\Framework\TestCase;

class ScheduleEngineTest extends TestCase
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function testConcurrentDependencySchedule(): void
    {
        $engine = new ScheduleEngine(new WorkingCalendar());
        $tasks = [
            ['id' => 1, 'estimated_duration_days' => 2],
            ['id' => 2, 'estimated_duration_days' => 5],
            ['id' => 3, 'estimated_duration_days' => 3],
            ['id' => 4, 'estimated_duration_days' => 3],
        ];
        $dependencies = [
            ['predecessor_task_id' => 1, 'successor_task_id' => 2, 'lag_days' => 0],
            ['predecessor_task_id' => 1, 'successor_task_id' => 3, 'lag_days' => 0],
            ['predecessor_task_id' => 2, 'successor_task_id' => 4, 'lag_days' => 0],
            ['predecessor_task_id' => 3, 'successor_task_id' => 4, 'lag_days' => 0],
        ];
        $result = $engine->calculate('2027-01-01', $tasks, $dependencies);
        self::assertSame('2027-01-11', $result['project_finish']);
        self::assertSame('2027-01-03', $result['tasks'][2]['start']);
        self::assertSame('2027-01-03', $result['tasks'][3]['start']);
        self::assertSame('2027-01-08', $result['tasks'][4]['start']);
    }

    public function testMilestonesDetermineDurationAndOffsets(): void
    {
        $engine = new ScheduleEngine(new WorkingCalendar());
        $result = $engine->calculate('2027-01-01', [[
            'id' => 1,
            'estimated_duration_days' => 99,
            'milestones' => [
                ['title' => 'Research', 'duration_days' => 2],
                ['title' => 'Compare', 'duration_days' => 3],
                ['title' => 'Develop', 'duration_days' => 2],
                ['title' => 'Review', 'duration_days' => 1],
            ],
        ]], []);
        self::assertSame(8, $result['tasks'][1]['duration_days']);
        self::assertSame([2, 5, 7, 8], array_column($result['tasks'][1]['milestones'], 'cumulative_offset_days'));
    }

    public function testWorkingDaysSkipWeekendAndHoliday(): void
    {
        $engine = new ScheduleEngine(new WorkingCalendar('working', 'America/Edmonton', [1, 2, 3, 4, 5], ['2027-01-04']));
        $result = $engine->calculate('2027-01-01', [['id' => 1, 'estimated_duration_days' => 2]], []);
        self::assertSame('2027-01-06', $result['project_finish']);
    }

    public function testCycleIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $engine = new ScheduleEngine(new WorkingCalendar());
        $engine->assertAcyclic([1, 2], [
            ['predecessor_task_id' => 1, 'successor_task_id' => 2, 'lag_days' => 0],
            ['predecessor_task_id' => 2, 'successor_task_id' => 1, 'lag_days' => 0],
        ]);
    }

    public function testManualLockProducesConflictWithoutOverwrite(): void
    {
        $engine = new ScheduleEngine(new WorkingCalendar());
        $result = $engine->calculate('2027-01-01', [
            ['id' => 1, 'estimated_duration_days' => 5],
            ['id' => 2, 'estimated_duration_days' => 2, 'scheduling_mode' => 'manual', 'dates_locked' => true, 'planned_start' => '2027-01-03', 'calculated_finish' => '2027-01-05'],
        ], [['predecessor_task_id' => 1, 'successor_task_id' => 2, 'lag_days' => 0]]);
        self::assertSame('2027-01-03', $result['tasks'][2]['start']);
        self::assertSame('locked_conflict', $result['warnings'][0]['type']);
    }

    public function testLongerCriticalBranchShiftsOnlyDownstreamWork(): void
    {
        $engine=new ScheduleEngine(new WorkingCalendar());$tasks=[['id'=>1,'estimated_duration_days'=>2],['id'=>2,'estimated_duration_days'=>7],['id'=>3,'estimated_duration_days'=>3],['id'=>4,'estimated_duration_days'=>3]];$dependencies=[['predecessor_task_id'=>1,'successor_task_id'=>2,'lag_days'=>0],['predecessor_task_id'=>1,'successor_task_id'=>3,'lag_days'=>0],['predecessor_task_id'=>2,'successor_task_id'=>4,'lag_days'=>0],['predecessor_task_id'=>3,'successor_task_id'=>4,'lag_days'=>0]];$result=$engine->calculate('2027-01-01',$tasks,$dependencies);self::assertSame('2027-01-13',$result['project_finish']);self::assertSame('2027-01-06',$result['tasks'][3]['finish']);self::assertSame('2027-01-10',$result['tasks'][4]['start']);
    }
}
