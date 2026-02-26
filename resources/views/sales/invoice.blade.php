<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Invoice') }} {{ $sale->invoice_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }

        /* Print-friendly invoice layout (match requested format) */
        :root{
            --ink:#111827;
            --muted:#4b5563;
            --border:#111827;
            --light:#f3f4f6;
            --paid:#16a34a;
            --unpaid:#b91c1c;
        }
        /* Landscape print like requested */
        @page { size: A4 landscape; margin: 10mm; }
        body { color: var(--ink); }
        .inv-page{
            /* A4 landscape width: 297mm */
            max-width: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
        }
        .inv-card{
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            padding: 14px 14px 12px;
        }
        @media print{
            .inv-card{ border: none; border-radius: 0; padding: 0; }
        }
        .inv-top{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap: 14px;
            margin-bottom: 6px;
        }
        .inv-company{
            display:flex;
            gap: 10px;
            align-items:flex-start;
            min-width: 55%;
        }
        .inv-logo{
            width: 54px;
            height: 54px;
            border: 2px solid #000;
            background: #facc15;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight: 900;
            letter-spacing: .5px;
            color:#000;
            flex: 0 0 auto;
        }
        .inv-logo img{ max-width: 100%; max-height: 100%; display:block; }
        .inv-co-name{ font-weight: 800; font-size: 15px; line-height: 1.1; }
        .inv-co-line{ font-size: 11px; color: var(--ink); line-height: 1.15; }
        .inv-co-muted{ font-size: 11px; color: var(--muted); line-height: 1.15; }

        .inv-meta{
            text-align:right;
            min-width: 40%;
        }
        .inv-meta .title{ font-weight: 900; font-size: 16px; letter-spacing: .5px; }
        .inv-meta .invno{ font-weight: 800; font-size: 12px; margin-top: 1px; }
        .inv-meta .row{ font-size: 11px; color: var(--ink); line-height: 1.2; }
        .inv-meta .row span{ color: var(--muted); }
        .inv-meta .status-box{
            margin-top: 4px;
            display:inline-block;
            padding: 6px 16px;
            border: 2px solid #16a34a;
            color:#166534;
            font-weight: 900;
            background: #ecfdf5;
            border-radius: 2px;
            font-size: 12px;
        }
        .inv-meta .status-box.unpaid{
            border-color: var(--unpaid);
            color: #7f1d1d;
            background: #fef2f2;
        }
        .inv-meta .status-box.cancelled{
            border-color: #64748b;
            color: #475569;
            background: #f1f5f9;
        }

        .inv-customer{
            font-size: 11px;
            margin: 6px 0 6px;
        }
        .inv-customer table{
            width: 100%;
            border-collapse: collapse;
        }
        .inv-customer td{ padding: 0; vertical-align: top; line-height: 1.2; }
        .inv-customer .lbl{ width: 62px; color: var(--ink); }
        .inv-customer .colon{ width: 10px; }
        .inv-customer .val{ color: var(--ink); }
        .inv-customer .trx{ margin-top: 3px; font-weight: 700; }
        .inv-customer .trx .muted{ font-weight: 500; color: var(--muted); }

        .inv-table{
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .inv-table th, .inv-table td{
            border: 1px solid var(--border);
            padding: 4px 5px;
            line-height: 1.15;
        }
        .inv-table thead th{
            background: #fff;
            font-weight: 800;
            text-align: center;
        }
        .inv-table .num{ text-align: right; white-space: nowrap; }
        .inv-table .center{ text-align: center; }
        .inv-desc{ white-space: pre-line; }
        .inv-desc .sku{ font-weight: 700; }
        .inv-desc .muted{ color: var(--muted); }

        .inv-bottom{
            display:flex;
            gap: 12px;
            margin-top: 6px;
            align-items: stretch;
        }
        .inv-terbilang{
            flex: 1 1 auto;
            border: 1px solid var(--border);
            padding: 6px;
            min-height: 54px;
        }
        .inv-terbilang .label{ font-weight: 800; margin-bottom: 6px; }
        .inv-terbilang .words{ font-style: italic; }
        .inv-totals{
            flex: 0 0 40%;
        }
        .inv-totals table{
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .inv-totals td{
            border: 1px solid var(--border);
            padding: 3px 5px;
            line-height: 1.15;
        }
        .inv-totals td.lbl{ color: var(--ink); }
        .inv-totals td.val{ text-align: right; white-space: nowrap; }

        .inv-section{
            margin-top: 6px;
        }
        .inv-section .label{
            font-weight: 800;
            margin-bottom: 4px;
            font-size: 11px;
        }
        .inv-pay-summary{
            display:flex;
            justify-content:flex-end;
            margin-top: 4px;
            font-size: 11px;
        }
        .inv-pay-summary .box{
            width: 280px;
        }
        .inv-pay-summary .row{
            display:flex;
            justify-content:space-between;
            padding: 2px 0;
        }
        .inv-sign{
            display:flex;
            justify-content:space-between;
            margin-top: 10px;
            font-size: 11px;
        }
        .inv-sign .col{
            width: 46%;
            text-align: left;
        }
        .inv-sign .col.right{
            text-align: right;
        }
        .inv-sign .name{
            margin-top: 26px;
            font-weight: 800;
        }
        /* keep name alignment following column */
    </style>
</head>
<body class="bg-slate-100">
    @php
        $sub = $sale->saleDetails->sum(fn($d) => (float) $d->quantity * (float) $d->price);
        $discount = (float) ($sale->discount_amount ?? 0);
        $adjustment = (float) ($sale->tax_amount ?? 0); // tampil sebagai "Penyesuaian" mengikuti format
        $grandTotal = (float) ($sale->total ?? 0);

        $totalPaid = (float) ($sale->total_paid ?? ($sale->payments?->sum(fn($p) => (float) $p->amount) ?? 0) + ($sale->tradeIns?->sum('trade_in_value') ?? 0));
        $change = max(0, $totalPaid - $grandTotal);
        $isPaid = $sale->status === \App\Models\Sale::STATUS_RELEASED && $totalPaid + 0.00001 >= $grandTotal;

        $statusText = $isPaid ? 'LUNAS' : ($sale->status === \App\Models\Sale::STATUS_CANCEL ? 'DIBATALKAN' : ($sale->status === \App\Models\Sale::STATUS_RELEASED ? 'BELUM LUNAS' : 'DRAFT'));
        $isCancelled = $sale->status === \App\Models\Sale::STATUS_CANCEL;

        $trxAt = $sale->released_at ?? $sale->created_at ?? now();

        if (!function_exists('irsaf_terbilang')) {
            function irsaf_terbilang(int $nilai): string
            {
                $nilai = abs($nilai);
                $huruf = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
                if ($nilai < 12) return $huruf[$nilai];
                if ($nilai < 20) return irsaf_terbilang($nilai - 10) . " belas";
                if ($nilai < 100) return irsaf_terbilang(intdiv($nilai, 10)) . " puluh" . (($nilai % 10) ? " " . irsaf_terbilang($nilai % 10) : "");
                if ($nilai < 200) return "seratus" . (($nilai - 100) ? " " . irsaf_terbilang($nilai - 100) : "");
                if ($nilai < 1000) return irsaf_terbilang(intdiv($nilai, 100)) . " ratus" . (($nilai % 100) ? " " . irsaf_terbilang($nilai % 100) : "");
                if ($nilai < 2000) return "seribu" . (($nilai - 1000) ? " " . irsaf_terbilang($nilai - 1000) : "");
                if ($nilai < 1000000) return irsaf_terbilang(intdiv($nilai, 1000)) . " ribu" . (($nilai % 1000) ? " " . irsaf_terbilang($nilai % 1000) : "");
                if ($nilai < 1000000000) return irsaf_terbilang(intdiv($nilai, 1000000)) . " juta" . (($nilai % 1000000) ? " " . irsaf_terbilang($nilai % 1000000) : "");
                if ($nilai < 1000000000000) return irsaf_terbilang(intdiv($nilai, 1000000000)) . " miliar" . (($nilai % 1000000000) ? " " . irsaf_terbilang($nilai % 1000000000) : "");
                return irsaf_terbilang(intdiv($nilai, 1000000000000)) . " triliun" . (($nilai % 1000000000000) ? " " . irsaf_terbilang($nilai % 1000000000000) : "");
            }
        }

        $terbilang = trim(irsaf_terbilang((int) round($grandTotal)));

        $logoPath = public_path('images/invoice-logo.png');
        $hasLogo = file_exists($logoPath);
    @endphp
    <div class="no-print max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="{{ route('sales.show', $sale) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-200 text-slate-800 hover:bg-slate-300">
            {{ __('Kembali') }}
        </a>
        <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
            {{ __('Print') }}
        </button>
    </div>

    <div class="inv-page px-4 pb-8">
        <div class="inv-card">
            <div class="inv-top">
                <div class="inv-company">
                    <div class="inv-logo" aria-label="Logo">
                        @if ($hasLogo)
                            <img src="{{ asset('images/invoice-logo.png') }}" alt="Logo">
                        @else
                            IT
                        @endif
                    </div>
                    <div>
                        <div class="inv-co-name">{{ $sale->branch?->name ?? config('app.name', 'IRSAF') }}</div>
                        @if ($sale->branch?->address)
                            <div class="inv-co-line">{{ $sale->branch->address }}</div>
                        @endif
                        <div class="inv-co-line">{{ __('Telepon') }}: {{ $sale->branch?->phone ?? '-' }}</div>
                    </div>
                </div>

                <div class="inv-meta">
                    <div class="title">INVOICE</div>
                    <div class="invno">{{ $sale->invoice_number }}</div>
                    <div class="row"><span>Status Bayar:</span> <strong>{{ $statusText }}</strong></div>
                    <div class="row"><span>Termin:</span> -</div>
                    <div class="row"><span>Jatuh Tempo:</span> -</div>
                    <div class="status-box {{ $isCancelled ? 'cancelled' : ($isPaid ? '' : 'unpaid') }}">{{ $isCancelled ? 'DIBATALKAN' : ($isPaid ? 'LUNAS' : 'BELUM LUNAS') }}</div>
                </div>
            </div>

            <div class="inv-customer">
                <table>
                    <tr>
                        <td class="lbl">Nama</td><td class="colon">:</td>
                        <td class="val">{{ $sale->customer?->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">No. Telp</td><td class="colon">:</td>
                        <td class="val">{{ $sale->customer?->phone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Alamat</td><td class="colon">:</td>
                        <td class="val">{{ $sale->customer?->address ?? '-' }}</td>
                    </tr>
                </table>
                <div class="trx">
                    <span class="muted">Transaksi</span>
                    : {{ $trxAt->translatedFormat('d F Y H:i:s') }}
                </div>
            </div>

            <table class="inv-table">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>Deskripsi</th>
                        <th style="width:55px;">Qty</th>
                        <th style="width:105px;">Harga Satuan</th>
                        <th style="width:105px;">Harga Total</th>
                        <th style="width:75px;">Diskon</th>
                        <th style="width:105px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sale->saleDetails as $i => $d)
                        @php
                            $lineTotal = (float) $d->quantity * (float) $d->price;
                            $serialText = trim((string) ($d->serial_numbers ?? ''));
                        @endphp
                        <tr>
                            <td class="center">{{ $i + 1 }}</td>
                            <td class="inv-desc">
                                <div class="sku">
                                    {{ $d->product?->sku ?? '-' }}
                                    {{ $d->product?->brand ? $d->product->brand : '' }}
                                    {{ $d->product?->series ? $d->product->series : '' }}
                                </div>
                                @if ($serialText !== '')
                                    <div class="muted">{{ $serialText }}</div>
                                @endif
                                @if ($loop->first && $sale->description)
                                    <div>{{ $sale->description }}</div>
                                @endif
                            </td>
                            <td class="num">{{ number_format((float) $d->quantity, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) $d->price, 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($lineTotal, 0, ',', '.') }}</td>
                            <td class="num">0</td>
                            <td class="num">{{ number_format($lineTotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="center">-</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="inv-bottom">
                <div class="inv-terbilang">
                    <div class="label">Terbilang</div>
                    <div class="words">"{{ $terbilang === '' ? '-' : $terbilang }} rupiah"</div>
                </div>
                <div class="inv-totals">
                    <table>
                        <tr>
                            <td class="lbl">Subtotal</td>
                            <td class="val">{{ number_format($sub, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Diskon</td>
                            <td class="val">{{ number_format($discount, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Penyesuaian</td>
                            <td class="val">{{ number_format($adjustment, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl"><strong>Total</strong></td>
                            <td class="val"><strong>{{ number_format($grandTotal, 0, ',', '.') }}</strong></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="inv-section">
                <div class="label">Pembayaran</div>
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">No</th>
                            <th style="width:170px;">Tanggal Pembayaran</th>
                            <th>Metode Pembayaran</th>
                            <th style="width:120px;">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $payDate = $sale->released_at ?? $sale->created_at ?? now();
                            $payIdx = 0;
                        @endphp
                        @foreach (($sale->payments ?? collect()) as $p)
                            @php
                                $pm = $p->paymentMethod;
                                $method = $pm ? strtoupper($pm->display_label) : '-';
                                $pDate = $p->created_at ?? $payDate;
                            @endphp
                            <tr>
                                <td class="center">{{ ++$payIdx }}</td>
                                <td class="center">{{ $pDate->translatedFormat('d F Y') }}</td>
                                <td>{{ $method }}</td>
                                <td class="num">{{ number_format((float) $p->amount, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        @foreach ($sale->tradeIns ?? [] as $ti)
                            <tr>
                                <td class="center">{{ ++$payIdx }}</td>
                                <td class="center">{{ $payDate->translatedFormat('d F Y') }}</td>
                                <td>TUKAR TAMBAH ({{ $ti->brand ?? '-' }} {{ $ti->serial_number }})</td>
                                <td class="num">{{ number_format((float) $ti->trade_in_value, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        @if ($payIdx === 0)
                            <tr>
                                <td colspan="4" class="center">-</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                <div class="inv-pay-summary">
                    <div class="box">
                        <div class="row"><span>Total Bayar</span><strong>{{ number_format($totalPaid, 0, ',', '.') }}</strong></div>
                        <div class="row"><span>Kembali</span><strong>{{ number_format($change, 0, ',', '.') }}</strong></div>
                    </div>
                </div>
            </div>

            <div class="inv-sign">
                <div class="col">
                    Customer,
                    <div class="name">{{ $sale->customer?->name ?? '-' }}</div>
                </div>
                <div class="col right">
                    Hormat Kami,
                    <div class="name">{{ $sale->user?->name ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

