<?php

namespace App\Http\Requests;

use App\Models\Role;
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
        $user = $this->user();
        $isBranchAdmin = $user && $user->hasAnyRole([Role::ADMIN_CABANG]);

        return [
            'category_id' => ['required', 'exists:categories,id'],
            'distributor_id' => ['required', 'exists:distributors,id'],
            'brand' => ['required', 'string', 'max:255'],
            'series' => ['nullable', 'string', 'max:255'],
            'processor' => ['nullable', 'string', 'max:255'],
            'ram' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'specs' => ['nullable', 'string'],
            'laptop_type' => $isBranchAdmin ? ['required', 'in:baru'] : ['required', 'in:baru,bekas'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
