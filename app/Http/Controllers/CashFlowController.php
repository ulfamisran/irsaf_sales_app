<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashOutRequest;
use App\Http\Requests\ManualIncomeRequest;
use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Services\KasBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashFlowController extends Controller
{
    public function outIndex(Request $request): View
    {
        $user = $request->user();

        $query = CashFlow::with(['user', 'branch', 'expenseCategory'])
            ->where('type', CashFlow::TYPE_OUT)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if (! $user->isSuperAdmin()) {
            if (! $user->branch_id) {
                abort(403, __('User branch not set.'));
            }
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
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

        $expenseCategories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $totalOut = (float) (clone $query)->sum('amount');

        return view('cash-flows.out-index', compact('expenses', 'branches', 'expenseCategories', 'totalOut'));
    }

    public function inIndex(Request $request): View
    {
        $user = $request->user();

        $query = CashFlow::with(['user', 'branch'])
            ->where('type', CashFlow::TYPE_IN)
            ->where('reference_type', CashFlow::REFERENCE_OTHER)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if (! $user->isSuperAdmin()) {
            if (! $user->branch_id) {
                abort(403, __('User branch not set.'));
            }
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
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

        $totalIn = (float) (clone $query)->sum('amount');

        return view('cash-flows.in-index', compact('incomes', 'branches', 'totalIn'));
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = CashFlow::with(['user', 'branch'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if (! $user->isSuperAdmin()) {
            if (! $user->branch_id) {
                abort(403, __('User branch not set.'));
            }
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
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

        $summary = CashFlow::selectRaw('type, SUM(amount) as total')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($user->isSuperAdmin() && $request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->groupBy('type')
            ->pluck('total', 'type');

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);

        return view('cash-flows.index', compact('cashFlows', 'summary', 'branches'));
    }

    public function createOut(Request $request): View
    {
        $user = $request->user();
        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);

        $expenseCategories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();

        if (! $user->isSuperAdmin() && ! $user->branch_id) {
            abort(403, __('User branch not set.'));
        }

        $branchIds = $branches->pluck('id')->toArray();
        $saldoMap = (new KasBalanceService)->getSaldoPerBranchAndPm($branchIds);

        return view('cash-flows.create-out', compact('branches', 'expenseCategories', 'paymentMethods', 'saldoMap'));
    }

    public function createIn(Request $request): View
    {
        $user = $request->user();

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);

        if (! $user->isSuperAdmin() && ! $user->branch_id) {
            abort(403, __('User branch not set.'));
        }

        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();

        return view('cash-flows.create-in', compact('branches', 'paymentMethods'));
    }

    public function storeOut(CashOutRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $branchId = $user->isSuperAdmin()
            ? (isset($validated['branch_id']) ? (int) $validated['branch_id'] : null)
            : (int) $user->branch_id;

        if (! $branchId) {
            return redirect()->back()->withInput()->withErrors(['branch_id' => __('Cabang wajib dipilih.')]);
        }

        $saldo = (new KasBalanceService)->getSaldo($branchId, (int) $validated['payment_method_id']);
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

        if (! $branchId) {
            abort(403, __('Branch is required.'));
        }

        CashFlow::create([
            'branch_id' => $branchId,
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
