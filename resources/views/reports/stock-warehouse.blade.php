<x-app-layout>
    <x-slot name="title">{{ __('Laporan Stok Gudang') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Laporan Stok Gudang') }}</h2>
            <x-icon-btn-back :href="route('reports.index')" :label="__('Kembali ke Laporan')" />
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('reports.stock-warehouse') }}" class="flex flex-wrap gap-4 items-end">
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Gudang') }}</label>
                        <select name="warehouse_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($warehouses as $w)
                                <option value="{{ $w->id }}" {{ request('warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Produk') }}</label>
                        <select name="product_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->sku }} - {{ $p->brand }}</option>
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
                        <a href="{{ route('reports.stock-warehouse') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kategori') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($stocks as $s)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">{{ $s->product?->sku }} - {{ $s->product?->brand }} {{ $s->product?->series }}</td>
                                <td class="px-4 py-3">{{ $s->product?->category?->name }}</td>
                                <td class="px-4 py-3">{{ $s->location?->name ?? '#' . $s->location_id }}</td>
                                <td class="px-4 py-3 text-right">{{ $s->quantity }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data stok.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $stocks->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
