<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class PaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'jenis_pembayaran' => ['required', 'string', 'max:50'],
            'nama_bank' => ['nullable', 'string', 'max:100'],
            'atas_nama_bank' => ['nullable', 'string', 'max:150'],
            'no_rekening' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $user = $this->user();
        if ($user && $user->isSuperAdminOrAdminPusat()) {
            $rules['placement_type'] = ['required', 'in:'.User::PLACEMENT_CABANG.','.User::PLACEMENT_GUDANG];
            $rules['branch_id'] = ['nullable', 'required_if:placement_type,'.User::PLACEMENT_CABANG, 'exists:branches,id'];
            $rules['warehouse_id'] = ['nullable', 'required_if:placement_type,'.User::PLACEMENT_GUDANG, 'exists:warehouses,id'];
        }

        return $rules;
    }
}

