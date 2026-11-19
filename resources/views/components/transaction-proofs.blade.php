@props(['owner', 'uploadUrl', 'hint' => null, 'noteRequired' => false])

@php
    $proofs = $owner->proofs ?? collect();
    $open = $owner instanceof \App\Models\Purchase
        ? ! $owner->isCancelled()
        : ($owner->status ?? null) === 'open';
    $proofRoute = match (true) {
        $owner instanceof \App\Models\Sale => 'sales',
        $owner instanceof \App\Models\Service => 'services',
        $owner instanceof \App\Models\Purchase => 'purchases',
        default => 'rentals',
    };
    $proofParam = match ($proofRoute) {
        'sales' => 'sale',
        'services' => 'service',
        'purchases' => 'purchase',
        default => 'rental',
    };
    $hintText = $hint ?? __('Wajib 1 file gambar atau PDF sebelum Release. File kedua opsional, maksimal 2 file.');
@endphp

<div style="margin-top:24px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;padding:16px;">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
        <div>
            <p style="margin:0;font-size:14px;font-weight:700;color:#0f172a;">{{ __('Bukti Transaksi') }}</p>
            <p style="margin:4px 0 0;font-size:12px;color:#64748b;">{{ $hintText }}</p>
        </div>
        <span style="font-size:12px;font-weight:600;color:#64748b;">{{ $proofs->count() }}/2</span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px;align-items:start;">
        @forelse ($proofs as $proof)
            @php $proofUrl = route($proofRoute.'.proofs.show', [$proofParam => $owner, 'proof' => $proof]); @endphp
            <div style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#fff;">
                <button type="button" data-proof-view data-proof-url="{{ $proofUrl }}" data-proof-kind="{{ $proof->isImage() ? 'image' : 'pdf' }}" data-proof-name="{{ $proof->original_name }}" style="display:block;width:100%;border:0;padding:0;background:transparent;cursor:pointer;text-align:left;">
                    <div style="height:150px;background:#f8fafc;display:flex;align-items:center;justify-content:center;">
                        @if ($proof->isImage())
                            <img src="{{ $proofUrl }}" alt="{{ $proof->original_name }}" style="width:100%;height:150px;object-fit:cover;">
                        @else
                            <x-proof-pdf-mark />
                        @endif
                    </div>
                </button>
                <div style="padding:12px;display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                    <div>
                        <button type="button" data-proof-view data-proof-url="{{ $proofUrl }}" data-proof-kind="{{ $proof->isImage() ? 'image' : 'pdf' }}" data-proof-name="{{ $proof->original_name }}" style="border:0;padding:0;background:transparent;cursor:pointer;text-align:left;font-size:12px;font-weight:600;color:#334155;word-break:break-word;">{{ $proof->original_name }}</button>
                        <p style="margin:4px 0 0;font-size:13px;color:#475569;">{{ $proof->note ?: '-' }}</p>
                    </div>
                    @if ($open)
                        <form method="POST" action="{{ route($proofRoute.'.proofs.destroy', [$proofParam => $owner, 'proof' => $proof]) }}" onsubmit="return confirm('{{ __('Hapus bukti ini?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="border:0;background:transparent;color:#be123c;font-size:12px;font-weight:600;cursor:pointer;">{{ __('Hapus') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p style="margin:0;font-size:13px;color:#64748b;">{{ __('Belum ada bukti transaksi.') }}</p>
        @endforelse

        @if ($open && $proofs->count() < 2)
            <form method="POST" action="{{ $uploadUrl }}" enctype="multipart/form-data" data-proof-card style="position:relative;border:1px dashed #cbd5e1;border-radius:14px;overflow:hidden;background:#fff;">
                @csrf
                <button type="button" data-proof-clear style="display:none;position:absolute;top:8px;right:8px;z-index:2;align-items:center;border:0;border-radius:999px;background:#fff;color:#be123c;padding:4px 10px;font-size:12px;font-weight:700;cursor:pointer;box-shadow:0 1px 3px rgba(15,23,42,.16);">{{ __('Hapus') }}</button>
                <label style="display:block;cursor:pointer;margin:0;">
                    <div style="height:150px;background:#f8fafc;position:relative;">
                        <img data-proof-image alt="" style="display:none;width:100%;height:150px;object-fit:cover;">
                        <div data-proof-pdf style="display:none;height:150px;align-items:center;justify-content:center;">
                            <x-proof-pdf-mark />
                        </div>
                        <div data-proof-empty style="height:150px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#64748b;text-align:center;padding:12px;">
                            <span style="font-size:13px;font-weight:600;color:#334155;">{{ __('Tambah bukti') }}</span>
                            <span style="font-size:12px;margin-top:4px;">{{ __('Klik untuk memilih gambar atau PDF') }}</span>
                        </div>
                    </div>
                    <input data-proof-input type="file" name="proof_file" accept="image/*,.pdf" required style="display:none;">
                </label>
                <div style="padding:12px;">
                    <p data-proof-name style="margin:0 0 8px;min-height:16px;font-size:12px;color:#475569;word-break:break-word;"></p>
                    <input type="text" name="note" value="{{ old('note') }}" maxlength="255" @if ($noteRequired) required @endif placeholder="{{ __('Keterangan bukti') }}" style="display:block;width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font-size:14px;">
                    <x-input-error :messages="$errors->get('proof_file')" class="mt-1" />
                    <x-input-error :messages="$errors->get('note')" class="mt-1" />
                    <button type="submit" style="margin-top:10px;border:0;border-radius:8px;background:#4f46e5;color:#fff;padding:8px 12px;font-size:13px;font-weight:600;cursor:pointer;">{{ __('Simpan Bukti') }}</button>
                </div>
            </form>
        @endif
    </div>
    <x-input-error :messages="$errors->get('proof_files')" class="mt-2" />
</div>
<x-proof-preview-script />
