<?php
declare(strict_types=1);

namespace app\webSocket;

use app\home\model\Room;
use app\home\model\RoomCommons;
use app\home\model\RoomUser;
use app\home\model\User;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;

/**
 * WebSocket服务器类
 * 提供WebSocket连接管理、消息处理、用户认证和心跳检测功能
 */
class Server
{
    private $maxCount=50;
    private $commonsCount=0;
    /**
     * Workerman工作进程实例
     * @var Worker
     */
    private Worker $worker;

    /**
     * 所有客户端连接集合
     * @var array
     */
    private array $clients = [];

    /**
     * 用户连接映射表，用于快速查找用户对应的连接
     * @var array
     */
    private array $userConnections = [];

    private array $userToRoom = [];

    /**
     * 心跳检测时间间隔（秒）
     * @var int
     */
    private const HEARTBEAT_TIME = 55;

    /**
     * 心跳ping发送间隔（秒）
     * @var int
     */
    private const PING_INTERVAL = 25;

    /**
     * 构造函数，初始化WebSocket服务器
     * 设置监听地址、进程数和服务器名称
     */
    public function __construct()
    {
        // 创建WebSocket工作进程，监听2346端口
        $this->worker = new Worker("websocket://localhost:2346");
        // 设置工作进程数为4，提高并发处理能力
        $this->worker->count = 1;
        // 设置进程名称，便于进程管理
        $this->worker->name = 'ChatWebSocket';

        // 设置工作进程启动回调
        $this->worker->onWorkerStart = [$this, 'onWorkerStart'];
        // 设置新连接建立回调
        $this->worker->onConnect = [$this, 'onConnect'];
        // 设置接收到消息回调
        $this->worker->onMessage = [$this, 'onMessage'];
        // 设置连接关闭回调
        $this->worker->onClose = [$this, 'onClose'];
        // 设置错误回调
        $this->worker->onError = [$this, 'onError'];
    }

    /**
     * 工作进程启动回调函数
     * 启动定时器，定期检测连接状态和发送心跳
     * @param Worker $worker 工作进程实例
     */
    public function onWorkerStart(Worker $worker): void
    {
        echo "WebSocket服务器已在端口2346启动\n";

        // 每隔PING_INTERVAL秒执行一次心跳检测
        Timer::add(self::PING_INTERVAL, function() use ($worker) {
            foreach ($worker->connections as $connection) {
                // 检查连接是否超时
                if (isset($connection->lastMessageTime)) {
                    $timeSinceLastMessage = time() - $connection->lastMessageTime;
                    // 如果超过心跳时间，关闭连接
                    if ($timeSinceLastMessage >= self::HEARTBEAT_TIME) {
                        $connection->close();
                        continue;
                    }

                    // 如果超过ping间隔，发送ping消息
                    if ($timeSinceLastMessage >= self::PING_INTERVAL) {
                        $this->sendToClient($connection, [
                            'type' => 'ping',
                            'timestamp' => time()
                        ]);
                    }
                }
            }
        });
    }

    /**
     * 新连接建立回调函数
     * 记录新连接并更新连接列表
     * @param TcpConnection $connection 新建立的连接
     */
    public function onConnect(TcpConnection $connection): void
    {
        // 记录连接最后消息时间
        $connection->lastMessageTime = time();
        // 将连接添加到客户端列表
        $this->clients[$connection->id] = array("connection" => $connection,"userID"=>-1);
        echo "新客户端连接: {$connection->id}\n";
    }

    /**
     * 接收消息回调函数
     * 处理客户端发送的消息，进行解析和分发
     * @param TcpConnection $connection 发送消息的连接
     * @param string $data 接收到的消息数据
     */
    public function onMessage(TcpConnection $connection, string $data): void
    {
        // 更新连接最后消息时间
        $connection->lastMessageTime = time();

        try {
            // 解析JSON消息
            $message = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            // 如果解析失败，返回错误信息
            if (!is_array($message)) {
                $this->sendErrorMessage($connection, 'Invalid message format');
                return;
            }

            // 根据消息类型处理不同逻辑
            $this->handleMessageType($connection, $message);

        } catch (\JsonException $e) {
            // JSON解析错误处理
            $this->sendErrorMessage($connection, 'JSON decode error: ' . $e->getMessage());
        }
    }

