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
                <form method="GET" action="{{ route('stock-units.index') }}" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Pencarian') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('SKU, serial, merek, seri...') }}"
                            class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        @if($canFilterLocation ?? false)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi Tipe') }}</label>
                            <select id="su_location_type" name="location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">{{ __('Semua') }}</option>
                                <option value="warehouse" {{ request('location_type') === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                <option value="branch" {{ request('location_type') === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                            </select>
                        </div>
                        @php
                            $selectedLocationType = (string) request('location_type', '');
                        @endphp
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                            <select name="location_id" id="su_location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">{{ __('Semua') }}</option>
                                @if ($selectedLocationType === 'warehouse')
                                    @foreach ($warehouses as $w)
                                        <option value="{{ $w->id }}" {{ request('location_id') == $w->id ? 'selected' : '' }}>{{ __('Gudang') }}: {{ $w->name }}</option>
                                    @endforeach
                                @elseif ($selectedLocationType === 'branch')
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}" {{ request('location_id') == $b->id ? 'selected' : '' }}>{{ __('Cabang') }}: {{ $b->name }}</option>
                                    @endforeach
                                @else
                                    @foreach ($warehouses as $w)
                                        <option value="{{ $w->id }}" {{ request('location_id') == $w->id ? 'selected' : '' }}>{{ __('Gudang') }}: {{ $w->name }}</option>
                                    @endforeach
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}" {{ request('location_id') == $b->id ? 'selected' : '' }}>{{ __('Cabang') }}: {{ $b->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        @elseif($filterLocked ?? false)
                        <div>
                            <x-locked-location label="{{ __('Tipe Lokasi') }}" :value="str_contains((string) ($locationLabel ?? ''), __('Cabang')) ? __('Cabang') : __('Gudang')" />
                        </div>
                        <div>
                            <x-locked-location label="{{ __('Lokasi') }}" :value="$locationLabel ?? ''" />
                            <input type="hidden" name="location_type" value="{{ $locationType }}">
                            <input type="hidden" name="location_id" value="{{ $locationId }}">
                        </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Kategori Produk') }}</label>
                            <select name="category_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($categories ?? [] as $cat)
                                    <option value="{{ $cat->id }}" {{ (string) request('category_id') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Produk') }}</label>
                            <select name="product_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->sku }} - {{ $p->brand }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status Stok') }}</label>
                            <select name="status" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2 md:justify-end">
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

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-slate-700">{{ __('Total In Stock per Kategori') }}</h3>
                <p class="text-xs text-slate-500">{{ __('Mengikuti filter aktif pada daftar unit') }}</p>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @forelse(($inStockCategoryTotals ?? collect()) as $item)
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-xs text-slate-500">{{ $item['category_name'] ?? '-' }}</p>
                            <p class="text-lg font-semibold text-indigo-700">{{ number_format((int) ($item['total'] ?? 0), 0, ',', '.') }}</p>
                        </div>
                    @empty
                        <div class="col-span-full text-sm text-slate-500">{{ __('Tidak ada barang in stock untuk filter saat ini.') }}</div>
                    @endforelse
                </div>
                <div class="mt-4 pt-3 border-t border-slate-200">
                    <p class="text-sm text-slate-600">{{ __('Total Seluruh In Stock') }}</p>
                    <p class="text-xl font-semibold text-emerald-600">{{ number_format((int) ($totalInStockUnits ?? 0), 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('SKU') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kategori') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Merek') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Type/Seri') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase min-w-[12rem]">{{ __('Spesifikasi Lengkap') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Distributor') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('HPP') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Harga Jual') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-slate-500 uppercase w-28">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Received') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Sold') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @php $tid = $tradeInProductIds ?? []; @endphp
                        @forelse ($productsPage as $product)
                            @php
                                $isTradeIn = isset($tid[$product->id]);
                                $kategoriLabel = $isTradeIn ? __('Tukar tambah') : ($product->laptop_type ? ucfirst($product->laptop_type) : '-');
                                $specsParts = array_filter([
                                    $product->processor ? 'Prosesor: ' . $product->processor : null,
                                    $product->ram ? 'RAM: ' . $product->ram : null,
                                    $product->storage ? 'Storage: ' . $product->storage : null,
                                    $product->color ? 'Warna: ' . $product->color : null,
                                    $product->specs ? trim($product->specs) : null,
                                ]);
                                $specsText = implode(' | ', $specsParts) ?: '-';
                            @endphp
                            <tr class="bg-indigo-100" style="background-color: #e0e7ff;">
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ $product->sku }}</td>
                                <td class="px-4 py-3 text-slate-700"><span class="px-2 py-0.5 rounded text-xs {{ $isTradeIn ? 'bg-amber-100 text-amber-800' : ($product->laptop_type === 'bekas' ? 'bg-slate-100 text-slate-700' : 'bg-blue-50 text-blue-800') }}">{{ $kategoriLabel }}</span></td>
                                <td class="px-4 py-3 text-slate-700">{{ $product->brand }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $product->series ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700 text-sm max-w-[14rem]" title="{{ $specsText }}">{{ Str::limit($specsText, 50) }}</td>
                                <td class="px-4 py-3">—</td>
                                <td class="px-4 py-3 text-slate-700">{{ $product->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">—</td>
                                <td class="px-4 py-3">—</td>
                                <td colspan="5" class="px-4 py-3 text-slate-700">
                                    <span class="font-semibold">{{ __('In Stock') }}: {{ (int) ($inStockCounts[$product->id] ?? 0) }}</span>
                                </td>
                            </tr>
                            @forelse ($unitsByProduct->get($product->id, collect()) as $u)
                                @php
                                    $p = $u->product;
                                    $uTradeIn = isset($tid[$p->id ?? 0]);
                                    $uKategori = $uTradeIn ? __('Tukar tambah') : ($p->laptop_type ? ucfirst($p->laptop_type) : '-');
                                    $uSpecsParts = $p ? array_filter([
                                        $p->processor ? 'Prosesor: ' . $p->processor : null,
                                        $p->ram ? 'RAM: ' . $p->ram : null,
                                        $p->storage ? 'Storage: ' . $p->storage : null,
                                        $p->color ? 'Warna: ' . $p->color : null,
                                        $p->specs ? trim($p->specs) : null,
                                    ]) : [];
                                    $uSpecsText = implode(' | ', $uSpecsParts) ?: '-';
                                @endphp
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-4 py-3 text-slate-600 text-sm">{{ $p?->sku ?? '-' }}</td>
                                    <td class="px-4 py-3 text-slate-600 text-sm"><span class="px-2 py-0.5 rounded text-xs {{ $uTradeIn ? 'bg-amber-100 text-amber-800' : ($p && $p->laptop_type === 'bekas' ? 'bg-slate-100 text-slate-700' : 'bg-blue-50 text-blue-800') }}">{{ $uKategori }}</span></td>
                                    <td class="px-4 py-3 text-slate-600 text-sm">{{ $p?->brand ?? '-' }}</td>
                                    <td class="px-4 py-3 text-slate-600 text-sm">{{ $p?->series ?? '-' }}</td>
                                    <td class="px-4 py-3 text-slate-600 text-sm max-w-[14rem]" title="{{ $uSpecsText }}">{{ Str::limit($uSpecsText, 50) }}</td>
                                    <td class="px-4 py-3 font-mono text-sm">{{ $u->serial_number }}</td>
                                    <td class="px-4 py-3 text-slate-600 text-sm">{{ $p?->distributor?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700 text-right">{{ number_format($u->harga_hpp ?? 0, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700 text-right">{{ number_format($u->harga_jual ?? 0, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium @class([
                                            'bg-emerald-100 text-emerald-800' => $u->status === \App\Models\ProductUnit::STATUS_IN_STOCK,
                                            'bg-amber-100 text-amber-900' => $u->status === \App\Models\ProductUnit::STATUS_NOT_IN_STOCK,
                                            'bg-slate-100 text-slate-800' => ! in_array($u->status, [\App\Models\ProductUnit::STATUS_IN_STOCK, \App\Models\ProductUnit::STATUS_NOT_IN_STOCK], true),
                                        ])">
                                            {{ $statusOptions[$u->status] ?? $u->status }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-3 text-xs text-slate-600 max-w-[7rem] truncate" title="{{ ($u->location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang')) }}: {{ $u->location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? ($u->warehouse?->name ?? '#'.$u->location_id) : ($u->branch?->name ?? '#'.$u->location_id) }}">
                                        @php
                                            $locationLabel = $u->location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang');
                                            $locationName = $u->location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? ($u->warehouse?->name ?? ('#'.$u->location_id)) : ($u->branch?->name ?? ('#'.$u->location_id));
                                        @endphp
                                        {{ $locationLabel }}: {{ $locationName }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $u->received_date?->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $soldInfo = $soldInfoBySerial[$u->serial_number] ?? null;
                                            $soldDateFromSale = data_get($soldInfo, 'sale_date');
                                            $soldDate = $u->sold_at
                                                ? $u->sold_at->format('d/m/Y H:i')
                                                : ($soldDateFromSale ? \Illuminate\Support\Carbon::parse($soldDateFromSale)->format('d/m/Y') : null);
                                            $invoice = data_get($soldInfo, 'invoice_number');
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
                                    <td colspan="14" class="px-4 py-6 text-center text-slate-500">{{ __('Tidak ada unit untuk produk ini.') }}</td>
                                </tr>
                            @endforelse
                        @empty
                            <tr>
                                <td colspan="14" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data unit.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $productsPage->links() }}</div>
            </div>
        </div>
    </div>

    @if($canFilterLocation ?? false)
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeSelect = document.getElementById('su_location_type');
            const locationSelect = document.getElementById('su_location_id');
            if (!typeSelect || !locationSelect) return;

            const warehouses = @json(($warehouses ?? collect())->map(fn($w) => ['id' => $w->id, 'name' => $w->name])->values());
            const branches = @json(($branches ?? collect())->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values());

            function rebuildLocationOptions() {
                const type = typeSelect.value;
                let rows = [];
                if (type === 'warehouse') {
                    rows = warehouses.map((w) => ({ value: String(w.id), label: `{{ __('Gudang') }}: ${w.name}` }));
                } else if (type === 'branch') {
                    rows = branches.map((b) => ({ value: String(b.id), label: `{{ __('Cabang') }}: ${b.name}` }));
                } else {
                    rows = [
                        ...warehouses.map((w) => ({ value: String(w.id), label: `{{ __('Gudang') }}: ${w.name}` })),
                        ...branches.map((b) => ({ value: String(b.id), label: `{{ __('Cabang') }}: ${b.name}` })),
                    ];
                }

                locationSelect.innerHTML = `<option value="">{{ __('Semua') }}</option>`;
                rows.forEach((row) => {
                    const option = document.createElement('option');
                    option.value = row.value;
                    option.textContent = row.label;
                    locationSelect.appendChild(option);
                });
            }

            typeSelect.addEventListener('change', function () {
                locationSelect.value = '';
                rebuildLocationOptions();
            });
        });
    </script>
    @endpush
    @endif
</x-app-layout>
