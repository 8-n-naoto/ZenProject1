<?php

namespace App\Console\Commands;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use App\Support\AccountRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * すぐにログインできるテストユーザーを作成する。
 *
 *   php artisan users:create-test
 *   php artisan users:create-test taro123 --name=太郎 --password=secret123
 *   php artisan users:create-test --count=3 --group="冬コミ有志の会" --role=responsible
 *
 * メール認証を済ませた状態で作るため、作成直後からログインできる。
 *
 * グループ・イベント・精算まで一式のデモデータが欲しい場合は、こちらを使う:
 *   php artisan db:seed --class=DemoSeeder
 *   （owner001 / leader01 / buyer001 / member01、パスワード password）
 *
 * パスワードが固定のアカウントを作れてしまうため、本番環境では実行できない。
 */
class CreateTestUserCommand extends Command
{
    protected $signature = 'users:create-test
        {user_id? : ログインID（省略時は test001 から順に自動生成）}
        {--name= : 表示名（省略時はログインID）}
        {--email= : メールアドレス（省略時は「ログインID@example.com」）}
        {--password=password : パスワード}
        {--count=1 : まとめて作成する人数（ログインIDを指定した場合は1人のみ）}
        {--unverified : メール未認証の状態で作る（認証フローの確認用）}
        {--group= : 参加させるグループ（IDまたはグループ名）}
        {--role=member : グループでの役割（member / responsible / highest）}
        {--force : 同じログインIDが既にある場合、パスワードを再設定して使えるようにする}';

    protected $description = 'メール認証済みの、すぐにログインできるテストユーザーを作成します';

    /** 自動生成するログインIDの接頭辞と、その上限 */
    private const GENERATED_PREFIX = 'test';

    private const GENERATED_MAX = 999;

    /** 一度に作れる人数の上限（打ち間違いで大量に作らないため） */
    private const COUNT_MAX = 50;

    /** @var array<string, GroupRole> */
    private const ROLES = [
        'member' => GroupRole::Member,
        'responsible' => GroupRole::Responsible,
        'highest' => GroupRole::HighestResponsible,
    ];

    public function handle(): int
    {
        // パスワードが分かりきったアカウントを作れるため、本番では絶対に動かさない
        if (app()->isProduction()) {
            $this->error('本番環境では実行できません。');

            return self::FAILURE;
        }

        $count = (int) $this->option('count');

        if ($count < 1 || $count > self::COUNT_MAX) {
            $this->error('--count は 1〜'.self::COUNT_MAX.' で指定してください。');

            return self::FAILURE;
        }

        $requestedUserId = $this->argument('user_id');

        if ($count > 1 && $requestedUserId !== null) {
            $this->error('ログインIDを指定したときは1人しか作れません。--count を外すか、ログインIDを省略してください。');

            return self::FAILURE;
        }

        if ($count > 1 && $this->option('email') !== null) {
            $this->error('メールアドレスは重複できないため、--count と --email は同時に指定できません。');

            return self::FAILURE;
        }

        $role = self::ROLES[$this->option('role')] ?? null;

        if ($role === null) {
            $this->error('--role は '.implode(' / ', array_keys(self::ROLES)).' のいずれかで指定してください。');

            return self::FAILURE;
        }

        $group = $this->resolveGroup();

        if ($group === false) {
            return self::FAILURE;
        }

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $userId = $requestedUserId ?? $this->generateUserId();

            if ($userId === null) {
                $this->error('自動生成できるログインIDが尽きました。ログインIDを指定して実行してください。');

                return self::FAILURE;
            }

            $user = $this->createOrUpdate($userId);

            if ($user === null) {
                return self::FAILURE;
            }

            if ($group !== null) {
                $this->joinGroup($group, $user, $role);
            }

            $rows[] = [
                $user->user_id,
                $user->name,
                $user->email,
                $this->option('password'),
                $user->hasVerifiedEmail() ? '認証済み' : '未認証',
            ];
        }

