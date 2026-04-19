<?php
declare (strict_types = 1);

namespace app\home\controller;

use app\BaseController;
use app\home\model\Room;
use app\home\model\RoomCommons;
use app\home\model\RoomUser;
use app\home\model\User;

class Index extends BaseController
{
    public function index()
    {
        // 如果未登录，先执行登录逻辑
        if(!session('?userList')){
            $this->login();
        }
        
        return view("index",array(
            "hostUrl" => request()->getHostUrl(),
            "token" => session("userList.user_token"),
        ));
    }

    // 获取AI对话组件（用于嵌入到首页）
    public function aiComponent()
    {
        if(!session('?userList')){
            $this->login();
        }
        return view("ai_component",array(
            "hostUrl" => request()->getHostUrl(),
            "token" => session("userList.user_token"),
        ));
    }

    /**
     * 获取用户的对话历史
     * @return \think\response\Json
     */
    public function getChatHistory()
    {
        if(!session('?userList')){
            return json(['code' => 401, 'msg' => '未登录']);
        }
        
        $userId = session("userList.id");
        $page = input('get.page/d', 1);
        $pageSize = input('get.page_size/d', 50);
        
        // 查询用户的对话历史，按时间正序（旧消息在前）
        $history = RoomCommons::where('user_id', $userId)
            ->order('create_time', 'asc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();
        
        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => $history,
            'total' => RoomCommons::where('user_id', $userId)->count()
        ]);
    }

    protected function login()
    {
        $ip = request()->ip();
        $userModel = new User();
        $user = $userModel->where("user_ip",$ip)->find();
        if(!$user){
            $token = $this->request->buildToken('__token__', 'sha1');
            $userModel->insert(array(
                "user_ip" => $ip,
                "user_token" => $token,
            ));
            session("userList",$userModel->toArray());
        }else{
            session("userList",$user->toArray());
        }
    }
}