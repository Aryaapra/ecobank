<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('waste_types')) {
            Schema::create('waste_types', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 80);
                $table->string('category', 20);
                $table->unsignedInteger('price');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['category', 'name'], 'uq_waste_category_name');
                $table->index(['is_active', 'category'], 'idx_waste_active_category');
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 100);
                $table->string('email', 254)->unique('uq_users_email');
                $table->string('phone', 20);
                $table->string('district', 30);
                $table->string('password_hash');
                $table->string('role', 20)->default('user');
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 20)->default('user');
            });
        }

        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('transaction_code', 24)->unique('uq_transaction_code');
                $table->string('customer_name', 100);
                $table->unsignedInteger('user_id')->nullable();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->unsignedInteger('waste_type_id');
                $table->decimal('weight_kg', 10, 2);
                $table->unsignedBigInteger('total_value');
                $table->string('status', 20)->default('pending');
                $table->timestamps();
                $table->index(['status', 'created_at'], 'idx_transaction_status_created');
                $table->foreign('waste_type_id', 'fk_transaction_waste_type')
                    ->references('id')->on('waste_types')
                    ->cascadeOnUpdate()->restrictOnDelete();
            });
        } elseif (! Schema::hasColumn('transactions', 'user_id')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->unsignedInteger('user_id')->nullable();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Existing EcoBank tables may contain user data, so rollback is intentionally non-destructive.
    }
};
