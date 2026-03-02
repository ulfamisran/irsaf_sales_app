<x-app-layout>
    <x-slot name="title">{{ __('Detail Unit') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Detail Unit') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $unit->product?->sku }} - {{ $unit->product?->brand }} {{ $unit->product?->series }}</p>
            </div>
            <x-icon-btn-back :href="route('stock-units.index')" :label="__('Kembali')" />
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Data Produk') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('SKU') }}</p>
                        <p class="font-medium text-slate-800">{{ $unit->product?->sku }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Merek') }}</p>
                        <p class="font-medium text-slate-800">{{ $unit->product?->brand }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Seri') }}</p>
                        <p class="font-medium text-slate-800">{{ $unit->product?->series ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Kategori') }}</p>
                        <p class="font-medium text-slate-800">{{ $unit->product?->category?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Distributor') }}</p>
                        <p class="font-medium text-slate-800">{{ $unit->product?->distributor?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Jenis Laptop') }}</p>
                        <p class="font-medium text-slate-800">{{ $unit->product?->laptop_type ? ucfirst($unit->product?->laptop_type) : '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Harga Beli') }}</p>
                        <p class="font-medium text-slate-800">{{ number_format($unit->product?->purchase_price ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Harga Jual') }}</p>
                        <p class="font-medium text-slate-800">{{ number_format($unit->product?->selling_price ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                @if ($unit->product?->specs)
                    <div class="mt-4">
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Spesifikasi') }}</p>
                        <p class="text-slate-700 whitespace-pre-line">{{ $unit->product?->specs }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Data Unit') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Serial') }}</p>
                        <p class="font-medium text-slate-800 font-mono">{{ $unit->serial_number }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Status') }}</p>
                        <p class="font-medium text-slate-800">{{ $unit->status }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Lokasi') }}</p>
                        @php
                            $locationLabel = $unit->location_type === \App\Models\Stock::LOCATION_WAREHOUSE
                                ? __('Gudang')
                                : __('Cabang');
                            $locationName = $unit->location_type === \App\Models\Stock::LOCATION_WAREHOUSE
                                ? ($unit->warehouse?->name ?? ('#'.$unit->location_id))
                                : ($unit->branch?->name ?? ('#'.$unit->location_id));
                        @endphp
                        <p class="font-medium text-slate-800">{{ $locationLabel }}: {{ $locationName }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Tanggal Masuk') }}</p>
                        <p class="font-medium text-slate-800">{{ $unit->received_date?->format('d/m/Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Tanggal Sold Out') }}</p>
                        <p class="font-medium text-slate-800">{{ $unit->sold_at?->format('d/m/Y H:i') ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Input Oleh') }}</p>
                        <p class="font-medium text-slate-800">{{ $unit->user?->name ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Informasi Penjualan') }}</h3>
                @if ($saleInfo)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('No. Invoice') }}</p>
                            <p class="font-medium text-slate-800">{{ $saleInfo->invoice_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Tanggal Penjualan') }}</p>
                            <p class="font-medium text-slate-800">{{ $saleInfo->sale_date?->format('d/m/Y') ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Cabang') }}</p>
                            <p class="font-medium text-slate-800">{{ $saleInfo->branch?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Kasir') }}</p>
                            <p class="font-medium text-slate-800">{{ $saleInfo->user?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Pelanggan') }}</p>
                            <p class="font-medium text-slate-800">{{ $saleInfo->customer?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('No. HP Pelanggan') }}</p>
                            <p class="font-medium text-slate-800">{{ $saleInfo->customer?->phone ?? '-' }}</p>
                        </div>
                    </div>
                @else
                    <p class="text-slate-500">{{ __('Belum ada informasi penjualan untuk unit ini.') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
