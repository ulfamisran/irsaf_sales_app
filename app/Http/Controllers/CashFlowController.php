<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashOutRequest;
use App\Http\Requests\ManualIncomeRequest;
use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\ExpenseCategory;
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

        if (! $user->isSuperAdmin()) {
            if ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }
        } else {
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

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        $expenseCategories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $totalOut = (float) (clone $query)->sum('amount');
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'no_rekening']);
        $paymentMethodTotals = (clone $query)
            ->reorder()
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id');

        return view('cash-flows.out-index', compact('expenses', 'branches', 'warehouses', 'expenseCategories', 'totalOut', 'paymentMethods', 'paymentMethodTotals'));
    }

    public function inIndex(Request $request): View
    {
        $user = $request->user();

        $query = CashFlow::with(['user', 'branch', 'warehouse'])
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

        if (! $user->isSuperAdmin()) {
            if ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }
        } else {
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $incomes = $query->paginate(20)->withQueryString();

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        $totalIn = (float) (clone $query)->sum('amount');
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'no_rekening']);
        $paymentMethodTotals = (clone $query)
            ->reorder()
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id');

        return view('cash-flows.in-index', compact('incomes', 'branches', 'warehouses', 'totalIn', 'paymentMethods', 'paymentMethodTotals'));
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = CashFlow::with(['user', 'branch', 'warehouse'])
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

        if (! $user->isSuperAdmin()) {
            if ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }
        } else {
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
            ->when(! $user->isSuperAdmin() && $user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($user->isSuperAdmin() && $request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($user->isSuperAdmin() && $request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->date_to));

        $summary = (clone $summaryBase)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $totalTradeIn = (float) DB::table('sale_trade_ins')
            ->join('sales', 'sale_trade_ins.sale_id', '=', 'sales.id')
            ->where('sales.status', \App\Models\Sale::STATUS_RELEASED)
            ->when(! $user->isSuperAdmin() && $user->branch_id, fn ($q) => $q->where('sales.branch_id', $user->branch_id))
            ->when($user->isSuperAdmin() && $request->filled('branch_id'), fn ($q) => $q->where('sales.branch_id', $request->branch_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('sales.sale_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('sales.sale_date', '<=', $request->date_to))
            ->sum('sale_trade_ins.trade_in_value');

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return view('cash-flows.index', compact('cashFlows', 'summary', 'branches', 'warehouses', 'totalTradeIn'));
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

        $paymentMethods = PaymentMethod::where('is_active', true)
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

        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();

        return view('cash-flows.create-in', compact('branches', 'warehouses', 'paymentMethods'));
    }

    public function storeOut(CashOutRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $branchId = $user->isSuperAdmin()
            ? (isset($validated['branch_id']) ? (int) $validated['branch_id'] : null)
            : (int) $user->branch_id;
        $warehouseId = $user->isSuperAdmin()
            ? (isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null)
            : null;

        if (! $branchId && ! $warehouseId) {
            return redirect()->back()->withInput()->withErrors(['branch_id' => __('Cabang/Gudang wajib dipilih.')]);
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
        $warehouseId = $user->isSuperAdmin()
            ? (isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null)
            : null;

        if (! $branchId && ! $warehouseId) {
            abort(403, __('Branch/Warehouse is required.'));
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
            'payment_method_id' => (int) $validated['payment_method_id'],
            'transaction_date' => $validated['transaction_date'],
            'user_id' => $user->id,
        ]);

        return redirect()->route('cash-flows.in.index')->with('success', __('Income recorded successfully.'));
    }
}
