<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    public const ROLE_HIGHEST_RESPONSIBLE = '最高責任者';

    public const ROLE_RESPONSIBLE = '責任者';

    public const ROLE_MEMBER = '一般メンバー';

    public const ROLES = [
        self::ROLE_HIGHEST_RESPONSIBLE,
        self::ROLE_RESPONSIBLE,
        self::ROLE_MEMBER,
    ];

    protected $fillable = ['name', 'description', 'image_path'];

    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot(['role', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }
}
