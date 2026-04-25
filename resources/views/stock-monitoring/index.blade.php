<x-app-layout>
    <x-slot name="title">{{ __('Monitoring Stok') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Monitoring Stok') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="card-modern overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('stock-monitoring.index') }}" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Pencarian') }}</label>
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="{{ __('SKU, merek, seri, spesifikasi...') }}"
                               class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        @if($canFilterLocation ?? false)
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                                <select name="location_type" id="sm_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">{{ __('Semua') }}</option>
                                    <option value="branch" {{ ($locationType ?? '') === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                    <option value="warehouse" {{ ($locationType ?? '') === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                                <select name="location_id" id="sm_location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">{{ __('Semua') }}</option>
                                    @if (($locationType ?? '') === 'branch')
                                        @foreach($branches as $b)
                                            <option value="{{ $b->id }}" {{ (string)($locationId ?? '') === (string)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                        @endforeach
                                    @else
                                        @foreach($warehouses as $w)
                                            <option value="{{ $w->id }}" {{ (string)($locationId ?? '') === (string)$w->id ? 'selected' : '' }}>{{ $w->name }}</option>
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
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">{{ __('Tampilkan') }}</button>
                            <a href="{{ route('stock-monitoring.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">{{ __('Reset') }}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="card-modern p-5">
                <p class="text-sm text-slate-500">{{ __('Total Stok') }}</p>
                <p class="text-2xl font-bold text-slate-900">{{ number_format((int) ($overallQty ?? 0), 0, ',', '.') }}</p>
            </div>
            <div class="card-modern p-5">
                <p class="text-sm text-slate-500">{{ __('Total Kategori Produk') }}</p>
                <p class="text-2xl font-bold text-slate-900">{{ number_format((int) ($totalCategories ?? 0), 0, ',', '.') }}</p>
            </div>
            <div class="card-modern p-5">
                <p class="text-sm text-slate-500">{{ __('Total Produk') }}</p>
                <p class="text-2xl font-bold text-slate-900">{{ number_format((int) ($totalProducts ?? 0), 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="card-modern p-5">
                <h3 class="font-semibold text-slate-800 mb-4">{{ __('Diagram Stok per Kategori') }}</h3>
                @php
                    $overallForPct = (int) ($overallQty ?? 0);
                @endphp
                <p class="text-xs text-slate-500 mb-3">{{ __('Lebar bar = persentase dari total stok; angka = jumlah unit.') }}</p>
                <div class="space-y-3">
                    @forelse(($categorySummaries ?? collect())->take(12) as $item)
                        @php
                            $qtyCat = (int) $item['total_qty'];
                            $pctCat = $overallForPct > 0 ? ($qtyCat / $overallForPct) * 100 : 0;
                            $w = $qtyCat > 0 && $overallForPct > 0 ? min(100, max(6, (int) round($pctCat))) : 0;
                        @endphp
                        <div>
                            <div class="flex justify-between items-baseline gap-2 mb-1.5">
                                <span class="text-sm font-semibold text-slate-800 leading-tight">{{ $item['category_name'] }}</span>
                                <span class="text-sm font-bold text-slate-900 tabular-nums shrink-0">{{ number_format($qtyCat, 0, ',', '.') }}</span>
                            </div>
                            <div class="w-full h-3 rounded-full bg-slate-100">
                                <div class="h-3 rounded-full bg-indigo-500" style="width: {{ $w }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('Belum ada data stok.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="card-modern p-5">
                <h3 class="font-semibold text-slate-800 mb-4">{{ __('Diagram Stok per Cabang/Gudang (per Kategori & Jenis)') }}</h3>
                <div class="space-y-3">
                    @forelse(($locationCategorySummaries ?? collect())->take(8) as $loc)
                        @php
                            $locTotalForPct = (int) ($loc['total_qty'] ?? 0);
                        @endphp
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-slate-800">{{ $loc['location_label'] }}</span>
                                <span class="font-semibold text-slate-900">{{ number_format((int) $loc['total_qty'], 0, ',', '.') }}</span>
                            </div>
                            <div class="space-y-3">
                                @foreach(collect($loc['categories'] ?? [])->take(6) as $cat)
                                    @php
                                        $qtyLocCat = (int) $cat['qty'];
                                        $pctLocCat = $locTotalForPct > 0 ? ($qtyLocCat / $locTotalForPct) * 100 : 0;
                                        $w = $qtyLocCat > 0 && $locTotalForPct > 0 ? min(100, max(6, (int) round($pctLocCat))) : 0;
                                    @endphp
                                    <div>
                                        {{-- Kategori: teks & bar lebih besar --}}
                                        <div class="flex justify-between items-baseline gap-2 mb-1.5">
                                            <span class="text-sm font-semibold text-slate-800 leading-tight">{{ $cat['category_name'] }}</span>
                                            <span class="text-sm font-bold text-slate-900 tabular-nums shrink-0">{{ number_format($qtyLocCat, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="w-full h-3 rounded-full bg-slate-100">
                                            <div class="h-3 rounded-full bg-emerald-500" style="width: {{ $w }}%"></div>
                                        </div>
                                        @if(!empty($cat['type_breakdown']))
                                            @php
                                                $catTotalForTypes = $qtyLocCat;
                                            @endphp
                                            {{-- Jenis (baru/bekas): teks & bar lebih kecil, berurutan di bawah kategori --}}
                                            <div class="mt-2 ml-2 space-y-1.5 border-l-2 border-slate-200 pl-3">
                                                @foreach($cat['type_breakdown'] as $tb)
                                                    @php
                                                        $qtyType = (int) $tb['qty'];
                                                        $pctType = $catTotalForTypes > 0 ? ($qtyType / $catTotalForTypes) * 100 : 0;
                                                        $tw = $qtyType > 0 && $catTotalForTypes > 0 ? min(100, max(10, (int) round($pctType))) : 0;
                                                        $barClass = $tb['key'] === 'baru' ? 'bg-blue-500' : ($tb['key'] === 'bekas' ? 'bg-amber-500' : 'bg-slate-400');
                                                    @endphp
                                                    <div class="w-[9rem] sm:w-[10rem]">
                                                        <div class="flex justify-between gap-2 mb-0.5">
                                                            <span class="text-[11px] font-medium text-slate-500 leading-tight shrink-0">{{ $tb['label'] }}</span>
                                                            <span class="text-[11px] font-semibold text-slate-600 tabular-nums leading-tight">{{ number_format($qtyType, 0, ',', '.') }}</span>
                                                        </div>
                                                        <div class="w-full h-1 rounded-full bg-slate-100">
                                                            <div class="h-1 rounded-full {{ $barClass }}" style="width: {{ $tw }}%"></div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('Belum ada data lokasi.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-5 border-b border-slate-100">
                <h3 class="font-semibold text-slate-800">{{ __('Tabel Monitoring Stok per Kategori') }}</h3>
                <p class="text-sm text-slate-500">{{ __('Centang checkbox untuk melihat detail produk tiap kategori.') }}</p>
            </div>
            <div class="p-5 space-y-4">
                @forelse($categorySummaries ?? [] as $idx => $category)
                    @php $detailId = 'cat-detail-' . $idx; @endphp
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <div class="px-4 py-3 bg-slate-50 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $category['category_name'] }}</p>
                                <p class="text-sm text-slate-600">{{ __('Total Stok') }}: <span class="font-semibold">{{ number_format((int) $category['total_qty'], 0, ',', '.') }}</span> | {{ __('Produk') }}: <span class="font-semibold">{{ number_format((int) $category['product_count'], 0, ',', '.') }}</span></p>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 sm-toggle-detail" data-target="{{ $detailId }}">
                                <span>{{ __('Lihat detail produk') }}</span>
                            </label>
                        </div>
                        <div id="{{ $detailId }}" class="hidden p-4 bg-white">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Stok') }}</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Per Cabang/Gudang') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($category['products'] as $product)
                                            <tr>
                                                <td class="px-3 py-2 text-sm text-slate-800">{{ $product['product_name'] }}</td>
                                                <td class="px-3 py-2 text-sm text-slate-800 text-right font-semibold">{{ number_format((int) $product['total_qty'], 0, ',', '.') }}</td>
                                                <td class="px-3 py-2 text-sm text-slate-600">
                                                    @foreach($product['per_location'] as $i => $pl)
                                                        <span>{{ $pl['location_label'] }}: {{ number_format((int) $pl['qty'], 0, ',', '.') }}</span>{{ $i < count($product['per_location']) - 1 ? ' | ' : '' }}
                                                    @endforeach
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 text-slate-500">{{ __('Tidak ada data stok untuk ditampilkan.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const locType = document.getElementById('sm_location_type');
            const locId = document.getElementById('sm_location_id');
            const warehouses = @json(($warehouses ?? collect())->map(fn($w) => ['id' => $w->id, 'name' => $w->name])->values());
            const branches = @json(($branches ?? collect())->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values());

            function renderLocationOptions(type, selected = '') {
                if (!locId) return;
                locId.innerHTML = `<option value="">{{ __('Semua') }}</option>`;
                const rows = type === 'branch' ? branches : warehouses;
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

            if (locType && locId) {
                renderLocationOptions(locType.value, locId.value);
                locType.addEventListener('change', function () {
                    renderLocationOptions(this.value, '');
                });
            }

            document.querySelectorAll('.sm-toggle-detail').forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    const targetId = checkbox.getAttribute('data-target');
                    const target = targetId ? document.getElementById(targetId) : null;
                    if (!target) return;
                    target.classList.toggle('hidden', !checkbox.checked);
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
