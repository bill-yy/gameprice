<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'store_id',
    'code',
    'discount_value',
    'discount_type',
    'valid_from',
    'valid_until',
    'is_active',
])]
class Voucher extends Model
{
    use HasFactory;

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isValid(): bool
    {
        return $this->is_active
            && $this->valid_from->lte(now())
            && $this->valid_until->gte(now());
    }

    public function applyToPrice(float $price): float
    {
        if ($this->discount_type === 'percentage') {
            return max(0, $price - ($price * $this->discount_value / 100));
        }

        return max(0, $price - $this->discount_value);
    }
}
