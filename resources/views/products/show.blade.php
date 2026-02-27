<x-app-layout>
    <x-slot name="title">{{ __('Detail Produk') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Detail Produk') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $product->sku }} - {{ $product->brand }} {{ $product->series }}</p>
            </div>
            <div class="flex gap-2">
                <x-icon-btn-edit :href="route('products.edit', $product)" />
                <x-icon-btn-back :href="route('products.index')" :label="__('Kembali')" />
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Kategori') }}</p>
                        <p class="font-medium text-slate-800">{{ $product->category?->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Harga Beli') }}</p>
                        <p class="font-medium text-slate-800">{{ number_format($product->purchase_price, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Harga Jual') }}</p>
                        <p class="font-medium text-slate-800">{{ number_format($product->selling_price, 0, ',', '.') }}</p>
                    </div>
                </div>
                @if ($product->specs)
                    <div class="mt-4">
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Spesifikasi') }}</p>
                        <p class="text-slate-700 whitespace-pre-line">{{ $product->specs }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('products.show', $product) }}" class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status') }}</label>
                        <select name="status" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="in_stock" {{ request('status') === 'in_stock' ? 'selected' : '' }}>{{ __('In Stock') }}</option>
                            <option value="sold" {{ request('status') === 'sold' ? 'selected' : '' }}>{{ __('Sold') }}</option>
                        </select>
                    </div>
                    <div class="min-w-[180px]">
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
                                <option value="{{ $w->id }}" {{ request('location_id') == $w->id ? 'selected' : '' }}>{{ __('Warehouse') }}: {{ $w->name }}</option>
                            @endforeach
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ request('location_id') == $b->id ? 'selected' : '' }}>{{ __('Branch') }}: {{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Serial') }}</label>
                        <input type="text" name="serial" value="{{ request('serial') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="SN..." />
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('products.show', $product) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial Number') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Received') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Sold') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($units as $u)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-mono text-sm">{{ $u->serial_number }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $u->status === 'in_stock' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                                        {{ $u->status }}
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
                                <td class="px-4 py-3">{{ $u->received_date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $u->sold_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-slate-500">{{ __('Belum ada unit/serial untuk produk ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $units->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>

