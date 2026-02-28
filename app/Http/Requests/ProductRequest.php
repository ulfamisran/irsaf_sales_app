<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        $productId = $this->route('product');
        $uniqueSku = $productId
            ? 'unique:products,sku,' . $productId
            : 'unique:products,sku';

        return [
            'category_id' => ['required', 'exists:categories,id'],
            'sku' => ['required', 'string', 'max:255', $uniqueSku],
            'brand' => ['required', 'string', 'max:255'],
            'series' => ['nullable', 'string', 'max:255'],
            'specs' => ['nullable', 'string'],
            'laptop_type' => ['required', 'in:baru,bekas'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
