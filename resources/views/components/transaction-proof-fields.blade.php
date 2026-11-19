@props(['hint' => null, 'slots' => 2, 'noteRequired' => false, 'existing' => null, 'owner' => null, 'requireOne' => false, 'uploadUrl' => null, 'canDelete' => false])

@php
    $slots = max(0, min(2, (int) $slots));
    $existingProofs = $existing ?? collect();
    $hintText = $hint ?? ($requireOne
        ? __('Wajib 1 file gambar atau PDF. File kedua opsional.')
        : __('Maksimal 2 file gambar atau PDF. Setiap file punya keterangan sendiri.'));
    $proofRoute = match (true) {
        $owner instanceof \App\Models\Sale => 'sales',
        $owner instanceof \App\Models\Service => 'services',
        $owner instanceof \App\Models\Purchase => 'purchases',
        $owner instanceof \App\Models\UnitExchange => 'sales.unit-exchanges',
        $owner instanceof \App\Models\Distribution => 'stock-mutations',
        default => 'rentals',
    };
    $proofParam = match ($proofRoute) {
        'sales' => 'sale',
        'services' => 'service',
        'purchases' => 'purchase',
        'sales.unit-exchanges' => 'exchange',
        'stock-mutations' => 'distribution',
        default => 'rental',
    };
@endphp

<div style="border:1px solid #e2e8f0;border-radius:16px;background:#fff;padding:16px;">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
        <div>
            <p style="margin:0;font-size:14px;font-weight:700;color:#0f172a;">{{ __('Bukti Transaksi') }}</p>
            <p style="margin:4px 0 0;font-size:12px;color:#64748b;">{{ $hintText }}</p>
        </div>
        <span style="font-size:12px;font-weight:600;color:#64748b;white-space:nowrap;">{{ $existingProofs->count() }}/2</span>
    </div>

    @if (($existingProofs->isNotEmpty() && $owner) || $slots > 0)
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px;align-items:start;">
            @if ($existingProofs->isNotEmpty() && $owner)
            @foreach ($existingProofs as $proof)
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
                    <div style="padding:10px 12px;display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                        <div>
                            <button type="button" data-proof-view data-proof-url="{{ $proofUrl }}" data-proof-kind="{{ $proof->isImage() ? 'image' : 'pdf' }}" data-proof-name="{{ $proof->original_name }}" style="border:0;padding:0;background:transparent;cursor:pointer;text-align:left;font-size:12px;font-weight:600;color:#334155;word-break:break-word;">{{ $proof->original_name }}</button>
                            <p style="margin:4px 0 0;font-size:12px;color:#64748b;">{{ $proof->note ?: '-' }}</p>
                        </div>
                        @if ($canDelete)
                            <form method="POST" action="{{ route($proofRoute.'.proofs.destroy', [$proofParam => $owner, 'proof' => $proof]) }}" onsubmit="return confirm('{{ __('Hapus bukti ini?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="border:0;background:transparent;color:#be123c;font-size:12px;font-weight:600;cursor:pointer;">{{ __('Hapus') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
            @endif

    @if ($slots > 0)
        @if ($uploadUrl)
            <form method="POST" action="{{ $uploadUrl }}" enctype="multipart/form-data" style="display:contents;">
        @endif
            @for ($i = 0; $i < $slots; $i++)
                <div data-proof-card style="position:relative;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#fff;">
                    <button type="button" data-proof-clear style="display:none;position:absolute;top:8px;right:8px;z-index:2;align-items:center;border:0;border-radius:999px;background:#fff;color:#be123c;padding:4px 10px;font-size:12px;font-weight:700;cursor:pointer;box-shadow:0 1px 3px rgba(15,23,42,.16);">{{ __('Hapus') }}</button>
                    <label style="display:block;cursor:pointer;margin:0;">
                        <div style="height:150px;background:#f8fafc;position:relative;">
                            <img data-proof-image alt="" style="display:none;width:100%;height:150px;object-fit:cover;">
                            <div data-proof-pdf style="display:none;height:150px;align-items:center;justify-content:center;">
                                <x-proof-pdf-mark />
                            </div>
                            <div data-proof-empty style="height:150px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#64748b;text-align:center;padding:12px;">
                                @php
                                    $proofNumber = $existingProofs->count() + $i + 1;
                                    $proofLabel = $requireOne && $proofNumber === 1
                                        ? __('Bukti 1 (wajib)')
                                        : ($requireOne ? __('Bukti :n (opsional)', ['n' => $proofNumber]) : __('File').' '.$proofNumber);
                                @endphp
                                <span style="font-size:13px;font-weight:600;color:#334155;">{{ $proofLabel }}</span>
                                <span style="font-size:12px;margin-top:4px;">{{ __('Klik untuk memilih gambar atau PDF') }}</span>
                            </div>
                        </div>
                        <input data-proof-input type="file" name="proof_files[]" accept="image/*,.pdf" style="display:none;">
                    </label>
                    <div style="padding:12px;">
                        <p data-proof-name style="margin:0 0 8px;min-height:16px;font-size:12px;color:#475569;word-break:break-word;"></p>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">
                            {{ __('Keterangan') }}
                            @if ($noteRequired)
                                <span style="font-weight:400;color:#94a3b8;">({{ __('wajib jika ada file') }})</span>
                            @endif
                        </label>
                        <input type="text" name="proof_notes[]" value="{{ old('proof_notes.'.$i) }}" maxlength="255" placeholder="{{ __('Keterangan bukti') }}" style="display:block;width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font-size:14px;">
                        <x-input-error :messages="$errors->get('proof_notes.'.$i)" class="mt-1" />
                    </div>
                </div>
            @endfor
        @if ($uploadUrl)
                <div style="grid-column:1 / -1;">
                    @csrf
                    <button type="submit" style="border:0;border-radius:8px;background:#4f46e5;color:#fff;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;">{{ __('Simpan Bukti') }}</button>
                    <x-input-error :messages="$errors->get('proof_files')" class="mt-2" />
                </div>
            </form>
        @else
            <div style="grid-column:1 / -1;">
                <x-input-error :messages="$errors->get('proof_files')" class="mt-2" />
            </div>
        @endif
    @endif
        </div>
    @else
        <x-input-error :messages="$errors->get('proof_files')" class="mt-2" />
    @endif
</div>
<x-proof-preview-script />
