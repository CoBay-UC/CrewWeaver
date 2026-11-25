<?php

declare(strict_types=1);

namespace CrewWeaver\Scheduling;

use DateTimeImmutable;
use DomainException;

class ScheduleEngine
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected WorkingCalendar $_calendar;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(WorkingCalendar $calendar)
    {
        $this->_calendar = $calendar;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _duration(array $task): int
    {
        if (!empty($task['milestones'])) {
            return array_sum(array_map(static fn (array $milestone): int => max(0, (int) $milestone['duration_days']), $task['milestones']));
        }
        return max(0, (int) ($task['estimated_duration_days'] ?? 0));
    }

    protected function _topologicalOrder(array $tasks, array $dependencies): array
    {
        $incoming = array_fill_keys(array_keys($tasks), 0);
        $outgoing = array_fill_keys(array_keys($tasks), []);
        foreach ($dependencies as $dependency) {
            $predecessor = (int) $dependency['predecessor_task_id'];
            $successor = (int) $dependency['successor_task_id'];
            if (!isset($tasks[$predecessor], $tasks[$successor]) || $predecessor === $successor) {
                throw new DomainException('Dependency references an invalid task.');
            }
            $incoming[$successor]++;
            $outgoing[$predecessor][] = $successor;
        }
        $queue = array_keys(array_filter($incoming, static fn (int $count): bool => $count === 0));
        sort($queue, SORT_NUMERIC);
        $order = [];
        while ($queue !== []) {
            $taskId = array_shift($queue);
            $order[] = $taskId;
            foreach ($outgoing[$taskId] as $successor) {
                $incoming[$successor]--;
                if ($incoming[$successor] === 0) {
                    $queue[] = $successor;
                    sort($queue, SORT_NUMERIC);
                }
            }
        }
        if (count($order) !== count($tasks)) {
            throw new DomainException('Task dependency cycle detected.');
        }
        return $order;
    }

    protected function _milestones(array $task, DateTimeImmutable $start): array
    {
        $offset = 0;
        $result = [];
        foreach ($task['milestones'] ?? [] as $milestone) {
            $offset += max(0, (int) $milestone['duration_days']);
            $result[] = $milestone + [
                'cumulative_offset_days' => $offset,
                'calculated_date' => $this->_calendar->addDays($start, $offset)->format('Y-m-d'),
            ];
        }
        return $result;
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function calculate(string $projectStart, array $taskList, array $dependencies): array
    {
        $tasks = [];
        foreach ($taskList as $task) {
            $tasks[(int) $task['id']] = $task;
        }
        $order = $this->_topologicalOrder($tasks, $dependencies);
        $incoming = [];
        foreach ($dependencies as $dependency) {
            $incoming[(int) $dependency['successor_task_id']][] = $dependency;
        }
        $projectStartDate = $this->_calendar->date($projectStart);
        $calculated = [];
        $warnings = [];
        $projectFinish = $projectStartDate;
        foreach ($order as $taskId) {
            $task = $tasks[$taskId];
            $start = $projectStartDate;
            foreach ($incoming[$taskId] ?? [] as $dependency) {
                $predecessorFinish = $this->_calendar->date($calculated[(int) $dependency['predecessor_task_id']]['finish']);
                $candidate = $this->_calendar->addDays($predecessorFinish, (int) $dependency['lag_days']);
                if ($candidate > $start) {
                    $start = $candidate;
                }
            }
            $duration = $this->_duration($task);
            $finish = $this->_calendar->addDays($start, $duration);
            if (($task['scheduling_mode'] ?? 'automatic') === 'manual' && !empty($task['dates_locked'])) {
                $lockedStart = !empty($task['planned_start']) ? $this->_calendar->date($task['planned_start']) : $start;
                $lockedFinish = !empty($task['calculated_finish']) ? $this->_calendar->date($task['calculated_finish']) : $finish;
                if ($lockedStart < $start) {
                    $warnings[] = ['task_id' => $taskId, 'type' => 'locked_conflict', 'message' => 'Locked start precedes a dependency finish.'];
                }
                $start = $lockedStart;
                $finish = $lockedFinish;
            }
            if (!empty($task['manual_due_date']) && $finish > $this->_calendar->date($task['manual_due_date'])) {
                $warnings[] = ['task_id' => $taskId, 'type' => 'deadline', 'message' => 'Calculated finish exceeds the task deadline.'];
            }
            $calculated[$taskId] = [
                'task_id' => $taskId,
                'start' => $start->format('Y-m-d'),
                'finish' => $finish->format('Y-m-d'),
                'duration_days' => $duration,
                'milestones' => $this->_milestones($task, $start),
            ];
            if ($finish > $projectFinish) {
                $projectFinish = $finish;
            }
        }
        return ['tasks' => $calculated, 'project_finish' => $projectFinish->format('Y-m-d'), 'warnings' => $warnings];
    }

    public function assertAcyclic(array $taskIds, array $dependencies): void
    {
        $tasks = array_fill_keys($taskIds, []);
        $this->_topologicalOrder($tasks, $dependencies);
    }
}
