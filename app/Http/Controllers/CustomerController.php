<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Repositories\CustomerRepository;
use App\Services\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerRepository $customerRepository
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $locationFilter = LocationService::getLocationFilterForUser(
            $user,
            $request->get('location_type'),
            $request->get('location_id') ? (int) $request->get('location_id') : null
        );

        $customers = $this->customerRepository->paginate(15, [
            'search' => $request->get('search'),
            'is_active' => $request->get('is_active'),
            'user' => $user,
            'location_type' => $locationFilter['locationType'],
            'location_id' => $locationFilter['locationId'],
        ]);

        return view('customers.index', compact('customers', 'locationFilter'));
    }

    public function create(Request $request): View
    {
        $locationOptions = LocationService::getLocationOptionsForUser($request->user());
        if (! $locationOptions['canChoose'] && $locationOptions['branches']->isEmpty() && $locationOptions['warehouses']->isEmpty()) {
            abort(403, __('Anda harus memiliki cabang atau gudang yang ditetapkan untuk menambah data.'));
        }

        return view('customers.create', $locationOptions);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) $request->input('is_active', false);

        $location = LocationService::resolveLocationFromUser($request->user(), $request->all());
        $data = array_merge($data, $location);

        $this->customerRepository->create($data);

        return redirect()->route('customers.index')->with('success', __('Pelanggan berhasil dibuat.'));
    }

    public function edit(Customer $customer, Request $request): View
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdminOrAdminPusat()) {
            $match = ($user->branch_id && $customer->branch_id === $user->branch_id)
                || ($user->warehouse_id && $customer->warehouse_id === $user->warehouse_id);
            if (! $match) {
                abort(403, __('Anda tidak memiliki akses ke data ini.'));
            }
        }
        $locationOptions = LocationService::getLocationOptionsForUser($user);
        if (! $locationOptions['canChoose'] && $locationOptions['branches']->isEmpty() && $locationOptions['warehouses']->isEmpty()) {
            abort(403, __('Anda harus memiliki cabang atau gudang yang ditetapkan.'));
        }
        $locationOptions['oldPlacementType'] = $customer->placement_type;
        $locationOptions['oldBranchId'] = $customer->branch_id;
        $locationOptions['oldWarehouseId'] = $customer->warehouse_id;

        return view('customers.edit', array_merge(compact('customer'), $locationOptions));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) $request->input('is_active', false);

        $location = LocationService::resolveLocationFromUser($request->user(), $request->all());
        $data = array_merge($data, $location);

        $this->customerRepository->update($customer, $data);

        return redirect()->route('customers.index')->with('success', __('Pelanggan berhasil diperbarui.'));
    }

    public function destroy(Customer $customer, Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdminOrAdminPusat()) {
            $match = ($user->branch_id && $customer->branch_id === $user->branch_id)
                || ($user->warehouse_id && $customer->warehouse_id === $user->warehouse_id);
            if (! $match) {
                abort(403, __('Anda tidak memiliki akses ke data ini.'));
            }
        }
        $this->customerRepository->delete($customer);

        return redirect()->route('customers.index')->with('success', __('Pelanggan berhasil dihapus.'));
    }
}

