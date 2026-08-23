<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * 再設定リンクの申請フォーム。
     */
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * 再設定リンクをメールで送信する。
     */
    public function email(Request $request): RedirectResponse
    {
        $request->validate(
            ['email' => ['required', 'email']],
            [],
            ['email' => 'メールアドレス']
        );

        $status = Password::sendResetLink($request->only('email'));

        // メールアドレスの存在有無を外部に漏らさないため、結果に関わらず同じ案内を返す。
        return back()->with('status', 'パスワード再設定用のメールを送信しました。メールをご確認ください。');
    }

    /**
     * 再設定フォーム。
     */
    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    /**
     * 新しいパスワードを保存する。
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ], [], [
            'email' => 'メールアドレス',
            'password' => 'パスワード',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'パスワードを再設定しました。新しいパスワードでログインしてください。');
    }
}
