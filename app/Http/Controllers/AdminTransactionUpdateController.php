<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\Service;
use App\Models\TransactionUpdateLog;
use App\Services\AdminTransactionUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AdminTransactionUpdateController extends Controller
{
    public function __construct(
        protected AdminTransactionUpdateService $updateService
    ) {}

    public function editSale(Sale $sale): View
    {
        $this->authorizePusat();
        if ($sale->status === Sale::STATUS_CANCEL) {
            abort(403, __('Transaksi yang dibatalkan tidak dapat diubah.'));
        }

        $sale->load(['payments.paymentMethod', 'branch', 'warehouse', 'customer']);
        $paymentMethods = $this->paymentMethodsFor($sale->branch_id, $sale->warehouse_id);
        $logs = $this->logsFor(TransactionUpdateLog::TYPE_SALE, (int) $sale->id);

        return view('admin-transaction-update.edit-sale', compact('sale', 'paymentMethods', 'logs'));
    }

    public function updateSale(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorizePusat();
        if ($sale->status === Sale::STATUS_CANCEL) {
            abort(403, __('Transaksi yang dibatalkan tidak dapat diubah.'));
        }

        $validated = $request->validate([
            'sale_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'payments' => ['nullable', 'array'],
            'payments.*.id' => ['required', 'integer', 'exists:sale_payments,id'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->updateService->updateSale($sale, $validated, (int) $request->user()->id, $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', __('Data penjualan berhasil diperbarui.'));
    }

    public function editService(Service $service): View
    {
        $this->authorizePusat();
        if ($service->status === Service::STATUS_CANCEL) {
            abort(403, __('Transaksi yang dibatalkan tidak dapat diubah.'));
        }

        $service->load(['payments.paymentMethod', 'branch', 'warehouse', 'customer']);
        $paymentMethods = $this->paymentMethodsFor($service->branch_id, $service->warehouse_id);
        $logs = $this->logsFor(TransactionUpdateLog::TYPE_SERVICE, (int) $service->id);

        return view('admin-transaction-update.edit-service', compact('service', 'paymentMethods', 'logs'));
    }

    public function updateService(Request $request, Service $service): RedirectResponse
    {
        $this->authorizePusat();
        if ($service->status === Service::STATUS_CANCEL) {
            abort(403, __('Transaksi yang dibatalkan tidak dapat diubah.'));
        }

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'exit_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'damage_description' => ['nullable', 'string', 'max:2000'],
            'laptop_detail' => ['nullable', 'string', 'max:1000'],
            'service_price' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'payments' => ['nullable', 'array'],
            'payments.*.id' => ['required', 'integer', 'exists:service_payments,id'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->updateService->updateService($service, $validated, (int) $request->user()->id, $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('services.show', $service)->with('success', __('Data service berhasil diperbarui.'));
    }

    public function editRental(Rental $rental): View
    {
        $this->authorizePusat();
        if ($rental->status === Rental::STATUS_CANCEL) {
            abort(403, __('Transaksi yang dibatalkan tidak dapat diubah.'));
        }

        $rental->load(['payments.paymentMethod', 'branch', 'warehouse', 'customer']);
        $paymentMethods = $this->paymentMethodsFor($rental->branch_id, $rental->warehouse_id);
        $logs = $this->logsFor(TransactionUpdateLog::TYPE_RENTAL, (int) $rental->id);

        return view('admin-transaction-update.edit-rental', compact('rental', 'paymentMethods', 'logs'));
    }

    public function updateRental(Request $request, Rental $rental): RedirectResponse
    {
        $this->authorizePusat();
        if ($rental->status === Rental::STATUS_CANCEL) {
            abort(403, __('Transaksi yang dibatalkan tidak dapat diubah.'));
        }

        $validated = $request->validate([
            'pickup_date' => ['required', 'date'],
            'return_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'payments' => ['nullable', 'array'],
            'payments.*.id' => ['required', 'integer', 'exists:rental_payments,id'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->updateService->updateRental($rental, $validated, (int) $request->user()->id, $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Data penyewaan berhasil diperbarui.'));
    }

    public function editPurchase(Purchase $purchase): View
    {
        $this->authorizePusat();
        if ($purchase->status === Purchase::STATUS_CANCELLED) {
            abort(403, __('Transaksi yang dibatalkan tidak dapat diubah.'));
        }

        $purchase->load(['payments.paymentMethod', 'branch', 'warehouse', 'distributor']);
        $paymentMethods = $this->paymentMethodsFor($purchase->branch_id, $purchase->warehouse_id);
        $logs = $this->logsFor(TransactionUpdateLog::TYPE_PURCHASE, (int) $purchase->id);

        return view('admin-transaction-update.edit-purchase', compact('purchase', 'paymentMethods', 'logs'));
    }

    public function updatePurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizePusat();
        if ($purchase->status === Purchase::STATUS_CANCELLED) {
            abort(403, __('Transaksi yang dibatalkan tidak dapat diubah.'));
        }

        $validated = $request->validate([
            'purchase_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'termin' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'payments' => ['nullable', 'array'],
            'payments.*.id' => ['required', 'integer', 'exists:purchase_payments,id'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.payment_date' => ['nullable', 'date'],
            'payments.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->updateService->updatePurchase($purchase, $validated, (int) $request->user()->id, $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', __('Data pembelian berhasil diperbarui.'));
    }

    private function authorizePusat(): void
    {
        if (! auth()->user()?->isSuperAdminOrAdminPusat()) {
            abort(403);
        }
    }

    private function paymentMethodsFor(?int $branchId, ?int $warehouseId)
    {
        return PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($branchId, $warehouseId)
            ->orderByRaw("CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 0 ELSE 1 END")
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get();
    }

    private function logsFor(string $type, int $id)
    {
        return TransactionUpdateLog::with('user')
            ->where('reference_type', $type)
            ->where('reference_id', $id)
            ->orderByDesc('updated_at_action')
            ->limit(50)
            ->get();
    }
}
