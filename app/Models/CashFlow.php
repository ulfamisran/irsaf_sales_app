<?php

namespace App\Models;

use App\Models\Concerns\RecordsClientDevice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Warehouse;

/**
 * @property-read \App\Models\PaymentMethod|null $paymentMethod
 * @property-read string $kas_pembayaran_label
 */
class CashFlow extends Model
{
    use HasFactory;
    use RecordsClientDevice;

    public const TYPE_IN = 'IN';
    public const TYPE_OUT = 'OUT';

    public const REFERENCE_SALE = 'sale';
    public const REFERENCE_SERVICE = 'service';
    public const REFERENCE_RENTAL = 'rental';
    public const REFERENCE_EXPENSE = 'expense';
    public const REFERENCE_TRADE_IN = 'trade_in';
    public const REFERENCE_PURCHASE = 'purchase';
    public const REFERENCE_PURCHASE_RETURN = 'purchase_return';
    public const REFERENCE_DISTRIBUTION = 'distribution';
    public const REFERENCE_OTHER = 'lainnya';
    public const REFERENCE_SETOR_TUNAI = 'setor_tunai';
    public const REFERENCE_MUTASI_DANA = 'mutasi_dana';
    public const REFERENCE_PENYESUAIAN_SALDO = 'penyesuaian_saldo';
    public const REFERENCE_REVERSAL = 'reversal';

    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'type',
        'amount',
        'description',
        'reference_type',
        'reference_id',
        'expense_category_id',
        'income_category_id',
        'payment_method_id',
        'transaction_date',
        'user_id',
        'client_ip',
        'client_user_agent',
        'cancelled_at',
        'cancel_user_id',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Get the user who recorded the transaction.
     */
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

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function incomeCategory(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function isAttachedToSourceTransaction(): bool
    {
        if ($this->type === self::TYPE_OUT && in_array($this->reference_type, [self::REFERENCE_PURCHASE, self::REFERENCE_PURCHASE_RETURN], true)) {
            return true;
        }

        return $this->type === self::TYPE_IN && in_array($this->reference_type, [
            self::REFERENCE_SALE,
            self::REFERENCE_SERVICE,
            self::REFERENCE_RENTAL,
            self::REFERENCE_TRADE_IN,
        ], true);
    }

    public function isStandaloneCash(): bool
    {
        return $this->reference_type === null || $this->reference_type === '' || $this->reference_type === self::REFERENCE_OTHER;
    }

    /**
     * Akhiran setelah " - " pada deskripsi kas masuk penjualan (isi metode bayar saat data dibuat).
     */
    public static function parsePaymentMethodSuffixFromSaleDescription(?string $description): ?string
    {
        if ($description === null || trim($description) === '') {
            return null;
        }
        if (preg_match('/ - (.+)$/', trim($description), $m)) {
            $tail = trim((string) $m[1]);

            return $tail !== '' ? $tail : null;
        }

        return null;
    }

    /**
     * Label kas/metode untuk kolom "Kas Pembayaran" (termasuk arsip penjualan tanpa sale_payments / payment_method_id).
     */
    public function getKasPembayaranLabelAttribute(): string
    {
        if ($this->payment_method_id) {
            return $this->paymentMethod?->display_label ?? '-';
        }
        if ($this->relationLoaded('paymentMethod') && $this->paymentMethod) {
            return $this->paymentMethod->display_label;
        }
        if ($this->reference_type === self::REFERENCE_SALE && $this->type === self::TYPE_IN) {
            $parsed = self::parsePaymentMethodSuffixFromSaleDescription($this->description);

            return $parsed ?? '-';
        }

        return $this->paymentMethod?->display_label ?? '-';
    }
}
