<x-app-layout>
    <x-slot name="title">{{ __('Barang Rusak Cadangan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Barang Rusak Cadangan') }}</h2>
            <x-icon-btn-add :href="route('damaged-goods.create')" :label="__('Catat Barang Rusak')" />
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
                @php
                    $locType = request('location_type') ?? '';
                    if ($filterLocked ?? false) {
                        $locType = ($lockedWarehouseId ?? null) ? 'warehouse' : (($lockedBranchId ?? null) ? 'branch' : '');
                    }
                    $selectedLocationId = request('location_id');
                    if (! $selectedLocationId) {
                        $selectedLocationId = request('warehouse_id') ?: request('branch_id');
                    }
                    if ($filterLocked ?? false) {
                        $selectedLocationId = ($lockedWarehouseId ?? null) ?: ($lockedBranchId ?? null);
                    }
                @endphp
                <form method="GET" action="{{ route('damaged-goods.index') }}" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Search') }}</label>
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="{{ __('Produk, serial, user, deskripsi...') }}"
                               class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    @if(($canFilterLocation ?? false) || ($filterLocked ?? false))
                        @if($filterLocked ?? false)
                            <div>
                                <x-locked-location label="{{ __('Tipe Lokasi') }}" :value="$locType === 'warehouse' ? __('Gudang') : __('Cabang')" />
                            </div>
                            <div>
                                <x-locked-location label="{{ __('Lokasi') }}" :value="$locationLabel ?? ''" />
                                <input type="hidden" name="location_type" value="{{ $locType }}">
                                <input type="hidden" name="location_id" value="{{ $selectedLocationId }}">
                            </div>
                        @else
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                                <select name="location_type" id="dg_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="" {{ $locType === '' ? 'selected' : '' }}>{{ __('Semua') }}</option>
                                    <option value="warehouse" {{ $locType === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                    <option value="branch" {{ $locType === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                                <select name="location_id" id="dg_location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Semua') }}</option>
                                    @if ($locType === 'warehouse')
                                        @foreach ($warehouses ?? [] as $w)
                                            <option value="{{ $w->id }}" {{ (string)($selectedLocationId ?? '') === (string)$w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                        @endforeach
                                    @elseif ($locType === 'branch')
                                        @foreach ($branches ?? [] as $b)
                                            <option value="{{ $b->id }}" {{ (string)($selectedLocationId ?? '') === (string)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        @endif
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Kategori') }}</label>
                        <select name="category_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach(($categories ?? []) as $cat)
                                <option value="{{ $cat->id }}" {{ (string) request('category_id') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Produk') }}</label>
                        <select name="product_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach(($products ?? []) as $p)
                                <option value="{{ $p->id }}" {{ (string) request('product_id') === (string) $p->id ? 'selected' : '' }}>{{ $p->sku }} - {{ $p->brand }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2 flex gap-2 md:justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('damaged-goods.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-modern p-6 mb-6">
            <p class="text-sm text-slate-600">{{ __('Total Beban HPP Barang Rusak') }}</p>
            <p class="text-2xl font-bold text-rose-600">{{ number_format($totalHpp ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kategori') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Serial') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Deskripsi Kerusakan') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('HPP') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($damagedGoods as $dg)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">{{ $dg->recorded_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $dg->productUnit?->product?->category?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    {{ ($dg->productUnit?->product?->sku ?? '') . ' - ' . ($dg->productUnit?->product?->brand ?? '') . ' ' . ($dg->productUnit?->product?->series ?? '') }}
                                </td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $dg->serial_number }}</td>
                                <td class="px-4 py-3">
                                    @if ($dg->productUnit?->location_type === 'warehouse')
                                        {{ __('Gudang') }}: {{ $dg->productUnit?->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $dg->productUnit?->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm max-w-xs truncate" title="{{ $dg->damage_description }}">{{ Str::limit($dg->damage_description, 50) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-600">{{ number_format($dg->harga_hpp, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('damaged-goods.reactivate-form', $dg) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                                        {{ __('Aktifkan Kembali') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data barang rusak.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $damagedGoods->links() }}</div>
            </div>
        </div>
    </div>

    @if($canFilterLocation ?? false)
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locType = document.getElementById('dg_location_type');
            const locId = document.getElementById('dg_location_id');
            const warehouses = @json(($warehouses ?? collect())->map(fn($w) => ['id' => $w->id, 'name' => $w->name])->values());
            const branches = @json(($branches ?? collect())->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values());
            if (locType) {
                function renderLocationOptions(type, selected = '') {
                    if (!locId) return;
                    locId.innerHTML = `<option value="">{{ __('Semua') }}</option>`;
                    const rows = type === 'branch' ? branches : (type === 'warehouse' ? warehouses : []);
                    rows.forEach((row) => {
                        const option = document.createElement('option');
                        option.value = String(row.id);
                        option.textContent = row.name;
                        if (String(selected) === String(row.id)) {
                            option.selected = true;
                        }
                        locId.appendChild(option);
                    });
                }
                if (locId) {
                    renderLocationOptions(locType.value, locId.value);
                    locType.addEventListener('change', function() {
                        renderLocationOptions(this.value, '');
                    });
                }
            }
        });
    </script>
    @endpush
    @endif
</x-app-layout>
