<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'game_id',
    'email',
    'target_price',
    'is_active',
    'notified_at',
])]
class PriceAlert extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'target_price' => 'decimal:2',
            'notified_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function shouldNotify(float $price): bool
    {
        return $this->is_active
            && $this->notified_at === null
            && $price <= (float) $this->target_price;
    }
}
