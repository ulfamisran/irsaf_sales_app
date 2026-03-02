<x-app-layout>
    <x-slot name="title">{{ __('Daftar Unit') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Daftar Unit') }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('stock-units.export', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                    {{ __('Download Excel') }}
                </a>
                <a href="{{ route('stock-units.export-pdf', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    {{ __('Download PDF') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('stock-units.index') }}" class="space-y-3">
                    <div style="width: 220px;">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Pencarian') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('SKU, serial, merek, seri...') }}"
                            class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex flex-wrap gap-3 items-end">
                        <div style="width: 220px;">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Produk') }}</label>
                            <select name="product_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->sku }} - {{ $p->brand }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-[200px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status Stok') }}</label>
                            <select name="status" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-[200px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi Tipe') }}</label>
                            <select name="location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                <option value="warehouse" {{ request('location_type') === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                <option value="branch" {{ request('location_type') === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                            </select>
                        </div>
                        <div class="min-w-[220px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                            <select name="location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" {{ request('location_id') == $w->id ? 'selected' : '' }}>{{ __('Gudang') }}: {{ $w->name }}</option>
                                @endforeach
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ request('location_id') == $b->id ? 'selected' : '' }}>{{ __('Cabang') }}: {{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                {{ __('Filter') }}
                            </button>
                            <a href="{{ route('stock-units.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                                {{ __('Reset') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('SKU') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Merek') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Seri') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Received') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Sold') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($productsPage as $product)
                            <tr class="bg-indigo-100" style="background-color: #e0e7ff;">
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ $product->sku }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $product->brand }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $product->series ?? '-' }}</td>
                                <td colspan="6" class="px-4 py-3">
                                    <span class="text-slate-700">{{ __('In Stock') }}: </span>
                                    <span class="font-semibold text-slate-800">{{ (int) ($inStockCounts[$product->id] ?? 0) }}</span>
                                </td>
                            </tr>
                            @forelse ($unitsByProduct->get($product->id, collect()) as $u)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-4 py-3 text-slate-600 text-sm">{{ $u->product?->sku ?? '-' }}</td>
                                    <td class="px-4 py-3 text-slate-600 text-sm">{{ $u->product?->brand ?? '-' }}</td>
                                    <td class="px-4 py-3 text-slate-600 text-sm">{{ $u->product?->series ?? '-' }}</td>
                                    <td class="px-4 py-3 font-mono text-sm">{{ $u->serial_number }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $u->status === \App\Models\ProductUnit::STATUS_IN_STOCK ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                                            {{ $statusOptions[$u->status] ?? $u->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $locationLabel = $u->location_type === \App\Models\Stock::LOCATION_WAREHOUSE
                                                ? __('Gudang')
                                                : __('Cabang');
                                            $locationName = $u->location_type === \App\Models\Stock::LOCATION_WAREHOUSE
                                                ? ($u->warehouse?->name ?? ('#'.$u->location_id))
                                                : ($u->branch?->name ?? ('#'.$u->location_id));
                                        @endphp
                                        {{ $locationLabel }}: {{ $locationName }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $u->received_date?->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $soldInfo = $soldInfoBySerial[$u->serial_number] ?? null;
                                            $soldDate = $u->sold_at
                                                ? $u->sold_at->format('d/m/Y H:i')
                                                : ($soldInfo?->sale_date?->format('d/m/Y') ?? null);
                                            $invoice = $soldInfo['invoice_number'] ?? null;
                                        @endphp
                                        @if ($soldDate || $invoice)
                                            <div class="text-sm text-slate-700">{{ $soldDate ?? '-' }}</div>
                                            @if ($invoice)
                                                <div class="text-xs text-slate-500">{{ $invoice }}</div>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-icon-btn-view :href="route('stock-units.show', $u)" :label="__('Detail')" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-6 text-center text-slate-500">{{ __('Tidak ada unit untuk produk ini.') }}</td>
                                </tr>
                            @endforelse
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data unit.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $productsPage->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
