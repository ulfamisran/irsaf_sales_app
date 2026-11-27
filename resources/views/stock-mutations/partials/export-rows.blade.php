@forelse ($distributions as $dist)
    @php
        $invoiceCancelled = $dist->isCancelled();
        $totalBiaya = (float) $dist->total;
        $totalPaid = (float) $dist->total_paid;
        $sisa = max(0, $totalBiaya - $totalPaid);

        if ($invoiceCancelled) {
            $statusBayar = __('Dibatalkan');
        } elseif ($totalBiaya <= 0) {
            $statusBayar = __('Gratis');
        } elseif ($dist->isPaidOff()) {
            $statusBayar = __('Lunas');
        } else {
            $statusBayar = __('Belum Lunas');
        }

        $fromIsWh = $dist->from_location_type === \App\Models\Stock::LOCATION_WAREHOUSE;
        $fromName = $fromIsWh
            ? ($warehousesById[$dist->from_location_id] ?? '#'.$dist->from_location_id)
            : ($branchesById[$dist->from_location_id] ?? '#'.$dist->from_location_id);
        $fromLabel = ($fromIsWh ? __('Gudang') : __('Cabang')).': '.$fromName;

        $toIsWh = $dist->to_location_type === \App\Models\Stock::LOCATION_WAREHOUSE;
        $toName = $toIsWh
            ? ($warehousesById[$dist->to_location_id] ?? '#'.$dist->to_location_id)
            : ($branchesById[$dist->to_location_id] ?? '#'.$dist->to_location_id);
        $toLabel = ($toIsWh ? __('Gudang') : __('Cabang')).': '.$toName;

        $produk = $dist->details->map(function ($detail) {
            $sku = $detail->product?->sku ?? '-';
            $brand = $detail->product?->brand ?? '';
            $qty = (int) $detail->quantity;

            return trim($sku.($brand !== '' ? ' - '.$brand : '').' ('.$qty.' unit)');
        })->implode('; ');
    @endphp
    <tr>
        <td>{{ $dist->invoice_number }}{{ $invoiceCancelled ? ' ('.__('Dibatalkan').')' : '' }}</td>
        <td>{{ $dist->distribution_date?->format('d/m/Y') ?? '-' }}</td>
        <td>{{ $fromLabel }}</td>
        <td>{{ $toLabel }}</td>
        <td>{{ $produk !== '' ? $produk : '-' }}</td>
        <td class="num">{{ number_format($totalBiaya, 0, ',', '.') }}</td>
        <td class="num">{{ number_format($totalPaid, 0, ',', '.') }}</td>
        <td class="num">{{ $invoiceCancelled ? '-' : number_format($sisa, 0, ',', '.') }}</td>
        <td>{{ \App\Support\DocumentStatusPresenter::label($dist->status) }}</td>
        <td>{{ $statusBayar }}</td>
        <td>{{ $dist->user?->name ?? '-' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="11">{{ __('Tidak ada data distribusi.') }}</td>
    </tr>
@endforelse
