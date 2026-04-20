<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distribution extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'invoice_number',
        'from_location_type',
        'from_location_id',
        'to_location_type',
        'to_location_id',
        'total',
        'total_paid',
        'notes',
        'user_id',
        'distribution_date',
        'status',
        'cancel_date',
        'cancel_user_id',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'distribution_date' => 'date',
            'cancel_date' => 'date',
        ];
    }

    public function isCancelled(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_CANCELLED;
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_user_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(DistributionDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DistributionPayment::class);
    }

    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }

    public function fromLocationName(): string
    {
        if ($this->from_location_type === Stock::LOCATION_WAREHOUSE) {
            return Warehouse::find($this->from_location_id)?->name ?? '#' . $this->from_location_id;
        }

        return Branch::find($this->from_location_id)?->name ?? '#' . $this->from_location_id;
    }

    public function toLocationName(): string
    {
        if ($this->to_location_type === Stock::LOCATION_WAREHOUSE) {
            return Warehouse::find($this->to_location_id)?->name ?? '#' . $this->to_location_id;
        }

        return Branch::find($this->to_location_id)?->name ?? '#' . $this->to_location_id;
    }
}