    /**
     * 根据消息类型处理不同业务逻辑
     * @param TcpConnection $connection 发送消息的连接
     * @param array $message 解析后的消息数组
     */
    private function handleMessageType(TcpConnection $connection, array $message): void
    {
        $type = $message['type'] ?? '';

        switch ($type) {
            case 'auth':
                // 处理认证消息
                $this->handleAuth($connection, $message['data'] ?? []);
                break;

            case 'chat':
                // 处理聊天消息
                $this->handleChat($connection, $message['data'] ?? []);
                break;

            case 'pong':
                // 处理心跳响应
                break;

            default:
                // 未知消息类型错误
                $this->sendErrorMessage($connection, 'Unknown message type: ' . $type);
        }
    }

    /**
     * 处理用户认证消息
     * 验证用户token并建立用户连接映射
     * @param TcpConnection $connection 认证连接
     * @param array $data 认证数据
     */
    private function handleAuth(TcpConnection $connection, array $data): void
    {
        $token = $data['token'] ?? '';
        $userId = $data['user_id'] ?? 0;
        $userId = "".$userId;

        // 验证token有效性
        if (!$this->validateToken($token, $userId)) {
            $this->sendErrorMessage($connection, 'Authentication failed');
            $connection->close();
            return;
        }

        // 建立用户连接映射
        $connection->userId = $userId;
        $this->userConnections[$userId] = $connection;
        $this->clients[$connection->id]["userID"] = $userId;

        // 发送认证成功消息
        $this->sendToClient($connection, [
            'type' => 'system',
            'data' => [
                'roomID' => $this->userToRoom[$userId],
                'event' => 'authenticated',
                'message' => 'Authentication successful'
            ]
        ]);

        echo "用户{$userId}认证成功\n";
    }

    /**
     * 验证用户token的有效性
     * 简单的token验证逻辑（实际项目中应使用更安全的验证方式）
     * @param string $token 用户token
     * @param string $userId 用户ID
     * @return bool token是否有效
     */
    private function validateToken(string $token, string $userId): bool
    {
        echo "验证: token：{$userId}\n";
        if (empty($token) || empty($userId)) {
            return false;
        }
        $user = User::where(array("user_token"=>$userId))->find();
        if(!empty($user->id)){
            $roomRow = RoomUser::where(array("user_id"=>$user->id))->find();
            if(!$roomRow){
                $roomRow = Room::create(array("create_user"=>$user->id));
                $room_id = $roomRow->id;
                RoomUser::create(array("user_id"=>$user->id,"room_id"=>$roomRow->id));
            }else{
                $room_id = $roomRow->room_id;
                $this->commonsCount = RoomCommons::where(array("user_id"=>$user->id))->count();
            }
            $this->userToRoom[$userId] = $room_id;
            return true;
        }else{
            return false;
        }
    }

