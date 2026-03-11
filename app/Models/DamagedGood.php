<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamagedGood extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_unit_id',
        'serial_number',
        'recorded_date',
        'damage_description',
        'harga_hpp',
        'user_id',
        'reactivated_at',
        'reactivated_by',
    ];

    protected function casts(): array
    {
        return [
            'recorded_date' => 'date',
            'harga_hpp' => 'decimal:2',
            'reactivated_at' => 'datetime',
        ];
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactivatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }

    public function isReactivated(): bool
    {
        return $this->reactivated_at !== null;
    }
}
