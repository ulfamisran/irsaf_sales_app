<x-app-layout>
    <x-slot name="title">{{ __('Monitoring Stok') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Monitoring Stok') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="card-modern overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('stock-monitoring.index') }}" class="flex flex-wrap gap-3 items-end">
                    @if($canFilterLocation ?? false)
                        <div class="min-w-[160px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                            <select name="location_type" id="sm_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">{{ __('Semua') }}</option>
                                <option value="branch" {{ ($locationType ?? '') === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                <option value="warehouse" {{ ($locationType ?? '') === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                            </select>
                        </div>
                        <div id="sm_location_wrapper" class="min-w-[190px]" style="{{ ($locationType ?? '') === '' ? 'display:none' : '' }}">
                            <label class="block text-sm font-medium text-slate-700 mb-1" id="sm_location_label">{{ ($locationType ?? '') === 'warehouse' ? __('Gudang') : __('Cabang') }}</label>
                            <div class="sm-loc-warehouse" style="{{ ($locationType ?? '') !== 'warehouse' ? 'display:none' : '' }}">
                                <select name="warehouse_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">{{ __('Semua') }}</option>
                                    @foreach($warehouses as $w)
                                        <option value="{{ $w->id }}" {{ (string)($locationId ?? '') === (string)$w->id && ($locationType ?? '') === 'warehouse' ? 'selected' : '' }}>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm-loc-branch" style="{{ ($locationType ?? '') !== 'branch' ? 'display:none' : '' }}">
                                <select name="branch_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">{{ __('Semua') }}</option>
                                    @foreach($branches as $b)
                                        <option value="{{ $b->id }}" {{ (string)($locationId ?? '') === (string)$b->id && ($locationType ?? '') === 'branch' ? 'selected' : '' }}>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @elseif($filterLocked ?? false)
                        <div class="min-w-[220px]">
                            <x-locked-location label="{{ __('Lokasi') }}" :value="$locationLabel ?? ''" />
                        </div>
                    @endif
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">{{ __('Tampilkan') }}</button>
                        <a href="{{ route('stock-monitoring.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">{{ __('Reset') }}</a>
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
                @php $maxCategory = max(1, (int) (($categorySummaries ?? collect())->max('total_qty') ?? 1)); @endphp
                <div class="space-y-3">
                    @forelse(($categorySummaries ?? collect())->take(12) as $item)
                        @php $w = min(100, (int) round(($item['total_qty'] / $maxCategory) * 100)); @endphp
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-slate-700">{{ $item['category_name'] }}</span>
                                <span class="font-semibold text-slate-900">{{ number_format((int) $item['total_qty'], 0, ',', '.') }}</span>
                            </div>
                            <div class="w-full h-2.5 rounded-full bg-slate-100">
                                <div class="h-2.5 rounded-full bg-indigo-500" style="width: {{ $w }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('Belum ada data stok.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="card-modern p-5">
                <h3 class="font-semibold text-slate-800 mb-4">{{ __('Diagram Stok per Cabang/Gudang (per Kategori)') }}</h3>
                <div class="space-y-3">
                    @forelse(($locationCategorySummaries ?? collect())->take(8) as $loc)
                        @php
                            $locMax = max(1, (int) (collect($loc['categories'] ?? [])->max('qty') ?? 1));
                        @endphp
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-slate-800">{{ $loc['location_label'] }}</span>
                                <span class="font-semibold text-slate-900">{{ number_format((int) $loc['total_qty'], 0, ',', '.') }}</span>
                            </div>
                            <div class="space-y-2">
                                @foreach(collect($loc['categories'] ?? [])->take(6) as $cat)
                                    @php $w = min(100, (int) round(($cat['qty'] / $locMax) * 100)); @endphp
                                    <div>
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-slate-600">{{ $cat['category_name'] }}</span>
                                            <span class="text-slate-700 font-medium">{{ number_format((int) $cat['qty'], 0, ',', '.') }}</span>
                                        </div>
                                        <div class="w-full h-2 rounded-full bg-slate-100">
                                            <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $w }}%"></div>
                                        </div>
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
            const locWrapper = document.getElementById('sm_location_wrapper');
            const locLabel = document.getElementById('sm_location_label');
            const whBlock = document.querySelector('.sm-loc-warehouse');
            const brBlock = document.querySelector('.sm-loc-branch');
            const whSelect = whBlock?.querySelector('select[name="warehouse_id"]');
            const brSelect = brBlock?.querySelector('select[name="branch_id"]');

            function toggleLocationFilter() {
                if (!locType || !locWrapper) return;
                const value = locType.value;
                if (!value) {
                    locWrapper.style.display = 'none';
                    if (whSelect) whSelect.value = '';
                    if (brSelect) brSelect.value = '';
                    return;
                }
                locWrapper.style.display = '';
                if (value === 'warehouse') {
                    if (locLabel) locLabel.textContent = '{{ __("Gudang") }}';
                    if (whBlock) whBlock.style.display = '';
                    if (brBlock) brBlock.style.display = 'none';
                    if (brSelect) brSelect.value = '';
                } else {
                    if (locLabel) locLabel.textContent = '{{ __("Cabang") }}';
                    if (whBlock) whBlock.style.display = 'none';
                    if (brBlock) brBlock.style.display = '';
                    if (whSelect) whSelect.value = '';
                }
            }

            if (locType) {
                locType.addEventListener('change', toggleLocationFilter);
                toggleLocationFilter();
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
