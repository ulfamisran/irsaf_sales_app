<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isSuperAdmin() || $user?->isAdminPusat();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'harga_hpp' => $this->parseRupiah($this->input('harga_hpp')),
            'harga_jual' => $this->parseRupiah($this->input('harga_jual')),
        ]);
    }

    public function rules(): array
    {
        return [
            'harga_hpp' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function parseRupiah(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $str = preg_replace('/[^\d]/', '', (string) $value);

        return $str !== '' ? (float) $str : null;
    }
}
