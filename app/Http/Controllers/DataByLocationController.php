<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Distributor;
use App\Models\PaymentMethod;
use App\Services\KasBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller to fetch data filtered by location (branch or warehouse).
 * Used by product create, sales, service, rental forms.
 */
class DataByLocationController extends Controller
{
    /**
     * Get distributors by location.
     */
    public function distributors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_type' => ['required', 'in:branch,warehouse,cabang,gudang'],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);

        $locationType = in_array($validated['location_type'], ['branch', 'cabang']) ? 'branch' : 'warehouse';
        $locationId = (int) $validated['location_id'];

        $query = Distributor::orderBy('name');
        if ($locationType === 'branch') {
            $query->where(function ($q) use ($locationId) {
                $q->where('branch_id', $locationId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('branch_id')->whereNull('warehouse_id');
                    });
            });
        } else {
            $query->where(function ($q) use ($locationId) {
                $q->where('warehouse_id', $locationId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('branch_id')->whereNull('warehouse_id');
                    });
            });
        }

        $distributors = $query->get(['id', 'name']);

        return response()->json([
            'distributors' => $distributors->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values(),
        ]);
    }

    /**
     * Get payment methods and customers by location.
     * For sales/service: location_type=branch
     * For rental: location_type=warehouse
     */
    public function formData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_type' => ['required', 'in:branch,warehouse,cabang,gudang'],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);

        $locationType = in_array($validated['location_type'], ['branch', 'cabang']) ? 'branch' : 'warehouse';
        $locationId = (int) $validated['location_id'];

        $branchId = $locationType === 'branch' ? $locationId : null;
        $warehouseId = $locationType === 'warehouse' ? $locationId : null;

        $paymentMethods = $this->getPaymentMethodsByLocation($branchId, $warehouseId);
        $customers = $this->getCustomersByLocation($branchId, $warehouseId);

        $saldoPerPm = [];
        if ($branchId) {
            $saldoPerPm = (new KasBalanceService)->getSaldoPerPaymentMethod($branchId);
        }

        return response()->json([
            'payment_methods' => $paymentMethods,
            'customers' => $customers,
            'saldo_per_pm' => $saldoPerPm,
        ]);
    }

    private function getPaymentMethodsByLocation(?int $branchId, ?int $warehouseId): array
    {
        $query = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('id');

        if ($branchId) {
            $query->forLocation($branchId, null);
        } elseif ($warehouseId) {
            $query->forLocation(null, $warehouseId);
        } else {
            return [];
        }

        return $query->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening'])
            ->map(fn ($pm) => [
                'id' => $pm->id,
                'label' => $pm->display_label,
            ])
            ->values()
            ->all();
    }

    private function getCustomersByLocation(?int $branchId, ?int $warehouseId): array
    {
        $query = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        } else {
            return [];
        }

        return $query->get(['id', 'name', 'phone'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
            ])
            ->values()
            ->all();
    }
}
