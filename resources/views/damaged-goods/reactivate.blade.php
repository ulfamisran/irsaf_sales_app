<x-app-layout>
    <x-slot name="title">{{ __('Aktifkan Kembali Barang') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Aktifkan Kembali ke Stok') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                        <h4 class="text-sm font-semibold text-slate-800 mb-2">{{ __('Informasi Barang') }}</h4>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                            <dt class="text-slate-500">Produk:</dt>
                            <dd>{{ $damagedGood->productUnit?->product?->sku ?? '-' }} - {{ $damagedGood->productUnit?->product?->brand ?? '' }} {{ $damagedGood->productUnit?->product?->series ?? '' }}</dd>
                            <dt class="text-slate-500">No. Serial:</dt>
                            <dd class="font-mono">{{ $damagedGood->serial_number }}</dd>
                            <dt class="text-slate-500">Tanggal Pencatatan:</dt>
                            <dd>{{ $damagedGood->recorded_date->format('d/m/Y') }}</dd>
                            <dt class="text-slate-500">Deskripsi Kerusakan:</dt>
                            <dd class="sm:col-span-2">{{ $damagedGood->damage_description }}</dd>
                            <dt class="text-slate-500">HPP Tercatat:</dt>
                            <dd>Rp {{ number_format($damagedGood->harga_hpp, 0, ',', '.') }}</dd>
                        </dl>
                    </div>

                    <form method="POST" action="{{ route('damaged-goods.reactivate', $damagedGood) }}">
                        @csrf
                        <p class="text-sm text-slate-600 mb-4">{{ __('Masukkan HPP dan Harga Jual baru untuk unit ini. Status akan diubah menjadi In Stock.') }}</p>
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="harga_hpp" :value="__('Harga HPP (Rp)')" />
                                <x-text-input id="harga_hpp" name="harga_hpp" type="text" data-rupiah="true" :value="old('harga_hpp', $damagedGood->harga_hpp)" required />
                                <x-input-error :messages="$errors->get('harga_hpp')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="harga_jual" :value="__('Harga Jual (Rp)')" />
                                <x-text-input id="harga_jual" name="harga_jual" type="text" data-rupiah="true" :value="old('harga_jual', $damagedGood->productUnit?->harga_jual)" required />
                                <x-input-error :messages="$errors->get('harga_jual')" class="mt-2" />
                            </div>
                            <div class="flex gap-4 pt-4">
                                <x-primary-button type="submit">{{ __('Aktifkan Kembali') }}</x-primary-button>
                                <a href="{{ route('damaged-goods.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.querySelector('form')?.addEventListener('submit', function() {
            document.querySelectorAll('[data-rupiah="true"]').forEach(inp => {
                if (typeof window.parseRupiahToNumber === 'function') {
                    const num = window.parseRupiahToNumber(inp.value);
                    inp.value = num > 0 ? num : '';
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
