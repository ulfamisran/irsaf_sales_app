<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\PaymentMethod;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Warehouse;
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
        $warehouseId = null;
        if (! $user->isSuperAdmin()) {
            $branchId = $user->branch_id;
        } elseif ($request->filled('branch_id')) {
            $branchId = (int) $request->branch_id;
        } elseif ($request->filled('warehouse_id')) {
            $warehouseId = (int) $request->warehouse_id;
        }
        if ($request->filled('warehouse_id')) {
            $warehouseId = (int) $request->warehouse_id;
            $branchId = null;
        }

        $salesQuery = Sale::query()
            ->where('status', Sale::STATUS_RELEASED)
            ->whereBetween('sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $salesQuery->whereRaw('1 = 0');
        }

        $sales = $salesQuery->get()->filter(fn ($sale) => $sale->isPaidOff());

        $totalSales = (float) DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.status', Sale::STATUS_RELEASED)
            ->whereBetween('sales.sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->sum('sale_payments.amount');
        $totalSalesHpp = (float) $sales->sum->total_hpp;
        $totalSalesProfit = $totalSales - $totalSalesHpp;

        $servicesQuery = Service::query()
            ->where('status', Service::STATUS_COMPLETED)
            ->whereBetween('entry_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $servicesQuery->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $servicesQuery->whereRaw('1 = 0');
        }

        $services = $servicesQuery->get();

        $totalServiceRevenue = (float) $services->sum('service_price');
        $totalServiceCost = (float) $services->sum('service_cost');
        $totalServiceProfit = $totalServiceRevenue - $totalServiceCost;

        $totalTradeIn = 0.0;
        if (! $warehouseId) {
            $totalTradeIn = (float) DB::table('sale_trade_ins')
                ->join('sales', 'sale_trade_ins.sale_id', '=', 'sales.id')
                ->where('sales.status', Sale::STATUS_RELEASED)
                ->whereBetween('sales.sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
                ->sum('sale_trade_ins.trade_in_value');
        }

        $incomeOtherQuery = CashFlow::query()
            ->where('type', CashFlow::TYPE_IN)
            ->where('reference_type', CashFlow::REFERENCE_OTHER)
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $incomeOtherQuery->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $incomeOtherQuery->where('warehouse_id', $warehouseId);
        }

        $totalOtherIncome = (float) $incomeOtherQuery->sum('amount');

        $expenseQuery = CashFlow::query()
            ->where('type', CashFlow::TYPE_OUT)
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $expenseQuery->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $expenseQuery->where('warehouse_id', $warehouseId);
        }

        $totalExpense = (float) $expenseQuery->sum('amount');

        $rentalQuery = Rental::query()
            ->where('status', '!=', Rental::STATUS_CANCEL)
            ->whereBetween('pickup_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
        if ($warehouseId) {
            $rentalQuery->where('warehouse_id', $warehouseId);
        }
        if ($branchId) {
            $rentalQuery->where('branch_id', $branchId);
        }
        $totalRentalIncome = (float) $rentalQuery->sum('total');

        $netProfit = ($totalSalesProfit + $totalServiceProfit + $totalRentalIncome + $totalOtherIncome) - $totalExpense;

        $expenseDetails = CashFlow::with('expenseCategory')
            ->where('type', CashFlow::TYPE_OUT)
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : collect();
        $warehouses = $user->isSuperAdmin()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
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
            'totalTradeIn' => $totalTradeIn,
            'totalRentalIncome' => $totalRentalIncome,
            'totalOtherIncome' => $totalOtherIncome,
            'totalExpense' => $totalExpense,
            'netProfit' => $netProfit,
            'expenseDetails' => $expenseDetails,
            'branches' => $branches,
            'selectedBranchId' => $branchId,
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
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
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->warehouse_id : null;

        // Filter tanggal (opsional) - default: tidak filter, tampilkan semua data
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : null;
        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // Branch IN from sales + service (payment method via payment_methods)
        $salePayments = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('sales.status', Sale::STATUS_RELEASED)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('sales.sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()]))
            ->selectRaw('sales.branch_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(sale_payments.amount) as total')
            ->groupBy('sales.branch_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        $servicePayments = DB::table('service_payments')
            ->join('services', 'service_payments.service_id', '=', 'services.id')
            ->join('payment_methods', 'service_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('services.status', Service::STATUS_COMPLETED)
            ->when($branchId, fn ($q) => $q->where('services.branch_id', $branchId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween(DB::raw('COALESCE(services.exit_date, services.entry_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()]))
            ->selectRaw('services.branch_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(service_payments.amount) as total')
            ->groupBy('services.branch_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        // CashFlow IN/OUT (manual + rental), grouped by branch_id/warehouse_id and payment method
        $cashFlowInByPm = DB::table('cash_flows')
            ->leftJoin('payment_methods', 'cash_flows.payment_method_id', '=', 'payment_methods.id')
            ->where('cash_flows.type', CashFlow::TYPE_IN)
            ->where(function ($q) {
                $q->whereNull('cash_flows.reference_type')
                    ->orWhereNotIn('cash_flows.reference_type', [CashFlow::REFERENCE_SALE, CashFlow::REFERENCE_SERVICE]);
            })
            ->where(function ($q) {
                $q->whereNull('cash_flows.reference_type')
                    ->orWhere('cash_flows.reference_type', '!=', CashFlow::REFERENCE_RENTAL)
                    ->orWhereIn('cash_flows.reference_id', function ($sq) {
                        $sq->select('id')
                            ->from('rentals')
                            ->where('status', '!=', 'cancel');
                    });
            })
            ->when($branchId, fn ($q) => $q->where('cash_flows.branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('cash_flows.warehouse_id', $warehouseId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('cash_flows.transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]))
            ->selectRaw('cash_flows.branch_id, cash_flows.warehouse_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(cash_flows.amount) as total')
            ->groupBy('cash_flows.branch_id', 'cash_flows.warehouse_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
            ->get();

        $cashFlowOutByPm = DB::table('cash_flows')
            ->leftJoin('payment_methods', 'cash_flows.payment_method_id', '=', 'payment_methods.id')
            ->where('cash_flows.type', CashFlow::TYPE_OUT)
            ->where(function ($q) {
                $q->whereNull('cash_flows.reference_type')
                    ->orWhereNotIn('cash_flows.reference_type', [CashFlow::REFERENCE_SALE, CashFlow::REFERENCE_SERVICE]);
            })
            ->where(function ($q) {
                $q->whereNull('cash_flows.reference_type')
                    ->orWhere('cash_flows.reference_type', '!=', CashFlow::REFERENCE_RENTAL)
                    ->orWhereIn('cash_flows.reference_id', function ($sq) {
                        $sq->select('id')
                            ->from('rentals')
                            ->where('status', '!=', 'cancel');
                    });
            })
            ->when($branchId, fn ($q) => $q->where('cash_flows.branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('cash_flows.warehouse_id', $warehouseId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('cash_flows.transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]))
            ->selectRaw('cash_flows.branch_id, cash_flows.warehouse_id, payment_methods.jenis_pembayaran, payment_methods.nama_bank, payment_methods.no_rekening, SUM(cash_flows.amount) as total')
            ->groupBy('cash_flows.branch_id', 'cash_flows.warehouse_id', 'payment_methods.jenis_pembayaran', 'payment_methods.nama_bank', 'payment_methods.no_rekening')
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

        // branchTotals[branch_id][key] & warehouseTotals[warehouse_id][key]
        $branchTotals = [];
        $warehouseTotals = [];
        $branchInTotals = [];
        $warehouseInTotals = [];
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
            $branchInTotals[$bid] = ($branchInTotals[$bid] ?? 0) + (float) $row->total;
        }

        foreach ($servicePayments as $row) {
            $bid = $row->branch_id;
            $key = $keyFromRow($row);
            $allKeys[$key] = true;
            if (! isset($branchTotals[$bid])) {
                $branchTotals[$bid] = [];
            }
            $branchTotals[$bid][$key] = ($branchTotals[$bid][$key] ?? 0) + (float) $row->total;
            $branchInTotals[$bid] = ($branchInTotals[$bid] ?? 0) + (float) $row->total;
        }

        foreach ($cashFlowInByPm as $row) {
            $key = $keyFromRow($row);
            $allKeys[$key] = true;
            if ($row->warehouse_id) {
                $wid = $row->warehouse_id;
                if (! isset($warehouseTotals[$wid])) {
                    $warehouseTotals[$wid] = [];
                }
                $warehouseTotals[$wid][$key] = ($warehouseTotals[$wid][$key] ?? 0) + (float) $row->total;
                $warehouseInTotals[$wid] = ($warehouseInTotals[$wid] ?? 0) + (float) $row->total;
            } else {
                $bid = $row->branch_id;
                if (! isset($branchTotals[$bid])) {
                    $branchTotals[$bid] = [];
                }
                $branchTotals[$bid][$key] = ($branchTotals[$bid][$key] ?? 0) + (float) $row->total;
                $branchInTotals[$bid] = ($branchInTotals[$bid] ?? 0) + (float) $row->total;
            }
        }

        foreach ($cashFlowOutByPm as $row) {
            $key = $keyFromRow($row);
            if ($row->warehouse_id) {
                $wid = $row->warehouse_id;
                if (! isset($warehouseTotals[$wid])) {
                    $warehouseTotals[$wid] = [];
                }
                $warehouseTotals[$wid][$key] = ($warehouseTotals[$wid][$key] ?? 0) - (float) $row->total;
            } else {
                $bid = $row->branch_id;
                if (! isset($branchTotals[$bid])) {
                    $branchTotals[$bid] = [];
                }
                $branchTotals[$bid][$key] = ($branchTotals[$bid][$key] ?? 0) - (float) $row->total;
            }
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
        $warehouseExpense = [];
        $cashFlowOutTotals = DB::table('cash_flows')
            ->where('type', CashFlow::TYPE_OUT)
            ->where(function ($q) {
                $q->whereNull('reference_type')
                    ->orWhereNotIn('reference_type', [CashFlow::REFERENCE_SALE, CashFlow::REFERENCE_SERVICE]);
            })
            ->where(function ($q) {
                $q->whereNull('reference_type')
                    ->orWhere('reference_type', '!=', CashFlow::REFERENCE_RENTAL)
                    ->orWhereIn('reference_id', function ($sq) {
                        $sq->select('id')
                            ->from('rentals')
                            ->where('status', '!=', 'cancel');
                    });
            })
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]))
            ->selectRaw('branch_id, warehouse_id, SUM(amount) as total')
            ->groupBy('branch_id', 'warehouse_id')
            ->get();
        foreach ($cashFlowOutTotals as $row) {
            if ($row->warehouse_id) {
                $warehouseExpense[$row->warehouse_id] = (float) $row->total;
            } else {
                $branchExpense[$row->branch_id] = (float) $row->total;
            }
        }

        $branchTradeIn = [];
        $overallTradeIn = 0.0;
        if (! $warehouseId) {
            $tradeInRows = DB::table('sale_trade_ins')
                ->join('sales', 'sale_trade_ins.sale_id', '=', 'sales.id')
                ->where('sales.status', Sale::STATUS_RELEASED)
                ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
                ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('sales.sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()]))
                ->selectRaw('sales.branch_id, SUM(sale_trade_ins.trade_in_value) as total')
                ->groupBy('sales.branch_id')
                ->get();
            foreach ($tradeInRows as $row) {
                $branchTradeIn[$row->branch_id] = (float) $row->total;
                $overallTradeIn += (float) $row->total;
            }
        }

        $overallCash = 0.0;
        $overallTotals = [];
        $overallIn = 0.0;
        $overallOut = 0.0;
        foreach ($branchTotals as $totals) {
            $overallCash += array_sum($totals);
            foreach ($totals as $key => $val) {
                $overallTotals[$key] = ($overallTotals[$key] ?? 0) + (float) $val;
            }
        }
        foreach ($warehouseTotals as $totals) {
            $overallCash += array_sum($totals);
            foreach ($totals as $key => $val) {
                $overallTotals[$key] = ($overallTotals[$key] ?? 0) + (float) $val;
            }
        }
        foreach ($branchInTotals as $val) {
            $overallIn += (float) $val;
        }
        foreach ($warehouseInTotals as $val) {
            $overallIn += (float) $val;
        }
        foreach ($branchExpense as $val) {
            $overallOut += (float) $val;
        }
        foreach ($warehouseExpense as $val) {
            $overallOut += (float) $val;
        }
        $overallSaldo = $overallIn - $overallOut;

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return view('finance.cash-monitoring', [
            'branchTotals' => $branchTotals,
            'branchExpense' => $branchExpense,
            'warehouseTotals' => $warehouseTotals,
            'warehouseExpense' => $warehouseExpense,
            'kasKeys' => $kasKeys,
            'kasLabels' => $kasLabels,
            'overallCash' => $overallCash,
            'overallTotals' => $overallTotals,
            'branchInTotals' => $branchInTotals,
            'warehouseInTotals' => $warehouseInTotals,
            'overallIn' => $overallIn,
            'overallOut' => $overallOut,
            'overallSaldo' => $overallSaldo,
            'branchTradeIn' => $branchTradeIn,
            'overallTradeIn' => $overallTradeIn,
            'branches' => $branches,
            'selectedBranchId' => $branchId,
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
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
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->warehouse_id : null;
        $kasKey = $request->filled('kas_key') ? $request->kas_key : null;
        $isOverall = $request->boolean('overall');

        if (! $kasKey || (! $isOverall && ! $branchId && ! $warehouseId)) {
            abort(404, __('Parameter tidak valid.'));
        }

        if (! $user->isSuperAdmin() && $branchId && (int) $user->branch_id !== $branchId) {
            abort(403, __('Akses ditolak.'));
        }

        $locationType = $isOverall ? 'overall' : ($warehouseId ? 'warehouse' : 'branch');
        $location = null;
        if (! $isOverall) {
            $location = $warehouseId
                ? Warehouse::findOrFail($warehouseId)
                : Branch::findOrFail($branchId);
        }

        // Filter tanggal (dari halaman utama)
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : null;
        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // Payment method IDs yang sesuai dengan kas_key
        $paymentMethodIds = $this->getPaymentMethodIdsByKasKey($kasKey);
        $includeNullPm = $kasKey === 'Tunai';

        if (empty($paymentMethodIds)) {
            $paymentMethodIds = [0]; // Avoid invalid WHERE IN ()
        }

        $transactions = collect();

        if (! $warehouseId) {
            $salePaymentsQuery = DB::table('sale_payments')
                ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
                ->where('sales.status', Sale::STATUS_RELEASED)
                ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
                ->whereIn('sale_payments.payment_method_id', $paymentMethodIds)
                ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('sales.sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()]));
            $salePaymentsDetail = $salePaymentsQuery->selectRaw('sales.sale_date as transaction_date, sales.invoice_number, sale_payments.amount')->get();

            foreach ($salePaymentsDetail as $row) {
                $transactions->push((object) [
                    'transaction_date' => $row->transaction_date,
                    'reference' => $row->invoice_number,
                    'description' => 'Penjualan ' . $row->invoice_number,
                    'amount' => (float) $row->amount,
                    'type' => 'IN',
                    'source' => 'Penjualan',
                ]);
            }

            $servicePaymentsQuery = DB::table('service_payments')
                ->join('services', 'service_payments.service_id', '=', 'services.id')
                ->where('services.status', Service::STATUS_COMPLETED)
                ->when($branchId, fn ($q) => $q->where('services.branch_id', $branchId))
                ->whereIn('service_payments.payment_method_id', $paymentMethodIds)
                ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween(DB::raw('COALESCE(services.exit_date, services.entry_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()]));
            $servicePaymentsDetail = $servicePaymentsQuery->selectRaw('COALESCE(services.exit_date, services.entry_date) as transaction_date, services.invoice_number, service_payments.amount')->get();

            foreach ($servicePaymentsDetail as $row) {
                $transactions->push((object) [
                    'transaction_date' => $row->transaction_date,
                    'reference' => $row->invoice_number,
                    'description' => 'Service Laptop ' . $row->invoice_number,
                    'amount' => (float) $row->amount,
                    'type' => 'IN',
                    'source' => 'Service',
                ]);
            }
        }

        $cashFlowQuery = CashFlow::with('expenseCategory')
            ->when($includeNullPm, function ($q) use ($paymentMethodIds) {
                $q->where(function ($qq) use ($paymentMethodIds) {
                    $qq->whereIn('payment_method_id', $paymentMethodIds)
                        ->orWhereNull('payment_method_id');
                });
            }, function ($q) use ($paymentMethodIds) {
                $q->whereIn('payment_method_id', $paymentMethodIds);
            })
            ->where(function ($q) {
                $q->whereNull('reference_type')
                    ->orWhereNotIn('reference_type', [CashFlow::REFERENCE_SALE, CashFlow::REFERENCE_SERVICE]);
            })
            ->where(function ($q) {
                $q->whereNull('reference_type')
                    ->orWhere('reference_type', '!=', CashFlow::REFERENCE_RENTAL)
                    ->orWhereIn('reference_id', function ($sq) {
                        $sq->select('id')
                            ->from('rentals')
                            ->where('status', '!=', 'cancel');
                    });
            })
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]));

        $cashFlows = $cashFlowQuery->orderByDesc('transaction_date')->orderByDesc('id')->get();
        foreach ($cashFlows as $row) {
            $transactions->push((object) [
                'transaction_date' => $row->transaction_date->toDateString(),
                'reference' => $row->reference_id,
                'description' => $row->description ?? (optional($row->expenseCategory)->name ?? 'Transaksi'),
                'amount' => $row->type === CashFlow::TYPE_OUT ? -(float) $row->amount : (float) $row->amount,
                'type' => $row->type,
                'source' => strtoupper($row->reference_type ?? 'cash'),
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
            'location' => $location,
            'locationType' => $locationType,
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

