<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveAccrual;
use App\Models\LeavePolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveAccrualService
{
    /**
     * Process all missing accrual periods up to the current month.
     *
     * Example:
     *
     * Probation start: 15 May 2026
     * Monthly accrual: 1.5 days
     * Current month: September 2026
     *
     * The system processes:
     *
     * May       = 1.5
     * June      = 1.5
     * July      = 1.5
     * August    = 1.5
     * September = 1.5
     */
    public function processCurrentPeriod(): array
    {
        $currentDate = Carbon::now();

        return $this->processUpToDate($currentDate);
    }

    /**
     * Process all required accrual periods up to the given date.
     */
    public function processUpToDate(Carbon $currentDate): array
    {
        $processed = 0;
        $held = 0;
        $skipped = 0;

        $policies = LeavePolicy::query()
            ->where('status', true)
            ->where('enable_accrual', true)
            ->with('leaveType')
            ->get();

        foreach ($policies as $policy) {

            $employees = Employee::query()
                ->whereHas('user', function ($query) {
                    $query->where('status', 'active')
                        ->whereNotIn('type', ['admin']);
                })
                ->get();

            foreach ($employees as $employee) {

                $startDate = $this->getAccrualStartDate(
                    $employee,
                    $policy,
                    $currentDate
                );

                if (!$startDate) {
                    $skipped++;
                    continue;
                }

                $period = $startDate->copy()->startOfMonth();

                $lastPeriod = $currentDate
                    ->copy()
                    ->startOfMonth();

                while ($period->lte($lastPeriod)) {

                    $result = $this->processEmployeeAccrual(
                        $employee,
                        $policy,
                        $period
                    );

                    if ($result === 'credited') {
                        $processed++;
                    } elseif ($result === 'held') {
                        $held++;
                    } else {
                        $skipped++;
                    }

                    $period->addMonth();
                }
            }
        }

        return [
            'period' => $currentDate->format('Y-m'),
            'processed' => $processed,
            'held' => $held,
            'skipped' => $skipped,
        ];
    }

    /**
     * Determine the first month from which accrual should start.
     *
     * If probation starts on 15 May,
     * May itself is included.
     */
    protected function getAccrualStartDate(
        Employee $employee,
        LeavePolicy $policy,
        Carbon $currentDate
    ): ?Carbon {

        /*
         * If probation start date exists,
         * start accrual from the probation start month.
         */
        if (!empty($employee->probation_start_date)) {

            $probationStart = Carbon::parse(
                $employee->probation_start_date
            );

            /*
             * Probation starts in future.
             */
            if ($probationStart->gt($currentDate)) {
                return null;
            }

            /*
             * IMPORTANT:
             *
             * 15 May becomes 1 May.
             *
             * Therefore May receives the full accrual.
             */
            return $probationStart
                ->copy()
                ->startOfMonth();
        }

        /*
         * If probation start date does not exist,
         * use joining date.
         */
        if (!empty($employee->joining_date)) {

            $joiningDate = Carbon::parse(
                $employee->joining_date
            );

            if ($joiningDate->gt($currentDate)) {
                return null;
            }

            return $joiningDate
                ->copy()
                ->startOfMonth();
        }

        /*
         * Fallback:
         * Start from current month.
         */
        return $currentDate
            ->copy()
            ->startOfMonth();
    }

    /**
     * Process one employee's accrual for one period.
     */
    public function processEmployeeAccrual(
        Employee $employee,
        LeavePolicy $policy,
        Carbon $periodDate
    ): string {

        $period = $periodDate->format('Y-m');

        /*
         * Prevent duplicate accrual for the same
         * employee + leave type + month.
         */
        $existing = LeaveAccrual::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $policy->leave_type_id)
            ->where('period', $period)
            ->first();

        if ($existing) {
            return 'skipped';
        }

        /*
         * Check whether accrual is due for this period.
         */
        if (!$this->isAccrualDue($policy, $periodDate)) {
            return 'skipped';
        }

        /*
         * Calculate how many days have already been
         * credited/released during this year.
         */
        $alreadyAccrued = $this->getYearAccrued(
            $employee->id,
            $policy->leave_type_id,
            $periodDate->year
        );

        /*
         * Annual allocation limit.
         */
        $remaining = max(
            0,
            (float) $policy->annual_allocation - $alreadyAccrued
        );

        if ($remaining <= 0) {
            return 'skipped';
        }

        /*
         * Accrual amount configured by admin.
         */
        $accrualDays = min(
            (float) $policy->accrual_days,
            $remaining
        );

        if ($accrualDays <= 0) {
            return 'skipped';
        }

        /*
         * Check probation.
         *
         * May 2026 period will correctly be considered
         * probation if probation starts on 15 May 2026.
         */
        $probationStatus = $this->getProbationStatus(
            $employee,
            $periodDate
        );

        if ($probationStatus === 'probation') {

            /*
             * If leave should not be accrued during probation,
             * hold it for later release.
             */
            if (!$policy->apply_during_probation) {

                return $this->createHeldAccrual(
                    $employee,
                    $policy,
                    $periodDate,
                    $accrualDays
                );
            }

            /*
             * If admin configured probation action as hold.
             */
            if ($policy->probation_action === 'hold') {

                return $this->createHeldAccrual(
                    $employee,
                    $policy,
                    $periodDate,
                    $accrualDays
                );
            }

            /*
             * If probation_action is:
             *
             * accrue
             * OR
             * accrue_and_restrict_usage
             *
             * then credit the accrual.
             */
        }

        return $this->creditAccrual(
            $employee,
            $policy,
            $periodDate,
            $accrualDays
        );
    }

    /**
     * Credit accrual directly to employee allocation.
     */
    protected function creditAccrual(
        Employee $employee,
        LeavePolicy $policy,
        Carbon $periodDate,
        float $accrualDays
    ): string {

        return DB::transaction(function () use (
            $employee,
            $policy,
            $periodDate,
            $accrualDays
        ) {

            $period = $periodDate->format('Y-m');

            /*
             * Double-check duplicate inside transaction.
             */
            $existing = LeaveAccrual::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $policy->leave_type_id)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return 'skipped';
            }

            /*
             * Create accrual record.
             */
            LeaveAccrual::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $policy->leave_type_id,
                'leave_policy_id' => $policy->id,
                'period' => $period,
                'accrual_date' => $periodDate->toDateString(),
                'accrual_days' => $accrualDays,
                'status' => 'credited',
                'remarks' => 'Leave accrual credited',
            ]);

            /*
             * Find or create yearly allocation.
             */
            $allocation = LeaveAllocation::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $policy->leave_type_id,
                    'year' => $periodDate->year,
                ],
                [
                    'allocated_days' => 0,
                ]
            );

            /*
             * Add accrued days to allocation.
             */
            $allocation->increment(
                'allocated_days',
                $accrualDays
            );

            return 'credited';
        });
    }

    /**
     * Create a held accrual during probation.
     *
     * Held days are NOT added to LeaveAllocation yet.
     */
    protected function createHeldAccrual(
        Employee $employee,
        LeavePolicy $policy,
        Carbon $periodDate,
        float $accrualDays
    ): string {

        $period = $periodDate->format('Y-m');

        /*
         * Prevent duplicate held record.
         */
        $existing = LeaveAccrual::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $policy->leave_type_id)
            ->where('period', $period)
            ->first();

        if ($existing) {
            return 'skipped';
        }

        LeaveAccrual::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $policy->leave_type_id,
            'leave_policy_id' => $policy->id,
            'period' => $period,
            'accrual_date' => $periodDate->toDateString(),
            'accrual_days' => $accrualDays,
            'status' => 'held',
            'remarks' => 'Accrual held during probation',
        ]);

        return 'held';
    }

    /**
     * Check whether accrual is due for the given period.
     */
    protected function isAccrualDue(
        LeavePolicy $policy,
        Carbon $periodDate
    ): bool {

        switch ($policy->accrual_type) {

            case 'monthly':
                return true;

            case 'quarterly':
                return in_array(
                    $periodDate->month,
                    [1, 4, 7, 10]
                );

            case 'half_yearly':
                return in_array(
                    $periodDate->month,
                    [1, 7]
                );

            case 'yearly':
                return $periodDate->month === 1;

            case 'daily':
                /*
                 * IMPORTANT:
                 *
                 * This service processes monthly periods.
                 * Therefore daily accrual cannot correctly
                 * be handled here using one record per month.
                 *
                 * For now, treat daily as monthly to avoid
                 * incorrectly creating duplicate daily records.
                 *
                 * If you really need daily accrual,
                 * it should be implemented separately.
                 */
                return true;

            default:
                return false;
        }
    }

    /**
     * Calculate how many days have already been
     * credited/released in the given year.
     *
     * Held accruals are not counted because they have
     * not yet been released to allocation.
     */
    protected function getYearAccrued(
        int $employeeId,
        int $leaveTypeId,
        int $year
    ): float {

        return (float) LeaveAccrual::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereYear('accrual_date', $year)
            ->whereIn('status', [
                'credited',
                'released',
            ])
            ->sum('accrual_days');
    }

    /**
     * Determine whether employee is in probation
     * for the given accrual month.
     *
     * IMPORTANT:
     *
     * If probation starts on 15 May,
     * the entire May accrual period is considered
     * a probation period.
     */
    protected function getProbationStatus(
        Employee $employee,
        Carbon $periodDate
    ): string {

        if (
            empty($employee->probation_start_date) ||
            empty($employee->probation_end_date)
        ) {
            return 'not_in_probation';
        }

        $probationStart = Carbon::parse(
            $employee->probation_start_date
        )->startOfDay();

        $probationEnd = Carbon::parse(
            $employee->probation_end_date
        )->endOfDay();

        /*
         * Current accrual month.
         *
         * Example:
         *
         * periodDate = 2026-05-01
         *
         * Month starts = 2026-05-01
         * Month ends   = 2026-05-31
         */
        $periodStart = $periodDate
            ->copy()
            ->startOfMonth();

        $periodEnd = $periodDate
            ->copy()
            ->endOfMonth();

        /*
         * If probation overlaps any part of the month,
         * consider that month a probation month.
         *
         * Therefore:
         *
         * probation starts 15 May
         * May = probation
         */
        if (
            $periodStart->lte($probationEnd) &&
            $periodEnd->gte($probationStart)
        ) {
            return 'probation';
        }

        return 'not_in_probation';
    }

    /**
     * Release all held accruals after probation.
     *
     * This must be called by your scheduler/command.
     */
    public function releaseHeldAccruals(): int
    {
        $releasedCount = 0;

        $employees = Employee::query()
            ->whereHas('user', function ($query) {
                $query->where('status', 'active')
                    ->whereNotIn('type', ['admin']);
            })
            ->get();

        foreach ($employees as $employee) {

            if (empty($employee->probation_end_date)) {
                continue;
            }

            $probationEnd = Carbon::parse(
                $employee->probation_end_date
            )->endOfDay();

            /*
             * Probation has not ended yet.
             */
            if (now()->lt($probationEnd)) {
                continue;
            }

            /*
             * Get all held accruals.
             */
            $heldAccruals = LeaveAccrual::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'held')
                ->with('leavePolicy')
                ->get();

            foreach ($heldAccruals as $accrual) {

                $policy = $accrual->leavePolicy;

                if (!$policy) {
                    continue;
                }

                /*
                 * Only release if admin enabled
                 * release after probation.
                 */
                if (!$policy->release_after_probation) {
                    continue;
                }

                DB::transaction(function () use (
                    $accrual,
                    $employee
                ) {

                    $allocationYear = Carbon::parse(
                        $accrual->accrual_date
                    )->year;

                    /*
                     * Find/create allocation for the year
                     * in which the accrual occurred.
                     */
                    $allocation = LeaveAllocation::firstOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'leave_type_id' => $accrual->leave_type_id,
                            'year' => $allocationYear,
                        ],
                        [
                            'allocated_days' => 0,
                        ]
                    );

                    /*
                     * Add held days to allocation.
                     */
                    $allocation->increment(
                        'allocated_days',
                        $accrual->accrual_days
                    );

                    /*
                     * Mark accrual as released.
                     */
                    $accrual->update([
                        'status' => 'released',
                        'remarks' => 'Leave released after probation',
                    ]);
                });

                $releasedCount++;
            }
        }

        return $releasedCount;
    }
}