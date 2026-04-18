<?php
declare (strict_types = 1);

namespace app\home\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Room extends Model
{
    protected $insert = ['room_code','room_name'];
    //
    public function setRoomNameAttr($value,$data)
    {
        return "房间".$data["create_user"];
    }
    public function setRoomCodeAttr($value,$data)
    {
        return "test_".$data["create_user"];
    }
}
