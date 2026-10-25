<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\ServicePayment;
use App\Models\TransactionUpdateLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminTransactionUpdateService
{
    public function __construct(
        protected TransactionUpdateLogService $logService
    ) {}

    /**
     * @param  array{sale_date?: string, description?: ?string, payments?: array<int, array{id: int, amount: float|string, payment_method_id: int}>}  $data
     */
    public function updateSale(Sale $sale, array $data, int $userId, string $reason): void
    {
        DB::transaction(function () use ($sale, $data, $userId, $reason) {
            $sale->load('payments');
            $changes = [];

            $headerOld = [
                'sale_date' => optional($sale->sale_date)->format('Y-m-d'),
                'description' => $sale->description,
            ];
            $headerNew = [
                'sale_date' => $data['sale_date'] ?? $headerOld['sale_date'],
                'description' => array_key_exists('description', $data) ? $data['description'] : $sale->description,
            ];
            $headerDiff = $this->logService->diff($headerOld, $headerNew);
            if ($headerDiff !== []) {
                $sale->update([
                    'sale_date' => $headerNew['sale_date'],
                    'description' => $headerNew['description'],
                ]);
                $changes['header'] = $headerDiff;
            }

            if (! empty($data['payments'])) {
                $payChanges = $this->syncPayments(
                    payments: $sale->payments,
                    incoming: $data['payments'],
                    referenceType: CashFlow::REFERENCE_SALE,
                    referenceId: (int) $sale->id,
                    cashFlowType: CashFlow::TYPE_IN,
                    branchId: $sale->branch_id ? (int) $sale->branch_id : null,
                    warehouseId: $sale->warehouse_id ? (int) $sale->warehouse_id : null,
                    invoiceNumber: (string) $sale->invoice_number,
                    transactionDateFallback: optional($sale->sale_date)->format('Y-m-d') ?: now()->toDateString(),
                );
                if ($payChanges !== []) {
                    $changes['payments'] = $payChanges;
                }
                $sale->update([
                    'total_paid' => round((float) $sale->payments()->sum('amount'), 2),
                ]);
            }

            if ($changes === []) {
                throw new InvalidArgumentException(__('Tidak ada perubahan data.'));
            }

            $this->logService->log(
                TransactionUpdateLog::TYPE_SALE,
                (int) $sale->id,
                $sale->invoice_number,
                $userId,
                $reason,
                $changes
            );
        });
    }

    /**
     * @param  array{entry_date?: string, exit_date?: ?string, description?: ?string, damage_description?: ?string, laptop_detail?: ?string, service_price?: float|string, payments?: array}  $data
     */
    public function updateService(Service $service, array $data, int $userId, string $reason): void
    {
        DB::transaction(function () use ($service, $data, $userId, $reason) {
            $service->load('payments');
            $changes = [];

            $headerOld = [
                'entry_date' => optional($service->entry_date)->format('Y-m-d'),
                'exit_date' => optional($service->exit_date)->format('Y-m-d'),
                'description' => $service->description,
                'damage_description' => $service->damage_description,
                'laptop_detail' => $service->laptop_detail,
                'service_price' => round((float) $service->service_price, 2),
            ];
            $headerNew = [
                'entry_date' => $data['entry_date'] ?? $headerOld['entry_date'],
                'exit_date' => array_key_exists('exit_date', $data) ? ($data['exit_date'] ?: null) : $headerOld['exit_date'],
                'description' => array_key_exists('description', $data) ? $data['description'] : $service->description,
                'damage_description' => array_key_exists('damage_description', $data) ? $data['damage_description'] : $service->damage_description,
                'laptop_detail' => array_key_exists('laptop_detail', $data) ? $data['laptop_detail'] : $service->laptop_detail,
                'service_price' => isset($data['service_price']) ? round((float) $data['service_price'], 2) : $headerOld['service_price'],
            ];
            $headerDiff = $this->logService->diff($headerOld, $headerNew);
            if ($headerDiff !== []) {
                $service->update([
                    'entry_date' => $headerNew['entry_date'],
                    'exit_date' => $headerNew['exit_date'],
                    'description' => $headerNew['description'],
                    'damage_description' => $headerNew['damage_description'],
                    'laptop_detail' => $headerNew['laptop_detail'],
                    'service_price' => $headerNew['service_price'],
                ]);
                $changes['header'] = $headerDiff;
            }

            if (! empty($data['payments'])) {
                $payChanges = $this->syncPayments(
                    payments: $service->payments,
                    incoming: $data['payments'],
                    referenceType: CashFlow::REFERENCE_SERVICE,
                    referenceId: (int) $service->id,
                    cashFlowType: CashFlow::TYPE_IN,
                    branchId: $service->branch_id ? (int) $service->branch_id : null,
                    warehouseId: $service->warehouse_id ? (int) $service->warehouse_id : null,
                    invoiceNumber: (string) $service->invoice_number,
                    transactionDateFallback: optional($service->entry_date)->format('Y-m-d') ?: now()->toDateString(),
                );
                if ($payChanges !== []) {
                    $changes['payments'] = $payChanges;
                }
                $paid = round((float) $service->payments()->sum('amount'), 2);
                $totalPrice = (float) $service->service_price + (float) ($service->materials_total_price ?? 0);
                $service->update([
                    'total_paid' => $paid,
                    'payment_status' => $paid >= ($totalPrice - 0.02)
                        ? Service::PAYMENT_LUNAS
                        : Service::PAYMENT_BELUM_LUNAS,
                ]);
            }

            if ($changes === []) {
                throw new InvalidArgumentException(__('Tidak ada perubahan data.'));
            }

            $this->logService->log(
                TransactionUpdateLog::TYPE_SERVICE,
                (int) $service->id,
                $service->invoice_number,
                $userId,
                $reason,
                $changes
            );
        });
    }

    /**
     * @param  array{pickup_date?: string, return_date?: ?string, description?: ?string, payments?: array}  $data
     */
    public function updateRental(Rental $rental, array $data, int $userId, string $reason): void
    {
        DB::transaction(function () use ($rental, $data, $userId, $reason) {
            $rental->load('payments');
            $changes = [];

            $headerOld = [
                'pickup_date' => optional($rental->pickup_date)->format('Y-m-d'),
                'return_date' => optional($rental->return_date)->format('Y-m-d'),
                'description' => $rental->description,
            ];
            $headerNew = [
                'pickup_date' => $data['pickup_date'] ?? $headerOld['pickup_date'],
                'return_date' => array_key_exists('return_date', $data) ? ($data['return_date'] ?: null) : $headerOld['return_date'],
                'description' => array_key_exists('description', $data) ? $data['description'] : $rental->description,
            ];
            $headerDiff = $this->logService->diff($headerOld, $headerNew);
            if ($headerDiff !== []) {
                $rental->update([
                    'pickup_date' => $headerNew['pickup_date'],
                    'return_date' => $headerNew['return_date'],
                    'description' => $headerNew['description'],
                ]);
                $changes['header'] = $headerDiff;
            }

            if (! empty($data['payments'])) {
                $payChanges = $this->syncPayments(
                    payments: $rental->payments,
                    incoming: $data['payments'],
                    referenceType: CashFlow::REFERENCE_RENTAL,
                    referenceId: (int) $rental->id,
                    cashFlowType: CashFlow::TYPE_IN,
                    branchId: $rental->branch_id ? (int) $rental->branch_id : null,
                    warehouseId: $rental->warehouse_id ? (int) $rental->warehouse_id : null,
                    invoiceNumber: (string) $rental->invoice_number,
                    transactionDateFallback: optional($rental->pickup_date)->format('Y-m-d') ?: now()->toDateString(),
                );
                if ($payChanges !== []) {
                    $changes['payments'] = $payChanges;
                }
                $paid = round((float) $rental->payments()->sum('amount'), 2);
                $rental->update([
                    'total_paid' => $paid,
                    'payment_status' => $paid >= ((float) $rental->total - 0.02)
                        ? Rental::PAYMENT_LUNAS
                        : Rental::PAYMENT_BELUM_LUNAS,
                ]);
            }

            if ($changes === []) {
                throw new InvalidArgumentException(__('Tidak ada perubahan data.'));
            }

            $this->logService->log(
                TransactionUpdateLog::TYPE_RENTAL,
                (int) $rental->id,
                $rental->invoice_number,
                $userId,
                $reason,
                $changes
            );
        });
    }

    /**
     * @param  array{purchase_date?: string, description?: ?string, termin?: ?string, due_date?: ?string, payments?: array}  $data
     */
    public function updatePurchase(Purchase $purchase, array $data, int $userId, string $reason): void
    {
        DB::transaction(function () use ($purchase, $data, $userId, $reason) {
            $purchase->load('payments');
            $changes = [];

            $headerOld = [
                'purchase_date' => optional($purchase->purchase_date)->format('Y-m-d'),
                'description' => $purchase->description,
                'termin' => $purchase->termin,
                'due_date' => optional($purchase->due_date)->format('Y-m-d'),
            ];
            $headerNew = [
                'purchase_date' => $data['purchase_date'] ?? $headerOld['purchase_date'],
                'description' => array_key_exists('description', $data) ? $data['description'] : $purchase->description,
                'termin' => array_key_exists('termin', $data) ? $data['termin'] : $purchase->termin,
                'due_date' => array_key_exists('due_date', $data) ? ($data['due_date'] ?: null) : $headerOld['due_date'],
            ];
            $headerDiff = $this->logService->diff($headerOld, $headerNew);
            if ($headerDiff !== []) {
                $purchase->update([
                    'purchase_date' => $headerNew['purchase_date'],
                    'description' => $headerNew['description'],
                    'termin' => $headerNew['termin'],
                    'due_date' => $headerNew['due_date'],
                ]);
                $changes['header'] = $headerDiff;
            }

            if (! empty($data['payments'])) {
                $payChanges = $this->syncPayments(
                    payments: $purchase->payments,
                    incoming: $data['payments'],
                    referenceType: CashFlow::REFERENCE_PURCHASE,
                    referenceId: (int) $purchase->id,
                    cashFlowType: CashFlow::TYPE_OUT,
                    branchId: $purchase->branch_id ? (int) $purchase->branch_id : null,
                    warehouseId: $purchase->warehouse_id ? (int) $purchase->warehouse_id : null,
                    invoiceNumber: (string) $purchase->invoice_number,
                    transactionDateFallback: optional($purchase->purchase_date)->format('Y-m-d') ?: now()->toDateString(),
                    allowPaymentDate: true,
                );
                if ($payChanges !== []) {
                    $changes['payments'] = $payChanges;
                }
                $purchase->update([
                    'total_paid' => round((float) $purchase->payments()->sum('amount'), 2),
                ]);
            }

            if ($changes === []) {
                throw new InvalidArgumentException(__('Tidak ada perubahan data.'));
            }

            $this->logService->log(
                TransactionUpdateLog::TYPE_PURCHASE,
                (int) $purchase->id,
                $purchase->invoice_number,
                $userId,
                $reason,
                $changes
            );
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SalePayment|ServicePayment|RentalPayment|PurchasePayment>  $payments
     * @param  array<int, array{id: int, amount: float|string, payment_method_id: int, payment_date?: string|null, notes?: string|null}>  $incoming
     * @return list<array<string, mixed>>
     */
    private function syncPayments(
        $payments,
        array $incoming,
        string $referenceType,
        int $referenceId,
        string $cashFlowType,
        ?int $branchId,
        ?int $warehouseId,
        string $invoiceNumber,
        string $transactionDateFallback,
        bool $allowPaymentDate = false,
    ): array {
        $byId = $payments->keyBy('id');
        $usedCashFlowIds = [];
        $payChanges = [];

        foreach ($incoming as $row) {
            $paymentId = (int) ($row['id'] ?? 0);
            /** @var SalePayment|ServicePayment|RentalPayment|PurchasePayment|null $payment */
            $payment = $byId->get($paymentId);
            if (! $payment) {
                throw new InvalidArgumentException(__('Pembayaran #:id tidak ditemukan.', ['id' => $paymentId]));
            }

            $oldAmount = round((float) $payment->amount, 2);
            $oldPmId = (int) $payment->payment_method_id;
            $newAmount = round((float) $row['amount'], 2);
            $newPmId = (int) $row['payment_method_id'];
            if ($newAmount <= 0) {
                throw new InvalidArgumentException(__('Nominal pembayaran harus lebih dari 0.'));
            }

            $oldDate = $allowPaymentDate && isset($payment->payment_date)
                ? optional($payment->payment_date)->format('Y-m-d')
                : null;
            $newDate = $allowPaymentDate
                ? (($row['payment_date'] ?? null) ?: $oldDate ?: $transactionDateFallback)
                : null;

            $diff = $this->logService->diff(
                [
                    'amount' => $oldAmount,
                    'payment_method_id' => $oldPmId,
                    'payment_date' => $oldDate,
                ],
                [
                    'amount' => $newAmount,
                    'payment_method_id' => $newPmId,
                    'payment_date' => $newDate,
                ]
            );
            if ($diff === []) {
                // still reserve cash flow link if present
                if ($payment->cash_flow_id) {
                    $usedCashFlowIds[] = (int) $payment->cash_flow_id;
                }
                continue;
            }

            $cashFlow = $this->resolveCashFlowForPayment(
                $payment,
                $referenceType,
                $referenceId,
                $cashFlowType,
                $oldAmount,
                $oldPmId,
                $usedCashFlowIds
            );

            $pm = PaymentMethod::find($newPmId);
            $pmLabel = $pm?->display_label ?? __('Payment');

            $paymentUpdate = [
                'amount' => $newAmount,
                'payment_method_id' => $newPmId,
            ];
            if ($allowPaymentDate && $newDate) {
                $paymentUpdate['payment_date'] = $newDate;
            }
            if (array_key_exists('notes', $row)) {
                $paymentUpdate['notes'] = $row['notes'];
            }
            $payment->update($paymentUpdate);

            if ($cashFlow) {
                $cashFlow->update([
                    'amount' => $newAmount,
                    'payment_method_id' => $newPmId,
                    'transaction_date' => $newDate ?: ($cashFlow->transaction_date?->format('Y-m-d') ?: $transactionDateFallback),
                    'description' => $this->buildCashFlowDescription($referenceType, $invoiceNumber, $pmLabel, $cashFlow->description),
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                ]);
                if (! $payment->cash_flow_id) {
                    $payment->update(['cash_flow_id' => $cashFlow->id]);
                }
                $usedCashFlowIds[] = (int) $cashFlow->id;
            } else {
                // Create missing cash flow so books stay balanced
                $cf = CashFlow::create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'type' => $cashFlowType,
                    'amount' => $newAmount,
                    'description' => $this->buildCashFlowDescription($referenceType, $invoiceNumber, $pmLabel, null),
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'payment_method_id' => $newPmId,
                    'transaction_date' => $newDate ?: $transactionDateFallback,
                    'user_id' => auth()->id(),
                ]);
                $payment->update(['cash_flow_id' => $cf->id]);
                $usedCashFlowIds[] = (int) $cf->id;
                $diff['cash_flow'] = ['old' => null, 'new' => 'created #'.$cf->id];
            }

            $payChanges[] = [
                'payment_id' => $payment->id,
                'changes' => $diff,
            ];
        }

        return $payChanges;
    }

    private function resolveCashFlowForPayment(
        $payment,
        string $referenceType,
        int $referenceId,
        string $cashFlowType,
        float $oldAmount,
        int $oldPmId,
        array $usedCashFlowIds
    ): ?CashFlow {
        if ($payment->cash_flow_id) {
            $cf = CashFlow::whereKey($payment->cash_flow_id)->first();
            if ($cf && ! in_array((int) $cf->id, $usedCashFlowIds, true)) {
                return $cf;
            }
        }

        $exact = CashFlow::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('type', $cashFlowType)
            ->where('payment_method_id', $oldPmId)
            ->whereRaw('ABS(amount - ?) < 0.02', [$oldAmount])
            ->when($usedCashFlowIds !== [], fn ($q) => $q->whereNotIn('id', $usedCashFlowIds))
            ->orderBy('id')
            ->first();
        if ($exact) {
            return $exact;
        }

        return CashFlow::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('type', $cashFlowType)
            ->when($usedCashFlowIds !== [], fn ($q) => $q->whereNotIn('id', $usedCashFlowIds))
            ->orderBy('id')
            ->first();
    }

    /**
     * Update manual cash in/out (REFERENCE_OTHER) and write audit log.
     *
     * @param  array{transaction_date: string, amount: float|string, description?: ?string, payment_method_id: int}  $data
     */
    public function updateCashFlow(CashFlow $cashFlow, array $data, int $userId, string $reason): void
    {
        DB::transaction(function () use ($cashFlow, $data, $userId, $reason) {
            $old = [
                'transaction_date' => optional($cashFlow->transaction_date)->format('Y-m-d'),
                'amount' => round((float) $cashFlow->amount, 2),
                'description' => $cashFlow->description,
                'payment_method_id' => (int) $cashFlow->payment_method_id,
            ];
            $new = [
                'transaction_date' => $data['transaction_date'],
                'amount' => round((float) $data['amount'], 2),
                'description' => array_key_exists('description', $data) ? $data['description'] : $cashFlow->description,
                'payment_method_id' => (int) $data['payment_method_id'],
            ];
            $diff = $this->logService->diff($old, $new);
            if ($diff === []) {
                throw new InvalidArgumentException(__('Tidak ada perubahan data.'));
            }

            $cashFlow->update([
                'transaction_date' => $new['transaction_date'],
                'amount' => $new['amount'],
                'description' => $new['description'],
                'payment_method_id' => $new['payment_method_id'],
            ]);

            $type = $cashFlow->type === CashFlow::TYPE_IN
                ? TransactionUpdateLog::TYPE_CASH_IN
                : TransactionUpdateLog::TYPE_CASH_OUT;

            $this->logService->log(
                $type,
                (int) $cashFlow->id,
                '#'.$cashFlow->id,
                $userId,
                $reason,
                ['header' => $diff]
            );
        });
    }

    private function buildCashFlowDescription(string $referenceType, string $invoice, string $pmLabel, ?string $existing): string
    {
        $prefix = match ($referenceType) {
            CashFlow::REFERENCE_SALE => __('Sale'),
            CashFlow::REFERENCE_SERVICE => __('Service'),
            CashFlow::REFERENCE_RENTAL => __('Rental'),
            CashFlow::REFERENCE_PURCHASE => __('Purchase'),
            default => __('Transaksi'),
        };

        return $prefix.' '.$invoice.' - '.$pmLabel;
    }
}
