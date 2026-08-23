<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View
    {
        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->fulfill();

        return redirect()->to($this->afterVerifyRedirect($request))
            ->with('status', 'メールアドレスの認証が完了しました。');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', '認証メールを再送信しました。');
    }

    /**
     * 招待リンクから登録した場合は、認証後にその招待へ戻す。
     */
    private function afterVerifyRedirect(Request $request): string
    {
        $token = $request->session()->get(\App\Http\Controllers\Group\JoinController::SESSION_KEY);

        return $token ? route('join.show', $token) : route('dashboard');
    }
}
