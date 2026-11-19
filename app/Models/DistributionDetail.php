<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'distribution_id',
        'product_id',
        'quantity',
        'biaya_distribusi_per_unit',
        'affects_hpp',
        'hpp_per_unit',
        'serial_numbers',
    ];

    protected function casts(): array
    {
        return [
            'biaya_distribusi_per_unit' => 'decimal:2',
            'affects_hpp' => 'boolean',
            'hpp_per_unit' => 'decimal:2',
        ];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
