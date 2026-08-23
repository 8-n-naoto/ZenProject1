<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    /** @deprecated App\Enums\GroupRole を使用する。既存コード互換のために残している。 */
    public const ROLE_HIGHEST_RESPONSIBLE = '最高責任者';

    /** @deprecated App\Enums\GroupRole を使用する。 */
    public const ROLE_RESPONSIBLE = '責任者';

    /** @deprecated App\Enums\GroupRole を使用する。 */
    public const ROLE_MEMBER = '一般メンバー';

    /** @deprecated App\Enums\GroupRole::values() を使用する。 */
    public const ROLES = [
        self::ROLE_HIGHEST_RESPONSIBLE,
        self::ROLE_RESPONSIBLE,
        self::ROLE_MEMBER,
    ];

    protected $fillable = ['name', 'description', 'image_path'];

    /**
     * 在籍中・脱退済みを問わない全ての所属履歴。
     * 認可・件数判定には activeMembers() を使うこと。
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot(['role', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * 在籍中のメンバーのみ（left_at が NULL）。
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivotNull('left_at');
    }

    /**
     * 脱退・除名済みのメンバー。
     */
    public function formerMembers(): BelongsToMany
    {
        return $this->members()->wherePivotNotNull('left_at');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function pendingInvitations(): HasMany
    {
        return $this->invitations()->where('status', InvitationStatus::Pending->value);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * 在籍中メンバーとしての所属レコードを取得する（pivot 付き）。
     */
    public function activeMembership(User|int|null $user): ?User
    {
        $userId = $user instanceof User ? $user->id : $user;

        if ($userId === null) {
            return null;
        }

        if ($this->relationLoaded('activeMembers')) {
            return $this->getRelation('activeMembers')->firstWhere('id', $userId);
        }

        return $this->activeMembers()->where('users.id', $userId)->first();
    }

    /**
     * 在籍中メンバーの役割を返す。非メンバー・脱退済みなら null。
     */
    public function roleOf(User|int|null $user): ?GroupRole
    {
        $membership = $this->activeMembership($user);

        if ($membership === null) {
            return null;
        }

        return GroupRole::tryFrom((string) $membership->pivot->role);
    }

    public function inviteLinks(): HasMany
    {
        return $this->hasMany(GroupInviteLink::class);
    }

    public function isActiveMember(User|int|null $user): bool
    {
        return $this->activeMembership($user) !== null;
    }

    /**
     * 過去に所属したことがあるが現在は脱退済みか。
     */
    public function isFormerMember(User|int|null $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        if ($userId === null) {
            return false;
        }

        return $this->formerMembers()->where('users.id', $userId)->exists();
    }

    public function countActiveWithRole(GroupRole $role): int
    {
        return $this->activeMembers()->wherePivot('role', $role->value)->count();
    }

    public function activeMemberCount(): int
    {
        return $this->activeMembers()->count();
    }

    /**
     * その役割の在籍者が自分ひとりだけか（最低1人ルールの判定）。
     */
    /**
     * グループ画像のURL。未設定なら null。
     */
    public function imageUrl(): ?string
    {
        if ($this->image_path === null || $this->image_path === '') {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path);
    }

    public function initial(): string
    {
        return mb_substr((string) $this->name, 0, 1);
    }

    public function isLastOfRole(GroupRole $role): bool
    {
        return $role->requiresAtLeastOne() && $this->countActiveWithRole($role) <= 1;
    }
}
