@forelse ($sales as $sale)
    @php
        $statusLabel = $sale->status === \App\Models\Sale::STATUS_CANCEL
            ? __('Dibatalkan')
            : $sale->status;
        if ($sale->status === \App\Models\Sale::STATUS_OPEN) {
            $payLabel = __('Belum Lunas');
        } elseif ($sale->status === \App\Models\Sale::STATUS_RELEASED) {
            $paid = (float) $sale->total_paid;
            $payLabel = $paid >= (float) $sale->total - 0.02 ? __('Lunas') : __('Belum Lunas');
        } else {
            $payLabel = '-';
        }
        $bankNames = $sale->payments
            ->map(fn ($p) => trim((string) ($p->paymentMethod?->nama_bank ?? '')))
            ->filter()
            ->unique()
            ->values();
        $fallbackMethods = $sale->payments
            ->map(fn ($p) => trim((string) ($p->paymentMethod?->jenis_pembayaran ?? '')))
            ->filter()
            ->unique()
            ->values();
        $methodLabel = $bankNames->isNotEmpty()
            ? $bankNames->implode(', ')
            : ($fallbackMethods->first() ?: '-');

        $productLines = [];
        foreach ($sale->saleDetails as $d) {
            $p = $d->product;
            $parts = array_filter([
                $p?->sku,
                $p?->brand,
                $p?->series,
                $p?->color,
            ], fn ($v) => $v !== null && trim((string) $v) !== '');
            $title = $parts ? implode(' ', $parts) : (__('Produk') . ' #' . $d->product_id);
            $line = $title . ' — ' . (int) $d->quantity . ' × ' . number_format((float) $d->price, 0, ',', '.');
            $sn = trim((string) ($d->serial_numbers ?? ''));
            if ($sn !== '') {
                $snFlat = preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $sn));
                $line .= ' | SN: ' . $snFlat;
            }
            $productLines[] = $line;
        }
        $productsForExcel = implode('<br>', array_map(static fn ($l) => e($l), $productLines));
        $productsPlain = $productLines ? implode("\n", $productLines) : '-';
    @endphp
    <tr>
        <td>{{ $sale->invoice_number }}</td>
        <td>{{ $sale->branch?->name }}</td>
        <td>{{ $sale->customer?->name ?? '-' }}</td>
        @if (! empty($forPdf))
            <td style="white-space: pre-wrap; font-size: 8px; max-width: 220px;">{{ $productsPlain }}</td>
        @else
            <td style="vertical-align: top;">{!! $productsForExcel ?: '-' !!}</td>
        @endif
        <td>{{ $sale->sale_date->format('d/m/Y') }}</td>
        <td>{{ $statusLabel }}</td>
        <td>{{ $payLabel }}</td>
        <td>{{ $methodLabel }}</td>
        <td>{{ $sale->user?->name }}</td>
        <td style="text-align: right;">{{ number_format($sale->total, 0, ',', '.') }}</td>
    </tr>
@empty
    <tr>
        <td colspan="10">{{ __('Tidak ada data penjualan.') }}</td>
    </tr>
@endforelse
