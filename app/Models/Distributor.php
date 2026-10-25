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

    public const LINGKUP_INTERNAL = 'internal';
    public const LINGKUP_EKSTERNAL = 'eksternal';

    /**
     * Distributor digunakan oleh seluruh cabang dan gudang.
     */
    public function isGlobal(): bool
    {
        return $this->branch_id === null && $this->warehouse_id === null;
    }

    public function isInternal(): bool
    {
        return ($this->lingkup_vendor ?? self::LINGKUP_EKSTERNAL) === self::LINGKUP_INTERNAL;
    }

    public function isEksternal(): bool
    {
        return ! $this->isInternal();
    }

    public function lingkupLabel(): string
    {
        return $this->isInternal() ? __('Internal') : __('Eksternal');
    }

    protected $fillable = [
        'placement_type',
        'branch_id',
        'warehouse_id',
        'name',
        'address',
        'phone',
        'lingkup_vendor',
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
