<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

class TransactionVerification extends Model
{
    public const TYPE_SALE = 'sale';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_SERVICE = 'service';
    public const TYPE_RENTAL = 'rental';
    public const TYPE_CASH_IN = 'cash_in';
    public const TYPE_CASH_OUT = 'cash_out';
    public const TYPE_CASH_OUT_EXTERNAL = 'cash_out_external';

    protected $fillable = [
        'verification_type',
        'document_number',
        'document_transaction_date',
        'document_recorded_at',
        'verified_at',
        'user_id',
        'flag_accepted',
        'flag_rejected',
        'notes',
        'reference_type',
        'reference_id',
        'branch_id',
        'warehouse_id',
        'client_ip',
    ];

    protected function casts(): array
    {
        return [
            'document_transaction_date' => 'date',
            'document_recorded_at' => 'datetime',
            'verified_at' => 'datetime',
            'flag_accepted' => 'boolean',
            'flag_rejected' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detailUrl(): ?string
    {
        return self::urlFor($this->verification_type, (int) $this->reference_id);
    }

    public static function urlFor(?string $type, ?int $id): ?string
    {
        if ($type === null || $id === null || $id < 1) {
            return null;
        }

        $route = match ($type) {
            self::TYPE_SALE => 'sales.show',
            self::TYPE_PURCHASE => 'purchases.show',
            self::TYPE_SERVICE => 'services.show',
            self::TYPE_RENTAL => 'rentals.show',
            self::TYPE_CASH_IN => 'cash-flows.in.show',
            self::TYPE_CASH_OUT => 'cash-flows.out.show',
            self::TYPE_CASH_OUT_EXTERNAL => 'cash-flows.out.show',
            default => null,
        };

        if ($route === null || ! Route::has($route)) {
            return null;
        }

        return route($route, $id);
    }
}
