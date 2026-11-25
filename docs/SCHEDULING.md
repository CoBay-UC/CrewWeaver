# Scheduling conventions

## Boundaries and display

The engine uses half-open date boundaries. Project start is day 0. A two-calendar-day stage beginning at `2027-01-01T00:00` finishes at the `2027-01-03T00:00` boundary. Calendar views render the occupied inclusive dates (Jan 1 and Jan 2), while forms and API responses expose the finish boundary and label it clearly.

Milestone offsets are cumulative finish boundaries. Durations must be non-negative integers; zero-day milestones are markers. A task with milestones derives its duration from their sum. Otherwise, its estimated duration is used.

## Dependencies

Dependencies are finish-to-start with integer lag days. A task starts at the latest of the project start and all predecessor finish-plus-lag boundaries. Independent tasks run concurrently. Cycles are rejected before persistence using a topological sort. Project calculated finish is the maximum active task finish, not a sum.

## Working calendars

Calendar-day mode advances every date. Working-day mode advances only configured weekdays and skips stored holidays in the schedule timezone. Date arithmetic normalizes at local midnight and writes date-only calculated values, avoiding DST-hour leakage.

## Manual and actual values

Automatic mode recalculates planned boundaries downstream. Manual locked dates are never overwritten; conflicts are returned as warnings. Actual starts/completions are historical facts and are never rewritten. Target dates are constraints that produce warnings rather than silently compressing work.

