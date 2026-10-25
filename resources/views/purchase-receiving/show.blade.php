<x-app-layout>
    <x-slot name="title">{{ __('Cek Pembelian') }} — {{ $purchase->invoice_number }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Cek Pembelian') }}: {{ $purchase->invoice_number }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('purchase-receiving.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">{{ __('Kembali') }}</a>
                <a href="{{ route('purchases.show', $purchase) }}" class="px-4 py-2 rounded-lg bg-indigo-50 text-indigo-700 text-sm font-medium hover:bg-indigo-100">{{ __('Detail Pembelian') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">{{ __('Nilai Invoice') }}</p>
                <p class="text-lg font-semibold text-slate-800">Rp {{ number_format($summary['invoice_total'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs text-emerald-700">{{ __('Utang Terakui') }}</p>
                <p class="text-lg font-semibold text-emerald-800">Rp {{ number_format($summary['recognized_debt'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-xs text-rose-700">{{ __('Nilai Retur') }}</p>
                <p class="text-lg font-semibold text-rose-800">Rp {{ number_format($summary['return_value'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs text-amber-700">{{ __('Ditangguhkan') }}</p>
                <p class="text-lg font-semibold text-amber-800">Rp {{ number_format($summary['deferred_value'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-orange-200 bg-orange-50 p-4">
                <p class="text-xs text-orange-700">{{ __('Belum Dicek') }}</p>
                <p class="text-lg font-semibold text-orange-800">Rp {{ number_format($summary['unchecked_value'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs text-slate-500">{{ __('Status Bayar (vs utang terakui)') }}</p>
                <p class="text-lg font-semibold text-slate-800">{{ $summary['payment_status_label'] }}</p>
            </div>
        </div>

        @if (! $summary['needs_check'])
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-6 text-center text-slate-600">
                {{ __('Semua barang pada pembelian ini sudah dicek.') }}
            </div>
        @else
            <form method="POST" action="{{ route('purchase-receiving.store', $purchase) }}" class="card-modern overflow-hidden">
                @csrf
                <div class="p-6 space-y-8">
                    @foreach ($lines as $i => $line)
                        @php $detail = $line['detail']; @endphp
                        <div class="border border-slate-200 rounded-xl p-4">
                            <div class="flex flex-wrap justify-between gap-2 mb-3">
                                <div>
                                    <p class="font-medium text-slate-800">{{ $detail->product?->sku ?? '-' }} — {{ trim(($detail->product?->brand ?? '').' '.($detail->product?->series ?? '')) }}</p>
                                    <p class="text-sm text-slate-500">{{ __('Qty beli') }}: {{ $detail->quantity }} · {{ __('Harga') }}: Rp {{ number_format($detail->unit_price, 0, ',', '.') }}</p>
                                </div>
                            </div>

                            @if ($line['requires_serial'])
                                @if ($line['unchecked_units']->isEmpty())
                                    <p class="text-sm text-slate-500">{{ __('Tidak ada serial belum dicek pada baris ini.') }}</p>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-sm">
                                            <thead>
                                                <tr class="text-left text-slate-500">
                                                    <th class="py-2 pr-3">{{ __('Serial') }}</th>
                                                    <th class="py-2">{{ __('Hasil Cek') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($line['unchecked_units'] as $u)
                                                    <tr class="border-t border-slate-100">
                                                        <td class="py-2 pr-3 font-mono">{{ $u->serial_number }}</td>
                                                        <td class="py-2">
                                                            <input type="hidden" name="units[{{ $u->id }}][unit_id]" value="{{ $u->id }}">
                                                            <select name="units[{{ $u->id }}][result]" class="rounded-md border-slate-300 text-sm">
                                                                <option value="">{{ __('— Lewati —') }}</option>
                                                                <option value="baik">{{ __('Baik → In Stock') }}</option>
                                                                <option value="reture">{{ __('Reture') }}</option>
                                                                <option value="pending">{{ __('Pending') }}</option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @else
                                @if ($line['unchecked_qty'] <= 0)
                                    <p class="text-sm text-slate-500">{{ __('Qty pada baris ini sudah dicek penuh.') }}</p>
                                @else
                                    <p class="text-sm text-amber-700 mb-2">{{ __('Sisa belum dicek') }}: {{ $line['unchecked_qty'] }}</p>
                                    <input type="hidden" name="qtys[{{ $detail->id }}][purchase_detail_id]" value="{{ $detail->id }}">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ __('Qty Baik') }}</label>
                                            <input type="number" min="0" max="{{ $line['unchecked_qty'] }}" name="qtys[{{ $detail->id }}][qty_baik]" value="0" class="w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ __('Qty Reture') }}</label>
                                            <input type="number" min="0" max="{{ $line['unchecked_qty'] }}" name="qtys[{{ $detail->id }}][qty_reture]" value="0" class="w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ __('Qty Pending') }}</label>
                                            <input type="number" min="0" max="{{ $line['unchecked_qty'] }}" name="qtys[{{ $detail->id }}][qty_pending]" value="0" class="w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach

                    <div class="flex justify-end gap-2">
                        <a href="{{ route('purchase-receiving.index') }}" class="px-4 py-2.5 rounded-lg bg-gray-200 text-gray-700 text-sm font-semibold">{{ __('Batal') }}</a>
                        <button type="submit" class="px-4 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">{{ __('Simpan Hasil Cek') }}</button>
                    </div>
                </div>
            </form>
        @endif

        @if (isset($logs) && $logs->isNotEmpty())
        <div class="card-modern overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-semibold text-slate-800">{{ __('Riwayat Cek Invoice Ini') }}</h3>
                <a href="{{ route('purchase-receiving.history', $purchase) }}" class="text-sm text-indigo-600 hover:underline">{{ __('Lihat detail riwayat') }}</a>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2 pr-3">{{ __('Waktu') }}</th>
                            <th class="py-2 pr-3">{{ __('Item') }}</th>
                            <th class="py-2 pr-3 text-center">{{ __('Hasil') }}</th>
                            <th class="py-2 pr-3">{{ __('Oleh') }}</th>
                            <th class="py-2 text-center">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr class="border-t border-slate-100">
                                <td class="py-2 pr-3">{{ $log->checked_at?->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-3">
                                    @if ($log->productUnit)
                                        <span class="font-mono">{{ $log->productUnit->serial_number }}</span>
                                    @else
                                        {{ __('Qty') }} {{ $log->qty }}
                                    @endif
                                </td>
                                <td class="py-2 pr-3 text-center">{{ $log->resultLabel() }}</td>
                                <td class="py-2 pr-3">{{ $log->checker?->name ?? '-' }}</td>
                                <td class="py-2 text-center">
                                    <a href="{{ route('purchase-receiving.logs.edit', $log) }}" class="text-indigo-600 hover:underline text-xs">{{ __('Update') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</x-app-layout>

