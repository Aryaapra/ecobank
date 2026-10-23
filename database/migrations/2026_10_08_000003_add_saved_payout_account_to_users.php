<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'payout_bank')) {
                $table->string('payout_bank', 30)->nullable();
            }
            if (! Schema::hasColumn('users', 'payout_account_number')) {
                $table->text('payout_account_number')->nullable();
            }
            if (! Schema::hasColumn('users', 'payout_account_name')) {
                $table->text('payout_account_name')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Preserve saved payout details when rolling back application code.
    }
};
