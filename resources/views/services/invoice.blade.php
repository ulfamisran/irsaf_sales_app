<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Invoice') }} {{ $service->invoice_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }

        :root{
            --ink:#111827;
            --muted:#4b5563;
            --border:#111827;
            --light:#f3f4f6;
            --paid:#16a34a;
            --unpaid:#b91c1c;
        }

        @page { size: A4 landscape; margin: 10mm; }

        body { color: var(--ink); }

        .inv-page{
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
            padding: 4px 10px;
            border: 2px solid #16a34a;
            color:#166534;
            font-weight: 800;
            background: #ecfdf5;
            border-radius: 2px;
            font-size: 11px;
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
    </style>
</head>
<body class="bg-slate-100">
    @php
        $grandTotal = (float) $service->service_price;
        $totalPaid = (float) ($service->total_paid ?? $service->payments?->sum('amount') ?? 0);
        $change = max(0, $totalPaid - $grandTotal);
        $isPaid = $service->isPaidOff();
        $isCancelled = $service->status === \App\Models\Service::STATUS_CANCEL;
        $statusText = $isCancelled ? 'DIBATALKAN' : ($isPaid ? 'LUNAS' : 'BELUM LUNAS');
        $trxAt = $service->exit_date ?? $service->entry_date ?? $service->created_at ?? now();

        if (!function_exists('irsaf_terbilang_svc')) {
            function irsaf_terbilang_svc(int $nilai): string {
                $nilai = abs($nilai);
                $huruf = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
                if ($nilai < 12) return $huruf[$nilai];
                if ($nilai < 20) return irsaf_terbilang_svc($nilai - 10) . " belas";
                if ($nilai < 100) return irsaf_terbilang_svc(intdiv($nilai, 10)) . " puluh" . (($nilai % 10) ? " " . irsaf_terbilang_svc($nilai % 10) : "");
                if ($nilai < 200) return "seratus" . (($nilai - 100) ? " " . irsaf_terbilang_svc($nilai - 100) : "");
                if ($nilai < 1000) return irsaf_terbilang_svc(intdiv($nilai, 100)) . " ratus" . (($nilai % 100) ? " " . irsaf_terbilang_svc($nilai % 100) : "");
                if ($nilai < 2000) return "seribu" . (($nilai - 1000) ? " " . irsaf_terbilang_svc($nilai - 1000) : "");
                if ($nilai < 1000000) return irsaf_terbilang_svc(intdiv($nilai, 1000)) . " ribu" . (($nilai % 1000) ? " " . irsaf_terbilang_svc($nilai % 1000) : "");
                if ($nilai < 1000000000) return irsaf_terbilang_svc(intdiv($nilai, 1000000)) . " juta" . (($nilai % 1000000) ? " " . irsaf_terbilang_svc($nilai % 1000000) : "");
                return (string) $nilai;
            }
        }
        $terbilang = trim(irsaf_terbilang_svc((int) round($grandTotal)));
        $hasLogo = file_exists(public_path('images/invoice-logo.png'));
    @endphp

    <div class="no-print max-w-4xl mx-auto px-4 py-4 flex justify-between">
        <a href="{{ route('services.show', $service) }}" class="px-4 py-2 rounded-lg bg-slate-200 text-slate-800 hover:bg-slate-300">{{ __('Kembali') }}</a>
        <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">{{ __('Print') }}</button>
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
                        <div class="inv-co-name">{{ $service->branch?->name ?? config('app.name', 'IRSAF') }}</div>
                        @if ($service->branch?->address)
                            <div class="inv-co-line">{{ $service->branch->address }}</div>
                        @endif
                        <div class="inv-co-line">{{ __('Telepon') }}: {{ $service->branch?->phone ?? '-' }}</div>
                    </div>
                </div>

                <div class="inv-meta">
                    <div class="title">INVOICE SERVICE</div>
                    <div class="invno">{{ $service->invoice_number }}</div>
                    <div class="row"><span>Status Bayar:</span> <strong>{{ $statusText }}</strong></div>
                    <div class="row"><span>Termin:</span> -</div>
                    <div class="row"><span>Jatuh Tempo:</span> -</div>
                    <div class="status-box {{ $isCancelled ? 'cancelled' : ($isPaid ? '' : 'unpaid') }}">{{ $statusText }}</div>
                </div>
            </div>

            <div class="inv-customer">
                <table>
                    <tr>
                        <td class="lbl">Nama</td><td class="colon">:</td>
                        <td class="val">{{ $service->customer?->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">No. Telp</td><td class="colon">:</td>
                        <td class="val">{{ $service->customer?->phone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Alamat</td><td class="colon">:</td>
                        <td class="val">{{ $service->customer?->address ?? '-' }}</td>
                    </tr>
                </table>
                <div class="trx">
                    <span class="muted">Tanggal</span>
                    : {{ $trxAt->translatedFormat('d F Y') }}
                </div>
            </div>

            <table class="inv-table">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>Deskripsi</th>
                        <th style="width:120px;">Harga Service</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="center">1</td>
                        <td>
                            <strong>Service {{ $service->laptop_type }}</strong>
                            @if ($service->laptop_detail)
                                <div style="font-size:10px;color:var(--muted)">{{ $service->laptop_detail }}</div>
                            @endif
                            @if ($service->damage_description)
                                <div style="font-size:10px;margin-top:4px;">Kerusakan: {{ $service->damage_description }}</div>
                            @endif
                        </td>
                        <td class="num">{{ number_format($service->service_price, 0, ',', '.') }}</td>
                    </tr>
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
                        @forelse (($service->payments ?? collect()) as $idx => $p)
                            @php
                                $pm = $p->paymentMethod;
                                $method = $pm ? strtoupper($pm->display_label) : '-';
                                $payDate = $p->transaction_date ?? $p->created_at ?? $trxAt;
                            @endphp
                            <tr>
                                <td class="center">{{ $idx + 1 }}</td>
                                <td class="center">{{ $payDate->translatedFormat('d F Y') }}</td>
                                <td>{{ $method }}</td>
                                <td class="num">{{ number_format((float) $p->amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="center">-</td>
                            </tr>
                        @endforelse
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
                    <div class="name">{{ $service->customer?->name ?? '-' }}</div>
                </div>
                <div class="col right">
                    Hormat Kami,
                    <div class="name">{{ $service->user?->name ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
