<x-app-layout>
    <x-slot name="title">{{ __('Barang Masuk') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Barang Masuk') }}</h2>
            <x-icon-btn-add :href="route('incoming-goods.create')" :label="__('Catat Barang Masuk')" />
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

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                @php
                    $selectedLocationType = request('location_type');
                    if (! $selectedLocationType) {
                        $selectedLocationType = request('warehouse_id') ? 'warehouse' : (request('branch_id') ? 'branch' : '');
                    }
                    $selectedLocationId = request('location_id');
                    if (! $selectedLocationId) {
                        $selectedLocationId = request('warehouse_id') ?: request('branch_id');
                    }
                @endphp
                <form method="GET" action="{{ route('incoming-goods.index') }}" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Search') }}</label>
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="{{ __('Produk / Cabang / User / Serial') }}"
                               class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        @if (! $isBranchUser)
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                                <select id="ig_location_type" name="location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Semua') }}</option>
                                    <option value="warehouse" {{ $selectedLocationType === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                    <option value="branch" {{ $selectedLocationType === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                                <select id="ig_location_id" name="location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Semua') }}</option>
                                    @if ($selectedLocationType === 'warehouse')
                                        @foreach ($warehouses as $w)
                                            <option value="{{ $w->id }}" {{ (string) $selectedLocationId === (string) $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                        @endforeach
                                    @elseif ($selectedLocationType === 'branch')
                                        @foreach ($branches as $b)
                                            <option value="{{ $b->id }}" {{ (string) $selectedLocationId === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        @else
                            <div>
                                <x-locked-location label="{{ __('Tipe Lokasi') }}" :value="__('Cabang')" />
                            </div>
                            <div>
                                <x-locked-location label="{{ __('Lokasi') }}" :value="$branch?->name ?? '-'" />
                            </div>
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
                                @foreach ($categories as $c)
                                    <option value="{{ $c->id }}" {{ request('category_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
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
                        <div class="md:col-span-2 flex gap-2 md:justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                {{ __('Filter') }}
                            </button>
                            <a href="{{ route('incoming-goods.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Detail') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($records as $r)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">{{ $r->received_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $r->product?->sku }} - {{ $r->product?->brand }}</td>
                                <td class="px-4 py-3">
                                    @if ($r->branch_id)
                                        {{ __('Cabang') }}: {{ $r->branch?->name ?? ('#' . $r->branch_id) }}
                                    @else
                                        {{ __('Gudang') }}: {{ $r->warehouse?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ $r->notes ? \Illuminate\Support\Str::limit(str_replace("\n", ', ', $r->notes), 40) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ $r->quantity }}</td>
                                <td class="px-4 py-3">{{ $r->user?->name }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors show-detail-btn"
                                        title="{{ __('Lihat Detail') }}"
                                        data-url="{{ route('incoming-goods.detail', $r) }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        {{ __('Detail') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $records->links() }}</div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locationTypeEl = document.getElementById('ig_location_type');
            const locationIdEl = document.getElementById('ig_location_id');
            const warehouses = @json($warehouses);
            const branches = @json($branches);

            function renderLocationOptions(selectedType, selectedId = '') {
                if (!locationIdEl) return;
                locationIdEl.innerHTML = '';
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = @json(__('Semua'));
                locationIdEl.appendChild(defaultOpt);

                const list = selectedType === 'warehouse'
                    ? warehouses
                    : (selectedType === 'branch' ? branches : []);

                list.forEach(function(item) {
                    const opt = document.createElement('option');
                    opt.value = String(item.id);
                    opt.textContent = item.name;
                    if (String(selectedId) === String(item.id)) {
                        opt.selected = true;
                    }
                    locationIdEl.appendChild(opt);
                });
            }

            if (locationTypeEl && locationIdEl) {
                renderLocationOptions(locationTypeEl.value, locationIdEl.value);
                locationTypeEl.addEventListener('change', function() {
                    renderLocationOptions(this.value, '');
                });
            }

            document.querySelectorAll('.show-detail-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const url = this.dataset.url;
                    if (!url) return;
                    const el = this;
                    el.disabled = true;
                    fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(r => r.json())
                    .then(function(data) {
                        const escape = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                        let unitsHtml = '';
                        if (data.units && data.units.length > 0) {
                            unitsHtml = '<div class="mt-4"><p class="text-sm font-semibold text-slate-700 mb-2">Detail Unit</p>' +
                                '<div class="overflow-x-auto max-h-48 overflow-y-auto rounded-lg border border-slate-200">' +
                                '<table class="min-w-full text-sm"><thead><tr class="bg-slate-50 border-b border-slate-200">' +
                                '<th class="px-3 py-2 text-left font-medium text-slate-600">No</th>' +
                                '<th class="px-3 py-2 text-left font-medium text-slate-600">Serial</th>' +
                                '<th class="px-3 py-2 text-left font-medium text-slate-600">Posisi</th>' +
                                '<th class="px-3 py-2 text-left font-medium text-slate-600">Status</th></tr></thead><tbody>';
                            data.units.forEach(function(u, i) {
                                const statusClass = u.status === 'Tersedia' ? 'bg-emerald-100 text-emerald-800' :
                                    u.status === 'Terjual' ? 'bg-amber-100 text-amber-800' :
                                    u.status === 'Dipesan' ? 'bg-blue-100 text-blue-800' :
                                    'bg-slate-100 text-slate-700';
                                unitsHtml += '<tr class="border-b border-slate-100 hover:bg-slate-50">' +
                                    '<td class="px-3 py-2 text-slate-600">' + (i+1) + '</td>' +
                                    '<td class="px-3 py-2 font-mono text-slate-800">' + escape(u.serial_number) + '</td>' +
                                    '<td class="px-3 py-2 text-slate-700">' + escape(u.posisi) + '</td>' +
                                    '<td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-medium ' + statusClass + '">' + escape(u.status) + '</span></td>' +
                                    '</tr>';
                            });
                            unitsHtml += '</tbody></table></div></div>';
                        } else if (data.has_serial === false) {
                            unitsHtml = '<p class="mt-3 text-sm text-slate-500 italic">Input tanpa nomor serial (hanya quantity)</p>';
                        } else {
                            unitsHtml = '<p class="mt-3 text-sm text-slate-500 italic">Data unit tidak ditemukan</p>';
                        }
                        const infoHtml = '<div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">' +
                            '<div><span class="text-slate-500 block text-xs">Tanggal</span><span class="font-medium text-slate-800">' + escape(data.tanggal) + '</span></div>' +
                            '<div><span class="text-slate-500 block text-xs">User</span><span class="font-medium text-slate-800">' + escape(data.user) + '</span></div>' +
                            '<div class="col-span-2"><span class="text-slate-500 block text-xs">Produk</span><span class="font-medium text-slate-800">' + escape(data.produk) + '</span></div>' +
                            '<div><span class="text-slate-500 block text-xs">Lokasi Tujuan</span><span class="font-medium text-slate-800">' + escape(data.lokasi) + '</span></div>' +
                            '<div><span class="text-slate-500 block text-xs">Quantity</span><span class="font-medium text-slate-800">' + escape(data.qty) + '</span></div>' +
                            '</div>';
                        Swal.fire({
                            title: 'Detail Barang Masuk',
                            html: '<div class="text-left">' + infoHtml + unitsHtml + '</div>',
                            confirmButtonText: 'Tutup',
                            confirmButtonColor: '#4f46e5',
                            width: '920px',
                            padding: '1.5rem'
                        });
                    })
                    .catch(function() {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: 'Tidak dapat memuat detail.', confirmButtonColor: '#dc2626' });
                    })
                    .finally(function() { el.disabled = false; });
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
