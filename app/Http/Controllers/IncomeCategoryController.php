<?php

namespace App\Http\Controllers;

use App\Models\IncomeCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomeCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = IncomeCategory::query()->orderBy('name');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $categories = $query->paginate(20)->withQueryString();

        return view('income-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('income-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        IncomeCategory::create($data);

        return redirect()
            ->route('income-categories.index')
            ->with('success', __('Kategori pemasukan berhasil disimpan.'));
    }

    public function edit(IncomeCategory $incomeCategory): View
    {
        return view('income-categories.edit', compact('incomeCategory'));
    }

    public function update(Request $request, IncomeCategory $incomeCategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $incomeCategory->update($data);

        return redirect()
            ->route('income-categories.index')
            ->with('success', __('Kategori pemasukan berhasil diperbarui.'));
    }

    public function destroy(IncomeCategory $incomeCategory): RedirectResponse
    {
        $incomeCategory->delete();

        return redirect()
            ->route('income-categories.index')
            ->with('success', __('Kategori pemasukan berhasil dihapus.'));
    }
}
