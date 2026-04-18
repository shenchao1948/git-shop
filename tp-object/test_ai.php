<?php
// 快速测试脚本 - 需要在 ThinkPHP 环境中运行

// 定义应用目录
define('APP_PATH', __DIR__ . '/app/');

// 加载基础文件
require __DIR__ . '/vendor/autoload.php';

// 执行应用并响应
$app = new think\App();
$app->initialize();

use app\webSocket\Aliyun;

echo "=== 阿里云百炼AI快速测试 ===\n\n";

try {
    echo "1. 初始化客户端...\n";
    $aliyun = new Aliyun();
    echo "✅ 初始化成功\n\n";
    
    echo "2. 发送测试消息（同步调用）...\n";
    $message = "你好";
    echo "用户: {$message}\n";
    echo "正在请求...\n";
    
    // 先测试同步调用
    $response = $aliyun->chat($message);
    
    if ($response) {
        echo "AI: {$response}\n";
        echo "\n✅ 同步调用成功！\n";
        echo "回复长度: " . mb_strlen($response) . " 字符\n\n";
    } else {
        echo "\n❌ 同步调用失败，返回null\n\n";
    }
    
    echo "3. 测试流式调用...\n";
    $streamMessage = "请写一首短诗";
    echo "用户: {$streamMessage}\n";
    echo "AI: ";
    
    $fullResponse = '';
    $chunkCount = 0;
    $success = $aliyun->streamChat($streamMessage, function($chunk) use (&$fullResponse, &$chunkCount) {
        echo $chunk;
        $fullResponse .= $chunk;
        $chunkCount++;
        flush();
    });
    
    if ($success) {
        echo "\n\n✅ 流式调用成功！\n";
        echo "数据块数: {$chunkCount}\n";
        echo "总回复长度: " . mb_strlen($fullResponse) . " 字符\n";
        if (mb_strlen($fullResponse) > 0) {
            echo "完整回复: {$fullResponse}\n";
        } else {
            echo "\n⚠️  警告：回复长度为0，可能是响应格式不匹配\n";
        }
    } else {
        echo "\n\n❌ 流式调用失败\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ 测试失败: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . "\n";
    echo "行号: " . $e->getLine() . "\n";
    echo "\n堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
