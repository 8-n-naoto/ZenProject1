<?php

namespace App\Http\Controllers;

use App\Enums\Theme;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\AccountDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AccountDeletionGuard $deletionGuard) {}

    public function edit(): View
    {
        $user = auth()->user();

        $events = \App\Models\Event::query()
            ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
            ->with(['group', 'days'])
            ->orderByDesc('starts_at')
            ->limit(10)
            ->get();

        $stats = [
            'groups' => $user->activeGroups()->count(),
            'events' => \App\Models\Event::query()
                ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
                ->count(),
            'purchased' => (int) \App\Models\PurchaseResult::query()
                ->where('purchase_assignee_user_id', $user->id)
                ->sum('purchased_quantity'),
        ];

        return view('profile.edit', [
            'user' => $user,
            'events' => $events,
            'stats' => $stats,
            'deletionReasons' => $this->deletionGuard->reasons($user),
            'themes' => Theme::options(),
            'currentTheme' => $user->preferredTheme(),
        ]);
    }

    /**
     * 画面の見た目を切り替える。保存するのは表示設定だけで、業務データには影響しない。
     */
    public function updateTheme(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            ['theme' => ['required', Rule::enum(Theme::class)]],
            [],
            ['theme' => 'デザイン']
        );

        $request->user()->update(['theme' => $validated['theme']]);

        return redirect()->route('profile.edit')->with('status', 'デザインを変更しました。');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        unset($validated['email_current_password']);

        $emailChanged = $validated['email'] !== $user->email;
        $previousEmail = $user->email;

        $user->fill($validated);

        if ($emailChanged) {
            // メールアドレスを変更したら再認証を求める
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            // 旧アドレス宛に発行済みの再設定リンクを無効にする
            // （そのアドレスで別人が登録すると、そのリンクが他人に効いてしまうため）
            Password::deleteToken($user);
            DB::table('password_reset_tokens')->where('email', $previousEmail)->delete();

            $user->sendEmailVerificationNotification();

            return redirect()
                ->route('verification.notice')
                ->with('status', 'メールアドレスを変更しました。新しいアドレスに認証メールを送信しました。');
        }

        return redirect()->route('profile.edit')->with('status', 'プロフィールを更新しました。');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $password = $request->validated('password');

        $request->user()->update(['password' => Hash::make($password)]);

        // 他の端末のログインを無効にする
        Auth::logoutOtherDevices($password);

        return redirect()->route('profile.edit')
            ->with('status', 'パスワードを変更しました。他の端末からはログアウトされます。');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // パスワード変更フォームと同じ画面にあるため、フィールド名を分けて
        // ラベルとエラーメッセージが混ざらないようにする
        $request->validate(
            ['deletion_password' => ['required', 'current_password']],
            [],
            ['deletion_password' => 'パスワード']
        );

        $user = $request->user();

        if (! $this->deletionGuard->canDelete($user)) {
            return back()->withErrors(['account' => $this->deletionGuard->reasons($user)]);
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', '退会しました。ご利用ありがとうございました。');
    }
}
