<x-app-layout>
    <x-slot name="title">{{ __('Edit Unit') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Edit Unit') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $product->sku }} — {{ $unit->serial_number }}</p>
            </div>
            <x-icon-btn-back :href="route('products.show', $product)" :label="__('Kembali')" />
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto">
        <div class="card-modern overflow-hidden">
            <div class="p-6">
                <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                    <h4 class="text-sm font-semibold text-slate-800 mb-2">{{ __('Informasi Unit') }}</h4>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                        <dt class="text-slate-500">{{ __('SKU') }}</dt>
                        <dd>{{ $product->sku }}</dd>
                        <dt class="text-slate-500">{{ __('Serial') }}</dt>
                        <dd class="font-mono">{{ $unit->serial_number }}</dd>
                        <dt class="text-slate-500">{{ __('Status') }}</dt>
                        <dd>{{ $unit->status }}</dd>
                        <dt class="text-slate-500">{{ __('Lokasi') }}</dt>
                        <dd>
                            @php
                                $locLabel = $unit->location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang');
                                $locName = $unit->location_type === \App\Models\Stock::LOCATION_WAREHOUSE
                                    ? ($unit->warehouse?->name ?? '#'.$unit->location_id)
                                    : ($unit->branch?->name ?? '#'.$unit->location_id);
                            @endphp
                            {{ $locLabel }}: {{ $locName }}
                        </dd>
                    </dl>
                    <p class="mt-3 text-xs text-amber-700">{{ __('Perubahan harga unit tidak mengubah nilai transaksi penjualan yang sudah released.') }}</p>
                </div>

                <form method="POST" action="{{ route('products.units.update', [$product, $unit]) }}" id="unit-edit-form">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-4">
                        <div>
                            <x-input-label for="harga_hpp" :value="__('Harga HPP (Rp)')" />
                            <x-text-input id="harga_hpp" name="harga_hpp" type="text" data-rupiah="true" :value="old('harga_hpp', $unit->harga_hpp)" required />
                            <x-input-error :messages="$errors->get('harga_hpp')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="harga_jual" :value="__('Harga Jual (Rp)')" />
                            <x-text-input id="harga_jual" name="harga_jual" type="text" data-rupiah="true" :value="old('harga_jual', $unit->harga_jual)" required />
                            <x-input-error :messages="$errors->get('harga_jual')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="notes" :value="__('Catatan')" />
                            <textarea id="notes" name="notes" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $unit->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>
                        <div class="flex gap-4 pt-2">
                            <x-primary-button type="submit">{{ __('Simpan') }}</x-primary-button>
                            <a href="{{ route('products.show', $product) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('unit-edit-form')?.addEventListener('submit', function () {
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
