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
class AiServer
{
    /**
     * 房间最大人数限制
     * @var int
     */
    private const MAX_ROOM_COUNT = 50;

    /**
     * Workerman工作进程实例
     * @var Worker
     */
    private Worker $worker;

    /**
     * 所有客户端连接集合
     * key: connection_id, value: ['connection' => TcpConnection, 'userID' => string]
     * @var array
     */
    private array $clients = [];

    /**
     * 用户连接映射表，用于快速查找用户对应的连接
     * key: user_token, value: TcpConnection
     * @var array
     */
    private array $userConnections = [];

    /**
     * 用户房间映射表
     * key: user_token, value: room_id
     * @var array
     */
    private array $userToRoom = [];

    /**
     * 房间用户映射表
     * key: room_id, value: array of user_tokens
     * @var array
     */
    private array $roomUsers = [];

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
     * 消息长度最大值
     * @var int
     */
    private const MAX_MESSAGE_LENGTH = 500;

    /**
     * 消息发送冷却时间（秒），防止刷屏
     * @var int
     */
    private const MESSAGE_COOLDOWN = 1;

    /**
     * 用户最后发消息时间记录
     * key: user_token, value: timestamp
     * @var array
     */
    private array $lastMessageTime = [];

    /**
     * 阿里云百炼AI实例
     * @var Aliyun|null
     */
    private ?Aliyun $aliyun = null;

