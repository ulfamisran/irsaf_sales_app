<?php

namespace App\Models;

use App\Models\Concerns\RecordsClientDevice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;

class Sale extends Model
{
    use HasFactory;
    use RecordsClientDevice;

    public const STATUS_OPEN = 'open';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CANCEL = 'cancel';

    protected $fillable = [
        'invoice_number',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'user_id',
        'client_ip',
        'client_user_agent',
        'total',
        'total_paid',
        'discount_amount',
        'tax_amount',
        'description',
        'sale_date',
        'status',
        'released_at',
        'cancel_date',
        'cancel_user_id',
        'cancel_reason',
        'requires_authorization',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'sale_date' => 'date',
            'released_at' => 'datetime',
            'cancel_date' => 'date',
            'requires_authorization' => 'boolean',
        ];
    }

    /**
     * Total penjualan untuk laba rugi: pembayaran tunai + tukar tambah + selisih tukar unit yang sudah release.
     */
    public function getPaymentTotalForReportAttribute(): float
    {
        $cash = (float) ($this->relationLoaded('payments')
            ? $this->payments->sum('amount')
            : $this->payments()->sum('amount'));

        $combined = round($cash + $this->trade_in_total, 2);
        if ($combined > 0) {
            return round($combined + $this->releasedUnitExchangeDifference(), 2);
        }

        return (float) $this->total_paid ?: (float) $this->total;
    }

    /**
     * Selisih harga tukar unit yang sudah release. Nilai negatif mengurangi omzet penjualan.
     */
    public function releasedUnitExchangeDifference(): float
    {
        static $ready = null;
        if ($ready === null) {
            $ready = Schema::hasTable('unit_exchanges') && Schema::hasTable('unit_exchange_items');
        }
        if (! $ready) {
            return 0.0;
        }

        $exchanges = $this->relationLoaded('unitExchanges')
            ? $this->unitExchanges
            : $this->unitExchanges()->where('status', UnitExchange::STATUS_RELEASED)->with('items')->get();

        return round((float) $exchanges
            ->filter(fn (UnitExchange $exchange) => $exchange->status === UnitExchange::STATUS_RELEASED)
            ->sum(fn (UnitExchange $exchange) => (float) $exchange->items->sum('price_difference')), 2);
    }

    /**
     * Apakah penjualan sudah lunas (total pembayaran >= total).
     */
    public function isPaidOff(): bool
    {
        $total = (float) $this->total;
        if ($total <= 0) {
            return true;
        }
        $paid = (float) $this->total_paid;
        if ($paid <= 0 && $this->relationLoaded('payments')) {
            $paid = (float) $this->payments->sum('amount');
        }

        return $paid >= $total - 0.02; // toleransi floating point
    }

    /**
     * Get the branch that owns the sale.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isWarehouseSale(): bool
    {
        return $this->warehouse_id !== null;
    }

    public function stockLocationType(): string
    {
        return $this->isWarehouseSale() ? Stock::LOCATION_WAREHOUSE : Stock::LOCATION_BRANCH;
    }

    public function stockLocationId(): int
    {
        return $this->isWarehouseSale() ? (int) $this->warehouse_id : (int) $this->branch_id;
    }

    /**
     * Get the user that created the sale.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proofs(): MorphMany
    {
        return $this->morphMany(TransactionProof::class, 'proofable');
    }

    public function cancelUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the sale details.
     */
    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function unitExchanges(): HasMany
    {
        return $this->hasMany(UnitExchange::class);
    }

    /**
     * Relasi yang dipakai cetakan invoice penjualan, termasuk tukar unit yang sudah release.
     *
     * @return array<int|string, mixed>
     */
    public static function invoiceEagerLoads(): array
    {
        return [
            'branch',
            'warehouse',
            'user',
            'customer',
            'saleDetails.product',
            'payments.paymentMethod',
            'tradeIns.category',
            'unitExchanges' => function ($query) {
                $query->where('status', UnitExchange::STATUS_RELEASED)
                    ->with(['items.product', 'items.paymentMethod', 'cashFlows.paymentMethod']);
            },
        ];
    }

    /**
     * Get the trade-ins (laptop yang ditukar) for this sale.
     */
    public function tradeIns(): HasMany
    {
        return $this->hasMany(SaleTradeIn::class, 'sale_id');
    }

    /**
     * Total nilai tukar tambah (untuk perhitungan pembayaran).
     */
    public function getTradeInTotalAttribute(): float
    {
        if (! $this->relationLoaded('tradeIns')) {
            $this->load('tradeIns');
        }

        return (float) $this->tradeIns->sum('trade_in_value');
    }

    /**
     * Total HPP (Harga Pokok Penjualan) dari detail untuk perhitungan laba rugi.
     */
    public function getTotalHppAttribute(): float
    {
        if (! $this->relationLoaded('saleDetails')) {
            $this->load('saleDetails');
        }

        return (float) $this->saleDetails->sum(fn ($d) => (float) $d->quantity * (float) ($d->hpp ?? 0));
    }

    /**
     * Laba kotor = Total penjualan - Total HPP.
     */
    public function getGrossProfitAttribute(): float
    {
        return (float) $this->total - $this->total_hpp;
    }
}
