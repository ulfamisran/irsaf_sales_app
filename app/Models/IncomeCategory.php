<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function cashFlows(): HasMany
    {
        return $this->hasMany(CashFlow::class, 'income_category_id');
    }

    /**
     * Resolve income category by code. Creates it if it doesn't exist.
     */
    public static function resolveByCode(string $code, string $name, ?string $description = null): self
    {
        return static::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $description ?? $name,
                'is_active' => true,
            ]
        );
    }
}
