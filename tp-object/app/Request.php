<?php
namespace app;

// 应用请求对象类
class Request extends \think\Request
{

    public function getHostUrl()
    {
        $host = request()->domain();
        $maxUrl = request()->url();
        $homeStr = request()->root();
        if(empty($homeStr)){
            return $host;
        }else{
            $list = explode($homeStr,$maxUrl);
            $list = current($list);
            return $host.$list;
        }
    }
}
