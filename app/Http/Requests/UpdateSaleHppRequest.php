<?php

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSaleHppRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isSuperAdminOrAdminPusat();
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            $items = [];
        }

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (isset($item['hpp'])) {
                $items[$i]['hpp'] = $this->parseRupiah($item['hpp']);
            }
            if (array_key_exists('sale_detail_id', $item)) {
                $items[$i]['sale_detail_id'] = (int) $item['sale_detail_id'];
            }
        }
        $this->merge(['items' => array_values($items)]);
    }

    public function rules(): array
    {
        /** @var Sale|null $sale */
        $sale = $this->route('sale');
        $saleId = $sale?->id;

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_detail_id' => [
                'required',
                'integer',
                Rule::exists('sale_details', 'id')->where(
                    fn ($query) => $query->where('sale_id', $saleId)
                ),
            ],
            'items.*.hpp' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Sale|null $sale */
            $sale = $this->route('sale');
            if (! $sale) {
                return;
            }

            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            $expectedDetailIds = $sale->saleDetails()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $submittedDetailIds = collect($items)
                ->pluck('sale_detail_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($expectedDetailIds !== $submittedDetailIds) {
                $validator->errors()->add(
                    'items',
                    __('Semua barang pada penjualan ini harus disertakan dalam form koreksi HPP.')
                );
            }
        });
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
