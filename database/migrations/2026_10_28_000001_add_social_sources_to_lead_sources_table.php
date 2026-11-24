<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lead_sources')) {
            return;
        }

        $sourcesToAdd = [
            'Facebook',
            'Instagram',
            'LinkedIn',
            'WhatsApp',
        ];

        $currentMax = DB::table('lead_sources')->max('sort_order') ?? 0;

        foreach ($sourcesToAdd as $index => $sourceName) {
            $exists = DB::table('lead_sources')->where('name', $sourceName)->exists();
            if (!$exists) {
                DB::table('lead_sources')->insert([
                    'name' => $sourceName,
                    'sort_order' => $currentMax + $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('lead_sources')) {
            return;
        }

        DB::table('lead_sources')
            ->whereIn('name', ['Facebook', 'Instagram', 'LinkedIn', 'WhatsApp'])
            ->delete();
    }
};

