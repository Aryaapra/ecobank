<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('transactions', 'delivery_method')) {
                $table->string('delivery_method', 20)->default('self_delivery');
            }
            if (! Schema::hasColumn('transactions', 'pickup_address')) {
                $table->string('pickup_address', 255)->nullable();
            }
            if (! Schema::hasColumn('transactions', 'unit_price')) {
                $table->unsignedInteger('unit_price')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Keep delivery and transaction data when rolling back application code.
    }
};
