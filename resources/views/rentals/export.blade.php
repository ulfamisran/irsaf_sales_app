<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Riwayat Penyewaan') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>PENYEWAAN DARI {{ $filterMeta['dateFrom'] ?? '-' }} - {{ $filterMeta['dateTo'] ?? '-' }}</strong></p>
    <p><strong>CABANG: {{ $filterMeta['branchLine'] ?? '-' }}</strong></p>
    <table>
        <thead>
            <tr>
                <th>{{ __('Nomor Invoice') }}</th>
                <th>{{ __('Tanggal Invoice') }}</th>
                <th class="num">{{ __('Total Bayar') }}</th>
                <th class="num">{{ __('Kurang Bayar') }}</th>
                <th>{{ __('Status Bayar') }}</th>
                <th>{{ __('Nama Customer') }}</th>
                <th>{{ __('User') }}</th>
                <th>{{ __('Cabang') }}</th>
            </tr>
        </thead>
        <tbody>
            @include('rentals.partials.export-rows', ['rentals' => $rentals])
        </tbody>
    </table>
</body>
</html>
