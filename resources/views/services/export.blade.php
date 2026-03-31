<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Riwayat Service') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>SERVICE DARI {{ $filterMeta['dateFrom'] ?? '-' }} - {{ $filterMeta['dateTo'] ?? '-' }}</strong></p>
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
            @include('services.partials.export-rows', ['services' => $services])
        </tbody>
    </table>
</body>
</html>
