<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Mutasi Stok') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 11px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
        .cat { background: #e0e7ff; font-weight: bold; }
    </style>
</head>
<body>
    <p><strong>{{ __('MUTASI STOK') }}</strong></p>
    <p>{{ __('Lokasi') }}: {{ $locationDisplay }} | {{ __('Periode') }}: {{ $dateFrom }} — {{ $dateTo }}</p>
    <p>
        {{ __('Stok Awal') }}: {{ number_format((int) ($totals['stok_awal'] ?? 0), 0, ',', '.') }} |
        {{ __('Stok Masuk') }}: {{ number_format((int) ($totals['stok_masuk'] ?? 0), 0, ',', '.') }} |
        {{ __('Stok Diterima') }}: {{ number_format((int) ($totals['stok_diterima'] ?? 0), 0, ',', '.') }} |
        {{ __('Stok Terjual') }}: {{ number_format((int) ($totals['stok_terjual'] ?? 0), 0, ',', '.') }} |
        {{ __('Stok Rusak') }}: {{ number_format((int) ($totals['stok_rusak'] ?? 0), 0, ',', '.') }} |
        {{ __('Stok Akhir') }}: {{ number_format((int) ($totals['stok_akhir'] ?? 0), 0, ',', '.') }}
    </p>
    <table>
        <thead>
            <tr>
                <th>{{ __('Jenis / Produk') }}</th>
                <th class="num">{{ __('Stok Awal') }}</th>
                <th class="num">{{ __('Stok Masuk') }}</th>
                <th>{{ __('Serial Masuk') }}</th>
                <th class="num">{{ __('Stok Diterima') }}</th>
                <th class="num">{{ __('Stok Terjual') }}</th>
                <th class="num">{{ __('Stok Rusak') }}</th>
                <th class="num">{{ __('Stok Akhir') }}</th>
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
                        <td>
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
                    <td colspan="8">{{ __('Tidak ada data.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
