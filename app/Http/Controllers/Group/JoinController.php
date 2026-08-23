<?php

namespace App\Http\Controllers\Group;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupInviteLink;
use App\Services\GroupInviteLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 招待リンク（合い言葉）でのグループ参加。
 *
 * 未ログイン・未登録でも開けるようにして、
 * ログイン／登録が済んだらそのまま参加まで進める。
 */
class JoinController extends Controller
{
    /** 招待トークンを覚えておくセッションキー */
    public const SESSION_KEY = 'pending_invite_token';

    public function __construct(private readonly GroupInviteLinkService $links) {}

    /**
     * 合い言葉の入力画面。
     */
    public function form(): View
    {
        return view('join.form');
    }

    /**
     * 合い言葉から招待リンクの確認画面へ。
     */
    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ], [], ['token' => '合い言葉']);

        return redirect()->route('join.show', trim($validated['token']));
    }

    /**
     * 招待の内容を見せて、参加するかどうかを確認する。
     */
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $link = $this->links->findUsable($token);

        if ($link === null) {
            return redirect()->route('join.form')
                ->withErrors(['token' => 'この招待リンクは見つかりません。合い言葉を確認してください。']);
        }

        // 未ログインならログイン後に戻ってこられるよう覚えておく
        if (! $request->user()) {
            $request->session()->put(self::SESSION_KEY, $link->token);
        }

        return view('join.show', [
            'link' => $link,
            'group' => $link->group,
            'reason' => $link->unusableReason(),
            'alreadyMember' => $request->user() !== null && $link->group->isActiveMember($request->user()),
        ]);
    }

    /**
     * 参加する。
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $link = $this->links->findUsable($token);

        if ($link === null) {
            return redirect()->route('join.form')
                ->withErrors(['token' => 'この招待リンクは見つかりません。']);
        }

        try {
            $group = $this->links->join($link, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('groups.show', $group)
            ->with('status', '「'.$group->name.'」に参加しました。');
    }

    /**
     * 招待リンクを発行する（責任者以上）。
     */
    public function issue(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('invite', $group);

        $validated = $request->validate([
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ], [], [
            'max_uses' => '使用回数',
            'expires_in_days' => '有効期間',
        ]);

        try {
            $this->links->issue(
                $group,
                $request->user(),
                $validated['max_uses'] ?? null,
                $validated['expires_in_days'] ?? 7,
            );
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', '招待リンクを発行しました。');
    }

    /**
     * 招待リンクを無効にする（責任者以上）。
     */
    public function revoke(Request $request, Group $group, GroupInviteLink $link): RedirectResponse
    {
        $this->authorize('invite', $group);
        abort_unless($link->group_id === $group->id, 404);

        try {
            $this->links->revoke($link, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', '招待リンクを無効にしました。');
    }
}
