<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Rekap Penjualan') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 16px; margin: 0 0 8px 0; }
        .meta { margin-bottom: 10px; }
        .meta div { margin: 2px 0; }
        .meta .muted { color: #6b7280; }
        .summary-grid { margin-bottom: 12px; }
        .summary-grid table { border-collapse: collapse; width: 100%; max-width: 520px; }
        .summary-grid td { border: 1px solid #ccc; padding: 4px 8px; }
        .summary-grid .label { background: #f3f4f6; width: 55%; }
        .summary-grid .val { text-align: right; font-weight: bold; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 5px; font-size: 9px; }
        table.data th { background: #f3f4f6; text-align: left; }
        table.data .num { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ __('REKAP PENJUALAN') }}</h1>
    <div class="meta">
        <div><strong>{{ __('Cabang / Lokasi') }}:</strong> {{ $filterMeta['branchLine'] }}</div>
        <div><strong>{{ __('Dari Tanggal') }}:</strong> {{ $filterMeta['dateFrom'] }} &nbsp; <strong>{{ __('Sampai') }}:</strong> {{ $filterMeta['dateTo'] }}</div>
        <div><strong>{{ __('Cari') }}:</strong> {{ $filterMeta['search'] }}</div>
        <div class="muted" style="font-size: 9px; margin-top: 4px;">{{ __('Penjualan dibatalkan tidak disertakan.') }}</div>
    </div>

    <div class="summary-grid">
        <table>
            <tr>
                <td class="label">{{ __('Total Dana Masuk (Gabungan)') }}</td>
                <td class="val">{{ number_format($totalSalesCombined ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Total Dana Masuk (Uang)') }}</td>
                <td class="val">{{ number_format($totalSalesCash ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Dana Masuk (Tukar Tambah)') }}</td>
                <td class="val">{{ number_format($totalTradeIn ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Total Penjualan Released (invoice)') }}</td>
                <td class="val">{{ number_format($totalSales ?? 0, 0, ',', '.') }}</td>
            </tr>
            @foreach ($paymentMethods ?? [] as $pm)
                @php $pmTotal = (float) data_get($paymentMethodTotals ?? [], $pm->id, 0); @endphp
                <tr>
                    <td class="label">{{ $pm->display_label }}</td>
                    <td class="val">{{ number_format($pmTotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Invoice') }}</th>
                <th>{{ __('Cabang') }}</th>
                <th>{{ __('Pelanggan') }}</th>
                <th>{{ __('Produk') }}</th>
                <th>{{ __('Tanggal') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Pembayaran') }}</th>
                <th>{{ __('Metode') }}</th>
                <th>{{ __('User') }}</th>
                <th class="num">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @include('sales.partials.export-sale-rows', ['sales' => $sales, 'forPdf' => true])
        </tbody>
    </table>
</body>
</html>
