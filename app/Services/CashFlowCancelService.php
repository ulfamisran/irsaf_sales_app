<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CashFlowCancelService
{
    public function cancel(CashFlow $cashFlow, int $userId, string $reason): void
    {
        if ($cashFlow->isAttachedToSourceTransaction()) {
            throw new InvalidArgumentException(__('Kas ini melekat pada transaksi lain. Batalkan transaksi induknya.'));
        }
        if (! $cashFlow->isStandaloneCash()) {
            throw new InvalidArgumentException(__('Kas ini tidak dapat dibatalkan dari halaman kas.'));
        }
        if ($cashFlow->cancelled_at) {
            throw new InvalidArgumentException(__('Kas ini sudah dibatalkan.'));
        }

        DB::transaction(function () use ($cashFlow, $userId, $reason) {
            $locked = CashFlow::query()->lockForUpdate()->findOrFail($cashFlow->id);
            if ($locked->cancelled_at || $locked->isAttachedToSourceTransaction() || ! $locked->isStandaloneCash()) {
                throw new InvalidArgumentException(__('Kas ini tidak dapat dibatalkan dari halaman kas.'));
            }

            $reversalType = $locked->type === CashFlow::TYPE_IN ? CashFlow::TYPE_OUT : CashFlow::TYPE_IN;
            $payload = [
                'branch_id' => $locked->branch_id,
                'warehouse_id' => $locked->warehouse_id,
                'type' => $reversalType,
                'amount' => $locked->amount,
                'description' => __('Reversal cancel kas').' #'.$locked->id,
                'reference_type' => CashFlow::REFERENCE_REVERSAL,
                'reference_id' => $locked->id,
                'payment_method_id' => $locked->payment_method_id,
                'transaction_date' => now()->toDateString(),
                'user_id' => $userId,
            ];
            if ($reversalType === CashFlow::TYPE_OUT) {
                $payload['expense_category_id'] = ExpenseCategory::firstOrCreate(
                    ['code' => 'REVERSAL'],
                    ['name' => 'Reversal', 'description' => 'Pengembalian dana pembatalan transaksi', 'is_active' => true]
                )->id;
            } else {
                $payload['income_category_id'] = IncomeCategory::firstOrCreate(
                    ['code' => 'REVERSAL'],
                    ['name' => 'Reversal', 'description' => 'Koreksi pemasukan dari pembatalan transaksi', 'is_active' => true]
                )->id;
            }
            CashFlow::create($payload);

            $locked->update([
                'cancelled_at' => now(),
                'cancel_user_id' => $userId,
                'cancel_reason' => $reason,
            ]);
        });
    }
}
