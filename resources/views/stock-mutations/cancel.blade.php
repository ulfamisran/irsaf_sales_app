<x-app-layout>
    <x-slot name="title">{{ __('Batalkan Distribusi') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">
                {{ __('Batalkan Distribusi') }} — {{ $stockMutation->invoice_number }}
            </h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('stock-mutations.invoice', $stockMutation) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                    {{ __('Invoice') }}
                </a>
                <x-icon-btn-back :href="route('stock-mutations.index')" :label="__('Kembali ke Distribusi')" />
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 sm:px-6 lg:px-8">
        @if (session('error'))
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                <p class="font-semibold mb-2">{{ __('Terdapat kesalahan:') }}</p>
                <ul class="list-disc list-inside text-sm space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card-modern overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-slate-800">{{ __('Detail distribusi') }}</h3>
                <p class="text-sm text-slate-500 mt-1">{{ __('Seluruh baris dengan nomor invoice yang sama akan dibatalkan.') }}</p>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-slate-500">{{ __('Tanggal') }}</p>
                        <p class="font-medium text-slate-800">{{ $stockMutation->mutation_date?->format('d/m/Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">{{ __('User') }}</p>
                        <p class="font-medium text-slate-800">{{ $stockMutation->user?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">{{ __('Dari') }}</p>
                        <p class="font-medium text-slate-800">
                            {{ $stockMutation->from_location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang') }}:
                            {{ $fromLocation?->name ?? ('#' . $stockMutation->from_location_id) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-500">{{ __('Ke') }}</p>
                        <p class="font-medium text-slate-800">
                            {{ $stockMutation->to_location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang') }}:
                            {{ $toLocation?->name ?? ('#' . $stockMutation->to_location_id) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-500">{{ __('Total biaya distribusi') }}</p>
                        <p class="font-medium text-slate-800">
                            @if ($totalBiaya > 0)
                                Rp {{ number_format($totalBiaya, 0, ',', '.') }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-500">{{ __('Status bayar (kas masuk lokasi asal)') }}</p>
                        <p class="font-medium">
                            @if ($totalBiaya <= 0)
                                <span class="text-slate-500">{{ __('Tanpa biaya') }}</span>
                            @elseif ($isLunas)
                                <span class="text-emerald-700">{{ __('Lunas') }}</span>
                            @else
                                <span class="text-amber-700">{{ __('Belum lunas') }}</span>
                                <span class="text-slate-600 font-normal"> — {{ __('Terbayar') }}: Rp {{ number_format($totalPaid, 0, ',', '.') }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                @if ($stockMutation->notes)
                    <div class="text-sm">
                        <p class="text-slate-500">{{ __('Catatan') }}</p>
                        <p class="text-slate-800 whitespace-pre-line">{{ $stockMutation->notes }}</p>
                    </div>
                @endif

                <div class="rounded-lg border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-950">
                    <p class="font-semibold mb-1">{{ __('Perhatian') }}</p>
                    <ul class="list-disc list-inside space-y-1 text-amber-900/90">
                        <li>{{ __('Unit harus masih berada di lokasi tujuan dan berstatus stok tersedia (belum terjual).') }}</li>
                        <li>{{ __('Reversal kas (keluar di lokasi asal dan masuk retur di lokasi tujuan) hanya dibuat jika total biaya distribusi > 0, sudah ada pencatatan kas masuk biaya di lokasi asal, dan sudah ada pencatatan kas keluar pembayaran hutang di lokasi tujuan. Jika salah satu tidak terpenuhi, stok dan hutang tetap dibersihkan tanpa jurnal reversal kas.') }}</li>
                        <li>{{ __('Data pembelian distribusi di tujuan akan dibatalkan dan daftar pembayaran dihapus sesuai alur pembatalan.') }}</li>
                    </ul>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-slate-500 uppercase">
                                <th class="px-3 py-2">{{ __('Produk') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Qty') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Biaya / unit') }}</th>
                                <th class="px-3 py-2">{{ __('Serial') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($allMutations as $m)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-slate-800">{{ $m->product?->sku }} — {{ $m->product?->brand }}</td>
                                    <td class="px-3 py-2 text-right">{{ $m->quantity }}</td>
                                    <td class="px-3 py-2 text-right">
                                        @if (($m->biaya_distribusi_per_unit ?? 0) > 0)
                                            {{ number_format((float) $m->biaya_distribusi_per_unit, 0, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-slate-600 font-mono text-xs whitespace-pre-line">{{ $m->serial_numbers ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($cashFlows->isNotEmpty())
                    <div>
                        <p class="text-sm font-semibold text-slate-700 mb-2">{{ __('Pembayaran tercatat (lokasi asal)') }}</p>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left">{{ __('Tanggal') }}</th>
                                        <th class="px-3 py-2 text-left">{{ __('Metode') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Nominal') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($cashFlows as $cf)
                                        <tr>
                                            <td class="px-3 py-2">{{ $cf->transaction_date?->format('d/m/Y') ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $cf->paymentMethod?->display_label ?? '-' }}</td>
                                            <td class="px-3 py-2 text-right">Rp {{ number_format((float) $cf->amount, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-8 border border-red-200 rounded-xl p-6 bg-red-50/40">
            <p class="font-semibold text-red-800 mb-2">{{ __('Batalkan transaksi distribusi') }}</p>
            <p class="text-sm text-red-900/80 mb-4">{{ __('Tindakan ini tidak dapat diurungkan. Pastikan kondisi stok dan kas sudah sesuai.') }}</p>
            <form method="POST" action="{{ route('stock-mutations.cancel', $stockMutation) }}"
                onsubmit="return confirm(@json(__('Batalkan seluruh distribusi untuk invoice ini?')))">
                @csrf
                <div class="flex flex-col gap-3 mb-4">
                    <label for="cancel_reason" class="text-sm font-medium text-slate-700">{{ __('Alasan pembatalan') }} <span class="text-red-600">*</span></label>
                    <textarea id="cancel_reason" name="cancel_reason" rows="3" required maxlength="255"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                        placeholder="{{ __('Jelaskan alasan pembatalan') }}">{{ old('cancel_reason') }}</textarea>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                        {{ __('Konfirmasi pembatalan') }}
                    </button>
                    <a href="{{ route('stock-mutations.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-slate-200 text-slate-800 text-sm font-medium hover:bg-slate-300">
                        {{ __('Batal') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
