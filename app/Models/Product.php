<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const LOCATION_WAREHOUSE = 'warehouse';
    public const LOCATION_BRANCH = 'branch';

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
        'location_type',
        'location_id',
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
     * Get the location (warehouse or branch).
     */
    public function location(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'location_type', 'location_id');
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

    /**
     * Check if any unit of this product has been sold.
     */
    public function hasSoldUnits(): bool
    {
        return $this->units()->where('status', ProductUnit::STATUS_SOLD)->exists();
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
        $prefix = self::categoryPrefixForSku($data['category_id'] ?? null);
        $typeCode = ($data['laptop_type'] ?? '') === 'baru' ? 'NW' : 'SC';

        $brand = self::skuBrandSegment($data['brand'] ?? '');
        $series = self::skuSegment($data['series'] ?? '');
        $random = Str::upper(Str::random(3));

        $category = isset($data['category_id']) ? Category::find($data['category_id']) : null;
        $isLaptop = $category && strtolower(trim($category->name ?? '')) === 'laptop';

        if ($isLaptop) {
            $processor = self::skuSegment($data['processor'] ?? '');
            $ram = self::skuSegment($data['ram'] ?? '');
            $storage = self::skuSegment($data['storage'] ?? '');

            return "{$prefix}-{$typeCode}-{$brand}-{$series}-{$processor}-{$ram}-{$storage}-{$random}";
        }

        return "{$prefix}-{$typeCode}-{$brand}-{$series}-{$random}";
    }

    /**
     * Get SKU prefix from category code (kolom code pada tabel categories).
     */
    protected static function categoryPrefixForSku(?int $categoryId): string
    {
        $category = $categoryId ? Category::find($categoryId) : null;
        $code = $category?->code ?? '';

        $code = Str::upper(trim((string) $code));
        $code = preg_replace('/[^A-Z0-9]/', '', $code);

        return $code !== '' ? $code : 'NA';
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
