<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

class KasBalanceService
{
    /**
     * Saldo per payment_method_id untuk suatu cabang.
     * Return: [payment_method_id => saldo]
     */
    public function getSaldoPerPaymentMethod(int $branchId): array
    {
        return $this->getSaldoPerPaymentMethodForLocation('branch', $branchId);
    }

    /**
     * Saldo per payment_method_id untuk suatu gudang.
     * Return: [payment_method_id => saldo]
     */
    public function getSaldoPerPaymentMethodForWarehouse(int $warehouseId): array
    {
        return $this->getSaldoPerPaymentMethodForLocation('warehouse', $warehouseId);
    }

    /**
     * Saldo untuk (branch_id, payment_method_id).
     */
    public function getSaldo(int $branchId, int $paymentMethodId): float
    {
        $all = $this->getSaldoPerPaymentMethod($branchId);

        return (float) ($all[$paymentMethodId] ?? 0);
    }

    public function getSaldoForLocation(string $locationType, int $locationId, int $paymentMethodId): float
    {
        $all = $this->getSaldoPerPaymentMethodForLocation($locationType, $locationId);

        return (float) ($all[$paymentMethodId] ?? 0);
    }

    /**
     * Saldo per branch dan payment method: [branch_id][payment_method_id] => saldo.
     *
     * @param  array<int>  $branchIds
     */
    public function getSaldoPerBranchAndPm(array $branchIds): array
    {
        $result = [];

        foreach ($branchIds as $bid) {
            $result[$bid] = $this->getSaldoPerPaymentMethod($bid);
        }

        return $result;
    }

    /**
     * Saldo per warehouse dan payment method: [warehouse_id][payment_method_id] => saldo.
     *
     * @param  array<int>  $warehouseIds
     */
    public function getSaldoPerWarehouseAndPm(array $warehouseIds): array
    {
        $result = [];

        foreach ($warehouseIds as $wid) {
            $result[$wid] = $this->getSaldoPerPaymentMethodForLocation('warehouse', $wid);
        }

        return $result;
    }

    /**
     * Hitung saldo per payment_method_id.
     * 100% berbasis cash_flows (konsisten Monitoring Kas/Detail Kas).
     * Include fallback legacy sale cash-in tanpa payment_method_id bila cocok unik ke sale_payment.
     * Konsisten dengan Monitoring Kas.
     *
     * @return array<int, float> [payment_method_id => saldo]
     */
    public function getSaldoPerPaymentMethodForLocation(string $locationType, int $locationId): array
    {
        $totals = [];

        $cashFlowRows = DB::table('cash_flows')
            ->select([
                'cash_flows.id',
                'cash_flows.type',
                'cash_flows.amount',
                'cash_flows.payment_method_id',
                'cash_flows.reference_type',
                'cash_flows.reference_id',
            ])
            ->where(function ($q) {
                $q->whereNull('cash_flows.reference_type')
                    ->orWhere('cash_flows.reference_type', '!=', CashFlow::REFERENCE_RENTAL)
                    ->orWhereIn('cash_flows.reference_id', function ($sq) {
                        $sq->select('id')->from('rentals')->where('status', '!=', 'cancel');
                    })
                    ->orWhere(function ($sq) {
                        $sq->where('cash_flows.reference_type', CashFlow::REFERENCE_RENTAL)
                            ->where('cash_flows.type', CashFlow::TYPE_OUT);
                    });
            })
            ->when($locationType === 'branch', fn ($q) => $q->where('cash_flows.branch_id', $locationId))
            ->when($locationType === 'warehouse', fn ($q) => $q->where('cash_flows.warehouse_id', $locationId))
            ->get();

        // Fallback legacy sale cash-in tanpa payment_method_id: map via sale_payments (sale_id + nominal).
        $legacySaleRows = $cashFlowRows
            ->filter(fn ($row) => (int) ($row->payment_method_id ?? 0) <= 0
                && ($row->reference_type ?? null) === CashFlow::REFERENCE_SALE
                && strtoupper((string) ($row->type ?? '')) === CashFlow::TYPE_IN
                && ! empty($row->reference_id));
        $legacySaleIds = $legacySaleRows
            ->pluck('reference_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $salePaymentsBySale = DB::table('sale_payments')
            ->select(['sale_id', 'payment_method_id', 'amount'])
            ->whereIn('sale_id', $legacySaleIds === [] ? [0] : $legacySaleIds)
            ->get();
        $salePaymentsIndex = [];
        foreach ($salePaymentsBySale as $sp) {
            $sid = (int) ($sp->sale_id ?? 0);
            if ($sid <= 0) {
                continue;
            }
            if (! isset($salePaymentsIndex[$sid])) {
                $salePaymentsIndex[$sid] = [];
            }
            $salePaymentsIndex[$sid][] = $sp;
        }

        foreach ($cashFlowRows as $row) {
            $amount = (float) ($row->amount ?? 0);
            if ($amount == 0.0) {
                continue;
            }
            $pmId = (int) ($row->payment_method_id ?? 0);
            if ($pmId <= 0
                && ($row->reference_type ?? null) === CashFlow::REFERENCE_SALE
                && strtoupper((string) ($row->type ?? '')) === CashFlow::TYPE_IN
            ) {
                $saleId = (int) ($row->reference_id ?? 0);
                $matches = collect($salePaymentsIndex[$saleId] ?? [])
                    ->filter(fn ($sp) => abs(((float) ($sp->amount ?? 0)) - $amount) < 0.02)
                    ->pluck('payment_method_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();
                if (count($matches) === 1) {
                    $pmId = $matches[0];
                } else {
                    // Setelah pembatalan penjualan, sale_payments dihapus — petakan lewat reversal OUT (nominal sama).
                    $revPmIds = DB::table('cash_flows')
                        ->where('reference_type', CashFlow::REFERENCE_SALE)
                        ->where('reference_id', $saleId)
                        ->where('type', CashFlow::TYPE_OUT)
                        ->whereRaw('ABS(amount - ?) < 0.02', [$amount])
                        ->whereNotNull('payment_method_id')
                        ->pluck('payment_method_id')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values();
                    if ($revPmIds->count() === 1) {
                        $pmId = (int) $revPmIds->first();
                    }
                }
            }
            if ($pmId <= 0) {
                continue;
            }
            $isIn = strtoupper((string) ($row->type ?? '')) === CashFlow::TYPE_IN;
            $totals[$pmId] = ($totals[$pmId] ?? 0) + ($isIn ? $amount : -$amount);
        }

        $result = [];
        foreach (PaymentMethod::all() as $pm) {
            $result[$pm->id] = round($totals[$pm->id] ?? 0, 2);
        }

        return $result;
    }
}
