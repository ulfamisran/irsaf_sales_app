<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\Service;
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
     * Untuk branch: sale_payments + service_payments (seluruh transaksi non-cancel) + cash_flows IN - cash_flows OUT.
     * Untuk warehouse: cash_flows saja (tidak ada sale/service di gudang).
     * Konsisten dengan Monitoring Kas.
     *
     * @return array<int, float> [payment_method_id => saldo]
     */
    private function getSaldoPerPaymentMethodForLocation(string $locationType, int $locationId): array
    {
        $totals = [];

        if ($locationType === 'branch') {
            $salePayments = DB::table('sale_payments')
                ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
                ->where('sales.status', '!=', Sale::STATUS_CANCEL)
                ->where('sales.branch_id', $locationId)
                ->selectRaw('payment_method_id, SUM(amount) as total')
                ->groupBy('payment_method_id')
                ->get();

            foreach ($salePayments as $row) {
                $pmId = (int) $row->payment_method_id;
                if ($pmId > 0) {
                    $totals[$pmId] = ($totals[$pmId] ?? 0) + (float) $row->total;
                }
            }

            $servicePayments = DB::table('service_payments')
                ->join('services', 'service_payments.service_id', '=', 'services.id')
                ->where('services.status', '!=', Service::STATUS_CANCEL)
                ->where('services.branch_id', $locationId)
                ->selectRaw('payment_method_id, SUM(amount) as total')
                ->groupBy('payment_method_id')
                ->get();

            foreach ($servicePayments as $row) {
                $pmId = (int) $row->payment_method_id;
                if ($pmId > 0) {
                    $totals[$pmId] = ($totals[$pmId] ?? 0) + (float) $row->total;
                }
            }
        }

        $cashFlowIn = DB::table('cash_flows')
            ->where('cash_flows.type', CashFlow::TYPE_IN)
            ->whereNotNull('cash_flows.payment_method_id')
            ->where(function ($q) {
                $q->whereNull('cash_flows.reference_type')
                    ->orWhereNotIn('cash_flows.reference_type', [CashFlow::REFERENCE_SALE, CashFlow::REFERENCE_SERVICE])
                    ->orWhere(function ($q2) {
                        $q2->where('cash_flows.reference_type', CashFlow::REFERENCE_SERVICE)
                            ->whereExists(function ($sq) {
                                $sq->select(DB::raw(1))
                                    ->from('income_categories')
                                    ->whereColumn('income_categories.id', 'cash_flows.income_category_id')
                                    ->where('income_categories.code', 'RTR');
                            });
                    });
            })
            ->where(function ($q) {
                $q->whereNull('cash_flows.reference_type')
                    ->orWhere('cash_flows.reference_type', '!=', CashFlow::REFERENCE_RENTAL)
                    ->orWhereIn('cash_flows.reference_id', function ($sq) {
                        $sq->select('id')->from('rentals')->where('status', '!=', 'cancel');
                    });
            })
            ->when($locationType === 'branch', fn ($q) => $q->where('cash_flows.branch_id', $locationId))
            ->when($locationType === 'warehouse', fn ($q) => $q->where('cash_flows.warehouse_id', $locationId))
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->get();

        foreach ($cashFlowIn as $row) {
            $pmId = (int) $row->payment_method_id;
            $totals[$pmId] = ($totals[$pmId] ?? 0) + (float) $row->total;
        }

        $cashFlowOut = DB::table('cash_flows')
            ->where('cash_flows.type', CashFlow::TYPE_OUT)
            ->whereNotNull('cash_flows.payment_method_id')
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
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->get();

        foreach ($cashFlowOut as $row) {
            $pmId = (int) $row->payment_method_id;
            $totals[$pmId] = ($totals[$pmId] ?? 0) - (float) $row->total;
        }

        $result = [];
        foreach (PaymentMethod::all() as $pm) {
            $result[$pm->id] = round($totals[$pm->id] ?? 0, 2);
        }

        return $result;
    }
}