    /**
     * 处理聊天消息
     * 验证用户认证状态并发送消息
     * @param TcpConnection $connection 发送消息的连接
     * @param array $data 消息数据
     */
    private function handleChat(TcpConnection $connection, array $data): void
    {
        // 检查用户是否已认证
        if (!isset($connection->userId)) {
            $this->sendErrorMessage($connection, 'Not authenticated');
            return;
        }

        $content = trim($data['content'] ?? '测试');
        // 检查消息内容是否为空
        if (empty($content)) {
            $this->sendErrorMessage($connection, 'Empty message content');
            return;
        }

        // 检查消息长度是否超过限制
        if (strlen($content) > 500) {
            $this->sendErrorMessage($connection, 'Message too long');
            return;
        }

        $targetUserId = $data['target_user_id'] ?? 0;
        $roomId = $data['room_id'] ?? 'default';
        //$toUserId = $data['to_user_id'] ?? '';

        // 构造消息数据
        $messageData = [
            'type' => 'chat',
            'data' => [
                'isStreaming' => false,
                'content' => $content,
                'timestamp' => time(),
                'room_id' => $roomId
            ]
        ];

        // 如果指定目标用户，发送私聊消息
        if ($targetUserId > 0 && isset($this->userConnections[$targetUserId])) {
            $this->sendToClient($this->userConnections[$targetUserId], $messageData);
        } else {
            // 否则广播消息
            $this->broadcastMessage($messageData, $connection);
        }

        // 发送发送成功确认
        $this->sendToClient($connection, [
            'type' => 'system',
            'data' => ['event' => 'message_sent']
        ]);
    }

    /**
     * 广播消息给所有在线用户（除了排除的连接）
     * @param array $message 消息数据
     * @param TcpConnection $excludeConnection 排除的连接
     */
    private function broadcastMessage(array $message, TcpConnection $excludeConnection = null): void
    {
        $userID = isset($this->clients[$excludeConnection->id])?$this->clients[$excludeConnection->id]['userID']:-1;
        $userRoom = isset($this->userToRoom[$userID])?$this->userToRoom[$userID]:-1;
        foreach ($this->userConnections as $user_token=>$conn) {
            if($userRoom>0){
                $conRoom = isset($this->userToRoom[$user_token])?$this->userToRoom[$user_token]:-1;
                // 发给房间所有人
                if ($excludeConnection && $conRoom == $userRoom) {
                    $this->sendToClient($conn, $message);
                }
            }else{
                // 广播消息只能某个用户发送
                if ($excludeConnection && $conn->id==2 && $conn->id != $excludeConnection->id) {
                    $this->sendToClient($conn, $message);
                }
            }
        }
    }

    /**
     * 向指定客户端发送消息
     * @param TcpConnection $connection 目标连接
     * @param array $data 消息数据
     */
    private function sendToClient(TcpConnection $connection, array $data): void
    {
        $connection->send(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 向客户端发送错误消息
     * @param TcpConnection $connection 目标连接
     * @param string $message 错误信息
     */
    private function sendErrorMessage(TcpConnection $connection, string $message): void
    {
        $this->sendToClient($connection, [
            'type' => 'system',
            'data' => [
                'event' => 'error',
                'message' => $message
            ]
        ]);
    }

    /**
     * 连接关闭回调函数
     * 清理连接和用户映射信息
     * @param TcpConnection $connection 关闭的连接
     */
    public function onClose(TcpConnection $connection): void
    {
        // 从客户端列表中移除
        unset($this->clients[$connection->id]);

        // 如果是已认证用户，从用户连接映射中移除
        if (isset($connection->userId)) {
            unset($this->userConnections[$connection->userId]);
            echo "用户{$connection->userId}断开连接\n";
        }

        echo "客户端{$connection->id}断开连接\n";
    }

    /**
     * 错误回调函数
     * 记录错误信息并清理相关资源
     * @param TcpConnection $connection 出错的连接
     * @param int $code 错误代码
     * @param string $msg 错误信息
     */
    public function onError(TcpConnection $connection, int $code, string $msg): void
    {
        echo "连接错误 [{$code}]: {$msg}\n";
        // 清理客户端列表
        unset($this->clients[$connection->id]);

        // 如果是已认证用户，清理用户连接映射
        if (isset($connection->userId)) {
            unset($this->userConnections[$connection->userId]);
        }
    }

    /**
     * 启动WebSocket服务器
     */
    public function run(): void
    {
        Worker::runAll();
    }
}

// 如果不是全局启动，则创建并运行服务器实例
/*if (!defined('GLOBAL_START')) {
    $server = new Server();
    $server->run();
}*/
