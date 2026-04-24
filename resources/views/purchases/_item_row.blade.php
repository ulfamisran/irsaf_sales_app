@php
    $detail = $detail ?? null;
    $pid = old('items.'.$idx.'.product_id', $detail?->product_id);
    $qty = old('items.'.$idx.'.quantity', $detail?->quantity ?? 1);
    $price = old('items.'.$idx.'.unit_price', $detail?->unit_price);
    $serialsText = old('items.'.$idx.'.serial_numbers_text');
    if ($serialsText === null && $detail?->serial_numbers) {
        $serialsText = trim((string) $detail->serial_numbers);
    }
    $serialsText = $serialsText ?? '';
    $p = $detail?->product;
    $labelText = $p ? (($p->sku ?? '').' — '.trim(($p->brand ?? '').' '.($p->series ?? ''))) : __('Pilih Produk');
@endphp
<div class="purchase-item rounded-lg border border-slate-200 bg-slate-50/50 p-4">
    <div class="purchase-serial-autocomplete relative rounded-lg border border-indigo-100 bg-indigo-50/40 p-3 mb-3">
        <x-input-label :value="__('Cari nomor serial')" class="text-xs font-medium text-indigo-900" />
        <input type="text" class="purchase-serial-search block mt-1 w-full rounded-md border border-indigo-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="{{ __('Ketik minimal 2 karakter, pilih dari daftar — mengisi produk & serial') }}" autocomplete="off">
        <div class="purchase-serial-dropdown hidden absolute left-0 right-0 z-30 mt-1 max-h-52 overflow-auto rounded-md border border-indigo-200 bg-white shadow-lg"></div>
        <p class="mt-1 text-[11px] text-indigo-900/80">{{ __('Mencari unit di semua lokasi. Saat disimpan, lokasi unit mengikuti lokasi pembelian yang dipilih di atas.') }}</p>
    </div>
    <div class="product-selector-block mb-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
            <div>
                <x-input-label :value="__('Merk')" class="mb-1" />
                <select class="brand-filter block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">Semua Merk</option>
                </select>
            </div>
            <div>
                <x-input-label :value="__('Series')" class="mb-1" />
                <select class="series-filter block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">Semua Series</option>
                </select>
            </div>
        </div>
        <div>
            <x-input-label :value="__('Produk')" class="mb-1" />
            <input type="hidden" name="items[{{ $idx }}][product_id]" class="product-id-input" value="{{ $pid }}">
            <div class="product-dropdown-wrapper relative">
                <button type="button" class="product-select-trigger w-full flex items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <span class="product-select-label {{ $pid ? 'text-slate-800' : 'text-slate-500' }}">{{ $pid ? $labelText : 'Pilih Produk' }}</span>
                    <svg class="h-5 w-5 text-slate-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                </button>
                <div class="product-dropdown hidden absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">
                    <div class="p-2 border-b border-gray-100">
                        <input type="text" class="product-search w-full rounded-md border border-gray-300 py-2 px-3 text-sm" placeholder="Cari SKU, merk, series...">
                    </div>
                    <div class="product-dropdown-list max-h-60 overflow-auto py-1"></div>
                    <div class="product-dropdown-empty hidden px-3 py-4 text-sm text-slate-500 text-center">Tidak ada produk yang cocok.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-3 items-start">
        <div class="min-w-0 md:col-span-2">
            <x-input-label :value="__('Qty')" />
            <p class="text-[11px] text-slate-500 -mt-0.5 mb-0.5">{{ __('Jika serial diisi, qty mengikuti jumlah baris serial (1 baris = 1 unit).') }}</p>
            <x-text-input type="number" name="items[{{ $idx }}][quantity]" min="1" value="{{ $qty }}" class="item-qty block mt-1 w-full max-w-full" required />
        </div>
        <div class="min-w-0 md:col-span-2">
            <x-input-label :value="__('Harga Beli')" />
            <x-text-input type="text" name="items[{{ $idx }}][unit_price]" data-rupiah="true" class="item-price block mt-1 w-full max-w-full" placeholder="0" value="{{ $price !== null && $price !== '' ? number_format((float) $price, 0, ',', '.') : '' }}" required />
        </div>
        <div class="min-w-0 md:col-span-8">
            <x-input-label :value="__('Serial (1 per baris, opsional)')" />
            <textarea name="items[{{ $idx }}][serial_numbers_text]" class="item-serials block mt-1 w-full max-w-full min-w-0 rounded-md border-gray-300 shadow-sm text-sm" rows="2" placeholder="SN001&#10;SN002" data-text-name="items[{{ $idx }}][serial_numbers_text]">{{ $serialsText }}</textarea>
        </div>
    </div>
    <div class="mt-2 flex justify-end">
        <button type="button" class="remove-item text-sm text-red-600 hover:text-red-800" style="{{ ($hideRemove ?? false) ? 'display:none' : '' }}">{{ __('Hapus') }}</button>
    </div>
</div>
