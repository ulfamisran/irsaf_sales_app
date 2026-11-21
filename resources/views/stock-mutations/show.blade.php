<x-app-layout>
    <x-slot name="title">{{ __('Detail Distribusi') }} - {{ $distribution->invoice_number }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight flex items-center gap-3 flex-wrap">
                {{ __('Distribusi') }}: {{ $distribution->invoice_number }}
                <x-document-status-badge :status="$distribution->status" />
                @if (! $distribution->isCancelled())
                    @if ($totalBiaya <= 0)
                        <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600">{{ __('Gratis') }}</span>
                    @elseif ($isLunas)
                        <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-700">{{ __('Lunas') }}</span>
                    @else
                        <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-semibold bg-red-100 text-red-700">{{ __('Belum Lunas') }}</span>
                    @endif
                @endif
            </h2>
            <div class="flex flex-wrap items-center gap-2 justify-end">
                <x-icon-btn-back :href="route('stock-mutations.index')" :label="__('Kembali')" />
            </div>
        </div>
    </x-slot>

    @php
        $fromKind = $distribution->from_location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang');
        $toKind = $distribution->to_location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang');
        $sisa = max(0, $totalBiaya - $totalPaid);
    @endphp

    <div class="max-w-6xl mx-auto space-y-6">
        <x-authorization-actions :model="$distribution" module="stock-mutations" :release-route="route('stock-mutations.release', $distribution)" />
        <div class="flex flex-wrap items-center gap-2">
            @if ($distribution->status !== \App\Models\Distribution::STATUS_OPEN)
                <x-action-btn variant="invoice" :href="route('stock-mutations.invoice', $distribution)" target="_blank" :label="__('Invoice')" />
            @endif
            <x-action-btn variant="report" :href="route('stock-mutations.report', $distribution)" target="_blank" />
            <x-action-btn variant="proof" :href="route('stock-mutations.proofs', $distribution)" />
            @if (! $distribution->isCancelled() && $distribution->status !== \App\Models\Distribution::STATUS_OPEN && $totalBiaya > 0 && ! $isLunas)
                <x-action-btn variant="pay" :href="route('stock-mutations.add-payment', $distribution)" />
            @endif
            @if (($canCancelDistribution ?? false) && ! $distribution->isCancelled())
                <x-action-btn variant="cancel" :href="route('stock-mutations.cancel.show', $distribution)" />
            @endif
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-slate-500">{{ __('Dari') }}</p>
                    <p class="font-medium text-slate-800">{{ $fromKind }}: {{ $fromLocation?->name ?? $distribution->from_location_id }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Ke') }}</p>
                    <p class="font-medium text-slate-800">{{ $toKind }}: {{ $toLocation?->name ?? $distribution->to_location_id }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Perpindahan') }}</p>
                    <p class="font-medium text-slate-800">{{ $fromKind }} {{ $fromLocation?->name ?? $distribution->from_location_id }} → {{ $toKind }} {{ $toLocation?->name ?? $distribution->to_location_id }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Tanggal Distribusi') }}</p>
                    <p class="font-medium text-slate-800">{{ $distribution->distribution_date?->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Dibuat Oleh') }}</p>
                    <p class="font-medium text-slate-800">{{ $distribution->user?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Tanggal Catat') }}</p>
                    <p class="font-medium text-slate-800"><x-wita-datetime :value="$distribution->created_at" /></p>
                </div>
                @if ($distribution->notes)
                    <div class="md:col-span-2">
                        <p class="text-sm text-slate-500">{{ __('Catatan') }}</p>
                        <p class="text-sm text-slate-700 whitespace-pre-line">{{ $distribution->notes }}</p>
                    </div>
                @endif
                @if ($distribution->isCancelled())
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Dibatalkan Oleh') }}</p>
                        <p class="font-medium text-slate-800">{{ $distribution->cancelUser?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Tanggal Batal') }}</p>
                        <p class="font-medium text-slate-800">{{ $distribution->cancel_date?->format('d/m/Y') ?? '-' }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-slate-500">{{ __('Alasan Batal') }}</p>
                        <p class="text-sm text-slate-700">{{ $distribution->cancel_reason ?: '-' }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="card-modern p-4">
                <p class="text-xs text-slate-500">{{ __('Total Biaya') }}</p>
                <p class="text-lg font-semibold text-slate-800">Rp {{ number_format($totalBiaya, 0, ',', '.') }}</p>
            </div>
            <div class="card-modern p-4">
                <p class="text-xs text-slate-500">{{ __('Terbayar') }}</p>
                <p class="text-lg font-semibold text-slate-800">Rp {{ number_format($totalPaid, 0, ',', '.') }}</p>
            </div>
            <div class="card-modern p-4">
                <p class="text-xs text-slate-500">{{ __('Sisa') }}</p>
                <p class="text-lg font-semibold {{ $sisa > 0.02 ? 'text-red-700' : 'text-emerald-700' }}">Rp {{ number_format($sisa, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <h3 class="font-semibold text-slate-800 mb-4">{{ __('Unit yang Didistribusi') }}</h3>
                @php
                    $hppBySerial = [];
                    foreach ($distribution->stockMutations as $mutation) {
                        foreach ($mutation->hppChanges() as $change) {
                            if ($change['serial'] !== '') {
                                $hppBySerial[mb_strtoupper($change['serial'])] = $change;
                            }
                        }
                    }
                @endphp
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Nomor Serial') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Biaya / Unit') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total Biaya') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase whitespace-nowrap">{{ __('Perubahan HPP') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase whitespace-nowrap">{{ __('Perpindahan Lokasi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($distribution->details as $detail)
                            @php
                                $serials = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) ($detail->serial_numbers ?? '')) ?: [])));
                                $biaya = (float) ($detail->biaya_distribusi_per_unit ?? 0);
                                $move = $fromKind.' '.($fromLocation?->name ?? $distribution->from_location_id).' → '.$toKind.' '.($toLocation?->name ?? $distribution->to_location_id);
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-sm text-slate-800">
                                    {{ $detail->product?->sku }} - {{ $detail->product?->brand }} {{ $detail->product?->series }}
                                </td>
                                <td class="px-4 py-3 text-sm font-mono text-slate-700 whitespace-nowrap">
                                    @if ($serials === [])
                                        -
                                    @else
                                        @foreach ($serials as $serial)
                                            <div>{{ $serial }}</div>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right">{{ $detail->quantity }}</td>
                                <td class="px-4 py-3 text-sm text-right">Rp {{ number_format($biaya, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-right">Rp {{ number_format($biaya * (int) $detail->quantity, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-800">
                                    @php
                                        $hppLines = $serials === []
                                            ? [null]
                                            : array_map(fn (string $serial) => $hppBySerial[mb_strtoupper($serial)] ?? null, $serials);
                                    @endphp
                                    @foreach ($hppLines as $change)
                                        <div class="whitespace-nowrap">
                                            @if (is_array($change))
                                                Rp {{ number_format($change['origin'], 0, ',', '.') }} → Rp {{ number_format($change['destination'], 0, ',', '.') }}
                                            @else
                                                -
                                            @endif
                                        </div>
                                    @endforeach
                                    @if ($detail->affects_hpp === true)
                                        <div class="text-[11px] text-emerald-700">{{ __('Mempengaruhi HPP') }}</div>
                                    @elseif ($detail->affects_hpp === false)
                                        <div class="text-[11px] text-slate-500">{{ __('Tidak mempengaruhi HPP') }}</div>
                                    @endif
                                    @if ($distribution->isCancelled())
                                        <div class="text-[11px] text-slate-500">{{ __('HPP dikembalikan ke nilai asal') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ $move }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">{{ __('Tidak ada unit pada distribusi ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <h3 class="font-semibold text-slate-800 mb-4">{{ __('Pembayaran') }}</h3>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Catat') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Metode') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Jumlah') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Catatan') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($distribution->payments as $payment)
                            <tr>
                                <td class="px-4 py-3 text-sm text-slate-700"><x-wita-datetime :value="$payment->created_at" /></td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $payment->paymentMethod?->display_label ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $payment->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">{{ __('Belum ada pembayaran.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-transaction-proof-fields
            :slots="0"
            :existing="$distribution->proofs"
            :owner="$distribution"
            :can-delete="false"
            :hint="__('Bukti yang dilampirkan pada distribusi ini.')"
        />
    </div>
</x-app-layout>
