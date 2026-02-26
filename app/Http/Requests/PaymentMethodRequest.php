<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jenis_pembayaran' => ['required', 'string', 'max:50'],
            'nama_bank' => ['nullable', 'string', 'max:100'],
            'atas_nama_bank' => ['nullable', 'string', 'max:150'],
            'no_rekening' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

