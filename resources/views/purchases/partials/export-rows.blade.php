@forelse ($purchases as $p)
    @php
        $sisa = max(0, (float) $p->total - (float) $p->total_paid);
        if ($p->isCancelled()) {
            $statusBayar = __('Dibatalkan');
        } elseif ((float) $p->total <= 0) {
            $statusBayar = '-';
        } elseif ($p->isPaidOff()) {
            $statusBayar = __('Lunas');
        } else {
            $statusBayar = __('Belum Lunas');
        }
        $lokasi = $p->warehouse_id
            ? (__('Gudang').': '.($p->warehouse?->name ?? '-'))
            : (__('Cabang').': '.($p->branch?->name ?? '-'));
    @endphp
    <tr>
        <td>{{ $p->invoice_number }}{{ $p->isCancelled() ? ' ('.__('Dibatalkan').')' : '' }}</td>
        <td>{{ $p->jenis_pembelian ?? __('Pembelian Unit') }}</td>
        <td>{{ $p->purchase_date?->format('d/m/Y') ?? '-' }}</td>
        <td>{{ $p->distributor?->name ?? '-' }}</td>
        <td>{{ $lokasi }}</td>
        <td class="num">{{ number_format((float) $p->total, 0, ',', '.') }}</td>
        <td class="num">{{ number_format((float) $p->total_paid, 0, ',', '.') }}</td>
        <td class="num">{{ $p->isCancelled() ? '-' : number_format($sisa, 0, ',', '.') }}</td>
        <td>{{ $statusBayar }}</td>
        <td>{{ $p->user?->name ?? '-' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="10">{{ __('Tidak ada data pembelian.') }}</td>
    </tr>
@endforelse
