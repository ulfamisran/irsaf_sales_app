<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashOutRequest;
use App\Http\Requests\ManualIncomeRequest;
use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\Role;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\PaymentMethod;
use App\Models\Warehouse;
use App\Services\KasBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CashFlowController extends Controller
{
    private function resolveExternalExpenseCategoryId(): ?int
    {
        $id = ExpenseCategory::query()
            ->where(function ($q) {
                $q->where('code', 'PENGELUARAN_EKSTERNAL')
                    ->orWhere('name', 'Pengeluaran Eksternal');
            })
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function outIndex(Request $request): View
    {
        $user = $request->user();

        $query = CashFlow::with(['user', 'branch', 'warehouse', 'expenseCategory', 'paymentMethod'])
            ->where('type', CashFlow::TYPE_OUT)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');
        // Exclude Reversal (transaksi cancel) - tidak dihitung & ditampilkan di pengeluaran
        $reversalCategoryId = ExpenseCategory::where('code', 'REVERSAL')->value('id');
        if ($reversalCategoryId) {
            $query->where('expense_category_id', '!=', $reversalCategoryId);
        }
        // Exclude Pengeluaran Eksternal dari halaman Pengeluaran Dana (arus kas umum)
        $externalExpenseCategoryId = $this->resolveExternalExpenseCategoryId();
        if ($externalExpenseCategoryId) {
            $query->where('expense_category_id', '!=', $externalExpenseCategoryId);
        }
        $query->where(function ($q) {
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
        });

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;
        $lockedBranchId = null;
        $lockedWarehouseId = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $lockedBranchId = (int) $user->branch_id;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $lockedWarehouseId = (int) $user->warehouse_id;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            } elseif ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
        }

        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $expenses = $query->paginate(20)->withQueryString();

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id ? Branch::whereKey($user->branch_id)->get(['id', 'name']) : collect());
        $warehouses = $user->isSuperAdminOrAdminPusat()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id ? Warehouse::whereKey($user->warehouse_id)->get(['id', 'name']) : collect());

        $expenseCategories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        if ($externalExpenseCategoryId) {
            $expenseCategories = $expenseCategories->where('id', '!=', $externalExpenseCategoryId)->values();
        }

        $totalOut = (float) (clone $query)->sum('amount');
        $pmBranchId = $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id
            ? (int) $user->branch_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id') ? (int) $request->branch_id : null);
        $pmWarehouseId = $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id
            ? (int) $user->warehouse_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('warehouse_id') ? (int) $request->warehouse_id : null);
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);
        $paymentMethodTotals = (clone $query)
            ->reorder()
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id');

        return view('cash-flows.out-index', compact('expenses', 'branches', 'warehouses', 'canFilterLocation', 'filterLocked', 'locationLabel', 'lockedBranchId', 'lockedWarehouseId', 'expenseCategories', 'totalOut', 'paymentMethods', 'paymentMethodTotals'));
    }

    public function outExternalIndex(Request $request): View
    {
        $user = $request->user();
        $externalExpenseCategoryId = $this->resolveExternalExpenseCategoryId();

        $query = CashFlow::with(['user', 'branch', 'warehouse', 'expenseCategory', 'paymentMethod'])
            ->where('type', CashFlow::TYPE_OUT)
            ->when($externalExpenseCategoryId, fn ($q) => $q->where('expense_category_id', $externalExpenseCategoryId))
            ->when(! $externalExpenseCategoryId, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        // Exclude Reversal (transaksi cancel) - tidak dihitung & ditampilkan di pengeluaran
        $reversalCategoryId = ExpenseCategory::where('code', 'REVERSAL')->value('id');
        if ($reversalCategoryId) {
            $query->where('expense_category_id', '!=', $reversalCategoryId);
        }

        $query->where(function ($q) {
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
        });

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;
        $lockedBranchId = null;
        $lockedWarehouseId = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $lockedBranchId = (int) $user->branch_id;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $lockedWarehouseId = (int) $user->warehouse_id;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            } elseif ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $expenses = $query->paginate(20)->withQueryString();

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id ? Branch::whereKey($user->branch_id)->get(['id', 'name']) : collect());
        $warehouses = $user->isSuperAdminOrAdminPusat()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id ? Warehouse::whereKey($user->warehouse_id)->get(['id', 'name']) : collect());

        $totalOut = (float) (clone $query)->sum('amount');
        $pmBranchId = $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id
            ? (int) $user->branch_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id') ? (int) $request->branch_id : null);
        $pmWarehouseId = $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id
            ? (int) $user->warehouse_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('warehouse_id') ? (int) $request->warehouse_id : null);

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        $paymentMethodTotals = (clone $query)
            ->reorder()
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id');

        return view('cash-flows.out-external-index', compact(
            'expenses',
            'branches',
            'warehouses',
            'canFilterLocation',
            'filterLocked',
            'locationLabel',
            'lockedBranchId',
            'lockedWarehouseId',
            'totalOut',
            'paymentMethods',
            'paymentMethodTotals'
        ));
    }

    public function createOutExternal(Request $request): View
    {
        $user = $request->user();

        $externalExpenseCategory = ExpenseCategory::query()
            ->where(function ($q) {
                $q->where('code', 'PENGELUARAN_EKSTERNAL')
                    ->orWhere('name', 'Pengeluaran Eksternal');
            })
            ->first();

        if (! $externalExpenseCategory) {
            abort(404, __('Kategori Pengeluaran Eksternal belum tersedia di database. Silakan buat dulu di Pengaturan Kategori Pengeluaran.'));
        }

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        $pmBranchId = $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id ? (int) $user->branch_id : null;
        $pmWarehouseId = $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id ? (int) $user->warehouse_id : null;

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();

        $branchIds = $branches->pluck('id')->toArray();
        $warehouseIds = $warehouses->pluck('id')->toArray();

        $saldoMapBranch = (new KasBalanceService)->getSaldoPerBranchAndPm($branchIds);
        $saldoMapWarehouse = (new KasBalanceService)->getSaldoPerWarehouseAndPm($warehouseIds);

        return view('cash-flows.create-out-external', compact(
            'branches',
            'warehouses',
            'externalExpenseCategory',
            'paymentMethods',
            'saldoMapBranch',
            'saldoMapWarehouse'
        ));
    }

    public function showOut(Request $request, CashFlow $cashFlow): View|RedirectResponse
    {
        if ($cashFlow->type !== CashFlow::TYPE_OUT) {
            abort(404);
        }
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                if ($cashFlow->branch_id != $user->branch_id) {
                    abort(403);
                }
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                if ($cashFlow->warehouse_id != $user->warehouse_id) {
                    abort(403);
                }
            } else {
                abort(403);
            }
        }
        $cashFlow->load(['user', 'branch', 'warehouse', 'expenseCategory', 'paymentMethod']);
        $referenceLabel = null;
        $referenceUrl = null;
        if ($cashFlow->reference_type && $cashFlow->reference_id) {
            $referenceLabel = match ($cashFlow->reference_type) {
                CashFlow::REFERENCE_PURCHASE => __('Pembelian #:id', ['id' => $cashFlow->reference_id]),
                CashFlow::REFERENCE_SALE => __('Penjualan #:id', ['id' => $cashFlow->reference_id]),
                CashFlow::REFERENCE_SERVICE => __('Servis #:id', ['id' => $cashFlow->reference_id]),
                CashFlow::REFERENCE_DISTRIBUTION => __('Distribusi #:id', ['id' => $cashFlow->reference_id]),
                default => $cashFlow->reference_type . ' #' . $cashFlow->reference_id,
            };
            if (in_array($cashFlow->reference_type, [CashFlow::REFERENCE_PURCHASE, CashFlow::REFERENCE_SALE, CashFlow::REFERENCE_SERVICE])) {
                $referenceUrl = match ($cashFlow->reference_type) {
                    CashFlow::REFERENCE_PURCHASE => route('purchases.show', $cashFlow->reference_id),
                    CashFlow::REFERENCE_SALE => route('sales.show', $cashFlow->reference_id),
                    CashFlow::REFERENCE_SERVICE => route('services.show', $cashFlow->reference_id),
                    default => null,
                };
            }
            if ($cashFlow->reference_type === CashFlow::REFERENCE_DISTRIBUTION) {
                $referenceUrl = route('stock-mutations.index');
            }
        }

        return view('cash-flows.out-show', compact('cashFlow', 'referenceLabel', 'referenceUrl'));
    }

    public function inIndex(Request $request): View
    {
        $user = $request->user();

        $query = CashFlow::with(['user', 'branch', 'warehouse', 'incomeCategory'])
            ->where('type', CashFlow::TYPE_IN)
            ->where('reference_type', CashFlow::REFERENCE_OTHER)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');
        $query->where(function ($q) {
            $q->whereNull('reference_type')
                ->orWhere('reference_type', '!=', CashFlow::REFERENCE_RENTAL)
                ->orWhereIn('reference_id', function ($sq) {
                    $sq->select('id')
                        ->from('rentals')
                        ->where('status', '!=', 'cancel');
                });
        });

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;

        $lockedBranchId = null;
        $lockedWarehouseId = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $lockedBranchId = (int) $user->branch_id;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $lockedWarehouseId = (int) $user->warehouse_id;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            } elseif ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }
        if ($request->filled('income_category_id')) {
            $query->where('income_category_id', $request->income_category_id);
        }

        $incomes = $query->paginate(20)->withQueryString();

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id ? Branch::whereKey($user->branch_id)->get(['id', 'name']) : collect());
        $warehouses = $user->isSuperAdminOrAdminPusat()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id ? Warehouse::whereKey($user->warehouse_id)->get(['id', 'name']) : collect());

        $totalIn = (float) (clone $query)->sum('amount');
        $pmBranchId = $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id
            ? (int) $user->branch_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id') ? (int) $request->branch_id : $lockedBranchId ?? null);
        $pmWarehouseId = $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id
            ? (int) $user->warehouse_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('warehouse_id') ? (int) $request->warehouse_id : $lockedWarehouseId ?? null);
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);
        $paymentMethodTotals = (clone $query)
            ->reorder()
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id');

        $incomeCategories = IncomeCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('cash-flows.in-index', compact('incomes', 'branches', 'warehouses', 'canFilterLocation', 'filterLocked', 'locationLabel', 'lockedBranchId', 'lockedWarehouseId', 'totalIn', 'paymentMethods', 'paymentMethodTotals', 'incomeCategories'));
    }

    public function index(Request $request): View
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
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('warehouse_id')) {
                $warehouseId = (int) $request->warehouse_id;
            } elseif ($request->filled('branch_id')) {
                $branchId = (int) $request->branch_id;
            }
        }

        $applyFilters = function ($q) use ($user, $request, $branchId, $warehouseId) {
            $q->where(function ($qq) {
                $qq->whereNull('reference_type')
                    ->orWhere('reference_type', '!=', CashFlow::REFERENCE_RENTAL)
                    ->orWhereIn('reference_id', function ($sq) {
                        $sq->select('id')->from('rentals')->where('status', '!=', 'cancel');
                    })
                    ->orWhere(function ($sq) {
                        $sq->where('reference_type', CashFlow::REFERENCE_RENTAL)
                            ->where('type', CashFlow::TYPE_OUT);
                    });
            });
            if ($branchId) {
                $q->where('branch_id', $branchId);
            }
            if ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }
            if ($request->filled('type')) {
                $q->where('type', $request->type);
            }
            if ($request->filled('date_from')) {
                $q->whereDate('transaction_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $q->whereDate('transaction_date', '<=', $request->date_to);
            }
            if ($request->filled('payment_method_id')) {
                $pmIds = $this->resolvePaymentMethodIdsForFilter((int) $request->payment_method_id, $branchId, $warehouseId);
                if (! empty($pmIds)) {
                    $q->whereIn('payment_method_id', $pmIds);
                } else {
                    $q->where('payment_method_id', $request->payment_method_id);
                }
            }
        };

        $query = CashFlow::with(['user', 'branch', 'warehouse', 'expenseCategory', 'incomeCategory', 'paymentMethod']);
        $applyFilters($query);
        $query->orderBy('transaction_date')->orderBy('id');

        $cashFlowsRaw = $query->limit(1000)->get();
        $runningBalance = 0.0;
        foreach ($cashFlowsRaw as $cf) {
            $runningBalance += $cf->type === CashFlow::TYPE_IN ? (float) $cf->amount : -(float) $cf->amount;
            $cf->running_balance = round($runningBalance, 2);
        }
        $orderDirection = $request->input('order', 'bawah_ke_atas');
        $cashFlows = $orderDirection === 'atas_ke_bawah'
            ? $cashFlowsRaw->values()
            : $cashFlowsRaw->reverse()->values();

        $summaryBase = CashFlow::query();
        $applyFilters($summaryBase);
        $summary = (clone $summaryBase)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $totalTradeIn = (float) DB::table('sale_trade_ins')
            ->join('sales', 'sale_trade_ins.sale_id', '=', 'sales.id')
            ->where('sales.status', \App\Models\Sale::STATUS_RELEASED)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('sales.sale_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('sales.sale_date', '<=', $request->date_to))
            ->sum('sale_trade_ins.trade_in_value');

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($branchId ? Branch::whereKey($branchId)->get(['id', 'name']) : collect());
        $warehouses = $user->isSuperAdminOrAdminPusat()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : ($warehouseId ? Warehouse::whereKey($warehouseId)->get(['id', 'name']) : collect());

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($branchId, $warehouseId)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        return view('cash-flows.index', compact('cashFlows', 'summary', 'branches', 'warehouses', 'paymentMethods', 'canFilterLocation', 'filterLocked', 'locationLabel', 'lockedBranchId', 'lockedWarehouseId', 'totalTradeIn', 'branchId', 'warehouseId', 'orderDirection'));
    }

    /**
     * Resolve payment method IDs for filter. "Tunai" may map to multiple PMs (jenis=tunai or bank+rek empty).
     */
    private function resolvePaymentMethodIdsForFilter(int $paymentMethodId, ?int $branchId, ?int $warehouseId): array
    {
        $pm = PaymentMethod::find($paymentMethodId);
        if (! $pm) {
            return [];
        }
        $jenis = strtolower(trim((string) ($pm->jenis_pembayaran ?? '')));
        $bank = trim((string) ($pm->nama_bank ?? ''));
        $rek = trim((string) ($pm->no_rekening ?? ''));
        if (str_contains($jenis, 'tunai') || ($bank === '' && $rek === '')) {
            return PaymentMethod::query()
                ->where('is_active', true)
                ->forLocation($branchId, $warehouseId)
                ->get()
                ->filter(fn ($p) => str_contains(strtolower(trim((string) ($p->jenis_pembayaran ?? ''))), 'tunai')
                    || (trim((string) ($p->nama_bank ?? '')) === '' && trim((string) ($p->no_rekening ?? '')) === ''))
                ->pluck('id')
                ->toArray();
        }

        return [$paymentMethodId];
    }

    public function createOut(Request $request): View
    {
        $user = $request->user();
        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        $expenseCategories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $pmBranchId = $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id ? (int) $user->branch_id : null;
        $pmWarehouseId = $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id ? (int) $user->warehouse_id : null;
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();

        if (! $user->isSuperAdmin() && ! $user->branch_id) {
            // allow staff gudang without branch
        }

        $branchIds = $branches->pluck('id')->toArray();
        $warehouseIds = $warehouses->pluck('id')->toArray();
        $saldoMapBranch = (new KasBalanceService)->getSaldoPerBranchAndPm($branchIds);
        $saldoMapWarehouse = (new KasBalanceService)->getSaldoPerWarehouseAndPm($warehouseIds);

        return view('cash-flows.create-out', compact('branches', 'warehouses', 'expenseCategories', 'paymentMethods', 'saldoMapBranch', 'saldoMapWarehouse'));
    }

    public function createIn(Request $request): View
    {
        $user = $request->user();

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->branch_id ? Branch::whereKey($user->branch_id)->get(['id', 'name']) : collect());
        $warehouses = $user->isSuperAdminOrAdminPusat()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : ($user->warehouse_id ? Warehouse::whereKey($user->warehouse_id)->get(['id', 'name']) : collect());

        $pmBranchId = $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id ? (int) $user->branch_id : null;
        $pmWarehouseId = $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id ? (int) $user->warehouse_id : null;
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();

        $incomeCategories = IncomeCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('cash-flows.create-in', compact('branches', 'warehouses', 'paymentMethods', 'incomeCategories'));
    }

    public function storeOut(CashOutRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $branchId = $user->isSuperAdmin()
            ? (isset($validated['branch_id']) ? (int) $validated['branch_id'] : null)
            : (int) $user->branch_id;
        $warehouseId = null;

        if (! $branchId) {
            return redirect()->back()->withInput()->withErrors(['branch_id' => __('Cabang wajib dipilih.')]);
        }

        $items = $validated['items'];
        $totalAmount = collect($items)->sum(fn ($item) => (float) $item['amount']);

        $saldo = (new KasBalanceService)->getSaldoForLocation(
            $warehouseId ? 'warehouse' : 'branch',
            $warehouseId ?: $branchId,
            (int) $validated['payment_method_id']
        );

        if ($totalAmount > $saldo) {
            return redirect()->back()->withInput()->withErrors([
                'items' => __('Total pengeluaran (Rp :total) melebihi saldo tersedia (Rp :saldo).', [
                    'total' => number_format($totalAmount, 0, ',', '.'),
                    'saldo' => number_format($saldo, 0, ',', '.'),
                ]),
            ]);
        }

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                CashFlow::create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'type' => CashFlow::TYPE_OUT,
                    'amount' => (float) $item['amount'],
                    'description' => $item['name'],
                    'reference_type' => CashFlow::REFERENCE_EXPENSE,
                    'reference_id' => null,
                    'expense_category_id' => (int) $item['expense_category_id'],
                    'payment_method_id' => (int) $validated['payment_method_id'],
                    'transaction_date' => $validated['transaction_date'],
                    'user_id' => $user->id,
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cash-flows.out.index')->with('success', __('Pengeluaran berhasil dicatat.')); 
    }

    public function storeOutExternal(CashOutRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $externalExpenseCategoryId = $this->resolveExternalExpenseCategoryId();
        if (! $externalExpenseCategoryId) {
            return redirect()->back()->withInput()->withErrors([
                'items' => __('Kategori Pengeluaran Eksternal tidak ditemukan. Jalankan migrasi kategori terlebih dahulu.'),
            ]);
        }

        $branchId = $user->isSuperAdmin()
            ? (isset($validated['branch_id']) ? (int) $validated['branch_id'] : null)
            : (int) $user->branch_id;
        $warehouseId = null;

        if (! $branchId) {
            return redirect()->back()->withInput()->withErrors(['branch_id' => __('Cabang wajib dipilih.')]);
        }

        $items = $validated['items'];
        $totalAmount = collect($items)->sum(fn ($item) => (float) $item['amount']);

        $saldo = (new KasBalanceService)->getSaldoForLocation(
            $warehouseId ? 'warehouse' : 'branch',
            $warehouseId ?: $branchId,
            (int) $validated['payment_method_id']
        );

        if ($totalAmount > $saldo) {
            return redirect()->back()->withInput()->withErrors([
                'items' => __('Total pengeluaran (Rp :total) melebihi saldo tersedia (Rp :saldo).', [
                    'total' => number_format($totalAmount, 0, ',', '.'),
                    'saldo' => number_format($saldo, 0, ',', '.'),
                ]),
            ]);
        }

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                CashFlow::create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'type' => CashFlow::TYPE_OUT,
                    'amount' => (float) $item['amount'],
                    'description' => $item['name'],
                    'reference_type' => CashFlow::REFERENCE_EXPENSE,
                    'reference_id' => null,
                    // Paksa selalu menggunakan kategori eksternal
                    'expense_category_id' => $externalExpenseCategoryId,
                    'payment_method_id' => (int) $validated['payment_method_id'],
                    'transaction_date' => $validated['transaction_date'],
                    'user_id' => $user->id,
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cash-flows.out.external.index')->with('success', __('Pengeluaran dana eksternal berhasil dicatat.'));
    }

    public function storeIn(ManualIncomeRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $branchId = null;
        $warehouseId = null;

        if ($validated['location_type'] === 'warehouse' && ! empty($validated['warehouse_id'])) {
            $warehouseId = (int) $validated['warehouse_id'];
        } elseif ($validated['location_type'] === 'branch' && ! empty($validated['branch_id'])) {
            $branchId = (int) $validated['branch_id'];
        }

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $branchId = (int) $user->branch_id;
                $warehouseId = null;
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $warehouseId = (int) $user->warehouse_id;
                $branchId = null;
            }
        }

        if (! $branchId && ! $warehouseId) {
            return redirect()->back()->withInput()->withErrors(['location_type' => __('Lokasi (Cabang atau Gudang) wajib dipilih.')]);
        }

        CashFlow::create([
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'type' => CashFlow::TYPE_IN,
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'reference_type' => CashFlow::REFERENCE_OTHER,
            'reference_id' => null,
            'expense_category_id' => null,
            'income_category_id' => (int) $validated['income_category_id'],
            'payment_method_id' => (int) $validated['payment_method_id'],
            'transaction_date' => $validated['transaction_date'],
            'user_id' => $user->id,
        ]);

        return redirect()->route('cash-flows.in.index')->with('success', __('Income recorded successfully.'));
    }
}
