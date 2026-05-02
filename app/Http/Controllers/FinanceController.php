<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\DamagedGood;
use App\Models\Distribution;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\PaymentMethod;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Stock;

use App\Models\Warehouse;
use App\Support\ExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function profitLossExport(Request $request): StreamedResponse
    {
        $data = $this->profitLoss($request)->getData();
        $html = view('finance.export-profit-loss', (array) $data)->render();
        $filename = 'laba-rugi-' . now()->format('Ymd-His') . '.xlsx';

        return ExcelExporter::downloadFromHtml($html, $filename, 'generic');
    }

    public function profitLossExportPdf(Request $request)
    {
        $data = $this->profitLoss($request)->getData();
        $pdf = Pdf::loadView('finance.export-profit-loss-pdf', (array) $data)
            ->setPaper('a4', 'landscape');

        return $pdf->download('laba-rugi-' . now()->format('Ymd-His') . '.pdf');
    }

    public function cashMonitoringExport(Request $request): StreamedResponse
    {
        $data = $this->cashMonitoring($request)->getData();
        $html = view('finance.export-cash-monitoring', (array) $data)->render();
        $filename = 'monitoring-kas-' . now()->format('Ymd-His') . '.xlsx';

        return ExcelExporter::downloadFromHtml($html, $filename, 'generic');
    }

    public function cashMonitoringExportPdf(Request $request)
    {
        $data = $this->cashMonitoring($request)->getData();
        $pdf = Pdf::loadView('finance.export-cash-monitoring-pdf', (array) $data)
            ->setPaper('a4', 'landscape');

        return $pdf->download('monitoring-kas-' . now()->format('Ymd-His') . '.pdf');
    }

    public function profitLossComparisonExport(Request $request): StreamedResponse
    {
        $data = $this->profitLossComparison($request)->getData();
        $html = view('finance.export-profit-loss-comparison', (array) $data)->render();
        $filename = 'perbandingan-laba-rugi-' . now()->format('Ymd-His') . '.xlsx';

        return ExcelExporter::downloadFromHtml($html, $filename, 'generic');
    }

    public function profitLossComparisonExportPdf(Request $request)
    {
        $data = $this->profitLossComparison($request)->getData();
        $pdf = Pdf::loadView('finance.export-profit-loss-comparison-pdf', (array) $data)
            ->setPaper('a4', 'landscape');

        return $pdf->download('perbandingan-laba-rugi-' . now()->format('Ymd-His') . '.pdf');
    }

    public function profitLoss(Request $request): View
    {
        $user = $request->user();

        // Toggle: apakah kategori "Pengeluaran Dana Eksternal" ikut mengurangi Laba Rugi?
        // Catatan: checkbox di Blade memakai hidden input sehingga parameter selalu dikirim.
        $includeExternalExpense = (int) $request->input('include_external_expense', 1) === 1;

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
        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;
        $lockedBranchId = null;
        $lockedWarehouseId = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $branchId = (int) $user->branch_id;
                $lockedBranchId = $branchId;
                $filterLocked = true;
                $branch = Branch::find($branchId);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $branchId);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $warehouseId = (int) $user->warehouse_id;
                $lockedWarehouseId = $warehouseId;
                $filterLocked = true;
                $warehouse = Warehouse::find($warehouseId);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $warehouseId);
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('warehouse_id')) {
                $warehouseId = (int) $request->warehouse_id;
            } elseif ($request->filled('branch_id')) {
                $branchId = (int) $request->branch_id;
            }
        }

        // Penjualan: hanya released, Harga Penjualan - HPP
        $salesQuery = Sale::query()
            ->where('status', Sale::STATUS_RELEASED)
            ->whereBetween('sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $salesQuery->where('warehouse_id', $warehouseId);
        }

        $sales = $salesQuery->with('saleDetails', 'payments')->get()->filter(fn ($sale) => $sale->isPaidOff());

        $totalSales = (float) DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.status', Sale::STATUS_RELEASED)
            ->whereBetween('sales.sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('sales.warehouse_id', $warehouseId))
            ->sum('sale_payments.amount');
        $totalSalesHpp = (float) $sales->sum->total_hpp;
        $totalSalesProfit = $totalSales - $totalSalesHpp;

        // Service: hanya completed, Total service - Biaya Material yang dibeli
        $servicesQuery = Service::query()
            ->with('serviceMaterials')
            ->where('status', Service::STATUS_COMPLETED)
            ->whereBetween(
                DB::raw('COALESCE(services.exit_date, services.entry_date)'),
                [$dateFrom->toDateString(), $dateTo->toDateString()]
            );

        if ($branchId) {
            $servicesQuery->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $servicesQuery->where('warehouse_id', $warehouseId);
        }

        $services = $servicesQuery->get();

        $totalServiceRevenue = (float) $services->sum->total_service_price;
        // Biaya Material = Pembelian Sparepart (quantity * price) - sama dengan CashFlow OUT "Pembelian Sparepart User (SERVICE)"
        $totalServiceMaterialCost = (float) $services->sum->materials_total_price;
        $totalServiceProfit = $totalServiceRevenue - $totalServiceMaterialCost;

        $totalTradeIn = 0.0;
        if (! $warehouseId) {
            $totalTradeIn = (float) DB::table('sale_trade_ins')
                ->join('sales', 'sale_trade_ins.sale_id', '=', 'sales.id')
                ->where('sales.status', Sale::STATUS_RELEASED)
                ->whereBetween('sales.sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
                ->sum('sale_trade_ins.trade_in_value');
        }

        // Pemasukan Distribusi: filter tanggal (CashFlow IN reference_type = distribution)
        $incomeDistributionQuery = CashFlow::query()
            ->where('type', CashFlow::TYPE_IN)
            ->where('reference_type', CashFlow::REFERENCE_DISTRIBUTION)
            ->where(function ($q) {
                $q->whereNull('income_category_id')
                    ->orWhereExists(function ($sq) {
                        $sq->select(DB::raw(1))
                            ->from('income_categories')
                            ->whereColumn('income_categories.id', 'cash_flows.income_category_id')
                            ->where('income_categories.affects_profit_loss', true);
                    });
            })
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $incomeDistributionQuery->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $incomeDistributionQuery->where('warehouse_id', $warehouseId);
        }

        $totalDistributionIncome = (float) $incomeDistributionQuery->sum('amount');

        // HPP Distribusi: dari data distribution_details via distributions
        $distributionHppQuery = DB::table('distribution_details')
            ->join('distributions', 'distribution_details.distribution_id', '=', 'distributions.id')
            ->where('distribution_details.biaya_distribusi_per_unit', '>', 0)
            ->where('distributions.status', '!=', 'cancelled')
            ->whereBetween('distributions.distribution_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $distributionHppQuery->where('distributions.from_location_type', Stock::LOCATION_BRANCH)
                ->where('distributions.from_location_id', $branchId);
        }
        if ($warehouseId) {
            $distributionHppQuery->where('distributions.from_location_type', Stock::LOCATION_WAREHOUSE)
                ->where('distributions.from_location_id', $warehouseId);
        }

        $totalDistributionHpp = (float) $distributionHppQuery->selectRaw('COALESCE(SUM(distribution_details.hpp_per_unit * distribution_details.quantity), 0) as total')->value('total');
        $totalDistributionProfit = $totalDistributionIncome - $totalDistributionHpp;

        // Pemasukan Lainnya (exclude distribusi): filter tanggal
        $incomeOtherQuery = CashFlow::query()
            ->where('type', CashFlow::TYPE_IN)
            ->where('reference_type', CashFlow::REFERENCE_OTHER)
            ->where(function ($q) {
                $q->whereNull('income_category_id')
                    ->orWhereExists(function ($sq) {
                        $sq->select(DB::raw(1))
                            ->from('income_categories')
                            ->whereColumn('income_categories.id', 'cash_flows.income_category_id')
                            ->where('income_categories.affects_profit_loss', true);
                    });
            })
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $incomeOtherQuery->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $incomeOtherQuery->where('warehouse_id', $warehouseId);
        }

        $totalOtherIncomeOnly = (float) $incomeOtherQuery->sum('amount');
        $totalOtherIncome = $totalDistributionProfit + $totalOtherIncomeOnly;

        // Pengeluaran: filter tanggal
        // EXCLUDE: SP-SVC (sudah di Biaya Material Service), REVERSAL (transaksi cancel tidak dihitung)
        $excludeExpenseCodes = ['SP-SVC', 'REVERSAL', 'REV'];
        $externalExpenseCategoryId = ExpenseCategory::query()
            ->where('code', 'PENGELUARAN_EKSTERNAL')
            ->value('id');

        $expenseBaseQuery = CashFlow::query()
            ->where('type', CashFlow::TYPE_OUT)
            ->where(function ($q) {
                $q->whereNull('expense_category_id')
                    ->orWhereExists(function ($sq) {
                        $sq->select(DB::raw(1))
                            ->from('expense_categories')
                            ->whereColumn('expense_categories.id', 'cash_flows.expense_category_id')
                            ->where('expense_categories.affects_profit_loss', true);
                    });
            })
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($branchId) {
            $expenseBaseQuery->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $expenseBaseQuery->where('warehouse_id', $warehouseId);
        }

        // Pengeluaran Dana Eksternal (kategori dipisah)
        $totalExternalExpense = 0.0;
        if ($externalExpenseCategoryId) {
            $totalExternalExpense = (float) (clone $expenseBaseQuery)
                ->where('expense_category_id', $externalExpenseCategoryId)
                ->sum('amount');
        }

        // Pengeluaran non-eksternal (sesuai excludeExpenseCodes + tidak termasuk kategori eksternal)
        $expenseQuery = (clone $expenseBaseQuery)
            ->where(function ($q) use ($excludeExpenseCodes) {
                $q->whereNull('expense_category_id')
                    ->orWhereNotIn('expense_category_id', function ($sub) use ($excludeExpenseCodes) {
                        $sub->select('id')->from('expense_categories')->whereIn('code', $excludeExpenseCodes);
                    });
            })
            ->when($externalExpenseCategoryId, function ($q) use ($externalExpenseCategoryId) {
                // Simpan transaksi dengan expense_category_id null, tapi exclude kategori eksternal.
                $q->where(function ($q2) use ($externalExpenseCategoryId) {
                    $q2->whereNull('expense_category_id')
                        ->orWhere('expense_category_id', '!=', $externalExpenseCategoryId);
                });
            });

        $totalExpense = (float) $expenseQuery->sum('amount');

        // Penyewaan: hanya released (selesai), Harga sewa
        $rentalQuery = Rental::query()
            ->where('status', Rental::STATUS_RELEASED)
            ->whereBetween('pickup_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
        if ($warehouseId) {
            $rentalQuery->where('warehouse_id', $warehouseId);
        }
        if ($branchId) {
            $rentalQuery->where('branch_id', $branchId);
        }
        $rentals = $rentalQuery->orderBy('pickup_date')->get();
        $totalRentalIncome = (float) $rentals->sum('total');

        // Detail pemasukan distribusi (untuk POV table)
        $incomeDistributionDetails = CashFlow::query()
            ->where('type', CashFlow::TYPE_IN)
            ->where('reference_type', CashFlow::REFERENCE_DISTRIBUTION)
            ->where(function ($q) {
                $q->whereNull('income_category_id')
                    ->orWhereExists(function ($sq) {
                        $sq->select(DB::raw(1))
                            ->from('income_categories')
                            ->whereColumn('income_categories.id', 'cash_flows.income_category_id')
                            ->where('income_categories.affects_profit_loss', true);
                    });
            })
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
        if ($branchId) {
            $incomeDistributionDetails->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $incomeDistributionDetails->where('warehouse_id', $warehouseId);
        }
        $incomeDistributionDetails = $incomeDistributionDetails->orderBy('transaction_date')->orderBy('id')->get();

        // Detail pemasukan lainnya (exclude distribusi)
        $incomeOtherDetails = CashFlow::query()
            ->where('type', CashFlow::TYPE_IN)
            ->where('reference_type', CashFlow::REFERENCE_OTHER)
            ->where(function ($q) {
                $q->whereNull('income_category_id')
                    ->orWhereExists(function ($sq) {
                        $sq->select(DB::raw(1))
                            ->from('income_categories')
                            ->whereColumn('income_categories.id', 'cash_flows.income_category_id')
                            ->where('income_categories.affects_profit_loss', true);
                    });
            })
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
        if ($branchId) {
            $incomeOtherDetails->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            $incomeOtherDetails->where('warehouse_id', $warehouseId);
        }
        $incomeOtherDetails = $incomeOtherDetails->orderBy('transaction_date')->orderBy('id')->get();

        // Beban barang rusak cadangan: sum DamagedGood.harga_hpp by recorded_date, filter lokasi via product_unit
        $damagedGoodsQuery = DamagedGood::query()
            ->whereNull('reactivated_at')
            ->whereBetween('recorded_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereHas('productUnit');

        if ($branchId) {
            $damagedGoodsQuery->whereHas('productUnit', fn ($q) => $q
                ->where('location_type', Stock::LOCATION_BRANCH)
                ->where('location_id', $branchId));
        }
        if ($warehouseId) {
            $damagedGoodsQuery->whereHas('productUnit', fn ($q) => $q
                ->where('location_type', Stock::LOCATION_WAREHOUSE)
                ->where('location_id', $warehouseId));
        }

        $damagedGoodsDetails = $damagedGoodsQuery->with('productUnit.product')->orderBy('recorded_date')->orderBy('id')->get();
        $totalDamagedGoodsExpense = (float) $damagedGoodsDetails->sum('harga_hpp');

        // Laba = Pemasukan Penjualan (sales+service+rental) + Pemasukan Distribusi + Pemasukan Lainnya - Pengeluaran - Beban Barang Rusak Cadangan
        $totalSalesIncome = $totalSalesProfit + $totalServiceProfit + $totalRentalIncome;
        $totalExternalExpenseForProfit = $includeExternalExpense ? $totalExternalExpense : 0.0;
        $netProfit = $totalSalesIncome + $totalOtherIncome - ($totalExpense + $totalExternalExpenseForProfit) - $totalDamagedGoodsExpense;

        $externalExpenseDetails = collect();
        if ($externalExpenseCategoryId) {
            $externalExpenseDetails = CashFlow::with('expenseCategory')
                ->where('type', CashFlow::TYPE_OUT)
                ->where('expense_category_id', $externalExpenseCategoryId)
                ->where(function ($q) {
                    $q->whereNull('expense_category_id')
                        ->orWhereExists(function ($sq) {
                            $sq->select(DB::raw(1))
                                ->from('expense_categories')
                                ->whereColumn('expense_categories.id', 'cash_flows.expense_category_id')
                                ->where('expense_categories.affects_profit_loss', true);
                        });
                })
                ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->get();
        }

        $expenseDetails = CashFlow::with('expenseCategory')
            ->where('type', CashFlow::TYPE_OUT)
            ->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where(function ($q) {
                $q->whereNull('expense_category_id')
                    ->orWhereExists(function ($sq) {
                        $sq->select(DB::raw(1))
                            ->from('expense_categories')
                            ->whereColumn('expense_categories.id', 'cash_flows.expense_category_id')
                            ->where('expense_categories.affects_profit_loss', true);
                    });
            })
            ->where(function ($q) use ($excludeExpenseCodes) {
                $q->whereNull('expense_category_id')
                    ->orWhereNotIn('expense_category_id', function ($sub) use ($excludeExpenseCodes) {
                        $sub->select('id')->from('expense_categories')->whereIn('code', $excludeExpenseCodes);
                    });
            })
            ->when($externalExpenseCategoryId, function ($q) use ($externalExpenseCategoryId) {
                $q->where(function ($q2) use ($externalExpenseCategoryId) {
                    $q2->whereNull('expense_category_id')
                        ->orWhere('expense_category_id', '!=', $externalExpenseCategoryId);
                });
            })
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : collect();
        $warehouses = $user->isSuperAdminOrAdminPusat()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : collect();

        $pov = $request->input('pov', 'card');

        return view('finance.profit-loss', [
            'canFilterLocation' => $canFilterLocation,
            'filterLocked' => $filterLocked,
            'locationLabel' => $locationLabel,
            'lockedBranchId' => $lockedBranchId ?? null,
            'lockedWarehouseId' => $lockedWarehouseId ?? null,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'totalSales' => $totalSales,
            'totalSalesHpp' => $totalSalesHpp,
            'totalSalesProfit' => $totalSalesProfit,
            'totalServiceRevenue' => $totalServiceRevenue,
            'totalServiceMaterialCost' => $totalServiceMaterialCost,
            'totalServiceProfit' => $totalServiceProfit,
            'totalSalesIncome' => $totalSalesIncome,
            'totalTradeIn' => $totalTradeIn,
            'totalRentalIncome' => $totalRentalIncome,
            'totalDistributionIncome' => $totalDistributionIncome ?? 0,
            'totalDistributionHpp' => $totalDistributionHpp ?? 0,
            'totalDistributionProfit' => $totalDistributionProfit ?? 0,
            'totalOtherIncomeOnly' => $totalOtherIncomeOnly ?? 0,
            'totalOtherIncome' => $totalOtherIncome,
            'totalDamagedGoodsExpense' => $totalDamagedGoodsExpense ?? 0,
            'totalExternalExpense' => $totalExternalExpense ?? 0,
            'totalExternalExpenseForProfit' => $totalExternalExpenseForProfit,
            'includeExternalExpense' => $includeExternalExpense,
            'totalExpense' => $totalExpense,
            'netProfit' => $netProfit,
            'sales' => $sales,
            'services' => $services,
            'rentals' => $rentals,
            'incomeDistributionDetails' => $incomeDistributionDetails ?? collect(),
            'incomeOtherDetails' => $incomeOtherDetails ?? collect(),
            'damagedGoodsDetails' => $damagedGoodsDetails ?? collect(),
            'externalExpenseDetails' => $externalExpenseDetails ?? collect(),
            'expenseDetails' => $expenseDetails,
            'branches' => $branches,
            'selectedBranchId' => $branchId,
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'pov' => $pov,
        ]);
    }

    /**
     * Monitoring Kas: tampilkan jumlah dana berdasarkan nama bank dan nomor rekening.
     */
    public function cashMonitoring(Request $request): View
    {
        $user = $request->user();

        $branchId = null;
        $warehouseId = null;
        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;
        $lockedBranchId = null;
        $lockedWarehouseId = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $branchId = (int) $user->branch_id;
                $lockedBranchId = $branchId;
                $filterLocked = true;
                $branch = Branch::find($branchId);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $branchId);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $warehouseId = (int) $user->warehouse_id;
                $lockedWarehouseId = $warehouseId;
                $filterLocked = true;
                $warehouse = Warehouse::find($warehouseId);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $warehouseId);
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('warehouse_id')) {
                $warehouseId = (int) $request->warehouse_id;
            } elseif ($request->filled('branch_id')) {
                $branchId = (int) $request->branch_id;
            }
        }

        // Filter tanggal (opsional) - default: tidak filter, tampilkan semua data
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : null;
        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // Sumber ringkasan Monitoring Kas: 100% dari cash_flows, konsisten dengan halaman detail.
        // Jika filter tanggal aktif, pair cancel sale (IN/OUT) tetap ditarik berpasangan.
        $cashFlowBaseQuery = CashFlow::query()
            ->with('paymentMethod')
            ->where(function ($q) {
                $q->whereNull('reference_type')
                    ->orWhere('reference_type', '!=', CashFlow::REFERENCE_RENTAL)
                    ->orWhereIn('reference_id', function ($sq) {
                        $sq->select('id')
                            ->from('rentals')
                            ->where('status', '!=', 'cancel');
                    })
                    ->orWhere(function ($sq) {
                        $sq->where('reference_type', CashFlow::REFERENCE_RENTAL)
                            ->where('type', CashFlow::TYPE_OUT);
                    });
            })
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $cashFlowRows = (clone $cashFlowBaseQuery)
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]))
            ->get();

        if ($dateFrom && $dateTo) {
            $saleReferenceIdsInRange = $cashFlowRows
                ->where('reference_type', CashFlow::REFERENCE_SALE)
                ->pluck('reference_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            if ($saleReferenceIdsInRange->isNotEmpty()) {
                $pairedSaleRows = (clone $cashFlowBaseQuery)
                    ->where('reference_type', CashFlow::REFERENCE_SALE)
                    ->whereIn('reference_id', $saleReferenceIdsInRange->all())
                    ->get();
                $cashFlowRows = $cashFlowRows->merge($pairedSaleRows)->unique('id')->values();
            }
        }

        // Pemetaan sale_payment untuk legacy cash flow sale IN yang payment_method_id-nya null.
        $salePaymentRows = DB::table('sale_payments as sp')
            ->join('payment_methods as pm', 'sp.payment_method_id', '=', 'pm.id')
            ->selectRaw('sp.sale_id, sp.amount, pm.jenis_pembayaran, pm.nama_bank, pm.no_rekening')
            ->get();
        $salePaymentBySale = [];
        foreach ($salePaymentRows as $spr) {
            $saleId = (int) ($spr->sale_id ?? 0);
            if ($saleId <= 0) {
                continue;
            }
            if (! isset($salePaymentBySale[$saleId])) {
                $salePaymentBySale[$saleId] = [];
            }
            $salePaymentBySale[$saleId][] = $spr;
        }

        $keyFromRow = function ($row) use ($salePaymentBySale) {
            $pm = $row->paymentMethod ?? null;
            $jenis = strtolower(trim((string) ($pm->jenis_pembayaran ?? ($row->jenis_pembayaran ?? ''))));
            $bank = trim((string) ($pm->nama_bank ?? ($row->nama_bank ?? '')));
            $rek = trim((string) ($pm->no_rekening ?? ($row->no_rekening ?? '')));

            // Jika payment method ada, klasifikasi langsung dari PM tersebut.
            if ($pm || $bank !== '' || $rek !== '' || $jenis !== '') {
                if (str_contains($jenis, 'tunai') || ($bank === '' && $rek === '')) {
                    return 'Tunai';
                }

                return $bank . '|' . $rek;
            }

            // Legacy sale cash-in tanpa payment_method_id: cocokkan dari sale_payments.
            if (($row->reference_type ?? null) === CashFlow::REFERENCE_SALE
                && strtoupper((string) ($row->type ?? '')) === CashFlow::TYPE_IN
                && ($row->payment_method_id ?? null) === null
            ) {
                $saleId = (int) ($row->reference_id ?? 0);
                $amount = (float) ($row->amount ?? 0);
                $candidates = $salePaymentBySale[$saleId] ?? [];
                $matchedKeys = [];
                foreach ($candidates as $candidate) {
                    if (abs(((float) ($candidate->amount ?? 0)) - $amount) >= 0.02) {
                        continue;
                    }
                    $cJenis = strtolower(trim((string) ($candidate->jenis_pembayaran ?? '')));
                    $cBank = trim((string) ($candidate->nama_bank ?? ''));
                    $cRek = trim((string) ($candidate->no_rekening ?? ''));
                    $matchedKeys[] = (str_contains($cJenis, 'tunai') || ($cBank === '' && $cRek === ''))
                        ? 'Tunai'
                        : ($cBank . '|' . $cRek);
                }
                $matchedKeys = array_values(array_unique($matchedKeys));
                if (count($matchedKeys) === 1) {
                    return $matchedKeys[0];
                }
            }

            // Null PM non-legacy / ambigu tidak dimasukkan ke kas/rekening tertentu.
            return null;
        };

        // branchTotals[branch_id][key] & warehouseTotals[warehouse_id][key]
        $branchTotals = [];
        $warehouseTotals = [];
        $branchInTotals = [];
        $warehouseInTotals = [];
        $branchOutTotals = [];
        $warehouseOutTotals = [];
        $allKeys = [];

        // Masukkan metode pembayaran aktif sesuai lokasi (nama bank + no rekening) agar rekening kosong tetap tampil
        $allPaymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($branchId, $warehouseId)
            ->get(['jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);
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

        foreach ($cashFlowRows as $row) {
            $key = $keyFromRow($row);
            if ($key === null || $key === '') {
                continue;
            }
            $allKeys[$key] = true;
            $amount = (float) ($row->amount ?? 0);
            $isIn = strtoupper((string) ($row->type ?? '')) === CashFlow::TYPE_IN;
            $signedAmount = $isIn ? $amount : -$amount;
            if ($row->warehouse_id) {
                $wid = $row->warehouse_id;
                if (! isset($warehouseTotals[$wid])) {
                    $warehouseTotals[$wid] = [];
                }
                $warehouseTotals[$wid][$key] = ($warehouseTotals[$wid][$key] ?? 0) + $signedAmount;
                if ($isIn) {
                    $warehouseInTotals[$wid] = ($warehouseInTotals[$wid] ?? 0) + $amount;
                } else {
                    $warehouseOutTotals[$wid] = ($warehouseOutTotals[$wid] ?? 0) + $amount;
                }
            } else {
                $bid = $row->branch_id;
                if (! isset($branchTotals[$bid])) {
                    $branchTotals[$bid] = [];
                }
                $branchTotals[$bid][$key] = ($branchTotals[$bid][$key] ?? 0) + $signedAmount;
                if ($isIn) {
                    $branchInTotals[$bid] = ($branchInTotals[$bid] ?? 0) + $amount;
                } else {
                    $branchOutTotals[$bid] = ($branchOutTotals[$bid] ?? 0) + $amount;
                }
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

        // Metode pembayaran per cabang/gudang: setiap card hanya menampilkan PM yang sesuai lokasinya
        $branchKasKeys = [];
        $warehouseKasKeys = [];
        $pmToKey = function ($pm) {
            $jenis = strtolower(trim((string) ($pm->jenis_pembayaran ?? '')));
            $bank = trim((string) ($pm->nama_bank ?? ''));
            $rek = trim((string) ($pm->no_rekening ?? ''));
            if (str_contains($jenis, 'tunai') || ($bank === '' && $rek === '')) {
                return 'Tunai';
            }
            return $bank . '|' . $rek;
        };
        foreach (PaymentMethod::query()->where('is_active', true)->whereNotNull('branch_id')->get(['branch_id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']) as $pm) {
            $key = $pmToKey($pm);
            $bid = $pm->branch_id;
            if (! isset($branchKasKeys[$bid])) {
                $branchKasKeys[$bid] = [];
            }
            $branchKasKeys[$bid][$key] = true;
        }
        foreach (PaymentMethod::query()->where('is_active', true)->whereNotNull('warehouse_id')->get(['warehouse_id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']) as $pm) {
            $key = $pmToKey($pm);
            $wid = $pm->warehouse_id;
            if (! isset($warehouseKasKeys[$wid])) {
                $warehouseKasKeys[$wid] = [];
            }
            $warehouseKasKeys[$wid][$key] = true;
        }
        foreach ($branchKasKeys as $bid => $keys) {
            $branchKasKeys[$bid] = array_keys($keys);
            usort($branchKasKeys[$bid], fn ($a, $b) => ($a === 'Tunai' ? -1 : ($b === 'Tunai' ? 1 : strcmp($a, $b))));
        }
        foreach ($warehouseKasKeys as $wid => $keys) {
            $warehouseKasKeys[$wid] = array_keys($keys);
            usort($warehouseKasKeys[$wid], fn ($a, $b) => ($a === 'Tunai' ? -1 : ($b === 'Tunai' ? 1 : strcmp($a, $b))));
        }

        // Label per key untuk tampilan: nama bank + nomor rekening
        $allKeysForLabels = array_unique(array_merge(
            $kasKeys,
            ...array_values($branchKasKeys),
            ...array_values($warehouseKasKeys)
        ));
        $kasLabels = [];
        foreach ($allKeysForLabels as $key) {
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

        $branchExpense = $branchOutTotals;
        $warehouseExpense = $warehouseOutTotals;

        $branchTradeIn = [];
        $overallTradeIn = 0.0;
        if (! $warehouseId) {
            $tradeInRows = DB::table('sale_trade_ins')
                ->join('sales', 'sale_trade_ins.sale_id', '=', 'sales.id')
                ->where('sales.status', '!=', Sale::STATUS_CANCEL)
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

        // Filter by bank (kas_key) jika dipilih
        $filterKasKey = $request->filled('kas_key') ? $request->kas_key : null;
        $allKasKeys = $kasKeys;
        if ($filterKasKey && in_array($filterKasKey, $allKasKeys, true)) {
            $kasKeys = [$filterKasKey];
            $branchKasKeys = array_map(fn ($keys) => array_values(array_intersect($keys, [$filterKasKey])), $branchKasKeys);
            $warehouseKasKeys = array_map(fn ($keys) => array_values(array_intersect($keys, [$filterKasKey])), $warehouseKasKeys);
            foreach ($branchTotals as $bid => $totals) {
                $branchTotals[$bid] = isset($totals[$filterKasKey]) ? [$filterKasKey => $totals[$filterKasKey]] : [];
            }
            foreach ($warehouseTotals as $wid => $totals) {
                $warehouseTotals[$wid] = isset($totals[$filterKasKey]) ? [$filterKasKey => $totals[$filterKasKey]] : [];
            }
            $overallTotals = isset($overallTotals[$filterKasKey]) ? [$filterKasKey => $overallTotals[$filterKasKey]] : [];
        }

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id ? Branch::whereKey($user->branch_id)->get(['id', 'name']) : collect());
        $warehouses = $user->isSuperAdminOrAdminPusat()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id ? Warehouse::whereKey($user->warehouse_id)->get(['id', 'name']) : collect());

        return view('finance.cash-monitoring', [
            'canFilterLocation' => $canFilterLocation,
            'filterLocked' => $filterLocked,
            'locationLabel' => $locationLabel,
            'lockedBranchId' => $lockedBranchId ?? null,
            'lockedWarehouseId' => $lockedWarehouseId ?? null,
            'branchTotals' => $branchTotals,
            'branchExpense' => $branchExpense,
            'warehouseTotals' => $warehouseTotals,
            'warehouseExpense' => $warehouseExpense,
            'kasKeys' => $kasKeys,
            'branchKasKeys' => $branchKasKeys,
            'warehouseKasKeys' => $warehouseKasKeys,
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
            'filterKasKey' => $filterKasKey,
            'allKasKeys' => $allKasKeys ?? [],
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

        if (! $kasKey) {
            abort(404, __('Parameter tidak valid.'));
        }

        if (! $user->isSuperAdminOrAdminPusat()) {
            // Non super admin harus selalu terikat ke lokasi miliknya.
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $branchId = (int) $user->branch_id;
                $warehouseId = null;
                $isOverall = false;
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $warehouseId = (int) $user->warehouse_id;
                $branchId = null;
                $isOverall = false;
            } else {
                abort(403, __('Akses ditolak.'));
            }
        }

        if (! $isOverall && ! $branchId && ! $warehouseId) {
            abort(404, __('Parameter tidak valid.'));
        }

        if ($branchId && $warehouseId) {
            abort(404, __('Parameter lokasi tidak valid.'));
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

        $kasParts = explode('|', $kasKey, 2);
        $selectedBank = trim((string) ($kasParts[0] ?? ''));
        $selectedRekening = trim((string) ($kasParts[1] ?? ''));
        $isTunaiKas = $kasKey === 'Tunai';
        $tunaiPaymentMethodIds = $isTunaiKas ? $this->getPaymentMethodIdsByKasKey('Tunai') : [];

        $transactions = collect();

        $cashFlowBaseQuery = CashFlow::with(['expenseCategory', 'paymentMethod'])
            ->when($isTunaiKas, function ($q) use ($tunaiPaymentMethodIds) {
                $tunaiPaymentMethodIds = array_values(array_unique(array_filter($tunaiPaymentMethodIds, fn ($id) => (int) $id > 0)));
                if ($tunaiPaymentMethodIds === []) {
                    $tunaiPaymentMethodIds = [0];
                }
                $q->where(function ($qq) use ($tunaiPaymentMethodIds) {
                    $qq->whereIn('payment_method_id', $tunaiPaymentMethodIds)
                        // Legacy sale cash-in dapat tidak punya payment_method_id.
                        // Hanya ikut jika sale_payment-nya benar-benar metode tunai dan nominal cocok.
                        ->orWhere(function ($legacySaleIn) use ($tunaiPaymentMethodIds) {
                            $legacySaleIn->where('reference_type', CashFlow::REFERENCE_SALE)
                                ->where('type', CashFlow::TYPE_IN)
                                ->whereNull('payment_method_id')
                                ->where(function ($inner) use ($tunaiPaymentMethodIds) {
                                    // Cocokkan ke sale_payments (sumber pembayaran asli).
                                    $inner->whereExists(function ($sub) use ($tunaiPaymentMethodIds) {
                                        $sub->select(DB::raw(1))
                                            ->from('sale_payments as sp')
                                            ->whereRaw('sp.sale_id = cash_flows.reference_id')
                                            ->whereIn('sp.payment_method_id', $tunaiPaymentMethodIds)
                                            ->whereRaw('ABS(sp.amount - cash_flows.amount) < 0.02');
                                    })
                                    // Cadangan: cocokkan ke pasangan reversal OUT pada cash_flows itu sendiri.
                                        ->orWhereExists(function ($sub) use ($tunaiPaymentMethodIds) {
                                            $sub->select(DB::raw(1))
                                                ->from('cash_flows as rev')
                                                ->whereColumn('rev.reference_id', 'cash_flows.reference_id')
                                                ->where('rev.reference_type', CashFlow::REFERENCE_SALE)
                                                ->where('rev.type', CashFlow::TYPE_OUT)
                                                ->whereRaw('ABS(rev.amount - cash_flows.amount) < 0.02')
                                                ->whereIn('rev.payment_method_id', $tunaiPaymentMethodIds);
                                        });
                                });
                        });
                });
            }, function ($q) use ($selectedBank, $selectedRekening) {
                $q->where(function ($qq) use ($selectedBank, $selectedRekening) {
                    $qq->whereHas('paymentMethod', function ($pm) use ($selectedBank, $selectedRekening) {
                        $pm->whereRaw('TRIM(COALESCE(nama_bank, "")) = ?', [$selectedBank])
                            ->whereRaw('TRIM(COALESCE(no_rekening, "")) = ?', [$selectedRekening]);
                    })
                    // Legacy sale cash-in dapat tidak punya payment_method_id.
                    // Tetap ikut jika ada sale_payment yang cocok bank+rekening dan nominalnya sama.
                        ->orWhere(function ($legacySaleIn) use ($selectedBank, $selectedRekening) {
                            $legacySaleIn->where('reference_type', CashFlow::REFERENCE_SALE)
                                ->where('type', CashFlow::TYPE_IN)
                                ->whereNull('payment_method_id')
                                ->where(function ($inner) use ($selectedBank, $selectedRekening) {
                                    $inner->whereExists(function ($sub) use ($selectedBank, $selectedRekening) {
                                        $sub->select(DB::raw(1))
                                            ->from('sale_payments as sp')
                                            ->join('payment_methods as pm', 'sp.payment_method_id', '=', 'pm.id')
                                            ->whereRaw('sp.sale_id = cash_flows.reference_id')
                                            ->whereRaw('ABS(sp.amount - cash_flows.amount) < 0.02')
                                            ->whereRaw('TRIM(COALESCE(pm.nama_bank, "")) = ?', [$selectedBank])
                                            ->whereRaw('TRIM(COALESCE(pm.no_rekening, "")) = ?', [$selectedRekening]);
                                    })
                                        ->orWhereExists(function ($sub) use ($selectedBank, $selectedRekening) {
                                            $sub->select(DB::raw(1))
                                                ->from('cash_flows as rev')
                                                ->join('payment_methods as pm', 'rev.payment_method_id', '=', 'pm.id')
                                                ->whereColumn('rev.reference_id', 'cash_flows.reference_id')
                                                ->where('rev.reference_type', CashFlow::REFERENCE_SALE)
                                                ->where('rev.type', CashFlow::TYPE_OUT)
                                                ->whereRaw('ABS(rev.amount - cash_flows.amount) < 0.02')
                                                ->whereRaw('TRIM(COALESCE(pm.nama_bank, "")) = ?', [$selectedBank])
                                                ->whereRaw('TRIM(COALESCE(pm.no_rekening, "")) = ?', [$selectedRekening]);
                                        });
                                });
                        });
                });
            })
            ->where(function ($q) {
                $q->whereNull('reference_type')
                    ->orWhere('reference_type', '!=', CashFlow::REFERENCE_RENTAL)
                    ->orWhereIn('reference_id', function ($sq) {
                        $sq->select('id')
                            ->from('rentals')
                            ->where('status', '!=', 'cancel');
                    })
                    ->orWhere(function ($sq) {
                        $sq->where('reference_type', CashFlow::REFERENCE_RENTAL)
                            ->where('type', CashFlow::TYPE_OUT);
                    });
            })
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $cashFlowQuery = clone $cashFlowBaseQuery;
        if ($dateFrom && $dateTo) {
            $cashFlowQuery->whereBetween('transaction_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
        }
        $cashFlows = $cashFlowQuery->orderByDesc('transaction_date')->orderByDesc('id')->get();

        // Jika salah satu sisi pair cancel terambil oleh filter tanggal, tarik juga pasangannya
        // agar transaksi pembatalan sale/service/rental/distribusi selalu tampil berpasangan (IN/OUT)
        // untuk referensi yang sama.
        if ($dateFrom && $dateTo) {
            $pairedReferenceTypes = [
                CashFlow::REFERENCE_SALE,
                CashFlow::REFERENCE_SERVICE,
                CashFlow::REFERENCE_RENTAL,
                CashFlow::REFERENCE_DISTRIBUTION,
            ];

            foreach ($pairedReferenceTypes as $refType) {
                $referenceIdsInRange = $cashFlows
                    ->where('reference_type', $refType)
                    ->pluck('reference_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($referenceIdsInRange->isEmpty()) {
                    continue;
                }

                $pairedCashFlows = (clone $cashFlowBaseQuery)
                    ->where('reference_type', $refType)
                    ->whereIn('reference_id', $referenceIdsInRange->all())
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('id')
                    ->get();

                $cashFlows = $cashFlows
                    ->merge($pairedCashFlows)
                    ->unique('id')
                    ->values();
            }
        }
        foreach ($cashFlows as $row) {
            $transactions->push((object) [
                'transaction_date' => $row->transaction_date->toDateString(),
                'reference' => $row->reference_id,
                'description' => $row->description ?? (optional($row->expenseCategory)->name ?? 'Transaksi'),
                'amount' => $row->type === CashFlow::TYPE_OUT ? -(float) $row->amount : (float) $row->amount,
                'type' => $row->type,
                'source' => strtoupper($row->reference_type ?? 'cash'),
                'reference_type' => $row->reference_type,
                'reference_id' => $row->reference_id,
            ]);
        }

        $referenceIdsByType = [
            CashFlow::REFERENCE_SALE => $transactions
                ->filter(fn ($tx) => ($tx->reference_type ?? null) === CashFlow::REFERENCE_SALE && ! empty($tx->reference_id))
                ->pluck('reference_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
            CashFlow::REFERENCE_SERVICE => $transactions
                ->filter(fn ($tx) => ($tx->reference_type ?? null) === CashFlow::REFERENCE_SERVICE && ! empty($tx->reference_id))
                ->pluck('reference_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
            CashFlow::REFERENCE_RENTAL => $transactions
                ->filter(fn ($tx) => ($tx->reference_type ?? null) === CashFlow::REFERENCE_RENTAL && ! empty($tx->reference_id))
                ->pluck('reference_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
            CashFlow::REFERENCE_DISTRIBUTION => $transactions
                ->filter(fn ($tx) => ($tx->reference_type ?? null) === CashFlow::REFERENCE_DISTRIBUTION && ! empty($tx->reference_id))
                ->pluck('reference_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ];

        $cancelledReferenceMap = [];
        foreach (($referenceIdsByType[CashFlow::REFERENCE_SALE] ?? []) as $id) {
            $cancelledReferenceMap[CashFlow::REFERENCE_SALE . ':' . (int) $id] = false;
        }
        foreach (($referenceIdsByType[CashFlow::REFERENCE_SERVICE] ?? []) as $id) {
            $cancelledReferenceMap[CashFlow::REFERENCE_SERVICE . ':' . (int) $id] = false;
        }
        foreach (($referenceIdsByType[CashFlow::REFERENCE_RENTAL] ?? []) as $id) {
            $cancelledReferenceMap[CashFlow::REFERENCE_RENTAL . ':' . (int) $id] = false;
        }
        foreach (($referenceIdsByType[CashFlow::REFERENCE_DISTRIBUTION] ?? []) as $id) {
            $cancelledReferenceMap[CashFlow::REFERENCE_DISTRIBUTION . ':' . (int) $id] = false;
        }

        if (! empty($referenceIdsByType[CashFlow::REFERENCE_SALE])) {
            foreach (Sale::query()->whereIn('id', $referenceIdsByType[CashFlow::REFERENCE_SALE])->where('status', Sale::STATUS_CANCEL)->pluck('id') as $id) {
                $cancelledReferenceMap[CashFlow::REFERENCE_SALE . ':' . (int) $id] = true;
            }
        }
        if (! empty($referenceIdsByType[CashFlow::REFERENCE_SERVICE])) {
            foreach (Service::query()->whereIn('id', $referenceIdsByType[CashFlow::REFERENCE_SERVICE])->where('status', Service::STATUS_CANCEL)->pluck('id') as $id) {
                $cancelledReferenceMap[CashFlow::REFERENCE_SERVICE . ':' . (int) $id] = true;
            }
        }
        if (! empty($referenceIdsByType[CashFlow::REFERENCE_RENTAL])) {
            foreach (Rental::query()->whereIn('id', $referenceIdsByType[CashFlow::REFERENCE_RENTAL])->where('status', Rental::STATUS_CANCEL)->pluck('id') as $id) {
                $cancelledReferenceMap[CashFlow::REFERENCE_RENTAL . ':' . (int) $id] = true;
            }
        }
        if (! empty($referenceIdsByType[CashFlow::REFERENCE_DISTRIBUTION])) {
            foreach (Distribution::query()->whereIn('id', $referenceIdsByType[CashFlow::REFERENCE_DISTRIBUTION])->where('status', Distribution::STATUS_CANCELLED)->pluck('id') as $id) {
                $cancelledReferenceMap[CashFlow::REFERENCE_DISTRIBUTION . ':' . (int) $id] = true;
            }
        }

        $transactions = $transactions->sort(function ($a, $b) use ($cancelledReferenceMap) {
            $aDate = (string) ($a->transaction_date ?? '');
            $bDate = (string) ($b->transaction_date ?? '');
            if ($aDate !== $bDate) {
                return strcmp($aDate, $bDate);
            }

            $aRefType = (string) ($a->reference_type ?? '');
            $bRefType = (string) ($b->reference_type ?? '');
            $aRefId = (int) ($a->reference_id ?? 0);
            $bRefId = (int) ($b->reference_id ?? 0);
            $aIsCancelledRef = ! empty($cancelledReferenceMap[$aRefType . ':' . $aRefId]);
            $bIsCancelledRef = ! empty($cancelledReferenceMap[$bRefType . ':' . $bRefId]);

            if ($aIsCancelledRef && $bIsCancelledRef && $aRefType === $bRefType && $aRefId === $bRefId) {
                if (($a->type ?? '') === ($b->type ?? '')) {
                    return 0;
                }
                return ($a->type ?? '') === CashFlow::TYPE_IN ? -1 : 1;
            }

            $aTypeOrder = strtoupper((string) ($a->type ?? '')) === 'IN' ? 0 : 1;
            $bTypeOrder = strtoupper((string) ($b->type ?? '')) === 'IN' ? 0 : 1;
            if ($aTypeOrder !== $bTypeOrder) {
                return $aTypeOrder <=> $bTypeOrder;
            }

            return abs((float) ($a->amount ?? 0)) <=> abs((float) ($b->amount ?? 0));
        })->values();

        foreach ($transactions as $tx) {
            $refType = (string) ($tx->reference_type ?? '');
            $refId = (int) ($tx->reference_id ?? 0);
            $tx->is_cancel_pair = ! empty($cancelledReferenceMap[$refType . ':' . $refId])
                && in_array(($tx->type ?? ''), [CashFlow::TYPE_IN, CashFlow::TYPE_OUT], true);
        }
        $runningBalance = 0.0;
        foreach ($transactions as $tx) {
            $runningBalance += (float) $tx->amount;
            $tx->running_balance = round($runningBalance, 2);
        }

        $label = $kasKey === 'Tunai'
            ? 'Tunai'
            : (explode('|', $kasKey, 2)[0] ?? '-') . ' (' . (explode('|', $kasKey, 2)[1] ?? '-') . ')';

        // Hitung ringkasan murni dari tabel cash_flows yang sudah terfilter.
        $totalPemasukan = (float) $cashFlows
            ->where('type', CashFlow::TYPE_IN)
            ->sum(fn ($cf) => (float) $cf->amount);
        $totalPengeluaran = (float) $cashFlows
            ->where('type', CashFlow::TYPE_OUT)
            ->sum(fn ($cf) => (float) $cf->amount);
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

    /**
     * Perbandingan laba rugi antar semua gudang dan cabang.
     * Hanya Super Admin dan Admin Pusat.
     */
    public function profitLossComparison(Request $request): View
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }

        $includeExternalExpense = (int) $request->input('include_external_expense', 1) === 1;

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : Carbon::today()->startOfMonth();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : Carbon::today()->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $excludeExpenseCodes = ['SP-SVC', 'REVERSAL', 'REV'];
        $externalExpenseCategoryId = ExpenseCategory::query()
            ->where('code', 'PENGELUARAN_EKSTERNAL')
            ->value('id');
        $dateFromStr = $dateFrom->toDateString();
        $dateToStr = $dateTo->toDateString();

        $locations = [];
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        foreach ($branches as $b) {
            $locations[] = ['type' => 'branch', 'id' => $b->id, 'name' => $b->name, 'label' => __('Cabang') . ' ' . $b->name];
        }
        foreach ($warehouses as $w) {
            $locations[] = ['type' => 'warehouse', 'id' => $w->id, 'name' => $w->name, 'label' => __('Gudang') . ' ' . $w->name];
        }

        $comparisonData = [];
        foreach ($locations as $loc) {
            $isBranch = $loc['type'] === 'branch';
            $branchId = $isBranch ? $loc['id'] : null;
            $warehouseId = ! $isBranch ? $loc['id'] : null;

            $totalPemasukan = 0.0;
            $salesProfit = 0.0;
            $serviceProfit = 0.0;
            $rentalProfit = 0.0;

            if ($isBranch) {
                $salesForBranch = Sale::query()
                    ->where('status', Sale::STATUS_RELEASED)
                    ->where('branch_id', $branchId)
                    ->whereBetween('sale_date', [$dateFromStr, $dateToStr])
                    ->with('saleDetails', 'payments')
                    ->get()
                    ->filter(fn ($sale) => $sale->isPaidOff());

                $salesPaid = (float) DB::table('sale_payments')
                    ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
                    ->where('sales.status', Sale::STATUS_RELEASED)
                    ->where('sales.branch_id', $branchId)
                    ->whereBetween('sales.sale_date', [$dateFromStr, $dateToStr])
                    ->sum('sale_payments.amount');
                $salesHpp = (float) $salesForBranch->sum->total_hpp;
                $salesProfit = $salesPaid - $salesHpp;

                $servicesForBranch = Service::query()
                    ->with('serviceMaterials')
                    ->where('branch_id', $branchId)
                    ->where('status', Service::STATUS_COMPLETED)
                    ->whereBetween(DB::raw('COALESCE(exit_date, entry_date)'), [$dateFromStr, $dateToStr])
                    ->get();
                $serviceTotal = (float) $servicesForBranch->sum->total_service_price;
                $serviceMaterial = (float) $servicesForBranch->sum->materials_total_price;
                $serviceProfit = $serviceTotal - $serviceMaterial;
            }

            $rentalTotal = (float) Rental::query()
                ->where('status', Rental::STATUS_RELEASED)
                ->whereBetween('pickup_date', [$dateFromStr, $dateToStr])
                ->when($isBranch, fn ($q) => $q->where('branch_id', $branchId))
                ->when(! $isBranch, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->sum('total');
            $rentalProfit = $rentalTotal;
            $totalPemasukan += $salesProfit + $serviceProfit + $rentalProfit;

            $cfIn = (float) CashFlow::query()
                ->where('type', CashFlow::TYPE_IN)
                ->whereIn('reference_type', [CashFlow::REFERENCE_DISTRIBUTION, CashFlow::REFERENCE_OTHER])
                ->where(function ($q) {
                    $q->whereNull('income_category_id')
                        ->orWhereExists(function ($sq) {
                            $sq->select(DB::raw(1))
                                ->from('income_categories')
                                ->whereColumn('income_categories.id', 'cash_flows.income_category_id')
                                ->where('income_categories.affects_profit_loss', true);
                        });
                })
                ->whereBetween('transaction_date', [$dateFromStr, $dateToStr])
                ->when($isBranch, fn ($q) => $q->where('branch_id', $branchId))
                ->when(! $isBranch, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->sum('amount');

            $distributionHpp = (float) DB::table('distribution_details')
                ->join('distributions', 'distribution_details.distribution_id', '=', 'distributions.id')
                ->where('distribution_details.biaya_distribusi_per_unit', '>', 0)
                ->where('distributions.status', '!=', 'cancelled')
                ->whereBetween('distributions.distribution_date', [$dateFromStr, $dateToStr])
                ->when($isBranch, fn ($q) => $q->where('distributions.from_location_type', Stock::LOCATION_BRANCH)->where('distributions.from_location_id', $branchId))
                ->when(! $isBranch, fn ($q) => $q->where('distributions.from_location_type', Stock::LOCATION_WAREHOUSE)->where('distributions.from_location_id', $warehouseId))
                ->selectRaw('COALESCE(SUM(distribution_details.hpp_per_unit * distribution_details.quantity), 0) as total')
                ->value('total');
            $totalPemasukan += ($cfIn - $distributionHpp);

            $totalPengeluaran = (float) CashFlow::query()
                ->where('type', CashFlow::TYPE_OUT)
                ->whereBetween('transaction_date', [$dateFromStr, $dateToStr])
                ->where(function ($q) {
                    $q->whereNull('expense_category_id')
                        ->orWhereExists(function ($sq) {
                            $sq->select(DB::raw(1))
                                ->from('expense_categories')
                                ->whereColumn('expense_categories.id', 'cash_flows.expense_category_id')
                                ->where('expense_categories.affects_profit_loss', true);
                        });
                })
                ->where(function ($q) use ($excludeExpenseCodes) {
                    $q->whereNull('expense_category_id')
                        ->orWhereNotIn('expense_category_id', function ($sub) use ($excludeExpenseCodes) {
                            $sub->select('id')->from('expense_categories')->whereIn('code', $excludeExpenseCodes);
                        });
                })
                ->when($isBranch, fn ($q) => $q->where('branch_id', $branchId))
                ->when(! $isBranch, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->sum('amount');

            if (! $includeExternalExpense && $externalExpenseCategoryId) {
                $externalExpenseAmount = (float) CashFlow::query()
                    ->where('type', CashFlow::TYPE_OUT)
                    ->where('expense_category_id', $externalExpenseCategoryId)
                    ->where(function ($q) {
                        $q->whereNull('expense_category_id')
                            ->orWhereExists(function ($sq) {
                                $sq->select(DB::raw(1))
                                    ->from('expense_categories')
                                    ->whereColumn('expense_categories.id', 'cash_flows.expense_category_id')
                                    ->where('expense_categories.affects_profit_loss', true);
                            });
                    })
                    ->whereBetween('transaction_date', [$dateFromStr, $dateToStr])
                    ->when($isBranch, fn ($q) => $q->where('branch_id', $branchId))
                    ->when(! $isBranch, fn ($q) => $q->where('warehouse_id', $warehouseId))
                    ->sum('amount');

                $totalPengeluaran -= $externalExpenseAmount;
            }

            $danaTukarTambah = 0.0;
            if ($isBranch) {
                $danaTukarTambah = (float) DB::table('sale_trade_ins')
                    ->join('sales', 'sale_trade_ins.sale_id', '=', 'sales.id')
                    ->where('sales.status', Sale::STATUS_RELEASED)
                    ->where('sales.branch_id', $branchId)
                    ->whereBetween('sales.sale_date', [$dateFromStr, $dateToStr])
                    ->sum('sale_trade_ins.trade_in_value');
            }

            $bebanBarangRusak = (float) DamagedGood::query()
                ->whereNull('reactivated_at')
                ->whereBetween('recorded_date', [$dateFromStr, $dateToStr])
                ->whereHas('productUnit', fn ($q) => $q
                    ->where('location_type', $isBranch ? Stock::LOCATION_BRANCH : Stock::LOCATION_WAREHOUSE)
                    ->where('location_id', $isBranch ? $branchId : $warehouseId))
                ->sum('harga_hpp');

            $comparisonData[] = [
                'location' => $loc,
                'total_pemasukan' => $totalPemasukan,
                'total_pengeluaran' => $totalPengeluaran,
                'dana_tukar_tambah' => $danaTukarTambah,
                'beban_barang_rusak' => $bebanBarangRusak,
                'laba_bersih' => $totalPemasukan - $totalPengeluaran - $bebanBarangRusak,
            ];
        }

        return view('finance.profit-loss-comparison', [
            'dateFrom' => $dateFromStr,
            'dateTo' => $dateToStr,
            'comparisonData' => $comparisonData,
            'locations' => $locations,
            'includeExternalExpense' => $includeExternalExpense,
        ]);
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

