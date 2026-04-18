<?php
declare (strict_types = 1);

namespace app\home\model;

use think\model\Pivot;
use think\Model;

/**
 * @mixin \think\Model
 */
class RoomUser extends Pivot
{
    protected $table = 'room_user';

    public function __construct(array $data = [], ?Model $parent = null, string $table = '')
    {
        $this->table = config("database.connections.mysql.prefix").$this->table;
        parent::__construct($data, $parent, $table);
    }
    protected $autoWriteTimestamp = true;
}
