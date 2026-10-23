<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'transaction_code',
        'request_group_code',
        'customer_name',
        'user_id',
        'waste_type_id',
        'weight_kg',
        'total_value',
        'delivery_method',
        'pickup_address',
        'unit_price',
        'process_stage',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'float',
            'total_value' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
