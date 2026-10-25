<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnitChangeLog extends Model
{
    protected $fillable = [
        'product_unit_id',
        'user_id',
        'changed_at',
        'serial_snapshot',
        'reason',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'changes' => 'array',
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
}
