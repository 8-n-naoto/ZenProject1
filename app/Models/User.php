<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\Theme;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'user_id',
        'email',
        'password',
        'theme',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * 所属履歴を含む全てのグループ。認可には activeGroups() を使うこと。
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot(['role', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * 現在在籍しているグループのみ。
     */
    public function activeGroups(): BelongsToMany
    {
        return $this->groups()->wherePivotNull('left_at');
    }

    public function receivedInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_user_id');
    }

    public function pendingReceivedInvitations(): HasMany
    {
        return $this->receivedInvitations()->where('status', InvitationStatus::Pending->value);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    /**
     * 画面表示用の名前。name が未設定ならログインIDを使う。
     */
    public function displayName(): string
    {
        $name = $this->name !== null && $this->name !== '' ? $this->name : (string) $this->user_id;

        // 退会済みでも過去の記録に名前が残るため、そうと分かるようにする
        return $this->trashed() ? $name.'（退会済み）' : $name;
    }

    /**
     * 退会済みかどうか。
     */
    public function hasLeftService(): bool
    {
        return $this->trashed();
    }

    /**
     * アバター用のイニシャル。
     */
    public function initial(): string
    {
        return mb_substr($this->displayName(), 0, 1);
    }

    /**
     * 選んでいる画面の見た目。未設定・不正な値なら既定のテーマ。
     */
    public function preferredTheme(): Theme
    {
        return Theme::fromValue($this->theme);
    }
}
