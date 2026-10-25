<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionUpdateLog extends Model
{
    public const TYPE_SALE = 'sale';
    public const TYPE_SERVICE = 'service';
    public const TYPE_RENTAL = 'rental';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_CASH_IN = 'cash_in';
    public const TYPE_CASH_OUT = 'cash_out';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'reference_number',
        'user_id',
        'updated_at_action',
        'reason',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'updated_at_action' => 'datetime',
            'changes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
