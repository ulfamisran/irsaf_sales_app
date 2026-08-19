<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Laporan Barang Rusak') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>{{ __('Laporan Barang Rusak') }} — {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</strong></p>
    <p><strong>{{ __('Total HPP') }}: {{ number_format($totalHpp ?? 0, 0, ',', '.') }}</strong></p>
    <table>
        <thead>
            <tr>
                <th>{{ __('Tanggal') }}</th>
                <th>{{ __('Waktu') }}</th>
                <th>{{ __('Produk') }}</th>
                <th>{{ __('No. Serial') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th>{{ __('Deskripsi') }}</th>
                <th class="num">{{ __('HPP') }}</th>
                <th>{{ __('User') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $dg)
                <tr>
                    <td>{{ $dg->recorded_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $dg->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>{{ ($dg->productUnit?->product?->sku ?? '') . ' - ' . ($dg->productUnit?->product?->brand ?? '') . ' ' . ($dg->productUnit?->product?->series ?? '') }}</td>
                    <td>{{ $dg->serial_number }}</td>
                    <td>
                        @if ($dg->productUnit?->location_type === 'warehouse')
                            {{ __('Gudang') }}: {{ $dg->productUnit?->warehouse?->name ?? '-' }}
                        @else
                            {{ __('Cabang') }}: {{ $dg->productUnit?->branch?->name ?? '-' }}
                        @endif
                    </td>
                    <td>{{ $dg->damage_description }}</td>
                    <td class="num">{{ number_format((float) $dg->harga_hpp, 0, ',', '.') }}</td>
                    <td>{{ $dg->user?->name ?? '-' }}</td>
                    <td>{{ $dg->isReactivated() ? __('Diaktifkan Kembali') : __('Dicadangkan') }}</td>
                </tr>
            @empty
                <tr><td colspan="9">{{ __('Tidak ada data.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
