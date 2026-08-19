<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Laporan Barang Masuk') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>{{ __('Laporan Barang Masuk') }} — {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</strong></p>
    <p><strong>{{ __('Total Pembelian') }}: {{ number_format($totalPembelian ?? 0, 0, ',', '.') }}</strong></p>
    <table>
        <thead>
            <tr>
                <th>{{ __('Jenis') }}</th>
                <th>{{ __('Referensi') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th>{{ __('Produk') }}</th>
                <th class="num">{{ __('Qty') }}</th>
                <th>{{ __('Unit / Serial') }}</th>
                <th>{{ __('User') }}</th>
                <th>{{ __('Tanggal') }}</th>
                <th>{{ __('Waktu') }}</th>
                <th class="num">{{ __('Nilai') }}</th>
                <th>{{ __('Keterangan') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['jenis'] }}</td>
                    <td>{{ $row['referensi'] }}</td>
                    <td>{{ $row['lokasi'] ?? '-' }}</td>
                    <td>{{ $row['produk'] }}</td>
                    <td class="num">{{ $row['qty'] }}</td>
                    <td>{{ $row['serial'] }}</td>
                    <td>{{ $row['user'] }}</td>
                    <td>{{ $row['tanggal']?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $row['waktu']?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td class="num">{{ $row['nilai'] !== null ? number_format((float) $row['nilai'], 0, ',', '.') : '-' }}</td>
                    <td>{{ $row['keterangan'] }}</td>
                </tr>
            @empty
                <tr><td colspan="11">{{ __('Tidak ada data.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
