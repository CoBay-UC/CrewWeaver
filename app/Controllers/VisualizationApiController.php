<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\ImportantDateRepository;
use CrewWeaver\Models\VisualizationRepository;

class VisualizationApiController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected VisualizationRepository $_visualization;
    protected ImportantDateRepository $_dates;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(VisualizationRepository $visualization, ImportantDateRepository $dates)
    {
        $this->_visualization=$visualization;$this->_dates=$dates;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _dateFilter(string $scope, int $id): array
    {
        return [$scope==='sub-team'?'sub_team_id':$scope.'_id'=>$id];
    }

    protected function _isCalendarDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1
            && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function board(Request $request): Response
    {
        $scope=$request->parameter('scope');$id=(int)$request->parameter('id');$filters=['search'=>$request->query('search'),'project_id'=>$request->query('project_id'),'member_id'=>$request->query('member_id'),'owner_user_id'=>$request->query('owner_user_id'),'priority'=>$request->query('priority'),'status'=>$request->query('status'),'overdue'=>$request->query('overdue')];
        return Response::success('Board retrieved.',['tasks'=>$this->_visualization->board($scope,$id,$filters),'dates'=>$this->_dates->list($this->_dateFilter($scope,$id))]);
    }

    public function gantt(Request $request): Response
    {
        return Response::success('Gantt data retrieved.',$this->_visualization->gantt($request->parameter('scope'),(int)$request->parameter('id')));
    }

    public function calendar(Request $request): Response
    {
        $scope = $request->parameter('scope');
        $id = (int) $request->parameter('id');
        if ($id < 1 || !in_array($scope, ['team', 'sub-team', 'project'], true)) {
            return Response::error('Scope not found.', 404);
        }
        $from = $until = null;
        $dateFilters = ['calendar_scope' => $scope, 'calendar_id' => $id];
        if ($request->query('all', '') !== '1') {
            $from = $request->query('start', date('Y-m-01'));
            $until = $request->query('end', (new \DateTimeImmutable('first day of next month'))->format('Y-m-d'));
            if (!$this->_isCalendarDate($from) || !$this->_isCalendarDate($until)) {
                return Response::error('Expected calendar dates in YYYY-MM-DD format.', 422);
            }
            if ($from >= $until) {
                return Response::error('The calendar end must be after its start.', 422);
            }
            $dateFilters += ['overlap_from' => $from, 'overlap_until' => $until];
        }
        $events = $this->_visualization->calendar($scope, $id, $from, $until);
        $dates = $this->_dates->list($dateFilters);
        foreach ($dates as $date) {
            $allDay = (bool) $date['is_all_day'] || empty($date['event_time']);
            $events[] = [
                'id' => 'date-' . $date['id'],
                'title' => $date['title'],
                'start' => $date['event_date'] . ($allDay ? '' : 'T' . $date['event_time']),
                'end' => $date['end_date'] ? (new \DateTimeImmutable($date['end_date']))->modify('+1 day')->format('Y-m-d') : null,
                'allDay' => $allDay,
                'color' => $date['colour'],
                'url' => '/important-dates?date=' . (int) $date['id'],
                'extendedProps' => ['type' => 'important_date', 'dateId' => $date['id']],
            ];
        }
        return Response::success('Calendar data retrieved.', $events);
    }
}
