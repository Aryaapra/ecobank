<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        if (! Schema::hasColumn('transactions', 'process_stage')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->string('process_stage', 30)->default('requested');
            });
        }

        DB::table('transactions')
            ->where('status', 'pickup_confirmed')
            ->update(['status' => 'pending', 'process_stage' => 'pickup_confirmed']);
        DB::table('transactions')
            ->where('status', 'weighing')
            ->update(['status' => 'pending', 'process_stage' => 'weighing']);
        DB::table('transactions')
            ->where('status', 'approved')
            ->where('process_stage', 'requested')
            ->update(['process_stage' => 'completed']);
        DB::table('transactions')
            ->where('status', 'cancelled')
            ->where('process_stage', 'requested')
            ->update(['process_stage' => 'cancelled']);
    }

    public function down(): void
    {
        // Keep workflow state when rolling back application code.
    }
};
