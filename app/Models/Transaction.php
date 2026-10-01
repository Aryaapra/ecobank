<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'transaction_code',
        'customer_name',
        'user_id',
        'waste_type_id',
        'weight_kg',
        'total_value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'float',
            'total_value' => 'integer',
        ];
    }

    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }
}
