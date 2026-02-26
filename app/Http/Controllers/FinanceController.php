<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function profitLoss(Request $request): View
    {
        $user = $request->user();

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : Carbon::today()->startOfMonth();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : Carbon::today()->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $branchId = null;
        if (! $user->isSuperAdmin()) {
            $branchId = $user->branch_id;
        } elseif ($request->filled('branch_id')) {
            $branchId = (int) $request->branch_id;
        }

        $salesQuery = Sale::query()
            ->where('status', Sale::STATUS_RELEASED)
            ->whereBetween('sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
        }

        $sales = $salesQuery->get()->filter(fn ($sale) => $sale->isPaidOff());

        $totalSales = (float) $sales->sum('total');
        $totalSalesHpp = (float) $sales->sum->total_hpp;
        $totalSalesProfit = $totalSales - $totalSalesHpp;

        $servicesQuery = Service::query()
            ->where('status', Service::STATUS_COMPLETED)
            ->whereBetween('entry_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $servicesQuery->where('branch_id', $branchId);
        }

        $services = $servicesQuery->get();

        $totalServiceRevenue = (float) $services->sum('service_price');
        $totalServiceCost = (float) $services->sum('service_cost');
        $totalServiceProfit = $totalServiceRevenue - $totalServiceCost;

        $expenseQuery = CashFlow::query()
            ->where('type', CashFlow::TYPE_OUT)
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $expenseQuery->where('branch_id', $branchId);
        }

        $totalExpense = (float) $expenseQuery->sum('amount');

        $netProfit = ($totalSalesProfit + $totalServiceProfit) - $totalExpense;

        $expenseByCategory = CashFlow::with('expenseCategory')
            ->where('type', CashFlow::TYPE_OUT)
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->groupBy('expense_category_id')
            ->map(function ($items) {
                /** @var \Illuminate\Support\Collection $items */
                $first = $items->first();
                return [
                    'name' => optional($first->expenseCategory)->name ?? '-',
                    'total' => (float) $items->sum('amount'),
                ];
            })
            ->values();

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : collect();

        return view('finance.profit-loss', [
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'totalSales' => $totalSales,
            'totalSalesHpp' => $totalSalesHpp,
            'totalSalesProfit' => $totalSalesProfit,
            'totalServiceRevenue' => $totalServiceRevenue,
            'totalServiceCost' => $totalServiceCost,
            'totalServiceProfit' => $totalServiceProfit,
            'totalExpense' => $totalExpense,
            'netProfit' => $netProfit,
            'expenseByCategory' => $expenseByCategory,
            'branches' => $branches,
            'selectedBranchId' => $branchId,
        ]);
    }

    /**
     * Monitoring Kas: tampilkan jumlah dana berdasarkan nama bank dan nomor rekening.
     */
    public function cashMonitoring(Request $request): View
    {
        $user = $request->user();

        $branchId = null;
        if (! $user->isSuperAdmin()) {
            $branchId = $user->branch_id;
        } elseif ($request->filled('branch_id')) {
            $branchId = (int) $request->branch_id;
        }

        // Filter tanggal (opsional) - default: tidak filter, tampilkan semua data
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : null;
        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // Pemasukan dari penjualan - group by branch_id, nama_bank, no_rekening
        $salePaymentsQuery = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('sales.status', Sale::STATUS_RELEASED)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('sales.sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()]));
        $salePayments = $salePaymentsQuery
            ->selectRaw('sales.branch_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(sale_payments.amount) as total')
            ->groupBy('sales.branch_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        // Pemasukan dari service - group by branch_id, nama_bank, no_rekening (filter by exit_date)
        $servicePaymentsQuery = DB::table('service_payments')
            ->join('services', 'service_payments.service_id', '=', 'services.id')
            ->join('payment_methods', 'service_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('services.status', Service::STATUS_COMPLETED)
            ->when($branchId, fn ($q) => $q->where('services.branch_id', $branchId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween(DB::raw('COALESCE(services.exit_date, services.entry_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()]));
        $servicePayments = $servicePaymentsQuery
            ->selectRaw('services.branch_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(service_payments.amount) as total')
            ->groupBy('services.branch_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        // Pemasukan lainnya (CashFlow IN - reference lainnya) - sekarang punya payment_method_id
        $cashFlowInQuery = DB::table('cash_flows')
            ->join('payment_methods', 'cash_flows.payment_method_id', '=', 'payment_methods.id')
            ->where('cash_flows.type', CashFlow::TYPE_IN)
            ->where('cash_flows.reference_type', CashFlow::REFERENCE_OTHER)
            ->whereNotNull('cash_flows.payment_method_id')
            ->when($branchId, fn ($q) => $q->where('cash_flows.branch_id', $branchId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('cash_flows.transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]));
        $cashFlowIn = $cashFlowInQuery
            ->selectRaw('cash_flows.branch_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(cash_flows.amount) as total')
            ->groupBy('cash_flows.branch_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        // Pengeluaran per cabang (total) dan per payment method (untuk kurangi saldo per kas)
        $cashFlowOutQuery = DB::table('cash_flows')
            ->where('type', CashFlow::TYPE_OUT)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]));
        $cashFlowOut = $cashFlowOutQuery
            ->selectRaw('branch_id, SUM(amount) as total')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        // Pengeluaran per kas (punya payment_method_id)
        $cashFlowOutByPm = DB::table('cash_flows')
            ->join('payment_methods', 'cash_flows.payment_method_id', '=', 'payment_methods.id')
            ->where('cash_flows.type', CashFlow::TYPE_OUT)
            ->whereNotNull('cash_flows.payment_method_id')
            ->when($branchId, fn ($q) => $q->where('cash_flows.branch_id', $branchId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('cash_flows.transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]))
            ->selectRaw('cash_flows.branch_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(cash_flows.amount) as total')
            ->groupBy('cash_flows.branch_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        $keyFromRow = function ($row) {
            $jenis = strtolower(trim((string) ($row->jenis_pembayaran ?? '')));
            $bank = trim((string) ($row->nama_bank ?? ''));
            $rek = trim((string) ($row->no_rekening ?? ''));
            if (str_contains($jenis, 'tunai') || ($bank === '' && $rek === '')) {
                return 'Tunai';
            }

            return $bank . '|' . $rek;
        };

        // branchTotals[branch_id][key] = total | key = "Tunai" atau "nama_bank|no_rekening"
        $branchTotals = [];
        $allKeys = [];

        // Masukkan semua metode pembayaran aktif (nama bank + no rekening) agar rekening kosong tetap tampil
        $allPaymentMethods = PaymentMethod::where('is_active', true)->get(['jenis_pembayaran', 'nama_bank', 'no_rekening']);
        foreach ($allPaymentMethods as $pm) {
            $jenis = strtolower(trim((string) ($pm->jenis_pembayaran ?? '')));
            $bank = trim((string) ($pm->nama_bank ?? ''));
            $rek = trim((string) ($pm->no_rekening ?? ''));
            if (str_contains($jenis, 'tunai') || ($bank === '' && $rek === '')) {
                $allKeys['Tunai'] = true;
            } else {
                $allKeys[$bank . '|' . $rek] = true;
            }
        }

        foreach ($salePayments as $row) {
            $bid = $row->branch_id;
            $key = $keyFromRow($row);
            $allKeys[$key] = true;
            if (! isset($branchTotals[$bid])) {
                $branchTotals[$bid] = [];
            }
            $branchTotals[$bid][$key] = ($branchTotals[$bid][$key] ?? 0) + (float) $row->total;
        }

        foreach ($servicePayments as $row) {
            $bid = $row->branch_id;
            $key = $keyFromRow($row);
            $allKeys[$key] = true;
            if (! isset($branchTotals[$bid])) {
                $branchTotals[$bid] = [];
            }
            $branchTotals[$bid][$key] = ($branchTotals[$bid][$key] ?? 0) + (float) $row->total;
        }

        foreach ($cashFlowIn as $row) {
            $bid = $row->branch_id;
            $key = $keyFromRow($row);
            $allKeys[$key] = true;
            if (! isset($branchTotals[$bid])) {
                $branchTotals[$bid] = [];
            }
            $branchTotals[$bid][$key] = ($branchTotals[$bid][$key] ?? 0) + (float) $row->total;
        }

        // Kurangi pengeluaran per kas (yang punya payment_method_id)
        foreach ($cashFlowOutByPm as $row) {
            $bid = $row->branch_id;
            $key = $keyFromRow($row);
            if (! isset($branchTotals[$bid])) {
                $branchTotals[$bid] = [];
            }
            $branchTotals[$bid][$key] = ($branchTotals[$bid][$key] ?? 0) - (float) $row->total;
        }

        // Urutkan: Tunai dulu, lalu alfabetis nama bank, no rekening
        $kasKeys = array_keys($allKeys);
        usort($kasKeys, function ($a, $b) {
            if ($a === 'Tunai') {
                return -1;
            }
            if ($b === 'Tunai') {
                return 1;
            }

            return strcmp($a, $b);
        });

        // Label per key untuk tampilan: nama bank + nomor rekening
        $kasLabels = [];
        foreach ($kasKeys as $key) {
            if ($key === 'Tunai') {
                $kasLabels[$key] = ['label' => 'Tunai', 'subtitle' => null];
            } else {
                $parts = explode('|', $key, 2);
                $kasLabels[$key] = [
                    'label' => $parts[0] ?: '-',
                    'subtitle' => $parts[1] ?? null,
                ];
            }
        }

        $branchExpense = [];
        foreach ($cashFlowOut as $row) {
            $branchExpense[$row->branch_id] = (float) $row->total;
        }

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);

        return view('finance.cash-monitoring', [
            'branchTotals' => $branchTotals,
            'branchExpense' => $branchExpense,
            'kasKeys' => $kasKeys,
            'kasLabels' => $kasLabels,
            'branches' => $branches,
            'selectedBranchId' => $branchId,
            'dateFrom' => $dateFrom?->toDateString(),
            'dateTo' => $dateTo?->toDateString(),
        ]);
    }

    /**
     * Detail arus kas per bank di Monitoring Kas.
     */
    public function cashMonitoringDetail(Request $request): View
    {
        $user = $request->user();

        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
        $kasKey = $request->filled('kas_key') ? $request->kas_key : null;

        if (! $branchId || ! $kasKey) {
            abort(404, __('Parameter tidak valid.'));
        }

        if (! $user->isSuperAdmin() && (int) $user->branch_id !== $branchId) {
            abort(403, __('Akses ditolak.'));
        }

        $branch = Branch::findOrFail($branchId);

        // Filter tanggal (dari halaman utama)
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : null;
        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // Payment method IDs yang sesuai dengan kas_key
        $paymentMethodIds = $this->getPaymentMethodIdsByKasKey($kasKey);

        if (empty($paymentMethodIds)) {
            $paymentMethodIds = [0]; // Avoid invalid WHERE IN ()
        }

        $transactions = collect();

        // 1. Pemasukan dari penjualan
        $salePaymentsQuery = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', Sale::STATUS_RELEASED)
            ->whereIn('sale_payments.payment_method_id', $paymentMethodIds)
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('sales.sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()]));
        $salePayments = $salePaymentsQuery->selectRaw('sales.sale_date as transaction_date, sales.invoice_number, sale_payments.amount')->get();

        foreach ($salePayments as $row) {
            $transactions->push((object) [
                'transaction_date' => $row->transaction_date,
                'reference' => $row->invoice_number,
                'description' => 'Penjualan ' . $row->invoice_number,
                'amount' => (float) $row->amount,
                'type' => 'IN',
                'source' => 'Penjualan',
            ]);
        }

        // 2. Pemasukan dari service
        $servicePaymentsQuery = DB::table('service_payments')
            ->join('services', 'service_payments.service_id', '=', 'services.id')
            ->where('services.branch_id', $branchId)
            ->where('services.status', Service::STATUS_COMPLETED)
            ->whereIn('service_payments.payment_method_id', $paymentMethodIds)
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween(DB::raw('COALESCE(services.exit_date, services.entry_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()]));
        $servicePayments = $servicePaymentsQuery->selectRaw('COALESCE(services.exit_date, services.entry_date) as transaction_date, services.invoice_number, service_payments.amount')->get();

        foreach ($servicePayments as $row) {
            $transactions->push((object) [
                'transaction_date' => $row->transaction_date,
                'reference' => $row->invoice_number,
                'description' => 'Service Laptop ' . $row->invoice_number,
                'amount' => (float) $row->amount,
                'type' => 'IN',
                'source' => 'Service',
            ]);
        }

        // 3. Pemasukan lainnya (manual)
        $cashFlowInQuery = CashFlow::where('branch_id', $branchId)
            ->where('type', CashFlow::TYPE_IN)
            ->where('reference_type', CashFlow::REFERENCE_OTHER)
            ->whereIn('payment_method_id', $paymentMethodIds)
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]));
        $cashFlowIn = $cashFlowInQuery->orderByDesc('transaction_date')->orderByDesc('id')->get();

        foreach ($cashFlowIn as $row) {
            $transactions->push((object) [
                'transaction_date' => $row->transaction_date->toDateString(),
                'reference' => null,
                'description' => $row->description ?? 'Pemasukan Lainnya',
                'amount' => (float) $row->amount,
                'type' => 'IN',
                'source' => 'Pemasukan Lainnya',
            ]);
        }

        // 4. Dana keluar (pengeluaran dari kas ini)
        $cashFlowOutQuery = CashFlow::with('expenseCategory')
            ->where('branch_id', $branchId)
            ->where('type', CashFlow::TYPE_OUT)
            ->whereIn('payment_method_id', $paymentMethodIds)
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]));
        $cashFlowOut = $cashFlowOutQuery->orderByDesc('transaction_date')->orderByDesc('id')->get();

        foreach ($cashFlowOut as $row) {
            $transactions->push((object) [
                'transaction_date' => $row->transaction_date->toDateString(),
                'reference' => null,
                'description' => $row->description ?? (optional($row->expenseCategory)->name ?? 'Pengeluaran'),
                'amount' => -(float) $row->amount,
                'type' => 'OUT',
                'source' => 'Pengeluaran',
            ]);
        }

        $transactions = $transactions->sortByDesc('transaction_date')->values();

        $label = $kasKey === 'Tunai'
            ? 'Tunai'
            : (explode('|', $kasKey, 2)[0] ?? '-') . ' (' . (explode('|', $kasKey, 2)[1] ?? '-') . ')';

        $totalPemasukan = $transactions->where('type', 'IN')->sum('amount');
        $totalPengeluaran = $transactions->where('type', 'OUT')->sum(fn ($t) => abs($t->amount));
        $saldo = $totalPemasukan - $totalPengeluaran;

        return view('finance.cash-monitoring-detail', [
            'branch' => $branch,
            'kasKey' => $kasKey,
            'kasLabel' => $label,
            'transactions' => $transactions,
            'totalPemasukan' => $totalPemasukan,
            'totalPengeluaran' => $totalPengeluaran,
            'saldo' => $saldo,
            'dateFrom' => $dateFrom?->toDateString(),
            'dateTo' => $dateTo?->toDateString(),
        ]);
    }

    private function getPaymentMethodIdsByKasKey(string $kasKey): array
    {
        if ($kasKey === 'Tunai') {
            return PaymentMethod::query()
                ->get()
                ->filter(fn ($pm) => str_contains(strtolower(trim((string) $pm->jenis_pembayaran)), 'tunai')
                    || (trim((string) $pm->nama_bank) === '' && trim((string) $pm->no_rekening) === ''))
                ->pluck('id')
                ->toArray();
        }

        $parts = explode('|', $kasKey, 2);
        $bank = trim($parts[0] ?? '');
        $rek = trim($parts[1] ?? '');

        return PaymentMethod::query()
            ->whereRaw('TRIM(COALESCE(nama_bank, "")) = ?', [$bank])
            ->whereRaw('TRIM(COALESCE(no_rekening, "")) = ?', [$rek])
            ->pluck('id')
            ->toArray();
    }

    private function kasKeyFromPaymentMethod(string $jenis, string $bank, string $rek): string
    {
        $jenis = strtolower(trim($jenis));
        $bank = trim($bank);
        $rek = trim($rek);
        if (str_contains($jenis, 'tunai') || ($bank === '' && $rek === '')) {
            return 'Tunai';
        }

        return $bank . '|' . $rek;
    }
}

