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
use App\Models\SalePayment;
use App\Models\Warehouse;
use App\Services\KasBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function editOut(Request $request, CashFlow $cashFlow): View
    {
        if ($cashFlow->type !== CashFlow::TYPE_OUT) {
            abort(404);
        }

        $externalExpenseCategoryId = $this->resolveExternalExpenseCategoryId();
        if ($externalExpenseCategoryId && (int) $cashFlow->expense_category_id === $externalExpenseCategoryId) {
            abort(404);
        }

        $reversalCategoryId = ExpenseCategory::where('code', 'REVERSAL')->value('id');
        if ($reversalCategoryId && (int) $cashFlow->expense_category_id === (int) $reversalCategoryId) {
            abort(404);
        }

        $cashFlow->load(['branch', 'warehouse', 'expenseCategory', 'paymentMethod', 'user']);

        $locationType = $cashFlow->warehouse_id ? 'warehouse' : 'branch';
        $locationId = $cashFlow->warehouse_id ? (int) $cashFlow->warehouse_id : (int) $cashFlow->branch_id;
        $pmBranchId = $locationType === 'branch' ? $locationId : null;
        $pmWarehouseId = $locationType === 'warehouse' ? $locationId : null;

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();

        $locationLabel = $cashFlow->warehouse_id
            ? __('Gudang') . ': ' . ($cashFlow->warehouse?->name ?? '#' . $cashFlow->warehouse_id)
            : __('Cabang') . ': ' . ($cashFlow->branch?->name ?? '#' . $cashFlow->branch_id);

        return view('cash-flows.out-edit', compact('cashFlow', 'paymentMethods', 'locationLabel'));
    }

    public function updateOut(Request $request, CashFlow $cashFlow): RedirectResponse
    {
        if ($cashFlow->type !== CashFlow::TYPE_OUT) {
            abort(404);
        }

        $externalExpenseCategoryId = $this->resolveExternalExpenseCategoryId();
        if ($externalExpenseCategoryId && (int) $cashFlow->expense_category_id === $externalExpenseCategoryId) {
            abort(404);
        }

        $reversalCategoryId = ExpenseCategory::where('code', 'REVERSAL')->value('id');
        if ($reversalCategoryId && (int) $cashFlow->expense_category_id === (int) $reversalCategoryId) {
            abort(404);
        }

        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
        ]);

        if (! $cashFlow->payment_method_id) {
            return redirect()->back()->withInput()->withErrors([
                'payment_method_id' => __('Metode pembayaran tidak tersedia untuk transaksi ini.'),
            ]);
        }

        $newAmount = (float) $validated['amount'];
        $oldAmount = (float) $cashFlow->amount;
        $oldPmId = (int) $cashFlow->payment_method_id;
        $newPmId = (int) $validated['payment_method_id'];

        $locationType = $cashFlow->warehouse_id ? 'warehouse' : 'branch';
        $locationId = $cashFlow->warehouse_id ? (int) $cashFlow->warehouse_id : (int) $cashFlow->branch_id;
        $pmBranchId = $locationType === 'branch' ? $locationId : null;
        $pmWarehouseId = $locationType === 'warehouse' ? $locationId : null;

        $paymentMethodAllowed = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->whereKey($newPmId)
            ->exists();
        if (! $paymentMethodAllowed) {
            return redirect()->back()->withInput()->withErrors([
                'payment_method_id' => __('Sumber dana tidak sesuai lokasi transaksi.'),
            ]);
        }

        // Catatan: validasi saldo sumber kas (overdraft protection) untuk transaksi OUT dihilangkan.

        $cashFlow->transaction_date = $validated['transaction_date'];
        $cashFlow->amount = $newAmount;
        $cashFlow->description = $validated['description'];
        $cashFlow->payment_method_id = $newPmId;
        $cashFlow->save();

        return redirect()->route('cash-flows.out.index')->with('success', __('Pengeluaran berhasil diperbarui.'));
    }

    public function destroyOut(Request $request, CashFlow $cashFlow): RedirectResponse
    {
        if ($cashFlow->type !== CashFlow::TYPE_OUT) {
            abort(404);
        }

        $externalExpenseCategoryId = $this->resolveExternalExpenseCategoryId();
        if ($externalExpenseCategoryId && (int) $cashFlow->expense_category_id === $externalExpenseCategoryId) {
            abort(404);
        }

        $cashFlow->delete();

        return redirect()->route('cash-flows.out.index')->with('success', __('Pengeluaran berhasil dihapus.'));
    }

    public function editOutExternal(Request $request, CashFlow $cashFlow): View
    {
        if ($cashFlow->type !== CashFlow::TYPE_OUT) {
            abort(404);
        }

        $externalExpenseCategoryId = $this->resolveExternalExpenseCategoryId();
        if (! $externalExpenseCategoryId || (int) $cashFlow->expense_category_id !== $externalExpenseCategoryId) {
            abort(404);
        }

        $cashFlow->load(['branch', 'warehouse', 'expenseCategory', 'paymentMethod', 'user']);

        $locationType = $cashFlow->warehouse_id ? 'warehouse' : 'branch';
        $locationId = $cashFlow->warehouse_id ? (int) $cashFlow->warehouse_id : (int) $cashFlow->branch_id;
        $pmBranchId = $locationType === 'branch' ? $locationId : null;
        $pmWarehouseId = $locationType === 'warehouse' ? $locationId : null;

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();

        $locationLabel = $cashFlow->warehouse_id
            ? __('Gudang') . ': ' . ($cashFlow->warehouse?->name ?? '#' . $cashFlow->warehouse_id)
            : __('Cabang') . ': ' . ($cashFlow->branch?->name ?? '#' . $cashFlow->branch_id);

        return view('cash-flows.out-external-edit', compact('cashFlow', 'paymentMethods', 'locationLabel'));
    }

    public function updateOutExternal(Request $request, CashFlow $cashFlow): RedirectResponse
    {
        if ($cashFlow->type !== CashFlow::TYPE_OUT) {
            abort(404);
        }

        $externalExpenseCategoryId = $this->resolveExternalExpenseCategoryId();
        if (! $externalExpenseCategoryId || (int) $cashFlow->expense_category_id !== $externalExpenseCategoryId) {
            abort(404);
        }

        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
        ]);

        if (! $cashFlow->payment_method_id) {
            return redirect()->back()->withInput()->withErrors([
                'payment_method_id' => __('Metode pembayaran tidak tersedia untuk transaksi ini.'),
            ]);
        }

        $newAmount = (float) $validated['amount'];
        $oldAmount = (float) $cashFlow->amount;
        $oldPmId = (int) $cashFlow->payment_method_id;
        $newPmId = (int) $validated['payment_method_id'];

        $locationType = $cashFlow->warehouse_id ? 'warehouse' : 'branch';
        $locationId = $cashFlow->warehouse_id ? (int) $cashFlow->warehouse_id : (int) $cashFlow->branch_id;
        $pmBranchId = $locationType === 'branch' ? $locationId : null;
        $pmWarehouseId = $locationType === 'warehouse' ? $locationId : null;

        $paymentMethodAllowed = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->whereKey($newPmId)
            ->exists();
        if (! $paymentMethodAllowed) {
            return redirect()->back()->withInput()->withErrors([
                'payment_method_id' => __('Sumber dana tidak sesuai lokasi transaksi.'),
            ]);
        }

        // Catatan: validasi saldo sumber kas (overdraft protection) dihilangkan.

        $cashFlow->transaction_date = $validated['transaction_date'];
        $cashFlow->amount = $newAmount;
        $cashFlow->description = $validated['description'];
        $cashFlow->payment_method_id = $newPmId;
        $cashFlow->save();

        return redirect()->route('cash-flows.out.external.index')->with('success', __('Pengeluaran dana eksternal berhasil diperbarui.'));
    }

    public function destroyOutExternal(Request $request, CashFlow $cashFlow): RedirectResponse
    {
        if ($cashFlow->type !== CashFlow::TYPE_OUT) {
            abort(404);
        }

        $externalExpenseCategoryId = $this->resolveExternalExpenseCategoryId();
        if (! $externalExpenseCategoryId || (int) $cashFlow->expense_category_id !== $externalExpenseCategoryId) {
            abort(404);
        }

        $cashFlow->delete();

        return redirect()->route('cash-flows.out.external.index')->with('success', __('Pengeluaran dana eksternal berhasil dihapus.'));
    }

    public function editIn(Request $request, CashFlow $cashFlow): View
    {
        if ($cashFlow->type !== CashFlow::TYPE_IN || $cashFlow->reference_type !== CashFlow::REFERENCE_OTHER) {
            abort(404);
        }

        $cashFlow->load(['branch', 'warehouse', 'incomeCategory', 'paymentMethod', 'user']);

        $locationType = $cashFlow->warehouse_id ? 'warehouse' : 'branch';
        $locationId = $cashFlow->warehouse_id ? (int) $cashFlow->warehouse_id : (int) $cashFlow->branch_id;
        $pmBranchId = $locationType === 'branch' ? $locationId : null;
        $pmWarehouseId = $locationType === 'warehouse' ? $locationId : null;

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();

        $locationLabel = $cashFlow->warehouse_id
            ? __('Gudang') . ': ' . ($cashFlow->warehouse?->name ?? '#' . $cashFlow->warehouse_id)
            : __('Cabang') . ': ' . ($cashFlow->branch?->name ?? '#' . $cashFlow->branch_id);

        return view('cash-flows.in-edit', compact('cashFlow', 'paymentMethods', 'locationLabel'));
    }

    public function updateIn(Request $request, CashFlow $cashFlow): RedirectResponse
    {
        if ($cashFlow->type !== CashFlow::TYPE_IN || $cashFlow->reference_type !== CashFlow::REFERENCE_OTHER) {
            abort(404);
        }

        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
        ]);

        $cashFlow->transaction_date = $validated['transaction_date'];
        $cashFlow->amount = (float) $validated['amount'];
        $cashFlow->description = $validated['description'] ?? null;
        $cashFlow->payment_method_id = (int) $validated['payment_method_id'];
        $cashFlow->save();

        return redirect()->route('cash-flows.in.index')->with('success', __('Pemasukan lainnya berhasil diperbarui.'));
    }

    public function destroyIn(Request $request, CashFlow $cashFlow): RedirectResponse
    {
        if ($cashFlow->type !== CashFlow::TYPE_IN || $cashFlow->reference_type !== CashFlow::REFERENCE_OTHER) {
            abort(404);
        }

        $cashFlow->delete();

        return redirect()->route('cash-flows.in.index')->with('success', __('Pemasukan lainnya berhasil dihapus.'));
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
                $this->applyArusKasPaymentMethodFilter($q, $request, $branchId, $warehouseId);
            }
            if ($request->filled('category_key')) {
                $raw = (string) $request->input('category_key');
                if (preg_match('/^income:(\d+)$/', $raw, $m)) {
                    $q->where('type', CashFlow::TYPE_IN)->where('income_category_id', (int) $m[1]);
                } elseif (preg_match('/^expense:(\d+)$/', $raw, $m)) {
                    $q->where('type', CashFlow::TYPE_OUT)->where('expense_category_id', (int) $m[1]);
                }
            }
        };

        $query = CashFlow::with(['user', 'branch', 'warehouse', 'expenseCategory', 'incomeCategory', 'paymentMethod']);
        $applyFilters($query);
        $query->orderBy('transaction_date')->orderBy('id');

        $cashFlowsRaw = $query->limit(1000)->get();
        $this->hydrateSaleCashFlowPaymentMethods($cashFlowsRaw);
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

        $totalTradeIn = $request->filled('category_key')
            ? 0.0
            : (float) DB::table('sale_trade_ins')
                ->join('sales', 'sale_trade_ins.sale_id', '=', 'sales.id')
                ->where('sales.status', \App\Models\Sale::STATUS_RELEASED)
                ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
                ->when($warehouseId, fn ($q) => $q->whereRaw('1 = 0'))
                ->when($request->filled('date_from'), fn ($q) => $q->whereDate('sales.sale_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn ($q) => $q->whereDate('sales.sale_date', '<=', $request->date_to))
                ->sum('sale_trade_ins.trade_in_value');

        $cashFlowIncomeCategories = IncomeCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $cashFlowExpenseCategories = ExpenseCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);

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

        return view('cash-flows.index', compact(
            'cashFlows',
            'summary',
            'branches',
            'warehouses',
            'paymentMethods',
            'canFilterLocation',
            'filterLocked',
            'locationLabel',
            'lockedBranchId',
            'lockedWarehouseId',
            'totalTradeIn',
            'branchId',
            'warehouseId',
            'orderDirection',
            'cashFlowIncomeCategories',
            'cashFlowExpenseCategories'
        ));
    }

    /**
     * Filter arus kas by payment method: baris dengan payment_method_id, atau penjualan (kas masuk) lama
     * yang payment_method_id null tetapi cocok dengan sale_payments (sale_id + nominal + metode).
     */
    private function applyArusKasPaymentMethodFilter($query, Request $request, ?int $branchId, ?int $warehouseId): void
    {
        $pmIds = $this->resolvePaymentMethodIdsForFilter((int) $request->payment_method_id, $branchId, $warehouseId);
        $ids = ! empty($pmIds) ? $pmIds : [(int) $request->payment_method_id];
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => (int) $id > 0)));
        if ($ids === []) {
            $query->where('payment_method_id', (int) $request->payment_method_id);

            return;
        }

        $saleIncomeCategoryIds = IncomeCategory::query()
            ->where('code', 'SALE')
            ->pluck('id')
            ->all();

        $query->where(function ($w) use ($ids, $saleIncomeCategoryIds) {
            $w->whereIn('cash_flows.payment_method_id', $ids)
                ->orWhere(function ($o) use ($ids, $saleIncomeCategoryIds) {
                    $o->where('cash_flows.reference_type', CashFlow::REFERENCE_SALE)
                        ->where('cash_flows.type', CashFlow::TYPE_IN)
                        ->whereNull('cash_flows.payment_method_id')
                        ->where(function ($inner) use ($ids, $saleIncomeCategoryIds) {
                            $inner->whereExists(function ($sub) use ($ids) {
                                $sub->select(DB::raw(1))
                                    ->from('sale_payments as sp')
                                    ->whereRaw('sp.sale_id = cash_flows.reference_id')
                                    ->whereIn('sp.payment_method_id', $ids)
                                    ->whereRaw('ABS(sp.amount - cash_flows.amount) < 0.02');
                            });
                            $pms = PaymentMethod::query()->whereIn('id', $ids)->get();
                            if ($pms->isEmpty()) {
                                return;
                            }
                            $inner->orWhere(function ($legacyMatch) use ($pms, $saleIncomeCategoryIds) {
                                if ($saleIncomeCategoryIds !== []) {
                                    $legacyMatch->whereIn('cash_flows.income_category_id', $saleIncomeCategoryIds);
                                }
                                $legacyMatch->where(function ($descOr) use ($pms) {
                                    foreach ($pms as $pm) {
                                        $label = trim((string) ($pm->display_label ?? ''));
                                        if ($label === '') {
                                            continue;
                                        }
                                        $descOr->orWhere(function ($one) use ($label) {
                                            $this->addCashFlowDescriptionContainsLabelInsensitive($one, $label);
                                        });
                                    }
                                });
                            });
                        });
                });
        });
    }

    /**
     * Pencarian label metode di deskripsi (setara ILIKE): PostgreSQL pakai ilike, driver lain LOWER(...) LIKE.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    private function addCashFlowDescriptionContainsLabelInsensitive($query, string $label): void
    {
        $escaped = $this->escapeSqlLike(trim($label));
        if ($escaped === '') {
            return;
        }
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $query->where('cash_flows.description', 'ilike', '%'.$escaped.'%');
            return;
        }
        $query->whereRaw('LOWER(cash_flows.description) LIKE ?', ['%' . Str::lower($escaped) . '%']);
    }

    private function escapeSqlLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
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

    /**
     * Baris kas masuk penjualan (release) lama bisa tanpa payment_method_id; tampilkan metode dari sale_payments jika cocok nominal.
     *
     * @param  \Illuminate\Support\Collection<int, CashFlow>|\Illuminate\Database\Eloquent\Collection<int, CashFlow>  $cashFlows
     */
    private function hydrateSaleCashFlowPaymentMethods($cashFlows): void
    {
        $saleIds = $cashFlows
            ->filter(fn (CashFlow $cf) => $cf->reference_type === CashFlow::REFERENCE_SALE
                && $cf->type === CashFlow::TYPE_IN
                && ! $cf->payment_method_id)
            ->pluck('reference_id')
            ->filter()
            ->unique()
            ->values();
        if ($saleIds->isEmpty()) {
            return;
        }

        $paymentsBySale = SalePayment::query()
            ->whereIn('sale_id', $saleIds->all())
            ->with('paymentMethod')
            ->orderBy('id')
            ->get()
            ->groupBy('sale_id');

        foreach ($cashFlows as $cf) {
            if ($cf->reference_type !== CashFlow::REFERENCE_SALE || $cf->type !== CashFlow::TYPE_IN || $cf->payment_method_id) {
                continue;
            }
            $saleId = (int) $cf->reference_id;
            $list = $paymentsBySale->get($saleId);
            if (! $list || $list->isEmpty()) {
                continue;
            }
            $amt = (float) $cf->amount;
            $match = $list->first(fn (SalePayment $sp) => abs((float) $sp->amount - $amt) < 0.02);
            if ($match?->paymentMethod) {
                $cf->setRelation('paymentMethod', $match->paymentMethod);
            }
        }

        foreach ($cashFlows as $cf) {
            if ($cf->reference_type !== CashFlow::REFERENCE_SALE || $cf->type !== CashFlow::TYPE_IN || $cf->payment_method_id) {
                continue;
            }
            if ($cf->relationLoaded('paymentMethod') && $cf->paymentMethod) {
                continue;
            }
            $label = CashFlow::parsePaymentMethodSuffixFromSaleDescription($cf->description);
            if ($label === null || $label === '') {
                continue;
            }
            $bid = (int) ($cf->branch_id ?? 0);
            $wid = (int) ($cf->warehouse_id ?? 0);
            if ($bid <= 0 && $wid <= 0) {
                continue;
            }
            $pm = PaymentMethod::query()
                ->where('is_active', true)
                ->forLocation($bid > 0 ? $bid : null, $wid > 0 ? $wid : null)
                ->get()
                ->first(fn (PaymentMethod $p) => trim((string) ($p->display_label ?? '')) === $label);
            if ($pm) {
                $cf->setRelation('paymentMethod', $pm);
            }
        }
    }

    public function createOut(Request $request): View
    {
        $user = $request->user();
        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->branch_id ? Branch::whereKey($user->branch_id)->get(['id', 'name']) : collect());
        $warehouses = $user->isSuperAdminOrAdminPusat()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : ($user->warehouse_id ? Warehouse::whereKey($user->warehouse_id)->get(['id', 'name']) : collect());

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

        $branchId = null;
        $warehouseId = null;

        if ($user->isSuperAdminOrAdminPusat()) {
            if (($validated['location_type'] ?? '') === 'warehouse' && ! empty($validated['warehouse_id'])) {
                $warehouseId = (int) $validated['warehouse_id'];
            } elseif (($validated['location_type'] ?? '') === 'branch' && ! empty($validated['branch_id'])) {
                $branchId = (int) $validated['branch_id'];
            }
        } else {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $branchId = (int) $user->branch_id;
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $warehouseId = (int) $user->warehouse_id;
            }
        }

        if (! $branchId && ! $warehouseId) {
            return redirect()->back()->withInput()->withErrors([
                'location_type' => __('Lokasi (Cabang atau Gudang) wajib dipilih.'),
            ]);
        }

        $pmAllowed = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($branchId, $warehouseId)
            ->whereKey((int) $validated['payment_method_id'])
            ->exists();
        if (! $pmAllowed) {
            return redirect()->back()->withInput()->withErrors([
                'payment_method_id' => __('Sumber dana tidak sesuai lokasi yang dipilih.'),
            ]);
        }

        $items = $validated['items'];
        $totalAmount = collect($items)->sum(fn ($item) => (float) $item['amount']);

        // Catatan: validasi saldo sumber kas (overdraft protection) dihilangkan.

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

        $branchId = null;
        $warehouseId = null;

        if ($user->isSuperAdminOrAdminPusat()) {
            if (($validated['location_type'] ?? '') === 'warehouse' && ! empty($validated['warehouse_id'])) {
                $warehouseId = (int) $validated['warehouse_id'];
            } elseif (($validated['location_type'] ?? '') === 'branch' && ! empty($validated['branch_id'])) {
                $branchId = (int) $validated['branch_id'];
            }
        } else {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $branchId = (int) $user->branch_id;
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $warehouseId = (int) $user->warehouse_id;
            }
        }

        if (! $branchId && ! $warehouseId) {
            return redirect()->back()->withInput()->withErrors([
                'location_type' => __('Lokasi (Cabang atau Gudang) wajib dipilih.'),
            ]);
        }

        $pmAllowed = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($branchId, $warehouseId)
            ->whereKey((int) $validated['payment_method_id'])
            ->exists();
        if (! $pmAllowed) {
            return redirect()->back()->withInput()->withErrors([
                'payment_method_id' => __('Sumber dana tidak sesuai lokasi yang dipilih.'),
            ]);
        }

        $items = $validated['items'];
        $totalAmount = collect($items)->sum(fn ($item) => (float) $item['amount']);

        // Catatan: validasi saldo sumber kas (overdraft protection) dihilangkan.

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
