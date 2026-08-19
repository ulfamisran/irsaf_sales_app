<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Laporan Log Pengguna') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
    </style>
</head>
<body>
    <p><strong>{{ __('Laporan Log Pengguna') }} — {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</strong></p>
    <p>
        {{ __('User') }}: {{ $filterUserId ? ($users->firstWhere('id', $filterUserId)?->name ?? $filterUserId) : __('Semua') }}
        | {{ __('Jenis') }}: {{ $filterJenis ?: __('Semua') }}
    </p>
    @if ($totalPembelian !== null)
        <p><strong>{{ __('Total Pembelian') }}: {{ number_format($totalPembelian, 0, ',', '.') }}</strong></p>
    @endif
    @if ($totalPenjualan !== null)
        <p><strong>{{ __('Total Penjualan') }}: {{ number_format($totalPenjualan, 0, ',', '.') }}</strong></p>
    @endif
    <table>
        <thead>
            <tr>
                <th>{{ __('Waktu') }}</th>
                <th>{{ __('Tanggal Transaksi') }}</th>
                <th>{{ __('User') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th>{{ __('Jenis Transaksi') }}</th>
                <th>{{ __('Referensi') }}</th>
                <th>{{ __('Deskripsi') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($activities as $row)
                <tr>
                    <td>{{ $row['waktu']?->format('d/m/Y H:i:s') ?? '-' }}</td>
                    <td>{{ $row['tanggal']?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $row['user'] }}</td>
                    <td>{{ $row['lokasi'] ?? '-' }}</td>
                    <td>{{ $row['jenis'] }}</td>
                    <td>{{ $row['referensi'] }}</td>
                    <td>{{ $row['deskripsi'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7">{{ __('Tidak ada data.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
