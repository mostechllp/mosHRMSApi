<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $oldStatuses = [
        'Active',
        'Completed',
        'On-hold',
        'In-progress',
    ];

    private array $newStatuses = [
        'Active',
        'Completed',
        'On-hold',
        'In-progress',
        'Proposal Created',
        'Proposal Sent',
        'Proposal Approved',
        'Quotation Created',
        'Quotation Sent',
        'Quotation Approved',
        'Invoice Created',
        'Invoice Sent',
        'Invoice Received',
        'Payment Pending',
        'Payment Received',
        'Project Started',
        'Project In Progress',
        'Project Completed',
    ];

    public function up(): void
    {
        $list = implode(',', array_map(fn ($status) => "'" . addslashes($status) . "'", $this->newStatuses));
        DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM($list) NOT NULL DEFAULT 'Active'");
    }

    public function down(): void
    {
        $list = implode(',', array_map(fn ($status) => "'" . addslashes($status) . "'", $this->oldStatuses));
        DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM($list) NOT NULL DEFAULT 'Active'");
    }
};
