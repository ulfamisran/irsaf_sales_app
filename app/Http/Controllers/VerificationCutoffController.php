<?php

namespace App\Http\Controllers;

use App\Support\VerificationCutoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationCutoffController extends Controller
{
    public function edit(): View
    {
        $this->authorizeManager();

        return view('verification-cutoff.edit', [
            'cutoffDate' => VerificationCutoff::date(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeManager();
        $validated = $request->validate([
            'cutoff_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        VerificationCutoff::save($validated['cutoff_date'] ?? null);

        return redirect()
            ->route('verification-cutoff.edit')
            ->with('success', __('Tanggal cut-off verifikasi berhasil disimpan.'));
    }

    private function authorizeManager(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->isStrictSuperAdmin() || $user->isAdminPusat()),
            403,
            __('Hanya Super Admin dan Admin Pusat yang dapat mengatur cut-off verifikasi.')
        );
    }
}
