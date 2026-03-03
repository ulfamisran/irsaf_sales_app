<?php

namespace App\Http\Controllers;

use App\Http\Requests\DistributorRequest;
use App\Models\Distributor;
use App\Repositories\DistributorRepository;
use App\Services\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DistributorController extends Controller
{
    public function __construct(
        protected DistributorRepository $distributorRepository
    ) {}

    /**
     * Display a listing of distributors.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $locationFilter = LocationService::getLocationFilterForUser(
            $user,
            $request->get('location_type'),
            $request->get('location_id') ? (int) $request->get('location_id') : null
        );

        $distributors = $this->distributorRepository->paginate(15, [
            'search' => $request->get('search'),
            'user' => $user,
            'location_type' => $locationFilter['locationType'],
            'location_id' => $locationFilter['locationId'],
        ]);

        return view('distributors.index', compact('distributors', 'locationFilter'));
    }

    /**
     * Show the form for creating a new distributor.
     */
    public function create(Request $request): View
    {
        $locationOptions = LocationService::getLocationOptionsForUser($request->user());
        if (! $locationOptions['canChoose'] && $locationOptions['branches']->isEmpty() && $locationOptions['warehouses']->isEmpty()) {
            abort(403, __('Anda harus memiliki cabang atau gudang yang ditetapkan untuk menambah data.'));
        }

        return view('distributors.create', $locationOptions);
    }

    /**
     * Store a newly created distributor.
     */
    public function store(DistributorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $location = LocationService::resolveLocationFromUser($request->user(), $request->all());
        $data = array_merge($data, $location);

        $this->distributorRepository->create($data);

        return redirect()->route('distributors.index')->with('success', __('Distributor berhasil dibuat.'));
    }

    /**
     * Show the form for editing the specified distributor.
     */
    public function edit(Distributor $distributor, Request $request): View
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdminOrAdminPusat()) {
            $match = ($user->branch_id && $distributor->branch_id === $user->branch_id)
                || ($user->warehouse_id && $distributor->warehouse_id === $user->warehouse_id);
            if (! $match) {
                abort(403, __('Anda tidak memiliki akses ke data ini.'));
            }
        }
        $locationOptions = LocationService::getLocationOptionsForUser($user);
        if (! $locationOptions['canChoose'] && $locationOptions['branches']->isEmpty() && $locationOptions['warehouses']->isEmpty()) {
            abort(403, __('Anda harus memiliki cabang atau gudang yang ditetapkan.'));
        }
        $locationOptions['oldPlacementType'] = $distributor->placement_type;
        $locationOptions['oldBranchId'] = $distributor->branch_id;
        $locationOptions['oldWarehouseId'] = $distributor->warehouse_id;

        return view('distributors.edit', array_merge(compact('distributor'), $locationOptions));
    }

    /**
     * Update the specified distributor.
     */
    public function update(DistributorRequest $request, Distributor $distributor): RedirectResponse
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdminOrAdminPusat()) {
            $match = ($user->branch_id && $distributor->branch_id === $user->branch_id)
                || ($user->warehouse_id && $distributor->warehouse_id === $user->warehouse_id);
            if (! $match) {
                abort(403, __('Anda tidak memiliki akses ke data ini.'));
            }
        }
        $data = $request->validated();
        $location = LocationService::resolveLocationFromUser($user, $request->all());
        $data = array_merge($data, $location);

        $this->distributorRepository->update($distributor, $data);

        return redirect()->route('distributors.index')->with('success', __('Distributor berhasil diperbarui.'));
    }

    /**
     * Remove the specified distributor.
     */
    public function destroy(Distributor $distributor, Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdminOrAdminPusat()) {
            $match = ($user->branch_id && $distributor->branch_id === $user->branch_id)
                || ($user->warehouse_id && $distributor->warehouse_id === $user->warehouse_id);
            if (! $match) {
                abort(403, __('Anda tidak memiliki akses ke data ini.'));
            }
        }
        $this->distributorRepository->delete($distributor);

        return redirect()->route('distributors.index')->with('success', __('Distributor berhasil dihapus.'));
    }
}
