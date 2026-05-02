<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\PaymentMethod;
use App\Models\Warehouse;
use App\Services\KasBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Penyesuaian Saldo (Balance Adjustment)
 *
 * Hanya bisa diakses Super Admin & Admin Pusat. Setiap penyesuaian membuat satu
 * baris CashFlow (IN/OUT) menggunakan kategori "Penyesuaian Saldo" (kode ADJ-SALDO),
 * sehingga riwayatnya otomatis muncul di Laporan Arus Kas dan Detail Monitoring Kas.
 */
class SaldoAdjustmentController extends Controller
{
    private const CATEGORY_CODE = 'ADJ-SALDO';
    private const CATEGORY_NAME = 'Penyesuaian Saldo';

    private function ensureAuthorized(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Hanya Super Admin atau Admin Pusat yang dapat mengakses Penyesuaian Saldo.'));
        }
    }

    public function index(Request $request): View
    {
        $this->ensureAuthorized($request);

        $query = CashFlow::with(['paymentMethod', 'branch', 'warehouse', 'user', 'incomeCategory', 'expenseCategory'])
            ->where('reference_type', CashFlow::REFERENCE_PENYESUAIAN_SALDO)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->filled('location_type') && $request->filled('location_id')) {
            $locType = (string) $request->location_type;
            $locId = (int) $request->location_id;
            if ($locType === 'branch' && $locId > 0) {
                $query->where('branch_id', $locId);
            } elseif ($locType === 'warehouse' && $locId > 0) {
                $query->where('warehouse_id', $locId);
            }
        }

        if ($request->filled('type') && in_array($request->type, [CashFlow::TYPE_IN, CashFlow::TYPE_OUT], true)) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $records = $query->paginate(20)->withQueryString();

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return view('saldo-adjustment.index', compact('records', 'branches', 'warehouses'));
    }

    public function create(Request $request): View
    {
        $this->ensureAuthorized($request);

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return view('saldo-adjustment.create', compact('branches', 'warehouses'));
    }

    public function formData(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $locationType = (string) $request->get('location_type', '');
        $locationId = (int) $request->get('location_id', 0);

        if (! in_array($locationType, ['branch', 'warehouse'], true) || $locationId <= 0) {
            return response()->json(['payment_methods' => []]);
        }

        $branchId = $locationType === 'branch' ? $locationId : null;
        $warehouseId = $locationType === 'warehouse' ? $locationId : null;

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($branchId, $warehouseId)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();

        $saldoMap = (new KasBalanceService)->getSaldoPerPaymentMethodForLocation($locationType, $locationId);

        $result = [];
        foreach ($paymentMethods as $pm) {
            $result[] = [
                'id' => $pm->id,
                'label' => $pm->display_label,
                'saldo' => round((float) ($saldoMap[$pm->id] ?? 0), 2),
            ];
        }

        return response()->json(['payment_methods' => $result]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'location_type' => ['required', 'in:branch,warehouse'],
            'location_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:' . CashFlow::TYPE_IN . ',' . CashFlow::TYPE_OUT],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $locationType = $validated['location_type'];
        $locationId = (int) $validated['location_id'];
        $branchId = $locationType === 'branch' ? $locationId : null;
        $warehouseId = $locationType === 'warehouse' ? $locationId : null;

        if ($branchId && ! Branch::whereKey($branchId)->exists()) {
            return back()->withInput()->withErrors(['location_id' => __('Cabang tidak ditemukan.')]);
        }
        if ($warehouseId && ! Warehouse::whereKey($warehouseId)->exists()) {
            return back()->withInput()->withErrors(['location_id' => __('Gudang tidak ditemukan.')]);
        }

        $pmAllowed = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($branchId, $warehouseId)
            ->whereKey((int) $validated['payment_method_id'])
            ->exists();
        if (! $pmAllowed) {
            return back()->withInput()->withErrors([
                'payment_method_id' => __('Sumber dana tidak sesuai dengan lokasi yang dipilih.'),
            ]);
        }

        $type = (string) $validated['type'];
        $isIn = $type === CashFlow::TYPE_IN;
        $amount = round((float) $validated['amount'], 2);

        DB::beginTransaction();
        try {
            $payload = [
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'amount' => $amount,
                'description' => self::CATEGORY_NAME . ' - ' . $validated['description'],
                'reference_type' => CashFlow::REFERENCE_PENYESUAIAN_SALDO,
                'reference_id' => null,
                'payment_method_id' => (int) $validated['payment_method_id'],
                'transaction_date' => $validated['transaction_date'],
                'user_id' => $request->user()->id,
            ];

            if ($isIn) {
                $incomeCategory = IncomeCategory::firstOrCreate(
                    ['code' => self::CATEGORY_CODE],
                    [
                        'name' => self::CATEGORY_NAME,
                        'description' => 'Pemasukan dari penyesuaian saldo manual oleh Super Admin / Admin Pusat',
                        'is_active' => true,
                        'affects_profit_loss' => false,
                    ]
                );
                $payload['income_category_id'] = $incomeCategory->id;
                $payload['expense_category_id'] = null;
            } else {
                $expenseCategory = ExpenseCategory::firstOrCreate(
                    ['code' => self::CATEGORY_CODE],
                    [
                        'name' => self::CATEGORY_NAME,
                        'description' => 'Pengeluaran dari penyesuaian saldo manual oleh Super Admin / Admin Pusat',
                        'is_active' => true,
                        'affects_profit_loss' => false,
                    ]
                );
                $payload['expense_category_id'] = $expenseCategory->id;
                $payload['income_category_id'] = null;
            }

            CashFlow::create($payload);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('saldo-adjustment.index')
            ->with('success', __('Penyesuaian saldo berhasil dicatat.'));
    }
}
