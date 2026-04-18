<?php
declare (strict_types = 1);

namespace app\home\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Room::class, RoomUser::class);
    }

    // 一对多关联
    public function userRooms()
    {
        return $this->hasMany(RoomUser::class, 'user_id', 'id');
    }
    // 一对多关联
    public function userCommons()
    {
        return $this->hasMany(RoomCommons::class, 'user_id', 'id');
    }
}
