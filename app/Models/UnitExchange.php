<?php

namespace App\Models;

use App\Models\Concerns\RecordsClientDevice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class UnitExchange extends Model
{
    use RecordsClientDevice;

    public const STATUS_OPEN = 'open';

    public const STATUS_RELEASED = 'released';

    public const STATUS_CANCEL = 'cancel';

    protected $fillable = [
        'exchange_number',
        'exchange_date',
        'status',
        'sale_id',
        'user_id',
        'client_ip',
        'client_user_agent',
        'branch_id',
        'warehouse_id',
        'requires_authorization',
    ];

    protected function casts(): array
    {
        return [
            'exchange_date' => 'date',
            'requires_authorization' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(UnitExchangeItem::class);
    }

    public function proofs(): MorphMany
    {
        return $this->morphMany(TransactionProof::class, 'proofable');
    }

    public function cashFlows(): HasMany
    {
        return $this->hasMany(CashFlow::class, 'reference_id')
            ->where('cash_flows.reference_type', CashFlow::REFERENCE_UNIT_EXCHANGE);
    }
}
