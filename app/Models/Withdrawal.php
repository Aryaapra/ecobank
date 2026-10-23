<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'withdrawal_code',
        'user_id',
        'amount',
        'payout_bank',
        'payout_account_number',
        'payout_account_name',
        'status',
        'rejection_reason',
        'transfer_reference',
        'reviewed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'payout_account_number' => 'encrypted',
            'payout_account_name' => 'encrypted',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
