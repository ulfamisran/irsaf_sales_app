<x-app-layout>
    <x-slot name="title">{{ __('Update Hasil Cek') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Update Hasil Cek') }}</h2>
            <a href="{{ route('purchase-receiving.history', $log->purchase) }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">{{ __('Kembali') }}</a>
        </div>
    </x-slot>

    <div class="max-w-xl mx-auto">
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6 space-y-4">
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 text-sm space-y-1">
                    <p><span class="text-slate-500">{{ __('Invoice') }}:</span> <strong>{{ $log->purchase?->invoice_number }}</strong></p>
                    <p><span class="text-slate-500">{{ __('Waktu cek') }}:</span> {{ $log->checked_at?->format('d/m/Y H:i') }}</p>
                    <p><span class="text-slate-500">{{ __('Dicek oleh') }}:</span> {{ $log->checker?->name ?? '-' }}</p>
                    @if ($log->productUnit)
                        <p><span class="text-slate-500">{{ __('Serial') }}:</span> <span class="font-mono">{{ $log->productUnit->serial_number }}</span></p>
                    @else
                        <p><span class="text-slate-500">{{ __('Produk') }}:</span>
                            {{ trim(($log->purchaseDetail?->product?->sku ?? '').' '.($log->purchaseDetail?->product?->brand ?? '').' '.($log->purchaseDetail?->product?->series ?? '')) }}
                        </p>
                        <p><span class="text-slate-500">{{ __('Qty') }}:</span> {{ $log->qty }}</p>
                    @endif
                    <p><span class="text-slate-500">{{ __('Hasil saat ini') }}:</span> <strong>{{ $log->resultLabel() }}</strong></p>
                </div>

                <form method="POST" action="{{ route('purchase-receiving.logs.update', $log) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <x-input-label for="result" :value="__('Hasil Cek Baru')" />
                        <select id="result" name="result" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="baik" {{ old('result', $log->result) === 'baik' ? 'selected' : '' }}>{{ __('Baik → In Stock') }}</option>
                            <option value="reture" {{ old('result', $log->result) === 'reture' ? 'selected' : '' }}>{{ __('Reture') }}</option>
                            <option value="pending" {{ old('result', $log->result) === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                        </select>
                        <x-input-error :messages="$errors->get('result')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="notes" :value="__('Catatan (opsional)')" />
                        <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $log->notes) }}</textarea>
                    </div>
                    <p class="text-xs text-slate-500">{{ __('Perubahan dari/ke Baik akan menyesuaikan stok otomatis. Serial yang sudah terjual/keep tidak dapat diubah.') }}</p>
                    <div class="flex justify-end gap-2">
                        <a href="{{ route('purchase-receiving.index', ['tab' => 'riwayat']) }}" class="px-4 py-2.5 rounded-lg bg-gray-200 text-gray-700 text-sm font-semibold">{{ __('Batal') }}</a>
                        <button type="submit" class="px-4 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">{{ __('Simpan Perubahan') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