    /**
     * 构造函数，初始化WebSocket服务器
     * 设置监听地址、进程数和服务器名称
     */
    public function __construct()
    {
        // 创建WebSocket工作进程，监听2346端口
        $this->worker = new Worker("websocket://0.0.0.0:2346");
        // 设置工作进程数为4，提高并发处理能力
        $this->worker->count = 4;
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
            $currentTime = time();
            foreach ($worker->connections as $connection) {
                // 检查连接是否超时
                if (isset($connection->lastMessageTime)) {
                    $timeSinceLastMessage = $currentTime - $connection->lastMessageTime;
                    // 如果超过心跳时间，关闭连接
                    if ($timeSinceLastMessage >= self::HEARTBEAT_TIME) {
                        echo "连接 {$connection->id} 超时，关闭连接\n";
                        $connection->close();
                        continue;
                    }

                    // 如果超过ping间隔，发送ping消息
                    if ($timeSinceLastMessage >= self::PING_INTERVAL) {
                        $this->sendToClient($connection, [
                            'type' => 'ping',
                            'timestamp' => $currentTime
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
        $this->clients[$connection->id] = [
            "connection" => $connection,
            "userID" => null
        ];
        echo "新客户端连接: {$connection->id}, 当前连接数: " . count($this->clients) . "\n";
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
        } catch (\Exception $e) {
            // 其他异常处理
            $this->sendErrorMessage($connection, 'Server error: ' . $e->getMessage());
            echo "处理消息异常: " . $e->getMessage() . "\n";
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
                // 处理心跳响应，无需处理
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

        // 验证参数有效性
        if (empty($token) || empty($userId)) {
            $this->sendErrorMessage($connection, 'Authentication failed: missing parameters');
            $connection->close();
            return;
        }

        // 验证token有效性
        $authResult = $this->validateToken($token, (string)$userId);
        
        if (!$authResult['success']) {
            $this->sendErrorMessage($connection, 'Authentication failed: ' . $authResult['message']);
            $connection->close();
            return;
        }

        // 建立用户连接映射
        $connection->userId = $token;
        $this->userConnections[$token] = $connection;
        $this->clients[$connection->id]["userID"] = $token;
        
        // 记录用户房间信息
        $roomId = $authResult['room_id'];
        $this->userToRoom[$token] = $roomId;
        
        // 将用户添加到房间
        if (!isset($this->roomUsers[$roomId])) {
            $this->roomUsers[$roomId] = [];
        }
        $this->roomUsers[$roomId][] = $token;

        // 发送认证成功消息
        $this->sendToClient($connection, [
            'type' => 'system',
            'data' => [
                'event' => 'authenticated',
                'room_id' => $roomId,
                'message' => 'Authentication successful'
            ]
        ]);

        echo "用户 {$userId} (token: {$token}) 认证成功，加入房间 {$roomId}\n";
    }

    /**
     * 验证用户token的有效性
     * @param string $token 用户token
     * @param string $userId 用户ID
     * @return array ['success' => bool, 'message' => string, 'room_id' => int|null]
     */
    private function validateToken(string $token, string $userId): array
    {
        try {
            // 查询用户信息
            $user = User::where('user_token', $token)->find();
            
            if (empty($user) || empty($user->id)) {
                return [
                    'success' => false,
                    'message' => 'Invalid token',
                    'room_id' => null
                ];
            }

            // 查询用户所在房间
            $roomUser = RoomUser::where('user_id', $user->id)->find();
            
            if (!$roomUser) {
                // 创建新房间
                $room = Room::create(['create_user' => $user->id]);
                $roomId = $room->id;
                
                // 关联用户到房间
                RoomUser::create([
                    'user_id' => $user->id,
                    'room_id' => $roomId
                ]);
                
                echo "为用户 {$userId} 创建新房间 {$roomId}\n";
            } else {
                $roomId = $roomUser->room_id;
            }

            return [
                'success' => true,
                'message' => 'Success',
                'room_id' => $roomId
            ];
            
        } catch (\Exception $e) {
            echo "Token验证异常: " . $e->getMessage() . "\n";
            return [
                'success' => false,
                'message' => 'Server error during authentication',
                'room_id' => null
            ];
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

        $userToken = $connection->userId;
        
        // 检查消息频率限制
        if (!$this->checkMessageRateLimit($userToken)) {
            $this->sendErrorMessage($connection, 'Message rate limit exceeded');
            return;
        }

        $content = trim($data['content'] ?? '');
        
        // 检查消息内容是否为空
        if (empty($content)) {
            $this->sendErrorMessage($connection, 'Empty message content');
            return;
        }

        // 检查消息长度是否超过限制
        if (mb_strlen($content) > self::MAX_MESSAGE_LENGTH) {
            $this->sendErrorMessage($connection, 'Message too long (max ' . self::MAX_MESSAGE_LENGTH . ' characters)');
            return;
        }

        $targetUserId = $data['target_user_id'] ?? 0;
        $roomId = $data['room_id'] ?? ($this->userToRoom[$userToken] ?? null);

        // 检查是否是发送给AI的消息（target_user_id 为特殊值或使用 is_ai 标志）
        $isAiMessage = ($data['is_ai'] ?? false) || $targetUserId === 'ai';

        if ($isAiMessage) {
            // 处理AI对话
            $this->handleAiChat($connection, $content, $roomId, $userToken);
            return;
        }

        // 构造消息数据
        $messageData = [
            'type' => 'chat',
            'data' => [
                'isStreaming' => false,
                'content' => $content,
                'timestamp' => time(),
                'room_id' => $roomId,
                'sender_id' => $userToken
            ]
        ];

        // 如果指定目标用户，发送私聊消息
        if ($targetUserId > 0 && isset($this->userConnections[$targetUserId])) {
            $this->sendToClient($this->userConnections[$targetUserId], $messageData);
            echo "私聊消息: {$userToken} -> {$targetUserId}\n";
        } elseif ($roomId) {
            // 否则广播到房间
            $this->broadcastToRoom($messageData, $roomId, $connection);
            echo "房间消息: 房间 {$roomId}, 发送者 {$userToken}\n";
        } else {
            $this->sendErrorMessage($connection, 'No valid room or target user');
            return;
        }

        // 发送发送成功确认
        $this->sendToClient($connection, [
            'type' => 'system',
            'data' => ['event' => 'message_sent']
        ]);
    }

    /**
     * 处理AI对话
     * @param TcpConnection $connection 连接对象
     * @param string $message 用户消息
     * @param int|null $roomId 房间ID
     * @param string $userToken 用户token
     */
    private function handleAiChat(TcpConnection $connection, string $message, ?int $roomId, string $userToken): void
    {
        echo "开始处理AI对话，用户: {$userToken}, 消息: {$message}\n";
        
        // 初始化阿里云百炼实例
        if ($this->aliyun === null) {
            try {
                echo "初始化Aliyun客户端...\n";
                $this->aliyun = new Aliyun();
                echo "Aliyun客户端初始化成功\n";
            } catch (\Exception $e) {
                echo "Aliyun客户端初始化失败: " . $e->getMessage() . "\n";
                $this->sendErrorMessage($connection, 'AI服务初始化失败: ' . $e->getMessage());
                return;
            }
        }

        // 先发送用户消息到房间（如果需要）
        if ($roomId) {
            $userMessageData = [
                'type' => 'chat',
                'data' => [
                    'isStreaming' => false,
                    'content' => $message,
                    'timestamp' => time(),
                    'room_id' => $roomId,
                    'sender_id' => $userToken,
                    'is_ai' => false
                ]
            ];
            $this->broadcastToRoom($userMessageData, $roomId, $connection);
            echo "已广播用户消息到房间 {$roomId}\n";
        }

        // 发送AI开始响应标记
        echo "发送AI开始响应标记...\n";
        $this->sendToClient($connection, [
            'type' => 'chat',
            'data' => [
                'isStreaming' => true,
                'content' => '',
                'timestamp' => time(),
                'room_id' => $roomId,
                'sender_id' => 'ai',
                'is_ai' => true,
                'status' => 'start'
            ]
        ]);

        // 累积AI回复内容
        $fullResponse = '';
        $chunkCount = 0;

        echo "开始调用阿里云百炼流式接口...\n";
        
        // 调用阿里云百炼流式接口
        $success = $this->aliyun->streamChat($message, function($chunk) use ($connection, $roomId, $userToken, &$fullResponse, &$chunkCount) {
            $fullResponse .= $chunk;
            $chunkCount++;
            
            // 实时发送每个文本块给客户端（只发送给当前用户）
            $this->sendToClient($connection, [
                'type' => 'chat',
                'data' => [
                    'isStreaming' => true,
                    'content' => $chunk,
                    'timestamp' => time(),
                    'room_id' => $roomId,
                    'sender_id' => 'ai',
                    'is_ai' => true,
                    'status' => 'streaming'
                ]
            ]);
        });

        echo "阿里云百炼调用完成，成功: " . ($success ? '是' : '否') . ", 数据块数: {$chunkCount}, 总长度: " . mb_strlen($fullResponse) . "\n";

        // 发送AI响应结束标记
        echo "发送AI响应结束标记...\n";
        $this->sendToClient($connection, [
            'type' => 'chat',
            'data' => [
                'isStreaming' => false,
                'content' => '',
                'timestamp' => time(),
                'room_id' => $roomId,
                'sender_id' => 'ai',
                'is_ai' => true,
                'status' => 'end',
                'full_content' => $fullResponse
            ]
        ]);

        if ($success) {
            echo "✅ AI回复完成，总长度: " . mb_strlen($fullResponse) . "\n";
        } else {
            echo "❌ AI服务响应失败\n";
            $this->sendErrorMessage($connection, 'AI服务响应失败');
        }
    }

    /**
     * 检查消息发送频率限制
     * @param string $userToken 用户token
     * @return bool 是否允许发送
     */
    private function checkMessageRateLimit(string $userToken): bool
    {
        $currentTime = time();
        
        if (isset($this->lastMessageTime[$userToken])) {
            $timeSinceLastMessage = $currentTime - $this->lastMessageTime[$userToken];
            if ($timeSinceLastMessage < self::MESSAGE_COOLDOWN) {
                return false;
            }
        }
        
        $this->lastMessageTime[$userToken] = $currentTime;
        return true;
    }

    /**
     * 广播消息给房间内所有用户（除了发送者）
     * @param array $message 消息数据
     * @param int $roomId 房间ID
     * @param TcpConnection $excludeConnection 排除的连接（发送者）
     */
    private function broadcastToRoom(array $message, int $roomId, TcpConnection $excludeConnection = null): void
    {
        if (!isset($this->roomUsers[$roomId])) {
            return;
        }

        $sentCount = 0;
        foreach ($this->roomUsers[$roomId] as $userToken) {
            // 跳过发送者
            if ($excludeConnection && isset($excludeConnection->userId) && $excludeConnection->userId === $userToken) {
                continue;
            }

            // 检查用户是否在线
            if (isset($this->userConnections[$userToken])) {
                $this->sendToClient($this->userConnections[$userToken], $message);
                $sentCount++;
            }
        }

        echo "消息广播到房间 {$roomId}, 发送给 {$sentCount} 个用户\n";
    }

    /**
     * 向指定客户端发送消息
     * @param TcpConnection $connection 目标连接
     * @param array $data 消息数据
     */
    private function sendToClient(TcpConnection $connection, array $data): void
    {
        try {
            // 确保所有字符串字段都是有效的 UTF-8 编码
            $data = $this->ensureUtf8Encoding($data);
            
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            $connection->send($json);
        } catch (\JsonException $e) {
            echo "JSON编码错误: " . $e->getMessage() . "\n";
            echo "原始数据: " . print_r($data, true) . "\n";
        }
    }

    /**
     * 确保数据中的所有字符串都是有效的 UTF-8 编码
     * @param mixed $data 需要处理的数据
     * @return mixed 处理后的数据
     */
    private function ensureUtf8Encoding($data)
    {
        if (is_string($data)) {
            // 如果字符串不是有效的 UTF-8，尝试转换
            if (!mb_check_encoding($data, 'UTF-8')) {
                // 尝试从 GBK 转换为 UTF-8
                $converted = mb_convert_encoding($data, 'UTF-8', 'GBK');
                if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                    return $converted;
                }
                // 如果转换失败，使用替代字符
                return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            }
            return $data;
        } elseif (is_array($data)) {
            // 递归处理数组
            foreach ($data as $key => $value) {
                $data[$key] = $this->ensureUtf8Encoding($value);
            }
            return $data;
        }
        
        return $data;
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
            $userToken = $connection->userId;
            unset($this->userConnections[$userToken]);
            
            // 从房间中移除用户
            if (isset($this->userToRoom[$userToken])) {
                $roomId = $this->userToRoom[$userToken];
                if (isset($this->roomUsers[$roomId])) {
                    $key = array_search($userToken, $this->roomUsers[$roomId]);
                    if ($key !== false) {
                        unset($this->roomUsers[$roomId][$key]);
                        // 重新索引数组
                        $this->roomUsers[$roomId] = array_values($this->roomUsers[$roomId]);
                    }
                    
                    // 如果房间为空，删除房间
                    if (empty($this->roomUsers[$roomId])) {
                        unset($this->roomUsers[$roomId]);
                        echo "房间 {$roomId} 已清空并删除\n";
                    }
                }
                
                unset($this->userToRoom[$userToken]);
            }
            
            // 清理消息时间记录
            unset($this->lastMessageTime[$userToken]);
            
            echo "用户 {$userToken} 断开连接\n";
        }

        echo "客户端 {$connection->id} 断开连接, 剩余连接数: " . count($this->clients) . "\n";
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
            $userToken = $connection->userId;
            unset($this->userConnections[$userToken]);
            unset($this->userToRoom[$userToken]);
            unset($this->lastMessageTime[$userToken]);
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
