@props([
    'href',
    'variant' => 'default',
    'label' => null,
])

@php
    /**
     * Warna pakai inline style agar tidak bergantung rebuild Tailwind.
     * Setiap variant punya warna berbeda.
     */
    $map = [
        'invoice' => [
            'label' => __('Print Invoice'),
            'bg' => '#4f46e5',
            'bgHover' => '#4338ca',
            'icon' => 'printer',
        ],
        'report' => [
            'label' => __('Cetak Laporan'),
            'bg' => '#0d9488',
            'bgHover' => '#0f766e',
            'icon' => 'document',
        ],
        'exchange' => [
            'label' => __('Tukar Unit'),
            'bg' => '#0284c7',
            'bgHover' => '#0369a1',
            'icon' => 'swap',
        ],
        'edit' => [
            'label' => __('Edit'),
            'bg' => '#d97706',
            'bgHover' => '#b45309',
            'icon' => 'pencil',
        ],
        'edit-hpp' => [
            'label' => __('Edit HPP'),
            'bg' => '#ea580c',
            'bgHover' => '#c2410c',
            'icon' => 'calculator',
        ],
        'update-tx' => [
            'label' => __('Update Transaksi'),
            'bg' => '#7c3aed',
            'bgHover' => '#6d28d9',
            'icon' => 'cog',
        ],
        'proof' => [
            'label' => __('Bukti'),
            'bg' => '#2563eb',
            'bgHover' => '#1d4ed8',
            'icon' => 'photo',
        ],
        'pay' => [
            'label' => __('Bayar'),
            'bg' => '#059669',
            'bgHover' => '#047857',
            'icon' => 'cash',
        ],
        'cancel' => [
            'label' => __('Batal'),
            'bg' => '#dc2626',
            'bgHover' => '#b91c1c',
            'icon' => 'x',
        ],
        'default' => [
            'label' => __('Aksi'),
            'bg' => '#475569',
            'bgHover' => '#334155',
            'icon' => 'dot',
        ],
    ];
    $cfg = $map[$variant] ?? $map['default'];
    $text = $label ?? $cfg['label'];
    $icon = $cfg['icon'];
    $uid = 'ab-'.substr(md5($variant.$href.(string) $text), 0, 8);
@endphp

<a
    id="{{ $uid }}"
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'action-btn inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium shadow-sm shrink-0 whitespace-nowrap no-underline',
    ])->except('style') }}
    style="background-color: {{ $cfg['bg'] }}; color: #fff; text-decoration: none;"
    onmouseover="this.style.backgroundColor='{{ $cfg['bgHover'] }}'"
    onmouseout="this.style.backgroundColor='{{ $cfg['bg'] }}'"
>
    @if ($icon === 'printer')
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
    @elseif ($icon === 'document')
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    @elseif ($icon === 'swap')
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
    @elseif ($icon === 'pencil')
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
    @elseif ($icon === 'calculator')
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m-6 4h.01M15 11h.01M9 15h.01M15 15h.01M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
    @elseif ($icon === 'cog')
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    @elseif ($icon === 'photo')
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    @elseif ($icon === 'cash')
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    @elseif ($icon === 'x')
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    @endif
    <span style="color:#fff;">{{ $text }}</span>
</a>
