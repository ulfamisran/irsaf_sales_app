(function () {
    function swalAvailable() {
        return typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function';
    }

    window.appAlert = async function (message, options = {}) {
        const text = String(message || '');
        if (!swalAvailable()) {
            window.alert(text);
            return;
        }
        await window.Swal.fire({
            icon: options.icon || 'info',
            title: options.title || undefined,
            text,
            confirmButtonText: options.confirmText || 'OK',
            confirmButtonColor: options.confirmColor || '#4f46e5',
        });
    };

    window.appConfirm = async function (message, options = {}) {
        const text = String(message || '');
        if (!swalAvailable()) {
            return window.confirm(text);
        }
        const result = await window.Swal.fire({
            icon: options.icon || 'question',
            title: options.title || 'Konfirmasi',
            text,
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Ya',
            cancelButtonText: options.cancelText || 'Batal',
            confirmButtonColor: options.confirmColor || '#4f46e5',
            cancelButtonColor: options.cancelColor || '#64748b',
            reverseButtons: true,
            focusCancel: true,
        });
        return !!result.isConfirmed;
    };

    document.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        const message = form.getAttribute('data-swal-confirm');
        if (!message || form.dataset.swalConfirmed === '1') {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        const ok = await window.appConfirm(message, {
            icon: form.getAttribute('data-swal-icon') || 'question',
            title: form.getAttribute('data-swal-title') || 'Konfirmasi',
            confirmText: form.getAttribute('data-swal-confirm-text') || 'Ya',
            cancelText: form.getAttribute('data-swal-cancel-text') || 'Batal',
            confirmColor: form.getAttribute('data-swal-confirm-color') || '#4f46e5',
        });
        if (!ok) {
            return;
        }
        form.dataset.swalConfirmed = '1';
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }, true);

    document.addEventListener('click', async function (event) {
        const el = event.target.closest('[data-swal-confirm]');
        if (!el || el.tagName === 'FORM') {
            return;
        }
        // Submit buttons are handled by the form submit listener when data-swal-confirm is on the form.
        // Support confirm on standalone buttons/links that trigger navigation or form submit via formaction.
        if (el.closest('form[data-swal-confirm]')) {
            return;
        }
        const message = el.getAttribute('data-swal-confirm');
        if (!message) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        const ok = await window.appConfirm(message, {
            icon: el.getAttribute('data-swal-icon') || 'question',
            title: el.getAttribute('data-swal-title') || 'Konfirmasi',
            confirmText: el.getAttribute('data-swal-confirm-text') || 'Ya',
            cancelText: el.getAttribute('data-swal-cancel-text') || 'Batal',
            confirmColor: el.getAttribute('data-swal-confirm-color') || '#4f46e5',
        });
        if (!ok) {
            return;
        }
        if (el.tagName === 'A' && el.getAttribute('href')) {
            window.location.href = el.getAttribute('href');
            return;
        }
        const form = el.closest('form');
        if (form) {
            form.dataset.swalConfirmed = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(el.matches('button, input') ? el : undefined);
            } else {
                form.submit();
            }
        }
    }, true);

    function readFlashPayload() {
        const el = document.getElementById('app-flash-messages');
        if (!el) {
            return null;
        }
        try {
            return JSON.parse(el.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    async function showFlashMessages() {
        const flash = readFlashPayload();
        if (!flash) {
            return;
        }

        const validation = Array.isArray(flash.validation) ? flash.validation.filter(Boolean) : [];
        const errorText = flash.error ? String(flash.error) : '';
        const warningText = flash.warning ? String(flash.warning) : '';
        const successText = flash.success ? String(flash.success) : '';

        if (errorText || validation.length) {
            const lines = [];
            if (errorText) {
                lines.push(errorText);
            }
            validation.forEach((msg) => {
                if (msg && msg !== errorText) {
                    lines.push(msg);
                }
            });
            const unique = [...new Set(lines)];
            if (swalAvailable()) {
                await window.Swal.fire({
                    icon: 'error',
                    title: 'Gagal menyimpan',
                    html: '<div style="text-align:left;max-height:280px;overflow:auto;">'
                        + unique.map((m) => '<div style="margin:0 0 6px;">• ' + String(m)
                            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                            + '</div>').join('')
                        + '</div>',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc2626',
                });
            } else {
                window.alert(unique.join('\n'));
            }
        } else if (warningText) {
            await window.appAlert(warningText, { icon: 'warning', title: 'Perhatian', confirmColor: '#d97706' });
        } else if (successText) {
            await window.appAlert(successText, { icon: 'success', title: 'Berhasil', confirmColor: '#059669' });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showFlashMessages);
    } else {
        showFlashMessages();
    }
})();
