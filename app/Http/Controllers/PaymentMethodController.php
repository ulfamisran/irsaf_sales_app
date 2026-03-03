<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodRepository;
use App\Services\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function __construct(
        protected PaymentMethodRepository $paymentMethodRepository
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $locationFilter = LocationService::getLocationFilterForUser(
            $user,
            $request->get('location_type'),
            $request->get('location_id') ? (int) $request->get('location_id') : null
        );

        $methods = $this->paymentMethodRepository->paginate(15, [
            'search' => $request->get('search'),
            'is_active' => $request->get('is_active'),
            'user' => $user,
            'location_type' => $locationFilter['locationType'],
            'location_id' => $locationFilter['locationId'],
        ]);

        return view('payment-methods.index', compact('methods', 'locationFilter'));
    }

    public function create(Request $request): View
    {
        $locationOptions = LocationService::getLocationOptionsForUser($request->user());
        if (! $locationOptions['canChoose'] && $locationOptions['branches']->isEmpty() && $locationOptions['warehouses']->isEmpty()) {
            abort(403, __('Anda harus memiliki cabang atau gudang yang ditetapkan untuk menambah data.'));
        }

        return view('payment-methods.create', $locationOptions);
    }

    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) $request->input('is_active', false);

        $location = LocationService::resolveLocationFromUser($request->user(), $request->all());
        $data = array_merge($data, $location);

        $this->paymentMethodRepository->create($data);

        return redirect()->route('payment-methods.index')->with('success', __('Metode pembayaran berhasil dibuat.'));
    }

    public function edit(PaymentMethod $paymentMethod, Request $request): View
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdminOrAdminPusat()) {
            $match = ($user->branch_id && $paymentMethod->branch_id === $user->branch_id)
                || ($user->warehouse_id && $paymentMethod->warehouse_id === $user->warehouse_id);
            if (! $match) {
                abort(403, __('Anda tidak memiliki akses ke data ini.'));
            }
        }
        $locationOptions = LocationService::getLocationOptionsForUser($user);
        if (! $locationOptions['canChoose'] && $locationOptions['branches']->isEmpty() && $locationOptions['warehouses']->isEmpty()) {
            abort(403, __('Anda harus memiliki cabang atau gudang yang ditetapkan.'));
        }
        $locationOptions['oldPlacementType'] = $paymentMethod->placement_type;
        $locationOptions['oldBranchId'] = $paymentMethod->branch_id;
        $locationOptions['oldWarehouseId'] = $paymentMethod->warehouse_id;

        return view('payment-methods.edit', array_merge(compact('paymentMethod'), $locationOptions));
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdminOrAdminPusat()) {
            $match = ($user->branch_id && $paymentMethod->branch_id === $user->branch_id)
                || ($user->warehouse_id && $paymentMethod->warehouse_id === $user->warehouse_id);
            if (! $match) {
                abort(403, __('Anda tidak memiliki akses ke data ini.'));
            }
        }

        $data = $request->validated();
        $data['is_active'] = (bool) $request->input('is_active', false);

        $location = LocationService::resolveLocationFromUser($user, $request->all());
        $data = array_merge($data, $location);

        $this->paymentMethodRepository->update($paymentMethod, $data);

        return redirect()->route('payment-methods.index')->with('success', __('Metode pembayaran berhasil diperbarui.'));
    }

    public function destroy(PaymentMethod $paymentMethod, Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdminOrAdminPusat()) {
            $match = ($user->branch_id && $paymentMethod->branch_id === $user->branch_id)
                || ($user->warehouse_id && $paymentMethod->warehouse_id === $user->warehouse_id);
            if (! $match) {
                abort(403, __('Anda tidak memiliki akses ke data ini.'));
            }
        }
        $this->paymentMethodRepository->delete($paymentMethod);

        return redirect()->route('payment-methods.index')->with('success', __('Metode pembayaran berhasil dihapus.'));
    }
}

