<x-app-layout>
    <x-slot name="title">{{ __('Detail Unit') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Detail Unit') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $unit->product?->sku }} - {{ $unit->product?->brand }} {{ $unit->product?->series }}</p>
            </div>
            <div class="flex gap-2">
                @if (auth()->user()?->isSuperAdminOrAdminPusat() && $unit->product)
                    <x-icon-btn-edit :href="route('products.units.edit', [$unit->product, $unit])" :label="__('Update Unit')" />
                @endif
                <x-icon-btn-back :href="route('stock-units.index')" :label="__('Kembali')" />
            </div>
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
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Harga HPP') }}</p>
                        <p class="font-medium text-slate-800">{{ number_format($unit->harga_hpp ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Harga Jual') }}</p>
                        <p class="font-medium text-slate-800">{{ number_format($unit->harga_jual ?? 0, 0, ',', '.') }}</p>
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

        @if (auth()->user()?->isSuperAdminOrAdminPusat())
        <div class="card-modern overflow-hidden mt-6">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-700">{{ __('Log Perubahan Unit') }}</h3>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Unit') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Perubahan') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Alasan') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($unit->changeLogs as $log)
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $log->changed_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $log->serial_snapshot ?? $unit->serial_number }}</td>
                                <td class="px-3 py-2">
                                    <ul class="space-y-0.5 text-xs text-slate-700">
                                        @foreach (($log->changes ?? []) as $field => $diff)
                                            <li>
                                                <span class="font-medium">{{ $field }}</span>:
                                                <span class="text-rose-600">{{ is_numeric($diff['old'] ?? null) ? number_format((float) $diff['old'], 0, ',', '.') : ($diff['old'] ?? '-') }}</span>
                                                →
                                                <span class="text-emerald-700">{{ is_numeric($diff['new'] ?? null) ? number_format((float) $diff['new'], 0, ',', '.') : ($diff['new'] ?? '-') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-3 py-2">{{ $log->reason }}</td>
                                <td class="px-3 py-2">{{ $log->user?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-slate-500">{{ __('Belum ada log perubahan unit.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
