<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleHppRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isSuperAdmin() || $user?->isAdminPusat();
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        foreach ($items as $i => $item) {
            if (isset($item['hpp'])) {
                $items[$i]['hpp'] = $this->parseRupiah($item['hpp']);
            }
        }
        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_detail_id' => ['required', 'integer', 'exists:sale_details,id'],
            'items.*.serial' => ['nullable', 'string', 'max:255'],
            'items.*.hpp' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
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
