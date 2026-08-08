<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_serial' => $this->boolean('requires_serial'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof \Illuminate\Database\Eloquent\Model
            ? $category->getKey()
            : $category;
        $uniqueCode = $categoryId !== null
            ? 'unique:categories,code,' . $categoryId
            : 'unique:categories,code';

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', $uniqueCode],
            'description' => ['nullable', 'string'],
            'requires_serial' => ['boolean'],
        ];
    }
}
