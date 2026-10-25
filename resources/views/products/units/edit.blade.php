<x-app-layout>
    <x-slot name="title">{{ __('Edit Unit') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Edit Unit') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $product->sku }} — {{ $unit->serial_number }}</p>
            </div>
            <div class="flex gap-2">
                <x-icon-btn-view :href="route('stock-units.show', $unit)" :label="__('Lihat Unit')" />
                <x-icon-btn-back :href="route('stock-units.index')" :label="__('Kembali')" />
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto space-y-6">
        @if (session('error'))
            <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6">
                <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                    <h4 class="text-sm font-semibold text-slate-800 mb-2">{{ __('Informasi Unit') }}</h4>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                        <dt class="text-slate-500">{{ __('SKU') }}</dt>
                        <dd>{{ $product->sku }}</dd>
                        <dt class="text-slate-500">{{ __('Serial saat ini') }}</dt>
                        <dd class="font-mono">{{ $unit->serial_number }}</dd>
                        <dt class="text-slate-500">{{ __('Status saat ini') }}</dt>
                        <dd>{{ $statusOptions[$unit->status] ?? $unit->status }}</dd>
                        <dt class="text-slate-500">{{ __('Lokasi') }}</dt>
                        <dd>
                            @php
                                $locLabel = $unit->location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang');
                                $locName = $unit->location_type === \App\Models\Stock::LOCATION_WAREHOUSE
                                    ? ($unit->warehouse?->name ?? '#'.$unit->location_id)
                                    : ($unit->branch?->name ?? '#'.$unit->location_id);
                            @endphp
                            {{ $locLabel }}: {{ $locName }}
                        </dd>
                    </dl>
                    <p class="mt-3 text-xs text-amber-700">{{ __('Perubahan harga/serial/status tidak mengubah nilai transaksi penjualan yang sudah released. Wajib isi alasan perubahan.') }}</p>
                </div>

                <form method="POST" action="{{ route('products.units.update', [$product, $unit]) }}" id="unit-edit-form">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-4">
                        <div>
                            <x-input-label for="serial_number" :value="__('Nomor Serial')" />
                            <x-text-input id="serial_number" name="serial_number" type="text" class="font-mono" :value="old('serial_number', $unit->serial_number)" required />
                            <x-input-error :messages="$errors->get('serial_number')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="harga_hpp" :value="__('Harga HPP / Harga Unit (Rp)')" />
                            <x-text-input id="harga_hpp" name="harga_hpp" type="text" data-rupiah="true" :value="old('harga_hpp', $unit->harga_hpp)" required />
                            <x-input-error :messages="$errors->get('harga_hpp')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="harga_jual" :value="__('Harga Jual (Rp)')" />
                            <x-text-input id="harga_jual" name="harga_jual" type="text" data-rupiah="true" :value="old('harga_jual', $unit->harga_jual)" required />
                            <x-input-error :messages="$errors->get('harga_jual')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="status" :value="__('Status Unit')" />
                            <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $unit->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="reason" :value="__('Alasan Perubahan')" />
                            <textarea id="reason" name="reason" rows="3" required class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Wajib diisi untuk log perubahan unit') }}">{{ old('reason') }}</textarea>
                            <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="notes" :value="__('Catatan Unit')" />
                            <textarea id="notes" name="notes" rows="2" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $unit->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>
                        <div class="flex gap-4 pt-2">
                            <x-primary-button type="submit">{{ __('Simpan Perubahan') }}</x-primary-button>
                            <a href="{{ route('stock-units.show', $unit) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-800">{{ __('Log Perubahan Unit') }}</h3>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Unit') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Perubahan') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Alasan') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($unit->changeLogs as $log)
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $log->changed_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $log->serial_snapshot ?? $unit->serial_number }}</td>
                                <td class="px-3 py-2">
                                    <ul class="space-y-0.5 text-xs text-slate-700">
                                        @foreach (($log->changes ?? []) as $field => $diff)
                                            <li>
                                                <span class="font-medium">{{ $field }}</span>:
                                                <span class="text-rose-600">{{ is_numeric($diff['old'] ?? null) ? number_format((float) $diff['old'], 0, ',', '.') : ($diff['old'] ?? '-') }}</span>
                                                →
                                                <span class="text-emerald-700">{{ is_numeric($diff['new'] ?? null) ? number_format((float) $diff['new'], 0, ',', '.') : ($diff['new'] ?? '-') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-3 py-2 text-slate-700">{{ $log->reason }}</td>
                                <td class="px-3 py-2">{{ $log->user?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-slate-500">{{ __('Belum ada log perubahan unit.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('unit-edit-form')?.addEventListener('submit', function () {
            document.querySelectorAll('[data-rupiah="true"]').forEach(function (inp) {
                if (typeof window.parseRupiahToNumber === 'function') {
                    const num = window.parseRupiahToNumber(inp.value);
                    if (!isNaN(num)) inp.value = String(num);
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
