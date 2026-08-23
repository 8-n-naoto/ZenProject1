<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $credentials = $request->validated();

        if (! Auth::attempt([
            'user_id' => $credentials['user_id'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            $request->recordFailedAttempt();

            return back()
                ->withErrors(['user_id' => 'ログインIDまたはパスワードが正しくありません。'])
                ->onlyInput('user_id');
        }

        $request->clearRateLimit();
        $request->session()->regenerate();

        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended($this->afterAuthRedirect($request));
    }

    /**
     * 招待リンクから来ていた場合は、その招待に戻す。
     */
    private function afterAuthRedirect(Request $request): string
    {
        $token = $request->session()->get(\App\Http\Controllers\Group\JoinController::SESSION_KEY);

        return $token ? route('join.show', $token) : route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
