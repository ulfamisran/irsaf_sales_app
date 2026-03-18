<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\Warehouse;
use App\Services\KasBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MutasiDanaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = CashFlow::with(['paymentMethod', 'branch', 'warehouse', 'user'])
            ->where(function ($q) {
                $q->where('reference_type', CashFlow::REFERENCE_MUTASI_DANA)
                  ->orWhere('reference_type', CashFlow::REFERENCE_SETOR_TUNAI);
            })
            ->where('type', CashFlow::TYPE_OUT)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        $filterLocked = false;
        $locationLabel = null;
        $canFilterLocation = false;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $locationLabel = __('Cabang') . ': ' . (Branch::find($user->branch_id)?->name ?? '');
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $locationLabel = __('Gudang') . ': ' . (Warehouse::find($user->warehouse_id)?->name ?? '');
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('location_type') && $request->filled('location_id')) {
                $locType = $request->location_type;
                $locId = (int) $request->location_id;
                if ($locType === 'branch' && $locId > 0) {
                    $query->where('branch_id', $locId);
                } elseif ($locType === 'warehouse' && $locId > 0) {
                    $query->where('warehouse_id', $locId);
                }
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $records = $query->paginate(20)->withQueryString();

        $outIds = $records->pluck('id')->all();
        $inRecords = ! empty($outIds)
            ? CashFlow::with('paymentMethod')
                ->where(function ($q) {
                    $q->where('reference_type', CashFlow::REFERENCE_MUTASI_DANA)
                      ->orWhere('reference_type', CashFlow::REFERENCE_SETOR_TUNAI);
                })
                ->where('type', CashFlow::TYPE_IN)
                ->whereIn('reference_id', $outIds)
                ->get()
                ->keyBy('reference_id')
            : collect();

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return view('mutasi-dana.index', compact(
            'records',
            'inRecords',
            'branches',
            'warehouses',
            'canFilterLocation',
            'filterLocked',
            'locationLabel'
        ));
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->branch_id ? Branch::whereKey($user->branch_id)->get(['id', 'name']) : collect());

        $warehouses = $user->isSuperAdminOrAdminPusat()
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : ($user->warehouse_id ? Warehouse::whereKey($user->warehouse_id)->get(['id', 'name']) : collect());

        return view('mutasi-dana.create', compact('branches', 'warehouses'));
    }

    public function formData(Request $request): JsonResponse
    {
        $locationType = $request->get('location_type');
        $locationId = (int) $request->get('location_id', 0);

        if (! $locationType || $locationId <= 0) {
            return response()->json(['payment_methods' => []]);
        }

        $branchId = $locationType === 'branch' ? $locationId : null;
        $warehouseId = $locationType === 'warehouse' ? $locationId : null;

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when(! $branchId && ! $warehouseId, fn ($q) => $q->whereRaw('1=0'))
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->get();

        $kasService = new KasBalanceService;
        $saldoMap = $kasService->getSaldoPerPaymentMethodForLocation($locationType, $locationId);

        $result = [];
        foreach ($paymentMethods as $pm) {
            $saldo = round($saldoMap[$pm->id] ?? 0, 2);
            $result[] = [
                'id' => $pm->id,
                'label' => $pm->display_label,
                'saldo' => $saldo,
            ];
        }

        return response()->json(['payment_methods' => $result]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'location_type' => ['required', 'in:branch,warehouse'],
            'location_id' => ['required', 'integer', 'min:1'],
            'source_payment_method_id' => ['required', 'exists:payment_methods,id'],
            'destination_payment_method_id' => ['required', 'exists:payment_methods,id', 'different:source_payment_method_id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $locationType = $validated['location_type'];
        $locationId = (int) $validated['location_id'];
        $amount = round((float) $validated['amount'], 2);
        $sourcePmId = (int) $validated['source_payment_method_id'];
        $destPmId = (int) $validated['destination_payment_method_id'];

        $kasService = new KasBalanceService;
        $saldoSource = $kasService->getSaldoForLocation($locationType, $locationId, $sourcePmId);

        if ($amount > $saldoSource) {
            return back()->withInput()->with('error', __('Saldo dana asal tidak mencukupi. Saldo tersedia: Rp :saldo', [
                'saldo' => number_format($saldoSource, 0, ',', '.'),
            ]));
        }

        $branchId = $locationType === 'branch' ? $locationId : null;
        $warehouseId = $locationType === 'warehouse' ? $locationId : null;

        $sourcePm = PaymentMethod::find($sourcePmId);
        $destPm = PaymentMethod::find($destPmId);
        $desc = $validated['description']
            ? __('Mutasi Dana') . ' - ' . $validated['description']
            : __('Mutasi Dana') . ' (' . ($sourcePm?->display_label ?? '-') . ' → ' . ($destPm?->display_label ?? '-') . ')';

        DB::beginTransaction();
        try {
            $expenseCategory = ExpenseCategory::firstOrCreate(
                ['code' => 'MUTASI-DANA'],
                ['name' => 'Mutasi Dana', 'description' => 'Pengeluaran dana untuk mutasi antar metode pembayaran', 'is_active' => true]
            );

            $incomeCategory = IncomeCategory::firstOrCreate(
                ['code' => 'MUTASI-DANA'],
                ['name' => 'Mutasi Dana', 'description' => 'Pemasukan dana dari mutasi antar metode pembayaran', 'is_active' => true]
            );

            $outRecord = CashFlow::create([
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'type' => CashFlow::TYPE_OUT,
                'amount' => $amount,
                'description' => $desc,
                'reference_type' => CashFlow::REFERENCE_MUTASI_DANA,
                'reference_id' => null,
                'expense_category_id' => $expenseCategory->id,
                'payment_method_id' => $sourcePmId,
                'transaction_date' => $validated['transaction_date'],
                'user_id' => $user->id,
            ]);

            CashFlow::create([
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'type' => CashFlow::TYPE_IN,
                'amount' => $amount,
                'description' => $desc,
                'reference_type' => CashFlow::REFERENCE_MUTASI_DANA,
                'reference_id' => $outRecord->id,
                'income_category_id' => $incomeCategory->id,
                'payment_method_id' => $destPmId,
                'transaction_date' => $validated['transaction_date'],
                'user_id' => $user->id,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('mutasi-dana.index')->with('success', __('Mutasi dana berhasil dicatat.'));
    }
}
