<?php

declare(strict_types=1);

namespace CrewWeaver\Scheduling;

use CrewWeaver\Models\ScheduleRepository;
use DomainException;
use RuntimeException;

class SchedulingService
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected ScheduleRepository $_schedules;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(ScheduleRepository $schedules)
    {
        $this->_schedules = $schedules;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function preview(int $projectId): array
    {
        $graph = $this->_schedules->projectGraph($projectId);
        if ($graph === null || empty($graph['project']['planned_start'])) {
            throw new RuntimeException('Project requires a planned start before scheduling.');
        }
        $configuration=$graph['calendar'];
        $weekdays=$configuration===null?[1,2,3,4,5]:array_values(array_keys(array_filter([
            1=>(bool)$configuration['monday'],2=>(bool)$configuration['tuesday'],3=>(bool)$configuration['wednesday'],4=>(bool)$configuration['thursday'],5=>(bool)$configuration['friday'],6=>(bool)$configuration['saturday'],7=>(bool)$configuration['sunday'],
        ])));
        $calendar = new WorkingCalendar($graph['project']['scheduling_mode'],(string)($configuration['timezone']??'UTC'),$weekdays,$graph['holidays']);
        $result = (new ScheduleEngine($calendar))->calculate($graph['project']['planned_start'], $graph['tasks'], $graph['dependencies']);
        if (!empty($graph['project']['target_completion']) && $result['project_finish'] > $graph['project']['target_completion']) {
            $result['warnings'][] = ['type' => 'project_deadline', 'message' => 'Calculated finish exceeds the project target completion.'];
        }
        return $result;
    }

    public function apply(int $projectId): array
    {
        $result = $this->preview($projectId);
        $this->_schedules->apply($projectId, $result);
        return $result;
    }

    public function validateDependencies(int $projectId): void
    {
        $graph = $this->_schedules->projectGraph($projectId);
        if ($graph === null) { throw new DomainException('Project not found.'); }
        $taskIds = array_map(static fn (array $task): int => (int) $task['id'], $graph['tasks']);
        (new ScheduleEngine(new WorkingCalendar()))->assertAcyclic($taskIds, $graph['dependencies']);
    }
}
