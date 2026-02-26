<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseRequest;
use App\Models\Warehouse;
use App\Repositories\WarehouseRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(
        protected WarehouseRepository $warehouseRepository
    ) {}

    public function index(Request $request): View
    {
        $warehouses = $this->warehouseRepository->paginate(15, [
            'search' => $request->get('search'),
        ]);
        return view('warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        return view('warehouses.create');
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        $this->warehouseRepository->create($request->validated());
        return redirect()->route('warehouses.index')->with('success', __('Warehouse created successfully.'));
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('warehouses.edit', compact('warehouse'));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->warehouseRepository->update($warehouse, $request->validated());
        return redirect()->route('warehouses.index')->with('success', __('Warehouse updated successfully.'));
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->warehouseRepository->delete($warehouse);
        return redirect()->route('warehouses.index')->with('success', __('Warehouse deleted successfully.'));
    }
}
