<x-app-layout>
    <x-slot name="title">{{ __('Detail Barang Rusak') }} #{{ $damagedGood->id }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Detail Barang Rusak') }} #{{ $damagedGood->id }}</h2>
            <div class="flex gap-2">
                @if (! $damagedGood->isReactivated())
                    <a href="{{ route('damaged-goods.reactivate-form', $damagedGood) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                        {{ __('Aktifkan Kembali') }}
                    </a>
                @endif
                <x-icon-btn-back :href="route('damaged-goods.index')" :label="__('Kembali')" />
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto">
        <div class="card-modern overflow-hidden">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Tanggal Transaksi') }}</p>
                        <p class="font-medium">{{ $damagedGood->recorded_date?->format('d/m/Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Waktu Input') }}</p>
                        <p class="font-medium">{{ $damagedGood->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('User') }}</p>
                        <p class="font-medium">{{ $damagedGood->user?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Status') }}</p>
                        <p class="font-medium">
                            @if ($damagedGood->isReactivated())
                                <span class="px-2 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-700">{{ __('Diaktifkan Kembali') }}</span>
                            @else
                                <span class="px-2 py-1 rounded-lg text-xs font-medium bg-rose-100 text-rose-800">{{ __('Dicadangkan') }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-slate-500">{{ __('Produk') }}</p>
                        <p class="font-medium">
                            {{ ($damagedGood->productUnit?->product?->sku ?? '') }} - {{ $damagedGood->productUnit?->product?->brand ?? '' }} {{ $damagedGood->productUnit?->product?->series ?? '' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Kategori') }}</p>
                        <p class="font-medium">{{ $damagedGood->productUnit?->product?->category?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('No. Serial') }}</p>
                        <p class="font-medium font-mono">{{ $damagedGood->serial_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Lokasi') }}</p>
                        <p class="font-medium">
                            @if ($damagedGood->productUnit?->location_type === 'warehouse')
                                {{ __('Gudang') }}: {{ $damagedGood->productUnit?->warehouse?->name ?? '-' }}
                            @else
                                {{ __('Cabang') }}: {{ $damagedGood->productUnit?->branch?->name ?? '-' }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('HPP') }}</p>
                        <p class="font-medium text-rose-600">{{ number_format((float) $damagedGood->harga_hpp, 0, ',', '.') }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-slate-500">{{ __('Deskripsi Kerusakan') }}</p>
                        <p class="text-slate-700 whitespace-pre-line">{{ $damagedGood->damage_description ?: '-' }}</p>
                    </div>
                    @if ($damagedGood->isReactivated())
                        <div>
                            <p class="text-sm text-slate-500">{{ __('Diaktifkan Pada') }}</p>
                            <p class="font-medium">{{ $damagedGood->reactivated_at?->format('d/m/Y H:i') ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">{{ __('Diaktifkan Oleh') }}</p>
                            <p class="font-medium">{{ $damagedGood->reactivatedByUser?->name ?? '-' }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
