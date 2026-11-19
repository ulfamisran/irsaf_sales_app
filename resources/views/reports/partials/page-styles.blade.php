<style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #e2e8f0; color: #0f172a; font-family: Arial, "Segoe UI", sans-serif; }
    .report-toolbar { max-width: 210mm; margin: 0 auto; padding: 12px 0; display: flex; justify-content: space-between; gap: 12px; align-items: center; }
    .report-toolbar a, .report-toolbar button { border: 0; border-radius: 8px; padding: 8px 14px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; }
    .report-toolbar a { background: #e2e8f0; color: #0f172a; }
    .report-toolbar button { background: #0f172a; color: #fff; }
    .group-title { max-width: 210mm; margin: 10px auto 6px; font-size: 16px; }
    .empty-note { max-width: 210mm; margin: 0 auto 16px; color: #475569; }
    .report-page {
        width: 198mm;
        height: 285mm;
        margin: 0 auto 14px;
        background: #fff;
        border: 1px solid #cbd5e1;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
        padding: 4mm 5mm 3mm;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .doc-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 4mm; margin: 0 0 1.5mm; }
    .invoice-no { margin: 0; font-size: 18px; font-weight: 800; text-decoration: underline; line-height: 1.15; }
    .doc-meta { text-align: right; font-size: 12px; font-weight: 700; line-height: 1.35; }
    .doc-meta span { font-weight: 600; color: #475569; margin-right: 1.5mm; }
    .meta { width: 100%; border-collapse: collapse; font-size: 11px; }
    .meta td { padding: 0.3mm 2mm 0.3mm 0; vertical-align: top; }
    .meta .lbl { width: 38mm; color: #475569; }
    .proof-block h2 { margin: 2mm 0 1.5mm; font-size: 12px; }
    .proofs { display: grid; grid-template-columns: 1fr 1fr; gap: 3mm; }
    .proof { border: 1px solid #cbd5e1; border-radius: 4px; padding: 1.5mm; min-height: 0; }
    .proof img, .proof iframe, .empty { width: 100%; height: 88mm; object-fit: contain; border: 0; background: #f8fafc; }
    .empty { display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 11px; }
    .proof-note { margin: 1mm 0 0; font-size: 12px; font-weight: 700; line-height: 1.25; color: #0f172a; max-height: 8mm; overflow: hidden; }
    .report-page.has-sheet { height: auto; min-height: 285mm; overflow: visible; }
    .report-page.has-sheet .proof img,
    .report-page.has-sheet .proof iframe,
    .report-page.has-sheet .empty { height: 48mm; }
    .purchase-sheet { margin-top: 2mm; }
    .purchase-sheet h2 { margin: 0 0 1.5mm; font-size: 12px; }
    .purchase-sums { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.5mm; margin-bottom: 2mm; }
    .purchase-sums div { border: 1px solid #cbd5e1; border-radius: 3px; padding: 1.2mm 1.5mm; }
    .purchase-sums span { display: block; font-size: 8px; font-weight: 700; color: #475569; }
    .purchase-sums strong { font-size: 11px; }
    .purchase-table { width: 100%; border-collapse: collapse; font-size: 10px; }
    .purchase-table th, .purchase-table td { border: 1px solid #cbd5e1; padding: 0.8mm 1.2mm; vertical-align: top; }
    .purchase-table th { background: #f8fafc; text-align: left; font-size: 9px; }
    .purchase-table .num, .purchase-table th.num, .purchase-table .center { text-align: center; }
    .purchase-table .num { text-align: right; white-space: nowrap; }
    .purchase-product td { background: #f1f5f9; font-weight: 800; font-size: 10px; }
    .purchase-serial { font-family: Consolas, "Courier New", monospace; font-size: 9px; }
    .mark { font-weight: 800; }
    .mark-diterima { color: #047857; }
    .mark-pending { color: #b45309; }
    .mark-retur { color: #be123c; }
    .mark-unchecked { color: #c2410c; }
    .purchase-table tr { break-inside: avoid; page-break-inside: avoid; }
    .invoice-fit {
        flex: 1 1 auto;
        min-height: 0;
        margin-top: 2mm;
        height: 128mm;
        overflow: hidden;
    }
    .invoice-fit .inv-page {
        zoom: 0.4;
    }
    @media print {
        body { background: #fff; }
        .no-print, .report-toolbar { display: none !important; }
        .report-page {
            width: auto;
            height: 285mm;
            margin: 0;
            border: 0;
            box-shadow: none;
            break-after: page;
            page-break-after: always;
        }
        .report-page.has-sheet {
            height: auto;
            overflow: visible;
        }
        .mark { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .report-page:last-of-type {
            break-after: auto;
            page-break-after: auto;
        }
        @page { size: A4 portrait; margin: 6mm; }
    }
</style>
<script>
    (function () {
        function fitInvoices() {
            document.querySelectorAll('.invoice-fit').forEach(function (box) {
                var page = box.querySelector('.inv-page');
                if (!page) return;
                page.style.zoom = '1';
                var availW = box.clientWidth;
                var availH = box.clientHeight;
                var needW = page.offsetWidth;
                var needH = page.offsetHeight;
                if (!availW || !availH || !needW || !needH) return;
                var scale = Math.min(availW / needW, availH / needH, 0.48);
                if (!isFinite(scale) || scale <= 0) return;
                page.style.zoom = String(scale);
            });
        }
        window.addEventListener('load', fitInvoices);
        window.addEventListener('beforeprint', fitInvoices);
    })();
</script>
