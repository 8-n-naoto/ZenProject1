<?php

use App\Http\Controllers\Approval\ApprovalController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Catalog\CircleController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Event\BudgetController;
use App\Http\Controllers\Event\ChangeHistoryController;
use App\Http\Controllers\Event\EventController;
use App\Http\Controllers\Event\EventMemberController;
use App\Http\Controllers\Event\ExportController;
use App\Http\Controllers\Event\VenueMapController;
use App\Http\Controllers\Group\GroupController;
use App\Http\Controllers\Group\InvitationController;
use App\Http\Controllers\Group\JoinController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchase\PersonalPurchaseController;
use App\Http\Controllers\Purchase\PurchaseResultController;
use App\Http\Controllers\Purchase\SharedPurchaseController;
use App\Http\Controllers\Purchase\ShoppingListController;
use App\Http\Controllers\Settlement\SettlementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ゲスト（未ログイン）向け
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // パスワード再設定
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| 招待リンク（合い言葉）でのグループ参加
|--------------------------------------------------------------------------
| 未登録の人にも開いてもらえるよう、閲覧はログイン不要にする。
*/
Route::get('/join', [JoinController::class, 'form'])->name('join.form');
Route::post('/join', [JoinController::class, 'lookup'])->name('join.lookup');
Route::get('/join/{token}', [JoinController::class, 'show'])->name('join.show');
Route::post('/join/{token}', [JoinController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('join.store');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| メール認証
|--------------------------------------------------------------------------
*/
Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['auth', 'signed'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

/*
|--------------------------------------------------------------------------
| ログイン済み・メール認証済み
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // グループ
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('/groups/{group}/edit', [GroupController::class, 'edit'])->name('groups.edit');
    Route::patch('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');

    // メンバー招待・管理
    Route::get('/groups/{group}/search-users', [GroupController::class, 'searchUsers'])->name('groups.search-users');
    Route::post('/groups/{group}/invite/{user}', [GroupController::class, 'invite'])->name('groups.invite');
    Route::post('/groups/{group}/invite-link', [JoinController::class, 'issue'])->name('groups.invite-link.issue');
    Route::delete('/groups/{group}/invite-link/{link}', [JoinController::class, 'revoke'])->name('groups.invite-link.revoke');
    Route::delete('/groups/{group}/invitations/{invitation}', [InvitationController::class, 'cancel'])->name('groups.invitations.cancel');
    Route::patch('/groups/{group}/members/{user}/role', [GroupController::class, 'updateMemberRole'])->name('groups.members.role.update');
    Route::delete('/groups/{group}/members/leave', [GroupController::class, 'leave'])->name('groups.members.leave');
    Route::delete('/groups/{group}/members/{user}/remove', [GroupController::class, 'removeMember'])->name('groups.members.remove');

    // イベント
    Route::get('/groups/{group}/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/groups/{group}/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/groups/{group}/events', [EventController::class, 'store'])->name('events.store');

    Route::get('/events/{event}/duplicate', [EventController::class, 'duplicateForm'])->name('events.duplicate.form');
    Route::post('/events/{event}/duplicate', [EventController::class, 'duplicate'])->name('events.duplicate');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::patch('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('/events/{event}/advance', [EventController::class, 'advance'])->name('events.advance');
    Route::post('/events/{event}/revert', [EventController::class, 'revert'])->name('events.revert');

    // イベント参加者
    Route::get('/events/{event}/members', [EventMemberController::class, 'index'])->name('events.members.index');
    Route::post('/events/{event}/join', [EventMemberController::class, 'join'])->name('events.join');
    Route::delete('/events/{event}/leave', [EventMemberController::class, 'leave'])->name('events.leave');
    Route::post('/events/{event}/members/{user}', [EventMemberController::class, 'add'])->name('events.members.add');
    Route::delete('/events/{event}/members/{user}', [EventMemberController::class, 'remove'])->name('events.members.remove');

    // 会場マップ
    Route::get('/events/{event}/map', [VenueMapController::class, 'show'])->name('events.map');
    Route::post('/events/{event}/map/image', [VenueMapController::class, 'updateImage'])->name('events.map.image');
    Route::patch('/events/{event}/map/circles/{circle}', [VenueMapController::class, 'placeCircle'])->name('events.map.place');

    // サークル・商品カタログ
    Route::get('/events/{event}/circles', [CircleController::class, 'index'])->name('circles.index');
    Route::get('/events/{event}/circles/create', [CircleController::class, 'create'])->name('circles.create');
    Route::get('/events/{event}/circles/bulk', [CircleController::class, 'bulkForm'])->name('circles.bulk.form');
    Route::post('/events/{event}/circles/bulk', [CircleController::class, 'bulkStore'])->name('circles.bulk.store');
    Route::post('/events/{event}/circles', [CircleController::class, 'store'])->name('circles.store');
    Route::get('/circles/{circle}', [CircleController::class, 'show'])->name('circles.show');
    Route::get('/circles/{circle}/edit', [CircleController::class, 'edit'])->name('circles.edit');
    Route::patch('/circles/{circle}', [CircleController::class, 'update'])->name('circles.update');
    Route::delete('/circles/{circle}', [CircleController::class, 'destroy'])->name('circles.destroy');

    Route::get('/circles/{circle}/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/circles/{circle}/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    // 購入リスト
    Route::get('/events/{event}/my-purchases', [PersonalPurchaseController::class, 'index'])->name('purchases.personal.index');
    Route::patch('/events/{event}/my-purchases', [PersonalPurchaseController::class, 'update'])->name('purchases.personal.update');
    Route::post('/events/{event}/my-purchases/copy', [PersonalPurchaseController::class, 'copy'])->name('purchases.personal.copy');
    Route::get('/events/{event}/purchase-summary', [PersonalPurchaseController::class, 'summary'])->name('purchases.summary');

    Route::get('/events/{event}/shared-purchases', [SharedPurchaseController::class, 'index'])->name('purchases.shared.index');
    Route::post('/events/{event}/shared-purchases/sync', [SharedPurchaseController::class, 'sync'])->name('purchases.shared.sync');
    Route::post('/events/{event}/shared-purchases/volunteer-all', [SharedPurchaseController::class, 'volunteerForUnassigned'])->name('purchases.shared.volunteer-all');
    Route::get('/shared-purchases/{sharedPurchase}', [SharedPurchaseController::class, 'show'])->name('purchases.shared.show');
    Route::patch('/shared-purchase-items/{item}', [SharedPurchaseController::class, 'updateItem'])->name('purchases.shared.items.update');
    Route::patch('/shared-purchase-items/{item}/assignees', [SharedPurchaseController::class, 'updateProductAssignees'])->name('purchases.shared.items.assignees');

    Route::post('/shared-purchases/{sharedPurchase}/volunteer', [SharedPurchaseController::class, 'volunteer'])->name('purchases.assignees.volunteer');
    Route::delete('/shared-purchases/{sharedPurchase}/volunteer', [SharedPurchaseController::class, 'withdraw'])->name('purchases.assignees.withdraw');
    Route::post('/shared-purchases/{sharedPurchase}/assignees/{user}', [SharedPurchaseController::class, 'assign'])->name('purchases.assignees.assign');
    Route::delete('/shared-purchases/{sharedPurchase}/assignees/{user}', [SharedPurchaseController::class, 'unassign'])->name('purchases.assignees.unassign');

    Route::patch('/events/{event}/budget', [BudgetController::class, 'update'])->name('events.budget.update');

    // 当日の買い物リスト
    Route::get('/events/{event}/shopping-list', [ShoppingListController::class, 'index'])->name('shopping.index');
    Route::patch('/events/{event}/shopping-list/route', [ShoppingListController::class, 'saveRoute'])->name('shopping.route.save');
    Route::delete('/events/{event}/shopping-list/route', [ShoppingListController::class, 'resetRoute'])->name('shopping.route.reset');
    Route::post('/shopping/items/{item}/planned', [ShoppingListController::class, 'markAsPlanned'])->name('shopping.items.planned');
    Route::post('/shopping/items/{item}/sold-out', [ShoppingListController::class, 'markAsSoldOut'])->name('shopping.items.sold-out');
    Route::post('/shopping/circles/{sharedPurchase}/planned', [ShoppingListController::class, 'markCircleAsPlanned'])->name('shopping.circles.planned');
    Route::post('/shopping/personal/{purchase}/{outcome}', [ShoppingListController::class, 'markPersonal'])->name('shopping.personal')
        ->whereIn('outcome', ['bought', 'missed']);

    // 購入結果
    Route::get('/events/{event}/results', [PurchaseResultController::class, 'index'])->name('results.index');
    Route::patch('/events/{event}/results/personal', [PurchaseResultController::class, 'storePersonal'])->name('results.personal.store');
    Route::get('/shared-purchase-items/{item}/result', [PurchaseResultController::class, 'edit'])->name('results.edit');
    Route::post('/shared-purchase-items/{item}/result', [PurchaseResultController::class, 'store'])->name('results.store');

    // 精算・支払い
    Route::get('/my/settlements', [SettlementController::class, 'mine'])->name('settlements.mine');
    Route::get('/events/{event}/settlements', [SettlementController::class, 'index'])->name('settlements.index');
    Route::get('/events/{event}/settlements/breakdown/{user}', [SettlementController::class, 'breakdown'])->name('settlements.breakdown');
    Route::post('/events/{event}/settlements/regenerate', [SettlementController::class, 'regenerate'])->name('settlements.regenerate');
    Route::get('/settlements/{settlement}', [SettlementController::class, 'show'])->name('settlements.show');
    Route::post('/settlements/{settlement}/report', [SettlementController::class, 'report'])->name('settlements.report');
    Route::post('/payments/{payment}/confirm', [SettlementController::class, 'confirm'])->name('payments.confirm');
    Route::post('/payments/{payment}/reject', [SettlementController::class, 'reject'])->name('payments.reject');

    // 承認フロー
    Route::get('/events/{event}/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::delete('/approvals/{approval}', [ApprovalController::class, 'withdraw'])->name('approvals.withdraw');
    Route::post('/approvals/{approval}/{decision}', [ApprovalController::class, 'vote'])->name('approvals.vote')
        ->whereIn('decision', ['approve', 'reject']);
    Route::post('/events/{event}/approvals/unlock', [ApprovalController::class, 'requestUnlock'])->name('approvals.unlock');
    Route::post('/events/{event}/approvals/relock', [ApprovalController::class, 'relock'])->name('approvals.relock');

    // CSVエクスポート
    Route::get('/events/{event}/export/results', [ExportController::class, 'results'])->name('events.export.results');
    Route::get('/events/{event}/export/settlements', [ExportController::class, 'settlements'])->name('events.export.settlements');

    // 変更履歴
    Route::get('/events/{event}/history', [ChangeHistoryController::class, 'index'])->name('histories.index');

    // 通知
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // プロフィール・アカウント
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::put('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 受信した招待
    Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
    Route::post('/invitations/{invitation}/decline', [InvitationController::class, 'decline'])->name('invitations.decline');
});
