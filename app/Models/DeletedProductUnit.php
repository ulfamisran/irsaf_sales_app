<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletedProductUnit extends Model
{
    protected $fillable = [
        'product_unit_id',
        'product_id',
        'serial_number',
        'sku',
        'product_label',
        'location_type',
        'location_id',
        'status',
        'reason',
        'deleted_by',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
