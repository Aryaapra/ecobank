<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || Schema::hasColumn('transactions', 'request_group_code')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('request_group_code', 32)->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'request_group_code')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['request_group_code']);
            $table->dropColumn('request_group_code');
        });
    }
};
