<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || DB::getDriverName() !== 'mysql') {
            return;
        }

        $constraintExists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'transactions')
            ->where('CONSTRAINT_NAME', 'chk_transaction_weight')
            ->exists();

        if ($constraintExists) {
            DB::statement('ALTER TABLE `transactions` DROP CONSTRAINT `chk_transaction_weight`');
        }

        DB::statement(
            'ALTER TABLE `transactions` ADD CONSTRAINT `chk_transaction_weight` CHECK (`weight_kg` >= 0)',
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions') || DB::getDriverName() !== 'mysql') {
            return;
        }

        if (DB::table('transactions')->where('weight_kg', '<=', 0)->exists()) {
            throw new RuntimeException('Cannot restore the positive-weight constraint while unweighed transactions exist.');
        }

        DB::statement('ALTER TABLE `transactions` DROP CONSTRAINT `chk_transaction_weight`');
        DB::statement(
            'ALTER TABLE `transactions` ADD CONSTRAINT `chk_transaction_weight` CHECK (`weight_kg` > 0)',
        );
    }
};
