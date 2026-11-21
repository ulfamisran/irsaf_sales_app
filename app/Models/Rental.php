<?php

namespace App\Models;

use App\Models\Concerns\RecordsClientDevice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Rental extends Model
{
    use HasFactory;
    use RecordsClientDevice;

    public const STATUS_OPEN = 'open';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CANCEL = 'cancel';

    public const RETURN_BELUM = 'belum_kembali';
    public const RETURN_SUDAH = 'sudah_kembali';

    public const PAYMENT_BELUM_LUNAS = 'belum_lunas';
    public const PAYMENT_LUNAS = 'lunas';

    public const LOCATION_WAREHOUSE = 'warehouse';
    public const LOCATION_BRANCH = 'branch';

    protected $fillable = [
        'invoice_number',
        'branch_id',
        'warehouse_id',
        'location_type',
        'customer_id',
        'user_id',
        'client_ip',
        'client_user_agent',
        'pickup_date',
        'return_date',
        'actual_return_date',
        'return_recorded_at',
        'return_user_id',
        'return_notes',
        'total_days',
        'subtotal',
        'tax_amount',
        'penalty_amount',
        'total',
        'total_paid',
        'payment_status',
        'return_status',
        'status',
        'description',
        'cancel_date',
        'cancel_user_id',
        'cancel_reason',
        'requires_authorization',
        'effects_pending',
    ];

    protected function casts(): array
    {
        return [
            'pickup_date' => 'date',
            'return_date' => 'date',
            'actual_return_date' => 'date',
            'return_recorded_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'cancel_date' => 'date',
            'requires_authorization' => 'boolean',
            'effects_pending' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

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

    public function returnUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class);
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
}
