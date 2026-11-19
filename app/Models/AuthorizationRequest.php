<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuthorizationRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'module',
        'reference_type',
        'reference_id',
        'document_number',
        'status',
        'requested_by',
        'requested_at',
        'decided_by',
        'decided_at',
        'notes',
        'branch_id',
        'warehouse_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'reference_type', 'reference_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function transactionDate(): ?string
    {
        $subject = $this->subject;
        if (! $subject) {
            return null;
        }

        $value = $subject->getAttribute('sale_date')
            ?? $subject->getAttribute('purchase_date')
            ?? $subject->getAttribute('entry_date')
            ?? $subject->getAttribute('pickup_date')
            ?? $subject->getAttribute('distribution_date')
            ?? $subject->getAttribute('exchange_date')
            ?? null;

        return $value ? (string) $value : null;
    }

    public function clientIp(): string
    {
        $subject = $this->subject;
        if (! $subject) {
            return '-';
        }

        $ip = trim((string) ($subject->getAttribute('client_ip') ?? ''));

        return $ip !== '' ? $ip : '-';
    }

    public function locationLabel(): string
    {
        if ($this->warehouse_id) {
            return __('Gudang').': '.($this->warehouse?->name ?? $this->warehouse_id);
        }
        if ($this->branch_id) {
            return __('Cabang').': '.($this->branch?->name ?? $this->branch_id);
        }

        return '-';
    }
}
