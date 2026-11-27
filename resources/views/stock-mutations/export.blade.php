<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Rekap Distribusi') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>{{ __('DISTRIBUSI') }} {{ $filterMeta['dateFrom'] ?? '-' }} - {{ $filterMeta['dateTo'] ?? '-' }}</strong></p>
    <p><strong>{{ __('Lokasi') }}:</strong> {{ $filterMeta['locationLine'] ?? '-' }}</p>
    <p>
        <strong>{{ __('Produk') }}:</strong> {{ $filterMeta['productLine'] ?? '-' }} |
        <strong>{{ __('Status') }}:</strong> {{ $filterMeta['statusLine'] ?? '-' }} |
        <strong>{{ __('Cari') }}:</strong> {{ $filterMeta['searchLine'] ?? '-' }}
    </p>
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
                <th>{{ __('Tanggal') }}</th>
                <th>{{ __('Dari') }}</th>
                <th>{{ __('Ke') }}</th>
                <th>{{ __('Produk') }}</th>
                <th class="num">{{ __('Total Trx') }}</th>
                <th class="num">{{ __('Total Bayar') }}</th>
                <th class="num">{{ __('Sisa') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Status Bayar') }}</th>
                <th>{{ __('User') }}</th>
            </tr>
        </thead>
        <tbody>
            @include('stock-mutations.partials.export-rows', [
                'distributions' => $distributions,
                'warehousesById' => $warehousesById,
                'branchesById' => $branchesById,
            ])
        </tbody>
    </table>
</body>
</html>
