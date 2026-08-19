@php
    $svcLokasi = function ($svc) {
        if ($svc->warehouse_id || $svc->warehouse) {
            return __('Gudang').': '.($svc->warehouse?->name ?? '#'.$svc->warehouse_id);
        }
        if ($svc->branch_id || $svc->branch) {
            return __('Cabang').': '.($svc->branch?->name ?? '#'.$svc->branch_id);
        }
        return '-';
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Laporan Service') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-bottom: 18px; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
        h3 { margin: 16px 0 8px; }
    </style>
</head>
<body>
    <p><strong>{{ __('Laporan Service') }} — {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</strong></p>

    <h3>{{ __('Barang Masuk Service') }} ({{ $masuk->count() }})</h3>
    <table>
        <thead>
            <tr>
                <th>{{ __('Invoice') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th>{{ __('Pelanggan') }}</th>
                <th>{{ __('Laptop') }}</th>
                <th>{{ __('User') }}</th>
                <th>{{ __('Tanggal Masuk') }}</th>
                <th>{{ __('Waktu Input') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($masuk as $svc)
                <tr>
                    <td>{{ $svc->invoice_number }}</td>
                    <td>{{ $svcLokasi($svc) }}</td>
                    <td>{{ $svc->customer?->name ?? '-' }}</td>
                    <td>{{ trim(($svc->laptop_type ?? '').' '.($svc->laptop_detail ?? '')) ?: '-' }}</td>
                    <td>{{ $svc->user?->name ?? '-' }}</td>
                    <td>{{ $svc->entry_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $svc->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>{{ $svc->status }}</td>
                </tr>
            @empty
                <tr><td colspan="8">{{ __('Tidak ada data.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>{{ __('Barang Keluar (Selesai Service)') }} ({{ $keluar->count() }})</h3>
    <table>
        <thead>
            <tr>
                <th>{{ __('Invoice') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th>{{ __('Pelanggan') }}</th>
                <th>{{ __('Laptop') }}</th>
                <th>{{ __('User') }}</th>
                <th>{{ __('Tanggal Masuk') }}</th>
                <th>{{ __('Tanggal Keluar') }}</th>
                <th>{{ __('Pickup') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($keluar as $svc)
                <tr>
                    <td>{{ $svc->invoice_number }}</td>
                    <td>{{ $svcLokasi($svc) }}</td>
                    <td>{{ $svc->customer?->name ?? '-' }}</td>
                    <td>{{ trim(($svc->laptop_type ?? '').' '.($svc->laptop_detail ?? '')) ?: '-' }}</td>
                    <td>{{ $svc->user?->name ?? '-' }}</td>
                    <td>{{ $svc->entry_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $svc->exit_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $svc->pickup_status === \App\Models\Service::PICKUP_SUDAH ? __('Sudah Diambil') : __('Belum Diambil') }}</td>
                </tr>
            @empty
                <tr><td colspan="8">{{ __('Tidak ada data.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>{{ __('Barang Menunggu Diambil') }} ({{ $menunggu->count() }})</h3>
    <table>
        <thead>
            <tr>
                <th>{{ __('Invoice') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th>{{ __('Pelanggan') }}</th>
                <th>{{ __('Laptop') }}</th>
                <th>{{ __('User') }}</th>
                <th>{{ __('Tanggal Masuk') }}</th>
                <th>{{ __('Tanggal Selesai') }}</th>
                <th>{{ __('Waktu Input') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($menunggu as $svc)
                <tr>
                    <td>{{ $svc->invoice_number }}</td>
                    <td>{{ $svcLokasi($svc) }}</td>
                    <td>{{ $svc->customer?->name ?? '-' }}</td>
                    <td>{{ trim(($svc->laptop_type ?? '').' '.($svc->laptop_detail ?? '')) ?: '-' }}</td>
                    <td>{{ $svc->user?->name ?? '-' }}</td>
                    <td>{{ $svc->entry_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $svc->exit_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $svc->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">{{ __('Tidak ada data.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
