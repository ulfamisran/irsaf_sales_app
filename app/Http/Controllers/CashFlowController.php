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
    public function outIndex(Request $request): View
    {
        $user = $request->user();

        $query = CashFlow::with(['user', 'branch', 'warehouse', 'expenseCategory'])
            ->where('type', CashFlow::TYPE_OUT)
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

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
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
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'no_rekening']);
        $paymentMethodTotals = (clone $query)
            ->reorder()
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id');

        return view('cash-flows.out-index', compact('expenses', 'branches', 'warehouses', 'canFilterLocation', 'filterLocked', 'locationLabel', 'expenseCategories', 'totalOut', 'paymentMethods', 'paymentMethodTotals'));
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

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('branch_id')) {
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
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'no_rekening']);
        $paymentMethodTotals = (clone $query)
            ->reorder()
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id');

        $incomeCategories = IncomeCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('cash-flows.in-index', compact('incomes', 'branches', 'warehouses', 'canFilterLocation', 'filterLocked', 'locationLabel', 'totalIn', 'paymentMethods', 'paymentMethodTotals', 'incomeCategories'));
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = CashFlow::with(['user', 'branch', 'warehouse', 'expenseCategory', 'incomeCategory'])
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

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $cashFlows = $query->paginate(20)->withQueryString();

        $summaryBase = CashFlow::query()
            ->when($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id, fn ($q) => $q->where('warehouse_id', $user->warehouse_id))
            ->when($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($user->isSuperAdminOrAdminPusat() && $request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->date_to));

        $summary = (clone $summaryBase)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $totalTradeIn = (float) DB::table('sale_trade_ins')
            ->join('sales', 'sale_trade_ins.sale_id', '=', 'sales.id')
            ->where('sales.status', \App\Models\Sale::STATUS_RELEASED)
            ->when($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id, fn ($q) => $q->where('sales.branch_id', $user->branch_id))
            ->when($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id'), fn ($q) => $q->where('sales.branch_id', $request->branch_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('sales.sale_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('sales.sale_date', '<=', $request->date_to))
            ->sum('sale_trade_ins.trade_in_value');

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id ? Branch::whereKey($user->branch_id)->get(['id', 'name']) : collect());
        $warehouses = $user->isSuperAdminOrAdminPusat()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id ? Warehouse::whereKey($user->warehouse_id)->get(['id', 'name']) : collect());

        return view('cash-flows.index', compact('cashFlows', 'summary', 'branches', 'warehouses', 'canFilterLocation', 'filterLocked', 'locationLabel', 'totalTradeIn'));
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

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        if (! $user->isSuperAdmin() && ! $user->branch_id) {
            // allow staff gudang without branch
        }

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

        $saldo = (new KasBalanceService)->getSaldoForLocation(
            $warehouseId ? 'warehouse' : 'branch',
            $warehouseId ?: $branchId,
            (int) $validated['payment_method_id']
        );
        $amount = (float) $validated['amount'];
        if ($amount > $saldo) {
            return redirect()->back()->withInput()->withErrors([
                'amount' => __('Saldo tidak mencukupi. Saldo tersedia: Rp :saldo', [
                    'saldo' => number_format($saldo, 0, ',', '.'),
                ]),
            ]);
        }

        CashFlow::create([
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'type' => CashFlow::TYPE_OUT,
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'reference_type' => CashFlow::REFERENCE_EXPENSE,
            'reference_id' => null,
            'expense_category_id' => $validated['expense_category_id'],
            'payment_method_id' => (int) $validated['payment_method_id'],
            'transaction_date' => $validated['transaction_date'],
            'user_id' => $user->id,
        ]);

        return redirect()->route('cash-flows.index')->with('success', __('Expense recorded successfully.'));
    }

    public function storeIn(ManualIncomeRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $branchId = $user->isSuperAdmin()
            ? (isset($validated['branch_id']) ? (int) $validated['branch_id'] : null)
            : (int) $user->branch_id;
        $warehouseId = null;

        if (! $branchId) {
            abort(403, __('Cabang wajib dipilih.'));
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
