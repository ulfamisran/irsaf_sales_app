<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRelease = false;
        if ($this->routeIs('services.store')) {
            $isRelease = $this->input('status', 'open') === 'release';
        } elseif ($this->routeIs('services.update')) {
            $isRelease = (bool) $this->input('mark_release');
        }

        return [
            'status' => [$this->routeIs('services.store') ? 'required' : 'nullable', 'in:open,release'],
            'mark_release' => ['nullable', 'boolean'],
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_new_name' => ['nullable', 'string', 'max:255', 'required_without:customer_id'],
            'customer_new_phone' => ['nullable', 'string', 'max:30'],
            'customer_new_address' => ['nullable', 'string'],

            'laptop_type' => ['required', 'string', 'max:100'],
            'laptop_detail' => ['nullable', 'string'],
            'damage_description' => ['nullable', 'string'],
            'service_fee' => [$isRelease ? 'required' : 'nullable', 'numeric', 'min:0'],
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],

            'payments' => array_filter([$isRelease ? 'required' : 'nullable', 'array', $isRelease ? 'min:1' : null]),
            'payments.*.payment_method_id' => array_filter(['nullable', 'exists:payment_methods,id', $isRelease ? 'required' : null]),
            'payments.*.amount' => array_filter(['nullable', 'numeric', $isRelease ? 'min:0.01' : 'min:0', $isRelease ? 'required' : null]),
            'payments.*.notes' => ['nullable', 'string'],

            'materials' => ['nullable', 'array'],
            'materials.*.name' => ['nullable', 'string', 'max:150'],
            'materials.*.quantity' => ['nullable', 'numeric', 'min:0.01', 'required_with:materials.*.name'],
            'materials.*.payment_method_id' => ['nullable', 'exists:payment_methods,id', 'required_with:materials.*.name'],
            'materials.*.price' => ['nullable', 'numeric', 'min:0', 'required_with:materials.*.name'],
            'materials.*.notes' => ['nullable', 'string'],
        ];
    }
}
