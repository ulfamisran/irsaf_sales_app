<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Rekap Penjualan') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
        .meta td { font-weight: 600; background: #fafafa; }
        .summary { background: #e0e7ff; font-weight: bold; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <table>
        <tr class="meta">
            <td colspan="2">{{ __('Rekap Penjualan') }}</td>
        </tr>
        <tr><td>{{ __('Cabang / Lokasi') }}</td><td>{{ $filterMeta['branchLine'] }}</td></tr>
        <tr><td>{{ __('Dari Tanggal') }}</td><td>{{ $filterMeta['dateFrom'] }}</td></tr>
        <tr><td>{{ __('Sampai Tanggal') }}</td><td>{{ $filterMeta['dateTo'] }}</td></tr>
        <tr><td>{{ __('Cari') }}</td><td>{{ $filterMeta['search'] }}</td></tr>
        <tr><td colspan="2">{{ __('Penjualan dibatalkan tidak disertakan dalam unduhan ini.') }}</td></tr>
        <tr class="summary">
            <td>{{ __('Total Dana Masuk (Gabungan)') }}</td>
            <td class="num">{{ number_format($totalSalesCombined ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr class="summary">
            <td>{{ __('Total Dana Masuk (Uang)') }}</td>
            <td class="num">{{ number_format($totalSalesCash ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr class="summary">
            <td>{{ __('Dana Masuk (Barang / Tukar Tambah)') }}</td>
            <td class="num">{{ number_format($totalTradeIn ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr class="summary">
            <td>{{ __('Total Penjualan Released (nilai invoice)') }}</td>
            <td class="num">{{ number_format($totalSales ?? 0, 0, ',', '.') }}</td>
        </tr>
        @foreach ($paymentMethods ?? [] as $pm)
            @php $pmTotal = (float) data_get($paymentMethodTotals ?? [], $pm->id, 0); @endphp
            <tr>
                <td>{{ $pm->display_label }}</td>
                <td class="num">{{ number_format($pmTotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <br>

    <table>
        <thead>
            <tr>
                <th>{{ __('Invoice') }}</th>
                <th>{{ __('Cabang') }}</th>
                <th>{{ __('Pelanggan') }}</th>
                <th>{{ __('Produk dijual') }}</th>
                <th>{{ __('Tanggal') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Pembayaran') }}</th>
                <th>{{ __('Metode (Bank)') }}</th>
                <th>{{ __('User') }}</th>
                <th class="num">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @include('sales.partials.export-sale-rows', ['sales' => $sales, 'forPdf' => false])
        </tbody>
    </table>
</body>
</html>
