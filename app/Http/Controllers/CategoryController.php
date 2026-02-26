<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryRepository $categoryRepository
    ) {}

    /**
     * Display a listing of categories.
     */
    public function index(Request $request): View
    {
        $categories = $this->categoryRepository->paginate(15, [
            'search' => $request->get('search'),
        ]);
        return view('categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        return view('categories.create');
    }

    /**
     * Store a newly created category.
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->categoryRepository->create($request->validated());
        return redirect()->route('categories.index')->with('success', __('Category created successfully.'));
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category): View
    {
        return view('categories.edit', compact('category'));
    }

    /**
     * Update the specified category.
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categoryRepository->update($category, $request->validated());
        return redirect()->route('categories.index')->with('success', __('Category updated successfully.'));
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $this->categoryRepository->delete($category);
        return redirect()->route('categories.index')->with('success', __('Category deleted successfully.'));
    }
}
