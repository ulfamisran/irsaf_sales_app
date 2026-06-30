<x-app-layout>
    <x-slot name="title">{{ __('Koreksi HPP Penjualan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Koreksi HPP Penjualan') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $sale->invoice_number }} — {{ $sale->sale_date?->format('d/m/Y') }}</p>
            </div>
            <x-icon-btn-back :href="route('sales.show', $sale)" :label="__('Kembali')" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800">
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                {{ __('Hanya nilai HPP yang dapat diubah. Total penjualan, harga jual, pembayaran, dan invoice tidak berubah. Perubahan memengaruhi perhitungan laba rugi.') }}
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('sales.update-hpp', $sale) }}" id="edit-hpp-form">
                        @csrf
                        @method('PATCH')

                        <div class="overflow-x-auto rounded-lg border border-gray-200 mb-6">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Harga Jual') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('HPP') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($hppRows as $i => $row)
                                        <tr>
                                            <td class="px-4 py-2">{{ $row['product_label'] }}</td>
                                            <td class="px-4 py-2 font-mono text-slate-600">{{ $row['serial'] ?? '-' }}</td>
                                            <td class="px-4 py-2 text-right text-slate-600">{{ number_format($row['price'], 0, ',', '.') }}</td>
                                            <td class="px-4 py-2 text-right text-slate-600">{{ $row['quantity'] }}</td>
                                            <td class="px-4 py-2 text-right">
                                                <input type="hidden" name="items[{{ $i }}][sale_detail_id]" value="{{ $row['sale_detail_id'] }}">
                                                @if ($row['serial'])
                                                    <input type="hidden" name="items[{{ $i }}][serial]" value="{{ $row['serial'] }}">
                                                @endif
                                                <x-text-input
                                                    name="items[{{ $i }}][hpp]"
                                                    type="text"
                                                    data-rupiah="true"
                                                    class="text-right w-full max-w-[160px] ml-auto"
                                                    :value="old('items.'.$i.'.hpp', $row['hpp'])"
                                                    required
                                                />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-6">
                            <x-input-label for="reason" :value="__('Alasan koreksi')" />
                            <textarea id="reason" name="reason" rows="3" required class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Wajib diisi untuk jejak audit') }}">{{ old('reason') }}</textarea>
                            <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                        </div>

                        <div class="flex gap-4">
                            <x-primary-button type="submit">{{ __('Simpan Koreksi HPP') }}</x-primary-button>
                            <a href="{{ route('sales.show', $sale) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('edit-hpp-form')?.addEventListener('submit', function () {
            document.querySelectorAll('[data-rupiah="true"]').forEach(function (inp) {
                if (typeof window.parseRupiahToNumber === 'function') {
                    const num = window.parseRupiahToNumber(inp.value);
                    if (!isNaN(num)) inp.value = String(num);
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
