<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Stock Units</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
        .summary { background: #e0e7ff; font-weight: bold; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Merek</th>
                <th>Seri</th>
                <th>Serial</th>
                <th>Status</th>
                <th>Lokasi</th>
                <th>Received</th>
                <th>Sold</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr class="summary">
                    <td>{{ $product->sku }}</td>
                    <td>{{ $product->brand }}</td>
                    <td>{{ $product->series ?? '-' }}</td>
                    <td colspan="5">In Stock: {{ (int) ($inStockCounts[$product->id] ?? 0) }}</td>
                </tr>
                @forelse ($unitsByProduct->get($product->id, collect()) as $u)
                    @php
                        $locationLabel = $u->location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? 'Gudang' : 'Cabang';
                        $locationName = $u->location_type === \App\Models\Stock::LOCATION_WAREHOUSE
                            ? ($u->warehouse?->name ?? ('#'.$u->location_id))
                            : ($u->branch?->name ?? ('#'.$u->location_id));
                        $soldInfo = $soldInfoBySerial[$u->serial_number] ?? null;
                        $soldDate = $u->sold_at
                            ? $u->sold_at->format('d/m/Y H:i')
                            : ($soldInfo?->sale_date?->format('d/m/Y') ?? null);
                        $invoice = $soldInfo['invoice_number'] ?? null;
                        $soldText = $soldDate ? $soldDate : '';
                        if ($invoice) {
                            $soldText .= ($soldText ? ' | ' : '') . $invoice;
                        }
                    @endphp
                    <tr>
                        <td class="muted">{{ $u->product?->sku ?? '-' }}</td>
                        <td class="muted">{{ $u->product?->brand ?? '-' }}</td>
                        <td class="muted">{{ $u->product?->series ?? '-' }}</td>
                        <td>{{ $u->serial_number }}</td>
                        <td>{{ $statusOptions[$u->status] ?? $u->status }}</td>
                        <td>{{ $locationLabel }}: {{ $locationName }}</td>
                        <td>{{ $u->received_date?->format('d/m/Y') ?? '' }}</td>
                        <td>{{ $soldText }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">Tidak ada unit untuk produk ini.</td>
                    </tr>
                @endforelse
            @empty
                <tr>
                    <td colspan="8">Tidak ada data unit.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
