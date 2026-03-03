<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Monitoring Stok</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 16px; margin: 0 0 4px 0; }
        .meta { margin-bottom: 10px; }
        .meta div { margin: 2px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 11px; }
        th { background: #f3f4f6; text-align: left; }
        .summary { background: #e0e7ff; font-weight: bold; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>MONITORING STOK</h1>
    <div class="meta">
        <div><strong>CABANG/GUDANG:</strong> {{ $locationLabel }}</div>
        <div><strong>STATUS STOK:</strong> {{ $statusLabel }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Kategori</th>
                <th>Merek</th>
                <th>Type/Seri</th>
                <th>Spesifikasi</th>
                <th>Serial</th>
                <th>Distributor</th>
                <th>Status</th>
                <th>Lokasi</th>
                <th>Received</th>
                <th>Sold</th>
            </tr>
        </thead>
        <tbody>
            @php $tid = $tradeInProductIds ?? []; @endphp
            @forelse ($products as $product)
                @php
                    $isTI = isset($tid[$product->id]);
                    $kat = $isTI ? 'Tukar tambah' : ($product->laptop_type ? ucfirst($product->laptop_type) : '-');
                    $sp = array_filter([$product->processor ? 'Prosesor: '.$product->processor : null, $product->ram ? 'RAM: '.$product->ram : null, $product->storage ? 'Storage: '.$product->storage : null, $product->color ? 'Warna: '.$product->color : null, $product->specs ? trim($product->specs) : null]);
                    $specsStr = implode(' | ', $sp) ?: '-';
                @endphp
                <tr class="summary">
                    <td>{{ $product->sku }}</td>
                    <td>{{ $kat }}</td>
                    <td>{{ $product->brand }}</td>
                    <td>{{ $product->series ?? '-' }}</td>
                    <td>{{ $specsStr }}</td>
                    <td>—</td>
                    <td>{{ $product->distributor?->name ?? '-' }}</td>
                    <td colspan="4">In Stock: {{ (int) ($inStockCounts[$product->id] ?? 0) }} | Harga Modal: {{ number_format($product->purchase_price ?? 0, 0, ',', '.') }}</td>
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
                        $p = $u->product;
                        $uTI = isset($tid[$p->id ?? 0]);
                        $uKat = $uTI ? 'Tukar tambah' : ($p->laptop_type ? ucfirst($p->laptop_type) : '-');
                        $uSp = $p ? array_filter([$p->processor ? 'Prosesor: '.$p->processor : null, $p->ram ? 'RAM: '.$p->ram : null, $p->storage ? 'Storage: '.$p->storage : null, $p->color ? 'Warna: '.$p->color : null, $p->specs ? trim($p->specs) : null]) : [];
                        $uSpecsStr = implode(' | ', $uSp) ?: '-';
                    @endphp
                    <tr>
                        <td class="muted">{{ $p?->sku ?? '-' }}</td>
                        <td class="muted">{{ $uKat }}</td>
                        <td class="muted">{{ $p?->brand ?? '-' }}</td>
                        <td class="muted">{{ $p?->series ?? '-' }}</td>
                        <td class="muted">{{ $uSpecsStr }}</td>
                        <td>{{ $u->serial_number }}</td>
                        <td>{{ $p?->distributor?->name ?? '-' }}</td>
                        <td>{{ $statusOptions[$u->status] ?? $u->status }}</td>
                        <td>{{ $locationLabel }}: {{ $locationName }}</td>
                        <td>{{ $u->received_date?->format('d/m/Y') ?? '' }}</td>
                        <td>{{ $soldText }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">Tidak ada unit untuk produk ini.</td>
                    </tr>
                @endforelse
            @empty
                <tr>
                    <td colspan="11">Tidak ada data unit.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
