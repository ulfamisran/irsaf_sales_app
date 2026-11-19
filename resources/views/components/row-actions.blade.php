<div class="sale-actions">
    <button type="button" class="sale-actions-btn">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="12" r="2.2" fill="currentColor"/><circle cx="12" cy="12" r="2.2" fill="currentColor"/><circle cx="18" cy="12" r="2.2" fill="currentColor"/></svg>
        <span>{{ __('Aksi') }}</span>
        <svg class="sale-actions-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="sale-actions-menu" hidden>
        {{ $slot }}
    </div>
</div>
@once
    <style>
        .sale-actions { position: relative; display: inline-block; }
        .sale-actions-btn { display: inline-flex; align-items: center; gap: 6px; border: 0; background: linear-gradient(180deg, #4f46e5 0%, #4338ca 100%); color: #fff; border-radius: 999px; padding: 7px 12px 7px 10px; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 6px 14px rgba(67, 56, 202, .28); }
        .sale-actions-btn svg { width: 18px; height: 18px; flex: 0 0 auto; }
        .sale-actions-caret { width: 16px; height: 16px; transition: transform .15s ease; }
        .sale-actions-btn:hover { background: linear-gradient(180deg, #6366f1 0%, #4f46e5 100%); }
        .sale-actions-btn.is-open .sale-actions-caret { transform: rotate(180deg); }
        .sale-actions-menu { position: fixed; z-index: 80; min-width: 190px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 12px 30px rgba(15, 23, 42, .16); padding: 6px; }
        .sale-actions-menu a,
        .sale-actions-menu button { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; width: 100%; text-align: left; background: transparent; border: 0; cursor: pointer; }
        .sale-actions-menu a svg,
        .sale-actions-menu button svg { width: 16px; height: 16px; flex: 0 0 auto; }
        .sale-actions-menu a:hover,
        .sale-actions-menu button:hover { background: #f1f5f9; }
        .sale-actions-menu form { margin: 0; }
    </style>
    <script>
        document.addEventListener('click', function (event) {
            const button = event.target.closest('.sale-actions-btn');
            const menus = document.querySelectorAll('.sale-actions-menu');
            document.querySelectorAll('.sale-actions-btn.is-open').forEach(function (item) { item.classList.remove('is-open'); });
            if (!button) {
                menus.forEach(function (menu) { menu.hidden = true; });
                return;
            }
            const menu = button.parentElement.querySelector('.sale-actions-menu');
            const opening = menu.hidden;
            menus.forEach(function (item) { item.hidden = true; });
            if (!opening) {
                return;
            }
            menu.hidden = false;
            button.classList.add('is-open');
            const rect = button.getBoundingClientRect();
            const left = Math.max(8, rect.right - menu.offsetWidth);
            let top = rect.bottom + 6;
            if (top + menu.offsetHeight > window.innerHeight - 8) {
                top = Math.max(8, rect.top - menu.offsetHeight - 6);
            }
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
        });
        window.addEventListener('scroll', function () {
            document.querySelectorAll('.sale-actions-menu').forEach(function (menu) { menu.hidden = true; });
            document.querySelectorAll('.sale-actions-btn.is-open').forEach(function (item) { item.classList.remove('is-open'); });
        }, true);
    </script>
@endonce
