<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('withdrawals') || ! Schema::hasTable('users')) {
            return;
        }

        Schema::create('withdrawals', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('withdrawal_code', 30)->unique();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('payout_bank', 30);
            $table->text('payout_account_number');
            $table->text('payout_account_name');
            $table->string('status', 20)->default('pending');
            $table->string('rejection_reason', 255)->nullable();
            $table->string('transfer_reference', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status'], 'idx_withdrawals_user_status');
            $table->index(['status', 'created_at'], 'idx_withdrawals_status_created');
        });
    }

    public function down(): void
    {
        // Withdrawal records are financial audit data and are intentionally retained.
    }
};
