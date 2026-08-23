<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\EventCircle;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Policies\EventCirclePolicy;
use App\Policies\EventPolicy;
use App\Policies\GroupPolicy;
use App\Policies\PurchasePolicy;
use App\Policies\SettlementPolicy;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(EventCircle::class, EventCirclePolicy::class);
        Gate::policy(SharedPurchase::class, PurchasePolicy::class);
        Gate::policy(SharedPurchaseItem::class, PurchasePolicy::class);
        Gate::policy(Settlement::class, SettlementPolicy::class);

        Carbon::setLocale(config('app.locale'));

        $this->localizeAuthNotifications();

        // 本番以外では N+1 クエリ（遅延ロード）を検出して落とす
        Model::preventLazyLoading(! app()->isProduction());
    }

    /**
     * 認証系メールを日本語にする。
     */
    private function localizeAuthNotifications(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('【コミケ共同購入管理】メールアドレスの確認')
                ->greeting('こんにちは')
                ->line('以下のボタンを押して、メールアドレスの確認を完了してください。')
                ->action('メールアドレスを確認する', $url)
                ->line('心当たりがない場合は、このメールを破棄してください。')
                ->salutation('コミケ共同購入管理');
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = URL::route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], true);

            return (new MailMessage)
                ->subject('【コミケ共同購入管理】パスワードの再設定')
                ->greeting('こんにちは')
                ->line('パスワード再設定のご依頼を受け付けました。以下のボタンから新しいパスワードを設定してください。')
                ->action('パスワードを再設定する', $url)
                ->line('このリンクは '.config('auth.passwords.users.expire').' 分間有効です。')
                ->line('心当たりがない場合は、このメールを破棄してください。')
                ->salutation('コミケ共同購入管理');
        });
    }
}
