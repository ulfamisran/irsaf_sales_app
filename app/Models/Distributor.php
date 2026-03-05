<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Distributor extends Model
{
    use HasFactory, SoftDeletes;

    public const PLACEMENT_CABANG = 'cabang';
    public const PLACEMENT_GUDANG = 'gudang';
    public const PLACEMENT_SEMUA = 'semua';

    /**
     * Distributor digunakan oleh seluruh cabang dan gudang.
     */
    public function isGlobal(): bool
    {
        return $this->branch_id === null && $this->warehouse_id === null;
    }

    protected $fillable = [
        'placement_type',
        'branch_id',
        'warehouse_id',
        'name',
        'address',
        'phone',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the products for the distributor.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
