<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'category_id' => ['required', 'exists:categories,id'],
            'distributor_id' => ['required', 'exists:distributors,id'],
            'brand' => ['required', 'string', 'max:255'],
            'series' => ['nullable', 'string', 'max:255'],
            'processor' => ['nullable', 'string', 'max:255'],
            'ram' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'specs' => ['nullable', 'string'],
            'laptop_type' => ['required', 'in:baru,bekas'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
        ];

        $rules['location_type'] = ['required', 'in:warehouse,branch'];
        $locType = $this->input('location_type');
        $rules['location_id'] = [
            'required',
            'integer',
            'min:1',
            $locType === 'warehouse' ? Rule::exists('warehouses', 'id') : Rule::exists('branches', 'id'),
        ];

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->routeIs('products.update') && ! $this->has('location_type')) {
            $user = $this->user();
            if ($user?->hasAnyRole([Role::ADMIN_GUDANG])) {
                if ($user->warehouse_id) {
                    $this->merge(['location_type' => 'warehouse', 'location_id' => $user->warehouse_id]);
                } elseif ($user->branch_id) {
                    $this->merge(['location_type' => 'branch', 'location_id' => $user->branch_id]);
                }
            }
        }
    }
}
