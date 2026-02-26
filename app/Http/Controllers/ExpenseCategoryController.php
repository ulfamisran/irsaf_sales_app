<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = ExpenseCategory::query()->orderBy('name');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $categories = $query->paginate(20)->withQueryString();

        return view('expense-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('expense-categories.create');
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

        ExpenseCategory::create($data);

        return redirect()
            ->route('expense-categories.index')
            ->with('success', __('Kategori pengeluaran berhasil disimpan.'));
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        return view('expense-categories.edit', compact('expenseCategory'));
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $expenseCategory->update($data);

        return redirect()
            ->route('expense-categories.index')
            ->with('success', __('Kategori pengeluaran berhasil diperbarui.'));
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->delete();

        return redirect()
            ->route('expense-categories.index')
            ->with('success', __('Kategori pengeluaran berhasil dihapus.'));
    }
}

