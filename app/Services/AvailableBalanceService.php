<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Withdrawal;

class AvailableBalanceService
{
    public function forUser(int $userId): int
    {
        $earned = Transaction::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->sum('total_value');
        $reservedOrPaid = Withdrawal::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->sum('amount');

        return max(0, (int) $earned - (int) $reservedOrPaid);
    }
}
