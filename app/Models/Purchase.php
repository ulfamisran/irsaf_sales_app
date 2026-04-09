<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    public const JENIS_PEMBELIAN_UNIT = 'Pembelian Unit';
    public const JENIS_DISTRIBUSI_UNIT = 'Distribusi Unit';
    public const JENIS_PEMBELIAN_SPAREPART_SERVICE = 'Pembelian Sparepart Service';

    protected $fillable = [
        'invoice_number',
        'jenis_pembelian',
        'distributor_id',
        'location_type',
        'warehouse_id',
        'branch_id',
        'purchase_date',
        'total',
        'total_paid',
        'description',
        'termin',
        'due_date',
        'user_id',
        'status',
        'stock_mutation_id',
        'service_id',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'purchase_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function isDistribusiUnit(): bool
    {
        return $this->jenis_pembelian === self::JENIS_DISTRIBUSI_UNIT;
    }

    public function isSparepartService(): bool
    {
        return $this->jenis_pembelian === self::JENIS_PEMBELIAN_SPAREPART_SERVICE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

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

        return $paid >= $total - 0.02;
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function stockMutation(): BelongsTo
    {
        return $this->belongsTo(StockMutation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class)->orderBy('payment_date')->orderBy('id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
