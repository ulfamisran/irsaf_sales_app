<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Rekap Pembelian') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 4px; font-size: 9px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>{{ __('PEMBELIAN') }} {{ $filterMeta['dateFrom'] ?? '-' }} - {{ $filterMeta['dateTo'] ?? '-' }}</strong></p>
    <p><strong>{{ __('Lokasi') }}:</strong> {{ $filterMeta['locationLine'] ?? '-' }}</p>
    <p><strong>{{ __('Distributor') }}:</strong> {{ $filterMeta['distributorLine'] ?? '-' }} | <strong>{{ __('Status') }}:</strong> {{ $filterMeta['statusLine'] ?? '-' }} | <strong>{{ __('Cari') }}:</strong> {{ $filterMeta['searchLine'] ?? '-' }}</p>
    <p>
        <strong>{{ __('Jumlah Data') }}:</strong> {{ $count ?? 0 }} |
        <strong>{{ __('Total') }}:</strong> {{ number_format($totalAmount ?? 0, 0, ',', '.') }} |
        <strong>{{ __('Terbayar') }}:</strong> {{ number_format($totalPaid ?? 0, 0, ',', '.') }} |
        <strong>{{ __('Sisa Belum Lunas') }}:</strong> {{ number_format($totalUnpaid ?? 0, 0, ',', '.') }}
    </p>
    <table>
        <thead>
            <tr>
                <th>{{ __('No. Invoice') }}</th>
                <th>{{ __('Jenis') }}</th>
                <th>{{ __('Tanggal') }}</th>
                <th>{{ __('Distributor') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th class="num">{{ __('Total') }}</th>
                <th class="num">{{ __('Terbayar') }}</th>
                <th class="num">{{ __('Sisa') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('User') }}</th>
            </tr>
        </thead>
        <tbody>
            @include('purchases.partials.export-rows', ['purchases' => $purchases])
        </tbody>
    </table>
</body>
</html>
