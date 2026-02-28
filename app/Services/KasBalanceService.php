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
     * @return array<int, float>
     */
    private function getSaldoPerPaymentMethodForLocation(string $locationType, int $locationId): array
    {
        $keyFromPm = function ($pm) {
            $jenis = strtolower(trim((string) ($pm->jenis_pembayaran ?? '')));
            $bank = trim((string) ($pm->nama_bank ?? ''));
            $rek = trim((string) ($pm->no_rekening ?? ''));
            if (str_contains($jenis, 'tunai') || ($bank === '' && $rek === '')) {
                return 'Tunai';
            }

            return $bank . '|' . $rek;
        };

        $keyFromRow = function ($row) {
            $jenis = strtolower(trim((string) ($row->jenis_pembayaran ?? '')));
            $bank = trim((string) ($row->nama_bank ?? ''));
            $rek = trim((string) ($row->no_rekening ?? ''));
            if (str_contains($jenis, 'tunai') || ($bank === '' && $rek === '')) {
                return 'Tunai';
            }

            return $bank . '|' . $rek;
        };

        $totals = [];

        $cashFlowIn = DB::table('cash_flows')
            ->leftJoin('payment_methods', 'cash_flows.payment_method_id', '=', 'payment_methods.id')
            ->where('cash_flows.type', CashFlow::TYPE_IN)
            ->when($locationType === 'branch', fn ($q) => $q->where('cash_flows.branch_id', $locationId))
            ->when($locationType === 'warehouse', fn ($q) => $q->where('cash_flows.warehouse_id', $locationId))
            ->selectRaw('payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(cash_flows.amount) as total')
            ->groupBy('payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        foreach ($cashFlowIn as $row) {
            $key = $keyFromRow($row);
            $totals[$key] = ($totals[$key] ?? 0) + (float) $row->total;
        }

        $cashFlowOut = DB::table('cash_flows')
            ->leftJoin('payment_methods', 'cash_flows.payment_method_id', '=', 'payment_methods.id')
            ->where('cash_flows.type', CashFlow::TYPE_OUT)
            ->when($locationType === 'branch', fn ($q) => $q->where('cash_flows.branch_id', $locationId))
            ->when($locationType === 'warehouse', fn ($q) => $q->where('cash_flows.warehouse_id', $locationId))
            ->selectRaw('payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(cash_flows.amount) as total')
            ->groupBy('payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        foreach ($cashFlowOut as $row) {
            $key = $keyFromRow($row);
            $totals[$key] = ($totals[$key] ?? 0) - (float) $row->total;
        }

        $result = [];
        foreach (PaymentMethod::all() as $pm) {
            $key = $keyFromPm($pm);
            $result[$pm->id] = round($totals[$key] ?? 0, 2);
        }

        return $result;
    }
}
