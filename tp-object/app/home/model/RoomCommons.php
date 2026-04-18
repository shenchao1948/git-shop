<?php
declare (strict_types = 1);

namespace app\home\model;


use think\Model;

/**
 * @mixin \think\Model
 */
class RoomCommons extends Model
{
    protected $name = 'room_commons';
    
    protected $autoWriteTimestamp = true;
    
    // 定义字段类型
    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'room_id' => 'integer',
    ];
}
