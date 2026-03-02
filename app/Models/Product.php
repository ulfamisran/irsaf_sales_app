<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'distributor_id',
        'user_id',
        'sku',
        'brand',
        'series',
        'processor',
        'ram',
        'storage',
        'color',
        'specs',
        'laptop_type',
        'purchase_price',
        'selling_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * Get the sale details for the product.
     */
    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    /**
     * Get unit items (serial-numbered laptops) for this product.
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public static function generateSku(array $data): string
    {
        $attempts = 0;
        do {
            $attempts++;
            $sku = self::buildSku($data);
            if (! self::where('sku', $sku)->exists()) {
                return $sku;
            }
        } while ($attempts < 50);

        return self::buildSku($data);
    }

    protected static function buildSku(array $data): string
    {
        $typeCode = ($data['laptop_type'] ?? '') === 'baru' ? 'NW' : 'SC';

        $brand = self::skuBrandSegment($data['brand'] ?? '');
        $series = self::skuSegment($data['series'] ?? '');
        $processor = self::skuSegment($data['processor'] ?? '');
        $ram = self::skuSegment($data['ram'] ?? '');
        $storage = self::skuSegment($data['storage'] ?? '');
        $random = Str::upper(Str::random(3));

        return "LP-{$typeCode}-{$brand}-{$series}-{$processor}-{$ram}-{$storage}-{$random}";
    }

    protected static function skuSegment(?string $value): string
    {
        $value = Str::upper(trim((string) $value));
        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^A-Z0-9]/', '', $value);

        return $value !== '' ? $value : 'NA';
    }

    protected static function skuBrandSegment(?string $value): string
    {
        $value = self::skuSegment($value);
        $value = preg_replace('/[AEIOU]/', '', $value);

        return $value !== '' ? $value : 'NA';
    }
}