        $this->info(count($rows).'人のテストユーザーを用意しました。');
        $this->table(['ログインID', '表示名', 'メールアドレス', 'パスワード', 'メール認証'], $rows);

        if ($group !== null) {
            $this->info('グループ「'.$group->name.'」に'.$role->label().'として参加させました。');
        }

        return self::SUCCESS;
    }

    /**
     * 作成または、--force 指定時は既存ユーザーの作り直し。
     * 入力に問題があればエラーを表示して null を返す。
     */
    private function createOrUpdate(string $userId): ?User
    {
        // 退会済みでもログインIDはDBのユニーク制約に残るため withTrashed で探す
        $existing = User::withTrashed()->where('user_id', $userId)->first();
        $force = (bool) $this->option('force');

        if ($existing !== null && ! $force) {
            $this->error('ログインID「'.$userId.'」はすでに使用されています。作り直す場合は --force を付けてください。');

            return null;
        }

        $password = (string) $this->option('password');

        // --force で作り直すときは、明示指定がなければ元の表示名・メールを保つ
        $email = $this->option('email') ?? $existing?->email ?? $userId.'@example.com';
        $name = $this->option('name') ?? $existing?->name ?? $userId;

        $validator = Validator::make(
            [
                'user_id' => $userId,
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ],
            [
                'user_id' => AccountRules::userId($existing?->id),
                'name' => AccountRules::name(),
                'email' => AccountRules::email($existing?->id),
                'password' => AccountRules::password(),
            ],
            AccountRules::messages(),
            [
                'user_id' => 'ログインID',
                'name' => '表示名',
                'email' => 'メールアドレス',
                'password' => 'パスワード',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return null;
        }

        $user = $existing ?? new User;

        // 退会済みのアカウントを --force で指定した場合は、退会を取り消して再利用する
        if ($existing !== null && $existing->trashed()) {
            $existing->restore();
            $this->warn('退会済みのアカウント「'.$userId.'」を復帰させました。');
        }

        $user->fill([
            'user_id' => $userId,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ])->save();

        if ($this->option('unverified')) {
            $user->forceFill(['email_verified_at' => null])->save();
        } else {
            // MustVerifyEmail の標準API。作成直後からログインできる状態にする
            $user->markEmailAsVerified();
        }

        return $user;
    }

    /**
     * --group の指定をグループに解決する。未指定なら null、見つからなければ false。
     */
    private function resolveGroup(): Group|false|null
    {
        $key = $this->option('group');

        if ($key === null) {
            return null;
        }

        $group = ctype_digit((string) $key)
            ? Group::find((int) $key)
            : Group::where('name', $key)->first();

        if ($group === null) {
            $this->error('グループ「'.$key.'」が見つかりません。');

            return false;
        }

        return $group;
    }

    /**
     * グループに参加させる。過去に脱退していれば在籍中に戻す。
     */
    private function joinGroup(Group $group, User $user, GroupRole $role): void
    {
        $isMember = $group->members()->where('users.id', $user->id)->exists();

        if ($isMember) {
            $group->members()->updateExistingPivot($user->id, [
                'role' => $role->value,
                'joined_at' => now(),
                'left_at' => null,
            ]);

            return;
        }

        $group->members()->attach($user->id, [
            'role' => $role->value,
            'joined_at' => now(),
        ]);
    }

    /**
     * test001 から順に、まだ使われていないログインIDを探す。
     *
     * 退会済みユーザーのログインIDもDBのユニーク制約に残るため、
     * withTrashed で見ないと「生成したのに重複で失敗する」ことになる。
     */
    private function generateUserId(): ?string
    {
        $used = User::withTrashed()
            ->where('user_id', 'like', self::GENERATED_PREFIX.'%')
            ->pluck('user_id')
            ->flip();

        for ($i = 1; $i <= self::GENERATED_MAX; $i++) {
            $candidate = self::GENERATED_PREFIX.str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            if (! $used->has($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
