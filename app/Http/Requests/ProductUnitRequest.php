<?php

namespace App\Http\Requests;

use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'serial_number' => trim((string) $this->input('serial_number', '')),
        ]);
    }

    public function rules(): array
    {
        /** @var ProductUnit|null $unit */
        $unit = $this->route('unit');

        return [
            'serial_number' => [
                'required',
                'string',
                'max:191',
                Rule::unique('product_units', 'serial_number')->ignore($unit?->id),
            ],
            'harga_hpp' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'status' => [
                'required',
                'string',
                Rule::in([
                    ProductUnit::STATUS_IN_STOCK,
                    ProductUnit::STATUS_KEEP,
                    ProductUnit::STATUS_RESERVED,
                    ProductUnit::STATUS_SOLD,
                    ProductUnit::STATUS_IN_RENT,
                    ProductUnit::STATUS_INACTIVE,
                    ProductUnit::STATUS_CANCEL,
                    ProductUnit::STATUS_NOT_IN_STOCK,
                    ProductUnit::STATUS_RETURE,
                    ProductUnit::STATUS_PENDING,
                ]),
            ],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => __('Alasan perubahan wajib diisi.'),
            'serial_number.unique' => __('Nomor serial sudah digunakan unit lain.'),
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
