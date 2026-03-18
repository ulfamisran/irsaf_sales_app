<?php

namespace App\Http\Requests;

use App\Models\Stock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payments = $this->input('distribution_payments', []);
        foreach ($payments as $i => $p) {
            if (isset($p['amount'])) {
                $payments[$i]['amount'] = $this->parseRupiah($p['amount']);
            }
        }
        $this->merge(['distribution_payments' => $payments]);
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

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.serial_numbers' => ['nullable', 'array'],
            'items.*.serial_numbers.*' => ['string'],
            'items.*.biaya_distribusi_per_unit' => ['nullable', 'numeric', 'min:0'],
            'from_location_type' => ['required', Rule::in([Stock::LOCATION_WAREHOUSE, Stock::LOCATION_BRANCH])],
            'from_location_id' => ['required', 'integer', 'min:1'],
            'to_location_type' => ['required', Rule::in([Stock::LOCATION_WAREHOUSE, Stock::LOCATION_BRANCH])],
            'to_location_id' => ['required', 'integer', 'min:1'],
            'mutation_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'distribution_payments' => ['nullable', 'array'],
            'distribution_payments.*.payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'distribution_payments.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
