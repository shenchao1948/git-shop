<?php
declare(strict_types=1);

namespace app\command;

use app\webSocket\AiServer;
use app\webSocket\Server;
use think\console\Command;
use think\console\Input;
use think\console\Output;
/**
 * WebSocket服务器类
 * 提供WebSocket连接管理、消息处理、用户认证和心跳检测功能
 */
class Ai extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('hello')
            ->setDescription('the hello command');
    }

    protected function execute(Input $input, Output $output)
    {
        $client = new AiServer();
        $client->run();
        // 指令输出
        $output->writeln('hello');
    }
}
