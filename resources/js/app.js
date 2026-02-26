import './bootstrap';

import Alpine from 'alpinejs';
import 'flowbite';

window.Alpine = Alpine;

Alpine.start();

// Global Rupiah formatter for money inputs
// Indonesian format: 8.200.000 (dot = thousands). Decimal like 8200000.00 must stay 8,200,000.
function toNumericString(value) {
    const str = String(value ?? '').trim();
    if (!str) return '';
    // Decimal format (e.g. 8200000.00) -> treat as number, round to int
    if (/^\d+\.\d{1,2}$/.test(str)) {
        return String(Math.round(parseFloat(str)));
    }
    return str.replace(/[^\d]/g, '');
}

function formatRupiah(value) {
    const numeric = toNumericString(value);
    if (!numeric) return '';
    return numeric.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

export function parseRupiahToNumber(value) {
    const numeric = toNumericString(value);
    if (!numeric) return 0;
    return parseFloat(numeric);
}

function attachRupiahFormatter() {
    const inputs = document.querySelectorAll('input[data-rupiah="true"]');
    inputs.forEach((input) => {
        // initial format
        if (input.value) {
            input.value = formatRupiah(input.value);
        }

        input.addEventListener('input', () => {
            const raw = input.value.replace(/[^\d]/g, '');
            input.value = formatRupiah(raw);
            input.setSelectionRange(input.value.length, input.value.length);
        });

        if (input.form && !input.form.__rupiahFormatterBound) {
            input.form.addEventListener('submit', (e) => {
                // Pastikan nilai Rupiah dinormalisasi SEBELUM form dikirim
                const moneyInputs = input.form.querySelectorAll('input[data-rupiah="true"]');
                moneyInputs.forEach((el) => {
                    const raw = String(el.value || '').replace(/[^\d]/g, '');
                    el.value = raw;
                });
            });
            input.form.__rupiahFormatterBound = true;
        }
    });
}

// expose helpers globally for inline scripts
window.attachRupiahFormatter = attachRupiahFormatter;
window.parseRupiahToNumber = parseRupiahToNumber;

document.addEventListener('DOMContentLoaded', attachRupiahFormatter);
