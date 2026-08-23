<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * すぐにログインできるテストユーザーを作成する。
 *
 *   php artisan users:create-test
 *   php artisan users:create-test taro123 --name=太郎 --email=taro@example.com --password=secret123
 *
 * メール認証を済ませた状態で作成するため、作成直後からログインできる。
 */
class CreateTestUserCommand extends Command
{
    protected $signature = 'users:create-test
        {user_id? : ログインID（省略時は自動生成）}
        {--name= : 表示名（省略時はログインIDを使用）}
        {--email= : メールアドレス（省略時は "ログインID@example.com"）}
        {--password=password : パスワード（省略時は "password"）}';

    protected $description = 'メール認証済みの、すぐにログインできるテストユーザーを作成します';

    public function handle(): int
    {
        $userId = $this->argument('user_id') ?? $this->generateUserId();
        $email = $this->option('email') ?? $userId.'@example.com';
        $name = $this->option('name') ?? $userId;
        $password = $this->option('password');

        $validator = Validator::make(
            [
                'user_id' => $userId,
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ],
            [
                'user_id' => ['required', 'string', 'min:5', 'max:15', 'regex:/^[a-z0-9]+$/', 'unique:users,user_id'],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:1'],
            ],
            [
                'user_id.regex' => 'ログインIDは英小文字と数字のみで入力してください。',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'user_id' => $userId,
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        // 作成直後からログインできるよう、メール認証済みにしておく
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info('テストユーザーを作成しました。');
        $this->table(
            ['項目', '値'],
            [
                ['ログインID', $userId],
                ['表示名', $name],
                ['メールアドレス', $email],
                ['パスワード', $password],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * ログインID未指定時に、規約（英小文字・数字、5〜15文字）を満たすIDを生成する。
     */
    private function generateUserId(): string
    {
        do {
            $userId = 'test'.random_int(10000, 999999);
        } while (User::query()->where('user_id', $userId)->exists());

        return $userId;
    }
}
