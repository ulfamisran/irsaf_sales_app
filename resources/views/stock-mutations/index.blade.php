<x-app-layout>
    <x-slot name="title">{{ __('Mutasi Stok') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Distribusi Stok') }}</h2>
            <x-icon-btn-add :href="route('stock-mutations.create')" :label="__('Distribusi Baru')" />
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('stock-mutations.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Produk') }}</label>
                        <select name="product_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->sku }} - {{ $p->brand }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('stock-mutations.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Dari') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Ke') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($mutations as $mutation)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">{{ $mutation->mutation_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $mutation->product?->sku }} - {{ $mutation->product?->brand }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $fromIsWarehouse = $mutation->from_location_type === \App\Models\Stock::LOCATION_WAREHOUSE;
                                        $fromName = $fromIsWarehouse
                                            ? ($warehousesById[$mutation->from_location_id] ?? null)
                                            : ($branchesById[$mutation->from_location_id] ?? null);
                                        $fromLabel = $fromIsWarehouse ? __('Gudang') : __('Cabang');
                                    @endphp
                                    {{ $fromLabel }}: {{ $fromName ?? ('#'.$mutation->from_location_id) }}
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $toIsWarehouse = $mutation->to_location_type === \App\Models\Stock::LOCATION_WAREHOUSE;
                                        $toName = $toIsWarehouse
                                            ? ($warehousesById[$mutation->to_location_id] ?? null)
                                            : ($branchesById[$mutation->to_location_id] ?? null);
                                        $toLabel = $toIsWarehouse ? __('Gudang') : __('Cabang');
                                    @endphp
                                    {{ $toLabel }}: {{ $toName ?? ('#'.$mutation->to_location_id) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ $mutation->serial_numbers ? \Illuminate\Support\Str::limit(str_replace("\n", ', ', $mutation->serial_numbers), 40) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ $mutation->quantity }}</td>
                                <td class="px-4 py-3">{{ $mutation->user?->name }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data mutasi.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $mutations->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
