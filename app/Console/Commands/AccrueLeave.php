<?php

namespace App\Console\Commands;

use App\Services\LeaveAccrualService;
use Illuminate\Console\Command;

class AccrueLeave extends Command
{
    protected $signature = 'leave:accrue';

    protected $description = 'Process monthly employee leave accrual';

    public function handle(
        LeaveAccrualService $accrualService
    ) {

        $this->info('Starting leave accrual...');

        $result = $accrualService->processCurrentPeriod();

        $this->info(
            'Period: ' . $result['period']
        );

        $this->info(
            'Credited: ' . $result['processed']
        );

        $this->info(
            'Held: ' . $result['held']
        );

        $this->info(
            'Skipped: ' . $result['skipped']
        );

        $released = $accrualService->releaseHeldAccruals();

        $this->info(
            'Released after probation: ' . $released
        );

        $this->info('Leave accrual completed.');

        return self::SUCCESS;
    }
}