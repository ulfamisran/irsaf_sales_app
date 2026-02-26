<x-app-layout>
    <x-slot name="title">{{ __('Stok Masuk/Keluar') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Mutasi Stok (IN/OUT)') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ __('Laporan pergerakan stok masuk dan keluar.') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="card-modern p-6">
                <p class="text-sm text-slate-600">{{ __('Total IN') }}</p>
                <p class="text-2xl font-semibold text-emerald-600">{{ (int) ($totals['total_in'] ?? 0) }}</p>
            </div>
            <div class="card-modern p-6">
                <p class="text-sm text-slate-600">{{ __('Total OUT') }}</p>
                <p class="text-2xl font-semibold text-rose-600">{{ (int) ($totals['total_out'] ?? 0) }}</p>
            </div>
            <div class="card-modern p-6">
                <p class="text-sm text-slate-600">{{ __('Net') }}</p>
                <p class="text-2xl font-semibold text-slate-800">{{ (int) ($totals['net'] ?? 0) }}</p>
            </div>
        </div>

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('stock-inout.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[240px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Produk') }}</label>
                        <select name="product_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->sku }} - {{ $p->brand }} {{ $p->series }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Distribusi') }}</label>
                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-sm text-slate-700">
                            <input type="checkbox" name="include_distribution" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                {{ (string) request('include_distribution', $includeDistribution ? '1' : '0') === '1' ? 'checked' : '' }}>
                            <span>{{ __('Hitung distribusi (transfer internal)') }}</span>
                        </label>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Jika tidak dicentang, IN/OUT hanya dari Barang Masuk dan Penjualan.') }}</p>
                    </div>

                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                        <select id="location_type" name="location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="warehouse" {{ (request('location_type') ?? $effectiveLocationType) === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                            <option value="branch" {{ (request('location_type') ?? $effectiveLocationType) === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Opsional. Jika dipilih, laporan akan difilter per lokasi.') }}</p>
                    </div>

                    <div class="min-w-[240px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Nama Lokasi') }}</label>
                        <select id="location_id" name="location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
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
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('stock-inout.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('IN') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('OUT') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Net') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rows as $r)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-800">
                                        {{ $r['product']->sku }} - {{ $r['product']->brand }} {{ $r['product']->series }}
                                    </div>
                                    <div class="text-xs text-slate-500 mt-0.5">
                                        {{ __('Barang Masuk') }}: {{ (int) $r['incoming_in'] }},
                                        {{ __('Penjualan') }}: {{ (int) $r['sales_out'] }}
                                        @if ($includeDistribution)
                                            , {{ __('Distribusi Masuk') }}: {{ (int) $r['distribution_in'] }},
                                            {{ __('Distribusi Keluar') }}: {{ (int) $r['distribution_out'] }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-emerald-700 font-semibold">{{ (int) $r['total_in'] }}</td>
                                <td class="px-4 py-3 text-right text-rose-700 font-semibold">{{ (int) $r['total_out'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ (int) $r['net'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const branches = @json($branches);
        const warehouses = @json($warehouses);
        const effectiveType = @json($effectiveLocationType);
        const effectiveId = @json($effectiveLocationId);

        function fillLocationOptions() {
            const typeEl = document.getElementById('location_type');
            const idEl = document.getElementById('location_id');
            if (!typeEl || !idEl) return;

            const type = typeEl.value || effectiveType || '';
            const selected = (new URLSearchParams(window.location.search)).get('location_id') || (effectiveId ? String(effectiveId) : '');
            const items = type === 'warehouse' ? warehouses : (type === 'branch' ? branches : []);

            idEl.innerHTML = '<option value="">' + @json(__('Semua')) + '</option>' +
                items.map(o => `<option value="${o.id}" ${selected && String(o.id) === String(selected) ? 'selected' : ''}>${o.name}</option>`).join('');

            // If no type chosen, keep only "Semua"
            if (!type) {
                idEl.innerHTML = '<option value="">' + @json(__('Semua')) + '</option>';
            }
        }

        document.getElementById('location_type')?.addEventListener('change', fillLocationOptions);
        fillLocationOptions();
    </script>
</x-app-layout>

