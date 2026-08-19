<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Laporan Barang Terjual') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 4px; font-size: 9px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>{{ __('Laporan Barang Terjual') }} — {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</strong></p>
    <p><strong>{{ __('Total Penjualan') }}: {{ number_format($totalPenjualan ?? 0, 0, ',', '.') }}</strong></p>
    <table>
        <thead>
            <tr>
                <th>{{ __('Invoice') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th>{{ __('Produk') }}</th>
                <th>{{ __('Unit') }}</th>
                <th class="num">{{ __('Qty') }}</th>
                <th class="num">{{ __('Nilai') }}</th>
                <th>{{ __('User') }}</th>
                <th>{{ __('Tanggal') }}</th>
                <th>{{ __('Waktu') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['invoice'] }}</td>
                    <td>{{ $row['lokasi'] ?? '-' }}</td>
                    <td>{{ $row['produk'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td class="num">{{ $row['qty'] }}</td>
                    <td class="num">{{ number_format((float) ($row['nilai'] ?? 0), 0, ',', '.') }}</td>
                    <td>{{ $row['user'] }}</td>
                    <td>{{ $row['tanggal']?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $row['waktu']?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>{{ $row['status_lunas'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10">{{ __('Tidak ada data.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
