<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginLog;
use App\Services\UserActivityLogger;
use App\Support\ClientIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, UserActivityLogger $activity): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            $activity->loginFailed($request);
            throw $e;
        }

        $request->session()->regenerate();

        $agent = trim((string) $request->userAgent());
        LoginLog::create([
            'user_id' => $request->user()?->id,
            'ip_publik' => ClientIp::resolve($request),
            'user_agent' => $agent !== '' ? mb_substr($agent, 0, 500) : null,
            'logged_in_at' => now(),
        ]);
        if ($request->user()) {
            $activity->login($request, $request->user());
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, UserActivityLogger $activity): RedirectResponse
    {
        $activity->logout($request, $request->user());

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
