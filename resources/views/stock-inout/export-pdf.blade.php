<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Mutasi Stok') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        table { border-collapse: collapse; width: 100%; margin-top: 8px; }
        th, td { border: 1px solid #cccccc; padding: 4px; font-size: 8px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
        .cat { background: #e0e7ff; font-weight: bold; }
        .meta { margin: 2px 0; }
        .serial { font-family: DejaVu Sans Mono, monospace; font-size: 7px; }
    </style>
</head>
<body>
    <h3 style="margin:0 0 6px;">{{ __('Mutasi Stok') }}</h3>
    <p class="meta"><strong>{{ __('Lokasi') }}:</strong> {{ $locationDisplay }}</p>
    <p class="meta"><strong>{{ __('Periode') }}:</strong> {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m/Y') }} — {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
    <p class="meta">
        <strong>{{ __('Awal') }}:</strong> {{ number_format((int) ($totals['stok_awal'] ?? 0), 0, ',', '.') }} |
        <strong>{{ __('Masuk') }}:</strong> {{ number_format((int) ($totals['stok_masuk'] ?? 0), 0, ',', '.') }} |
        <strong>{{ __('Diterima') }}:</strong> {{ number_format((int) ($totals['stok_diterima'] ?? 0), 0, ',', '.') }} |
        <strong>{{ __('Terjual') }}:</strong> {{ number_format((int) ($totals['stok_terjual'] ?? 0), 0, ',', '.') }} |
        <strong>{{ __('Rusak') }}:</strong> {{ number_format((int) ($totals['stok_rusak'] ?? 0), 0, ',', '.') }} |
        <strong>{{ __('Akhir') }}:</strong> {{ number_format((int) ($totals['stok_akhir'] ?? 0), 0, ',', '.') }}
    </p>

    <table>
        <thead>
            <tr>
                <th style="width:22%">{{ __('Jenis / Produk') }}</th>
                <th class="num">{{ __('Awal') }}</th>
                <th class="num">{{ __('Masuk') }}</th>
                <th style="width:20%">{{ __('Serial Masuk') }}</th>
                <th class="num">{{ __('Diterima') }}</th>
                <th class="num">{{ __('Terjual') }}</th>
                <th class="num">{{ __('Rusak') }}</th>
                <th class="num">{{ __('Akhir') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categoryRows as $cat)
                <tr class="cat">
                    <td>{{ $cat['category_name'] }}</td>
                    <td class="num">{{ number_format((int) $cat['stok_awal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((int) $cat['stok_masuk'], 0, ',', '.') }}</td>
                    <td></td>
                    <td class="num">{{ number_format((int) $cat['stok_diterima'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((int) $cat['stok_terjual'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((int) $cat['stok_rusak'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((int) $cat['stok_akhir'], 0, ',', '.') }}</td>
                </tr>
                @foreach ($cat['products'] as $row)
                    <tr>
                        <td>{{ $row['product']->sku }} — {{ $row['product']->brand }} {{ $row['product']->series }}</td>
                        <td class="num">{{ number_format((int) $row['stok_awal'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format((int) $row['stok_masuk'], 0, ',', '.') }}</td>
                        <td class="serial">
                            @if (!empty($row['serials_masuk']))
                                @foreach ($row['serials_masuk'] as $i => $snIn)
                                    @php
                                        $sn = is_array($snIn) ? ($snIn['serial'] ?? '') : (string) $snIn;
                                        $checkLabel = is_array($snIn) ? ($snIn['check_label'] ?? '') : '';
                                    @endphp
                                    {{ $i > 0 ? ', ' : '' }}{{ $sn }}@if ($checkLabel !== '') [{{ $checkLabel }}]@endif
                                @endforeach
                            @else
                                -
                            @endif
                        </td>
                        <td class="num">{{ number_format((int) $row['stok_diterima'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format((int) $row['stok_terjual'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format((int) $row['stok_rusak'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format((int) $row['stok_akhir'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;">{{ __('Tidak ada data.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
