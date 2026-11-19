<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMutation extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'stock_mutations';

    protected $fillable = [
        'distribution_id',
        'invoice_number',
        'product_id',
        'from_location_type',
        'from_location_id',
        'to_location_type',
        'to_location_id',
        'quantity',
        'biaya_distribusi_per_unit',
        'affects_hpp',
        'hpp_per_unit',
        'distribution_payment_method_id',
        'mutation_date',
        'notes',
        'serial_numbers',
        'distribution_unit_snapshot',
        'user_id',
        'status',
        'cancel_date',
        'cancel_user_id',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'biaya_distribusi_per_unit' => 'decimal:2',
            'affects_hpp' => 'boolean',
            'hpp_per_unit' => 'decimal:2',
            'mutation_date' => 'date',
            'cancel_date' => 'date',
            'distribution_unit_snapshot' => 'array',
        ];
    }

    public function isCancelled(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_CANCELLED;
    }

    public function cancelUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_user_id');
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who created the mutation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class);
    }

    public function distributionPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'distribution_payment_method_id');
    }

    public function purchase(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Purchase::class);
    }

    public function destinationHpp(float $originHpp): float
    {
        $origin = round($originHpp, 2);
        $cost = round((float) $this->biaya_distribusi_per_unit, 2);
        if ($this->affects_hpp === false || $cost <= 0) {
            return $origin;
        }

        return round($origin + $cost, 2);
    }

    /**
     * @return list<array{serial: string, origin: float, destination: float}>
     */
    public function hppChanges(?string $serial = null): array
    {
        $wanted = $serial !== null ? mb_strtoupper(trim($serial)) : null;
        $changes = [];
        foreach (is_array($this->distribution_unit_snapshot) ? $this->distribution_unit_snapshot : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $number = trim((string) ($item['serial_number'] ?? ''));
            if ($number === '' || ($wanted !== null && mb_strtoupper($number) !== $wanted)) {
                continue;
            }
            $origin = round((float) ($item['harga_hpp'] ?? 0), 2);
            $changes[] = [
                'serial' => $number,
                'origin' => $origin,
                'destination' => $this->destinationHpp($origin),
            ];
        }

        if ($changes === [] && $wanted === null && $this->hpp_per_unit !== null) {
            $origin = round((float) $this->hpp_per_unit, 2);
            $changes[] = [
                'serial' => '',
                'origin' => $origin,
                'destination' => $this->destinationHpp($origin),
            ];
        }

        return $changes;
    }

    public function hppChangeLabel(?string $serial = null): string
    {
        $changes = $this->hppChanges($serial);
        if ($changes === []) {
            return '';
        }

        $money = fn (float $amount): string => 'Rp '.number_format($amount, 0, ',', '.');
        $signatures = array_unique(array_map(
            fn (array $change): string => $change['origin'].'|'.$change['destination'],
            $changes
        ));
        $first = $changes[0];
        if (count($signatures) === 1) {
            if (abs($first['origin'] - $first['destination']) < 0.01) {
                return 'HPP tetap '.$money($first['origin']);
            }

            return 'HPP '.$money($first['origin']).' → '.$money($first['destination']);
        }

        $parts = [];
        foreach ($changes as $change) {
            $prefix = $change['serial'] !== '' ? $change['serial'].' ' : '';
            $parts[] = $prefix.$money($change['origin']).' → '.$money($change['destination']);
        }

        return 'HPP '.implode(', ', $parts);
    }
}
