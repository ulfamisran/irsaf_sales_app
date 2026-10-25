<x-app-layout>
    <x-slot name="title">{{ __('Mutasi Stok') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Mutasi Stok') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ __('Stok awal, masuk, diterima, terjual, rusak, dan stok akhir per lokasi.') }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('stock-inout.export', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                    {{ __('Download Excel') }}
                </a>
                <a href="{{ route('stock-inout.export-pdf', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                    {{ __('Download PDF') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                @php
                    $selectedLocationType = $effectiveLocationType ?: request('location_type', '');
                    $selectedLocationId = $effectiveLocationId ?: request('location_id');
                @endphp
                <form method="GET" action="{{ route('stock-inout.index') }}" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        @if ($canFilterLocation)
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                                <select name="location_type" id="sio_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Semua') }}</option>
                                    <option value="warehouse" {{ $selectedLocationType === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                    <option value="branch" {{ $selectedLocationType === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                                <select name="location_id" id="sio_location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Semua') }}</option>
                                    @if ($selectedLocationType === 'branch')
                                        @foreach ($branches as $b)
                                            <option value="{{ $b->id }}" {{ (string) $selectedLocationId === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                        @endforeach
                                    @elseif ($selectedLocationType === 'warehouse')
                                        @foreach ($warehouses as $w)
                                            <option value="{{ $w->id }}" {{ (string) $selectedLocationId === (string) $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        @elseif ($filterLocked)
                            <div>
                                <x-locked-location label="{{ __('Tipe Lokasi') }}" :value="str_contains((string) $locationLabel, __('Cabang')) ? __('Cabang') : __('Gudang')" />
                            </div>
                            <div>
                                <x-locked-location label="{{ __('Lokasi') }}" :value="$locationLabel" />
                                <input type="hidden" name="location_type" value="{{ $effectiveLocationType }}">
                                <input type="hidden" name="location_id" value="{{ $effectiveLocationId }}">
                            </div>
                        @endif
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                            <input type="date" name="date_from" value="{{ $dateFrom }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                            <input type="date" name="date_to" value="{{ $dateTo }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer select-none">
                            <input type="checkbox" name="show_all" value="1" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ !empty($showAll) ? 'checked' : '' }}>
                            <span>{{ __('Tampilkan Seluruh Unit') }}</span>
                        </label>
                        <span class="text-xs text-slate-500">{{ __('Jika tidak dicentang: hanya produk yang ada stok masuk atau stok keluar di periode.') }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('stock-inout.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset (Hari Ini)') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600">
            <p><span class="font-medium text-slate-800">{{ __('Lokasi') }}:</span> {{ $locationDisplay }}</p>
            <p>
                <span class="font-medium text-slate-800">{{ __('Periode') }}:</span>
                {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m/Y') }}
                —
                {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') }}
            </p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                <p class="text-xs font-medium text-sky-800">{{ __('Stok Masuk') }}</p>
                <p class="text-2xl font-bold text-sky-700 mt-1">{{ number_format((int) ($totals['stok_masuk'] ?? 0), 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-medium text-emerald-800">{{ __('Stok Diterima') }}</p>
                <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format((int) ($totals['stok_diterima'] ?? 0), 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-medium text-indigo-800">{{ __('Stok Terjual') }}</p>
                <p class="text-2xl font-bold text-indigo-700 mt-1">{{ number_format((int) ($totals['stok_terjual'] ?? 0), 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-xs font-medium text-rose-800">{{ __('Stok Rusak') }}</p>
                <p class="text-2xl font-bold text-rose-700 mt-1">{{ number_format((int) ($totals['stok_rusak'] ?? 0), 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-800">{{ __('Rincian Barang') }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ !empty($showAll) ? __('Menampilkan seluruh produk.') : __('Hanya produk dengan stok masuk atau stok keluar.') }}
                    </p>
                </div>
                @if ($categoryRows->isNotEmpty())
                    <div class="flex gap-2">
                        <button type="button" id="expand-all-cats" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium hover:bg-slate-200">{{ __('Buka Semua') }}</button>
                        <button type="button" id="collapse-all-cats" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium hover:bg-slate-200">{{ __('Tutup Semua') }}</button>
                    </div>
                @endif
            </div>
            <div class="p-6 space-y-3">
                @forelse ($categoryRows as $ci => $cat)
                    @php $panelId = 'cat-panel-'.$ci; @endphp
                    <div class="rounded-xl border border-slate-200 overflow-hidden" data-cat-accordion>
                        <button type="button"
                            class="w-full flex flex-wrap items-center justify-between gap-2 px-4 py-3 bg-slate-50 hover:bg-slate-100 text-left cat-toggle"
                            data-target="{{ $panelId }}"
                            aria-expanded="true">
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="w-4 h-4 text-slate-500 cat-chevron transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                                <span class="text-sm font-semibold text-indigo-800 truncate">{{ $cat['category_name'] }}</span>
                                <span class="text-xs text-slate-500">({{ count($cat['products']) }} {{ __('produk') }})</span>
                            </div>
                            <p class="text-xs text-slate-600">
                                {{ __('Awal') }}: <span class="font-semibold">{{ number_format((int) $cat['stok_awal'], 0, ',', '.') }}</span>
                                · {{ __('Masuk') }}: <span class="font-semibold text-sky-700">{{ number_format((int) $cat['stok_masuk'], 0, ',', '.') }}</span>
                                · {{ __('Diterima') }}: <span class="font-semibold text-emerald-700">{{ number_format((int) $cat['stok_diterima'], 0, ',', '.') }}</span>
                                · {{ __('Terjual') }}: <span class="font-semibold text-indigo-700">{{ number_format((int) $cat['stok_terjual'], 0, ',', '.') }}</span>
                                · {{ __('Rusak') }}: <span class="font-semibold text-rose-700">{{ number_format((int) $cat['stok_rusak'], 0, ',', '.') }}</span>
                                · {{ __('Akhir') }}: <span class="font-semibold text-amber-800">{{ number_format((int) $cat['stok_akhir'], 0, ',', '.') }}</span>
                            </p>
                        </button>
                        <div id="{{ $panelId }}" class="cat-panel border-t border-slate-200 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-white">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Stok Awal') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Stok Masuk') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase min-w-[180px]">{{ __('Serial Masuk') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Stok Diterima') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase min-w-[140px]">{{ __('Stok Terjual') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase min-w-[140px]">{{ __('Stok Rusak') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase min-w-[140px]">{{ __('Stok Akhir') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @foreach ($cat['products'] as $row)
                                        <tr class="hover:bg-slate-50/50 align-top">
                                            <td class="px-3 py-2 text-sm text-slate-800">
                                                {{ $row['product']->sku }} — {{ $row['product']->brand }} {{ $row['product']->series }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-right font-medium text-slate-800">{{ number_format((int) $row['stok_awal'], 0, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-sm text-right font-medium text-sky-700">{{ number_format((int) $row['stok_masuk'], 0, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-xs text-slate-700">
                                                @if (!empty($row['requires_serial']) && !empty($row['serials_masuk']))
                                                    <ul class="space-y-1 font-mono">
                                                        @foreach ($row['serials_masuk'] as $snIn)
                                                            @php
                                                                $sn = is_array($snIn) ? ($snIn['serial'] ?? '') : (string) $snIn;
                                                                $checkKey = is_array($snIn) ? ($snIn['check_key'] ?? '') : '';
                                                                $checkLabel = is_array($snIn) ? ($snIn['check_label'] ?? '') : '';
                                                                $badgeClass = match ($checkKey) {
                                                                    'baik' => 'bg-emerald-100 text-emerald-700',
                                                                    'reture' => 'bg-rose-100 text-rose-700',
                                                                    'pending' => 'bg-orange-100 text-orange-700',
                                                                    'unchecked' => 'bg-amber-100 text-amber-800',
                                                                    'distribusi' => 'bg-sky-100 text-sky-700',
                                                                    default => 'bg-slate-100 text-slate-700',
                                                                };
                                                            @endphp
                                                            <li>
                                                                {{ $sn }}
                                                                @if ($checkLabel !== '')
                                                                    <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-sans font-medium {{ $badgeClass }}">{{ $checkLabel }}</span>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <span class="text-slate-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-right font-medium text-emerald-700">
                                                {{ number_format((int) $row['stok_diterima'], 0, ',', '.') }}
                                                @if (!empty($row['requires_serial']) && !empty($row['serials_diterima']))
                                                    <ul class="mt-1 space-y-0.5 text-[10px] font-mono text-slate-500 font-normal text-left">
                                                        @foreach ($row['serials_diterima'] as $sn)
                                                            <li>{{ $sn }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-right font-medium text-indigo-700">
                                                {{ number_format((int) $row['stok_terjual'], 0, ',', '.') }}
                                                @if (!empty($row['requires_serial']) && !empty($row['serials_terjual']))
                                                    <ul class="mt-1 space-y-0.5 text-[10px] font-mono text-slate-500 font-normal text-left">
                                                        @foreach ($row['serials_terjual'] as $sn)
                                                            <li>{{ $sn }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-right font-medium text-rose-700">
                                                {{ number_format((int) $row['stok_rusak'], 0, ',', '.') }}
                                                @if (!empty($row['requires_serial']) && !empty($row['serials_rusak']))
                                                    <ul class="mt-1 space-y-0.5 text-[10px] font-mono text-slate-500 font-normal text-left">
                                                        @foreach ($row['serials_rusak'] as $sn)
                                                            <li>{{ $sn }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-right font-semibold text-amber-800">
                                                {{ number_format((int) $row['stok_akhir'], 0, ',', '.') }}
                                                @if (!empty($row['requires_serial']) && !empty($row['serials_akhir']))
                                                    <ul class="mt-1 space-y-0.5 text-[10px] font-mono text-slate-500 font-normal text-left">
                                                        @foreach ($row['serials_akhir'] as $sn)
                                                            <li>{{ $sn }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="py-12 text-center text-slate-500">{{ __('Tidak ada data stok pada periode / lokasi ini.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function setPanel(btn, open) {
                const id = btn.getAttribute('data-target');
                const panel = document.getElementById(id);
                const chevron = btn.querySelector('.cat-chevron');
                if (!panel) return;
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                panel.classList.toggle('hidden', !open);
                if (chevron) chevron.classList.toggle('rotate-[-90deg]', !open);
            }

            document.querySelectorAll('.cat-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const open = btn.getAttribute('aria-expanded') !== 'true';
                    setPanel(btn, open);
                });
            });

            const expandAll = document.getElementById('expand-all-cats');
            const collapseAll = document.getElementById('collapse-all-cats');
            if (expandAll) {
                expandAll.addEventListener('click', function () {
                    document.querySelectorAll('.cat-toggle').forEach(function (btn) { setPanel(btn, true); });
                });
            }
            if (collapseAll) {
                collapseAll.addEventListener('click', function () {
                    document.querySelectorAll('.cat-toggle').forEach(function (btn) { setPanel(btn, false); });
                });
            }

            @if ($canFilterLocation)
            const locType = document.getElementById('sio_location_type');
            const locId = document.getElementById('sio_location_id');
            const warehouses = @json($warehouses);
            const branches = @json($branches);
            const selectedId = @json((string) ($selectedLocationId ?? ''));

            function renderOptions(type, selected) {
                if (!locId) return;
                locId.innerHTML = '<option value="">{{ __('Semua') }}</option>';
                if (!type) return;
                const list = type === 'branch' ? branches : warehouses;
                list.forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = String(item.id);
                    opt.textContent = item.name;
                    if (String(selected) === String(item.id)) opt.selected = true;
                    locId.appendChild(opt);
                });
            }

            if (locType) {
                renderOptions(locType.value, selectedId);
                locType.addEventListener('change', function () {
                    renderOptions(locType.value, '');
                });
            }
            @endif
        });
    </script>
    @endpush
</x-app-layout>
