@once
    <div id="proof-viewer" hidden style="position:fixed;inset:0;z-index:80;background:rgba(15,23,42,.72);padding:24px;align-items:center;justify-content:center;">
        <div data-proof-viewer-panel style="background:#fff;border-radius:16px;width:min(960px,100%);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 50px rgba(15,23,42,.35);">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;padding:12px 16px;border-bottom:1px solid #e2e8f0;">
                <p data-proof-viewer-title style="margin:0;font-size:14px;font-weight:700;color:#0f172a;word-break:break-word;"></p>
                <button type="button" data-proof-viewer-close style="border:0;border-radius:8px;background:#f1f5f9;color:#0f172a;padding:6px 12px;font-size:13px;font-weight:700;cursor:pointer;">{{ __('Tutup') }}</button>
            </div>
            <div style="background:#0f172a;min-height:280px;display:flex;align-items:center;justify-content:center;overflow:auto;">
                <img data-proof-viewer-image alt="" style="display:none;max-width:100%;max-height:78vh;object-fit:contain;">
                <iframe data-proof-viewer-frame title="{{ __('Bukti transaksi') }}" style="display:none;width:100%;height:78vh;border:0;background:#fff;"></iframe>
            </div>
        </div>
    </div>
    <script>
        function renderProofCard(input) {
            const card = input.closest('[data-proof-card]');
            if (!card) {
                return;
            }
            const image = card.querySelector('[data-proof-image]');
            const pdf = card.querySelector('[data-proof-pdf]');
            const empty = card.querySelector('[data-proof-empty]');
            const name = card.querySelector('[data-proof-name]');
            const clear = card.querySelector('[data-proof-clear]');
            const file = input.files && input.files[0];
            if (image && image.dataset.url) {
                URL.revokeObjectURL(image.dataset.url);
                delete image.dataset.url;
            }
            if (image) {
                image.style.display = 'none';
                image.removeAttribute('src');
            }
            if (pdf) {
                pdf.style.display = 'none';
            }
            if (!file) {
                if (empty) empty.style.display = 'flex';
                if (name) name.textContent = '';
                if (clear) clear.style.display = 'none';
                return;
            }
            if (empty) empty.style.display = 'none';
            if (clear) clear.style.display = 'inline-flex';
            if (name) name.textContent = file.name;
            if (image && (file.type || '').startsWith('image/')) {
                const url = URL.createObjectURL(file);
                image.dataset.url = url;
                image.src = url;
                image.style.display = 'block';
            } else if (pdf) {
                pdf.style.display = 'flex';
            }
        }

        document.addEventListener('change', function (event) {
            const input = event.target;
            if (input.matches('[data-proof-input]')) {
                renderProofCard(input);
                return;
            }
            if (input.matches('[data-plain-file]')) {
                const clear = input.parentElement?.querySelector('[data-plain-file-clear]');
                if (clear) {
                    clear.style.display = input.files && input.files.length ? 'inline-flex' : 'none';
                }
            }
        });

        function closeProofViewer() {
            const viewer = document.getElementById('proof-viewer');
            if (!viewer) {
                return;
            }
            viewer.hidden = true;
            viewer.style.display = 'none';
            const image = viewer.querySelector('[data-proof-viewer-image]');
            const frame = viewer.querySelector('[data-proof-viewer-frame]');
            if (image) {
                image.removeAttribute('src');
                image.style.display = 'none';
            }
            if (frame) {
                frame.removeAttribute('src');
                frame.style.display = 'none';
            }
        }

        function openProofViewer(url, kind, name) {
            const viewer = document.getElementById('proof-viewer');
            if (!viewer) {
                return;
            }
            const image = viewer.querySelector('[data-proof-viewer-image]');
            const frame = viewer.querySelector('[data-proof-viewer-frame]');
            const title = viewer.querySelector('[data-proof-viewer-title]');
            if (title) {
                title.textContent = name || '';
            }
            if (kind === 'pdf') {
                image.style.display = 'none';
                image.removeAttribute('src');
                frame.src = url;
                frame.style.display = 'block';
            } else {
                frame.style.display = 'none';
                frame.removeAttribute('src');
                image.src = url;
                image.style.display = 'block';
            }
            viewer.hidden = false;
            viewer.style.display = 'flex';
        }

        document.addEventListener('click', function (event) {
            const opener = event.target.closest('[data-proof-view]');
            if (opener) {
                event.preventDefault();
                openProofViewer(opener.dataset.proofUrl, opener.dataset.proofKind, opener.dataset.proofName);
                return;
            }
            if (event.target.id === 'proof-viewer' || event.target.closest('[data-proof-viewer-close]')) {
                closeProofViewer();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeProofViewer();
            }
        });

        document.addEventListener('click', function (event) {
            const proofClear = event.target.closest('[data-proof-clear]');
            if (proofClear) {
                event.preventDefault();
                event.stopPropagation();
                const card = proofClear.closest('[data-proof-card]');
                const input = card?.querySelector('[data-proof-input]');
                if (!input) {
                    return;
                }
                input.value = '';
                const note = card.querySelector('input[type="text"]');
                if (note) {
                    note.value = '';
                }
                renderProofCard(input);
                return;
            }

            const plainClear = event.target.closest('[data-plain-file-clear]');
            if (!plainClear) {
                return;
            }
            event.preventDefault();
            const input = plainClear.parentElement?.querySelector('[data-plain-file]');
            if (!input) {
                return;
            }
            input.value = '';
            plainClear.style.display = 'none';
        });
    </script>
@endonce
