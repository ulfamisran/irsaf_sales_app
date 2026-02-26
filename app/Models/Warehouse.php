<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    public const LOCATION_TYPE = 'warehouse';

    protected $fillable = [
        'name',
        'address',
        'pic_name',
    ];

    /**
     * Get the stocks for the warehouse.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'location_id')
            ->where('location_type', self::LOCATION_TYPE);
    }
}
