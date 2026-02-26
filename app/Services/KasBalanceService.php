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

        $branchTotals = [];

        $salePayments = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', Sale::STATUS_RELEASED)
            ->selectRaw('sales.branch_id, sale_payments.payment_method_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(sale_payments.amount) as total')
            ->groupBy('sales.branch_id', 'sale_payments.payment_method_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        foreach ($salePayments as $row) {
            $key = $keyFromRow($row);
            $branchTotals[$key] = ($branchTotals[$key] ?? 0) + (float) $row->total;
        }

        $servicePayments = DB::table('service_payments')
            ->join('services', 'service_payments.service_id', '=', 'services.id')
            ->join('payment_methods', 'service_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('services.branch_id', $branchId)
            ->where('services.status', Service::STATUS_COMPLETED)
            ->selectRaw('services.branch_id, service_payments.payment_method_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(service_payments.amount) as total')
            ->groupBy('services.branch_id', 'service_payments.payment_method_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        foreach ($servicePayments as $row) {
            $key = $keyFromRow($row);
            $branchTotals[$key] = ($branchTotals[$key] ?? 0) + (float) $row->total;
        }

        $cashFlowIn = DB::table('cash_flows')
            ->join('payment_methods', 'cash_flows.payment_method_id', '=', 'payment_methods.id')
            ->where('cash_flows.branch_id', $branchId)
            ->where('cash_flows.type', CashFlow::TYPE_IN)
            ->where('cash_flows.reference_type', CashFlow::REFERENCE_OTHER)
            ->whereNotNull('cash_flows.payment_method_id')
            ->selectRaw('cash_flows.branch_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(cash_flows.amount) as total')
            ->groupBy('cash_flows.branch_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        foreach ($cashFlowIn as $row) {
            $key = $keyFromRow($row);
            $branchTotals[$key] = ($branchTotals[$key] ?? 0) + (float) $row->total;
        }

        $cashFlowOut = DB::table('cash_flows')
            ->join('payment_methods', 'cash_flows.payment_method_id', '=', 'payment_methods.id')
            ->where('cash_flows.branch_id', $branchId)
            ->where('cash_flows.type', CashFlow::TYPE_OUT)
            ->whereNotNull('cash_flows.payment_method_id')
            ->selectRaw('cash_flows.branch_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(cash_flows.amount) as total')
            ->groupBy('cash_flows.branch_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        foreach ($cashFlowOut as $row) {
            $key = $keyFromRow($row);
            $branchTotals[$key] = ($branchTotals[$key] ?? 0) - (float) $row->total;
        }

        $result = [];
        foreach (PaymentMethod::all() as $pm) {
            $key = $keyFromPm($pm);

            $result[$pm->id] = round($branchTotals[$key] ?? 0, 2);
        }

        return $result;
    }

    /**
     * Saldo untuk (branch_id, payment_method_id).
     */
    public function getSaldo(int $branchId, int $paymentMethodId): float
    {
        $all = $this->getSaldoPerPaymentMethod($branchId);

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
}
