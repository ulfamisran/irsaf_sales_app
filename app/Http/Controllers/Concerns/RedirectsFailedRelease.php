<?php

namespace App\Http\Controllers\Concerns;

use App\Services\AuthorizationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsFailedRelease
{
    protected function redirectReleaseFailure(Request $request, Model $model, string $message): RedirectResponse
    {
        $url = app(AuthorizationService::class)->releaseCompletionUrl($model);

        return redirect()
            ->to($url)
            ->withInput()
            ->with('error', $message)
            ->with('release_incomplete', true);
    }
}
