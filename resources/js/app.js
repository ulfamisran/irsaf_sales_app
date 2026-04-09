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
    // Indonesian format with comma decimals (e.g. 7.800.000,00)
    if (/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/.test(str)) {
        const head = str.split(',')[0] || '';
        return head.replace(/\./g, '');
    }
    // Simple comma decimal (e.g. 7800000,00)
    if (/^\d+,\d{1,2}$/.test(str)) {
        return String(Math.round(parseFloat(str.replace(',', '.'))));
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

function wrapWithRpPrefix(input) {
    if (input.dataset.rpWrapped) {
        const wrapper = input.parentElement;
        if (wrapper?.classList?.contains('relative')) {
            wrapper.classList.add('w-full', 'max-w-full', 'min-w-0');
        }
        if (!input.classList.contains('w-full')) {
            input.classList.add('w-full', 'max-w-full', 'min-w-0');
        }
        return;
    }
    input.dataset.rpWrapped = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'relative w-full max-w-full min-w-0';

    const prefix = document.createElement('span');
    prefix.className = 'absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500 text-sm pointer-events-none';
    prefix.textContent = 'Rp';

    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(prefix);
    wrapper.appendChild(input);

    if (!input.classList.contains('pl-10')) {
        input.classList.remove('pl-3', 'px-3');
        input.classList.add('pl-10');
    }
    if (!input.classList.contains('w-full')) {
        input.classList.add('w-full', 'max-w-full', 'min-w-0');
    }
}

function attachRupiahFormatter(container) {
    const root = container || document;
    const inputs = root.querySelectorAll('input[data-rupiah="true"]');
    inputs.forEach((input) => {
        wrapWithRpPrefix(input);

        if (input.dataset.rpFormatBound) return;
        input.dataset.rpFormatBound = '1';

        if (input.value) {
            input.value = formatRupiah(input.value);
        }

        input.addEventListener('input', () => {
            const raw = toNumericString(input.value);
            input.value = formatRupiah(raw);
            input.setSelectionRange(input.value.length, input.value.length);
        });

        if (input.form && !input.form.__rupiahFormatterBound) {
            input.form.addEventListener('submit', (e) => {
                const moneyInputs = input.form.querySelectorAll('input[data-rupiah="true"]');
                moneyInputs.forEach((el) => {
                    const raw = toNumericString(el.value);
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
