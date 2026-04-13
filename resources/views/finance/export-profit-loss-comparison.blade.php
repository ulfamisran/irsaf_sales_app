<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Perbandingan Laba Rugi') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 11px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>{{ __('PERBANDINGAN LABA RUGI') }}</strong></p>
    <p><strong>{{ __('Periode') }}:</strong> {{ $dateFrom ?? '-' }} - {{ $dateTo ?? '-' }}</p>
    <table>
        <thead>
            <tr>
                <th>{{ __('Lokasi') }}</th>
                <th class="num">{{ __('Total Pemasukan') }}</th>
                <th class="num">{{ __('Pengeluaran') }}</th>
                <th class="num">{{ __('Dana (Barang Tukar Tambah)') }}</th>
                <th class="num">{{ __('Beban Barang Rusak') }}</th>
                <th class="num">{{ __('Laba Bersih') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($comparisonData ?? []) as $row)
                <tr>
                    <td>{{ $row['location']['label'] ?? '-' }}</td>
                    <td class="num">{{ number_format((float) ($row['total_pemasukan'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($row['total_pengeluaran'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($row['dana_tukar_tambah'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($row['beban_barang_rusak'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($row['laba_bersih'] ?? 0), 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
