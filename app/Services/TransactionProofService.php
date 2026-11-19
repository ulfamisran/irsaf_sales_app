<?php

namespace App\Services;

use App\Models\TransactionProof;
use App\Support\UploadVault;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransactionProofService
{
    public const MAX_FILES = 2;

    public function validateUploads(Request $request, ?Model $owner, bool $required, bool $noteRequired = false): void
    {
        $incoming = $this->incomingFiles($request);
        $existing = $owner ? $owner->proofs()->count() : 0;
        $notes = $request->input('proof_notes', []);

        $validator = validator($request->all(), [
            'proof_notes' => ['nullable', 'array'],
            'proof_notes.*' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request, $existing, $incoming, $required, $noteRequired, $notes) {
            foreach ($this->rawFiles($request) as $file) {
                if ($file->isValid()) {
                    continue;
                }
                $error = $file->getError();
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $validator->errors()->add('proof_files', $this->uploadErrorMessage($error));
            }
            foreach ($incoming as $index => $file) {
                if ($noteRequired) {
                    $note = is_array($notes) && array_key_exists($index, $notes) ? trim((string) $notes[$index]) : '';
                    if ($note === '') {
                        $validator->errors()->add('proof_notes.'.$index, __('Keterangan bukti wajib diisi.'));
                    }
                }
                if ($file->getSize() > 8192 * 1024) {
                    $validator->errors()->add('proof_files', __('Ukuran bukti transaksi maksimal 8 MB.'));
                }
                if (! $this->isAllowedProof($file)) {
                    $validator->errors()->add('proof_files', __('Bukti transaksi harus berupa gambar atau PDF.'));
                }
            }
            if ($existing + count($incoming) > self::MAX_FILES) {
                $validator->errors()->add('proof_files', __('Maksimal :max bukti transaksi.', ['max' => self::MAX_FILES]));
            }
            if ($required && $existing + count($incoming) < 1) {
                $validator->errors()->add('proof_files', __('Minimal 1 bukti transaksi sebelum status diubah menjadi Release.'));
            }
        });

        $validator->validate();
    }

    public function storeUploaded(Request $request, Model $owner, ?int $userId): void
    {
        $notes = $request->input('proof_notes', []);
        foreach ($this->incomingFiles($request) as $index => $file) {
            if ($owner->proofs()->count() >= self::MAX_FILES) {
                break;
            }
            $note = is_array($notes) && array_key_exists($index, $notes) ? (string) $notes[$index] : null;
            $this->storeFile($owner, $file, $note, $userId);
        }
    }

    public function storeFile(Model $owner, UploadedFile $file, ?string $note, ?int $userId): TransactionProof
    {
        if ($owner->proofs()->count() >= self::MAX_FILES) {
            throw ValidationException::withMessages([
                'proof_file' => __('Maksimal :max bukti transaksi.', ['max' => self::MAX_FILES]),
            ]);
        }

        $folder = 'transaction-proofs/'.class_basename($owner).'/'.$owner->getKey();
        $path = $file->store($folder, 'local');
        UploadVault::mirror('local', $path);

        return $owner->proofs()->create([
            'file_path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getClientMimeType(),
            'note' => trim((string) $note) !== '' ? mb_substr(trim((string) $note), 0, 255) : null,
            'user_id' => $userId,
        ]);
    }

    public function delete(TransactionProof $proof): void
    {
        UploadVault::forget('local', $proof->file_path);
        Storage::disk('local')->delete($proof->file_path);
        $proof->delete();
    }

    public function assertHasProof(Model $owner): void
    {
        if ($owner->proofs()->count() < 1) {
            throw ValidationException::withMessages([
                'proof_files' => __('Minimal 1 bukti transaksi sebelum status diubah menjadi Release.'),
            ]);
        }
    }

    public function allowsFile(mixed $file): bool
    {
        return $file instanceof UploadedFile && $file->isValid() && $this->isAllowedProof($file);
    }

    private function isAllowedProof(UploadedFile $file): bool
    {
        if (! $file->isValid() || ! is_file($file->getPathname())) {
            return false;
        }

        $mime = strtolower((string) $file->getClientMimeType());
        $ext = strtolower($file->getClientOriginalExtension());
        if (is_readable($file->getPathname())) {
            try {
                $guessed = strtolower((string) $file->getMimeType());
                if ($guessed !== '') {
                    $mime = $guessed;
                }
            } catch (Throwable) {
                // Pakai jenis file yang dilaporkan browser bila berkas sementara tidak terbaca.
            }
        }
        if ($ext === 'svg' || str_contains($mime, 'svg')) {
            return false;
        }
        if ($mime === 'application/pdf' || $ext === 'pdf') {
            return true;
        }

        return str_starts_with($mime, 'image/')
            || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif', 'tif', 'tiff', 'jfif', 'avif', 'ico'], true);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function incomingFiles(Request $request): array
    {
        $uploaded = [];
        foreach ($this->rawFiles($request) as $index => $file) {
            if ($file->isValid() && is_file($file->getPathname())) {
                $uploaded[$index] = $file;
            }
        }

        return $uploaded;
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function rawFiles(Request $request): array
    {
        $files = $request->file('proof_files', []);
        if ($files instanceof UploadedFile) {
            $files = [0 => $files];
        }
        if (! is_array($files)) {
            return [];
        }

        $uploaded = [];
        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $uploaded[(int) $index] = $file;
            }
        }

        return $uploaded;
    }

    private function uploadErrorMessage(int $error): string
    {
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return __('Ukuran bukti melebihi batas unggah server (:limit). Cukup unggah 1 file yang lebih kecil.', [
                'limit' => (string) ini_get('upload_max_filesize'),
            ]);
        }

        return __('File bukti tidak dapat dibaca. Unggah 1 gambar atau PDF.');
    }
}
