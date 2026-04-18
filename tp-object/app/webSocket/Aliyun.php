<?php

namespace app\webSocket;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\StreamInterface;
use think\facade\Config;

class Aliyun
{
    /**
     * Guzzle HTTP客户端实例
     */
    private Client $client;

    /**
     * API Key
     */
    private string $apiKey;

    /**
     * API基础URL
     */
    private string $baseUrl;

    /**
     * 默认配置
     */
    private array $defaultConfig;

    /**
     * 构造函数，初始化Guzzle客户端并加载配置
     */
    public function __construct()
    {
        // 从ThinkPHP配置文件读取阿里云配置
        $config = Config::get('aliyun', []);
        
        // 验证必要配置
        if (empty($config['api_key'])) {
            throw new \Exception('阿里云API Key未配置，请在 config/aliyun.php 中配置 api_key');
        }

        // 设置API Key
        $this->apiKey = $config['api_key'];
        
        // 设置基础URL
        $this->baseUrl = $config['base_url'] ?? 'https://dashscope.aliyuncs.com/api/v1/services/aigc/text-generation/generation';
        
        // 保存默认配置
        $this->defaultConfig = [
            'model' => $config['default_model'] ?? 'qwen-turbo',
            'temperature' => $config['temperature'] ?? 0.7,
            'top_p' => $config['top_p'] ?? 0.8,
            'max_tokens' => $config['max_tokens'] ?? 1500,
        ];

        // 初始化Guzzle客户端
        $timeout = $config['timeout'] ?? 30;
        $connectTimeout = $config['connect_timeout'] ?? 10;
        $this->client = new Client([
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
        ]);
    }

    /**
     * 流式调用阿里云百炼AI模型
     *
     * @param string $message 用户消息
     * @param callable $onChunk 流式数据块回调函数 function(string $chunk): void
     * @param array $options 可选配置参数
     * @return bool 是否成功
     */
    public function streamChat(string $message, callable $onChunk, array $options = []): bool
    {
        return $this->streamChatWithContext($message, [], $onChunk, $options);
    }

    /**
     * 流式调用阿里云百炼AI模型（带上下文）
     *
     * @param string $message 用户当前消息
     * @param array $historyMessages 历史消息数组，格式: [['message_type' => 'user'|'ai', 'content' => '...'], ...]
     * @param callable $onChunk 流式数据块回调函数 function(string $chunk): void
     * @param array $options 可选配置参数
     * @return bool 是否成功
     */
    public function streamChatWithContext(string $message, array $historyMessages, callable $onChunk, array $options = []): bool
    {
        try {
            // 构建请求体（包含历史上下文）
            $body = $this->buildRequestBodyWithContext($message, $historyMessages, $options);
            
            echo "\n[DEBUG] 请求URL: {$this->baseUrl}\n";
            echo "[DEBUG] 历史消息数量: " . count($historyMessages) . "\n";
            echo "[DEBUG] 请求体: " . json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

            // 发送流式请求
            $response = $this->client->post($this->baseUrl, [
                'headers' => $this->buildHeaders(),
                'json' => $body,
                'stream' => true, // 启用流式响应
            ]);

            // 检查响应状态
            if ($response->getStatusCode() !== 200) {
                throw new \Exception('API请求失败，状态码: ' . $response->getStatusCode());
            }

            // 处理流式响应
            $this->processStreamResponse($response->getBody(), $onChunk);

            return true;

        } catch (RequestException $e) {
            echo "\n[ERROR] 阿里云百炼请求异常: " . $e->getMessage() . "\n";
            if ($e->hasResponse()) {
                echo "[ERROR] 响应内容: " . $e->getResponse()->getBody()->getContents() . "\n";
            }
            return false;
        } catch (\Exception $e) {
            echo "\n[ERROR] 流式聊天异常: " . $e->getMessage() . "\n";
            echo "[ERROR] 文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
            return false;
        }
    }

    /**
     * 构建请求体（带上下文）
     *
     * @param string $message 用户当前消息
     * @param array $historyMessages 历史消息数组
     * @param array $options 配置选项
     * @return array 请求体数组
     */
    private function buildRequestBodyWithContext(string $message, array $historyMessages, array $options): array
    {
        // 判断是否是应用API（URL中包含/apps/）
        $isAppApi = strpos($this->baseUrl, '/apps/') !== false;
        
        if ($isAppApi) {
            // 应用API的请求格式
            // 将历史对话拼接到 prompt 中实现上下文
            $fullPrompt = $this->buildContextualPrompt($message, $historyMessages);
            
            $requestBody = [
                'input' => [
                    'prompt' => $fullPrompt,
                ],
            ];
            
            // 只有当有额外参数时才添加 parameters
            if (!empty($options)) {
                // 移除 session_id，因为我们已经手动拼接了上下文
                unset($options['session_id']);
                if (!empty($options)) {
                    $requestBody['parameters'] = $options;
                }
            }
            
            echo "[DEBUG] 应用API - 历史消息数量: " . count($historyMessages) . "\n";
            echo "[DEBUG] 应用API - 拼接后的prompt长度: " . mb_strlen($fullPrompt) . "\n";
            
            return $requestBody;
        } else {
            // 标准模型API的请求格式（支持多轮对话）
            $config = array_merge($this->defaultConfig, $options);
            
            // 构建消息列表（包含历史上下文）
            $messages = [];
            
            // 添加历史消息
            foreach ($historyMessages as $historyMsg) {
                if (isset($historyMsg['message_type']) && isset($historyMsg['content'])) {
                    // 修正：user消息映射为'user'，ai消息映射为'assistant'
                    $role = $historyMsg['message_type'] === 'user' ? 'user' : 'assistant';
                    $messages[] = [
                        'role' => $role,
                        'content' => $historyMsg['content']
                    ];
                }
            }
            
            // 添加当前用户消息
            $messages[] = [
                'role' => 'user',
                'content' => $message
            ];
            
            echo "[DEBUG] 标准API - 构建的消息列表:\n";
            foreach ($messages as $idx => $msg) {
                echo "  [$idx] {$msg['role']}: " . mb_substr($msg['content'], 0, 50) . "...\n";
            }
            
            return [
                'model' => $config['model'],
                'input' => [
                    'messages' => $messages,
                ],
                'parameters' => [
                    'result_format' => 'message',
                    'incremental_output' => true, // 启用增量输出（流式）
                    'temperature' => $config['temperature'],
                    'top_p' => $config['top_p'],
                    'max_tokens' => $config['max_tokens'],
                ],
            ];
        }
    }

    /**
     * 构建包含上下文的prompt（用于应用API）
     *
     * @param string $currentMessage 当前用户消息
     * @param array $historyMessages 历史消息数组
     * @return string 拼接后的完整prompt
     */
    private function buildContextualPrompt(string $currentMessage, array $historyMessages): string
    {
        if (empty($historyMessages)) {
            return $currentMessage;
        }
        
        // 构建对话历史文本
        $contextParts = [];
        $contextParts[] = "以下是之前的对话历史：";
        $contextParts[] = "========================================";
        
        foreach ($historyMessages as $historyMsg) {
            if (isset($historyMsg['message_type']) && isset($historyMsg['content'])) {
                $speaker = $historyMsg['message_type'] === 'user' ? '用户' : '助手';
                $contextParts[] = "";
                $contextParts[] = "{$speaker}：";
                $contextParts[] = "{$historyMsg['content']}";
            }
        }
        
        // 添加分隔线和当前问题
        $contextParts[] = "";
        $contextParts[] = "========================================";
        $contextParts[] = "以上是历史对话，请根据以上内容回答以下问题：";
        $contextParts[] = "";
        $contextParts[] = "用户：{$currentMessage}";
        $contextParts[] = "";
        $contextParts[] = "助手：";
        
        return implode("\n", $contextParts);
    }

    /**
     * 构建请求头
     *
     * @return array 请求头数组
     */
    private function buildHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'X-DashScope-SSE' => 'enable', // 启用SSE流式输出
        ];
    }

    /**
     * 处理流式响应
     *
     * @param StreamInterface $stream 响应流
     * @param callable $onChunk 数据块回调函数
     */
    private function processStreamResponse(StreamInterface $stream, callable $onChunk): void
    {
        $buffer = '';
        $lastText = ''; // 记录上一次的完整文本，用于计算增量

        // 逐行读取流式数据
        while (!$stream->eof()) {
            $chunk = $stream->read(1024);
            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;

            // 按行分割处理
            $lines = explode("\n", $buffer);

            // 保留最后一行（可能不完整）
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);

                // 跳过空行
                if (empty($line)) {
                    continue;
                }

                // 处理SSE格式数据
                if (str_starts_with($line, 'data:')) {
                    $data = substr($line, 5);
                    $data = trim($data);

                    // 如果是结束标记
                    if ($data === '[DONE]') {
                        return;
                    }

                    // 解析JSON数据
                    try {
                        $jsonData = json_decode($data, true);
                        
                        // 检查JSON解析是否成功
                        if ($jsonData === null && json_last_error() !== JSON_ERROR_NONE) {
                            continue;
                        }

                        if ($jsonData && is_array($jsonData)) {
                            // 检查是否有错误
                            if (isset($jsonData['code']) && isset($jsonData['message'])) {
                                error_log("API返回错误: " . $jsonData['message'] . " (代码: " . $jsonData['code'] . ")");
                                continue;
                            }
                            
                            // 提取文本内容
                            $currentText = '';
                            
                            // 应用API格式 - output.text（累积文本）
                            if (isset($jsonData['output']['text'])) {
                                $currentText = $jsonData['output']['text'];
                            }
                            // 标准模型API流式格式 - choices[0].delta.content（增量文本）
                            elseif (isset($jsonData['output']['choices'][0]['delta']['content'])) {
                                $currentText = $jsonData['output']['choices'][0]['delta']['content'];
                            }
                            // 标准模型API格式 - choices[0].message.content
                            elseif (isset($jsonData['output']['choices'][0]['message']['content'])) {
                                $currentText = $jsonData['output']['choices'][0]['message']['content'];
                            }

                            if (!empty($currentText)) {
                                // 确保文本是有效的 UTF-8 编码
                                if (!mb_check_encoding($currentText, 'UTF-8')) {
                                    $currentText = mb_convert_encoding($currentText, 'UTF-8', 'UTF-8');
                                }
                                
                                // 判断是否是累积文本（应用API）还是增量文本（标准API）
                                $isAppApi = strpos($this->baseUrl, '/apps/') !== false;
                                
                                if ($isAppApi) {
                                    // 应用API：计算增量部分
                                    $lastTextLen = mb_strlen($lastText, 'UTF-8');
                                    $currentTextLen = mb_strlen($currentText, 'UTF-8');

                                    // 只有当当前文本比上次长时才计算增量
                                    if ($currentTextLen > $lastTextLen) {
                                        $incrementalText = mb_substr($currentText, $lastTextLen, null, 'UTF-8');
                                        $lastText = $currentText; // 更新上次文本

                                        // 只发送真正的增量部分
                                        if (!empty($incrementalText)) {
                                            $onChunk($incrementalText);
                                        }
                                    }
                                    // 如果长度相同或更短，说明没有新内容，跳过
                                } else {
                                    // 标准API：直接发送增量
                                    $onChunk($currentText);
                                }
                            }
                        }
                    } catch (\JsonException $e) {
                        error_log('JSON异常: ' . $e->getMessage());
                    }
                }
            }
        }

        // 处理缓冲区中剩余的数据
        if (!empty(trim($buffer))) {
            $line = trim($buffer);
            if (str_starts_with($line, 'data:')) {
                $data = substr($line, 5);
                $data = trim($data);

                if ($data !== '[DONE]') {
                    try {
                        $jsonData = json_decode($data, true);
                        
                        if ($jsonData !== null && is_array($jsonData)) {
                            $currentText = '';
                            
                            if (isset($jsonData['output']['text'])) {
                                $currentText = $jsonData['output']['text'];
                            } elseif (isset($jsonData['output']['choices'][0]['delta']['content'])) {
                                $currentText = $jsonData['output']['choices'][0]['delta']['content'];
                            } elseif (isset($jsonData['output']['choices'][0]['message']['content'])) {
                                $currentText = $jsonData['output']['choices'][0]['message']['content'];
                            }

                            if (!empty($currentText)) {
                                if (!mb_check_encoding($currentText, 'UTF-8')) {
                                    $currentText = mb_convert_encoding($currentText, 'UTF-8', 'UTF-8');
                                }
                                
                                $isAppApi = strpos($this->baseUrl, '/apps/') !== false;
                                
                                if ($isAppApi) {
                                    $lastTextLen = mb_strlen($lastText, 'UTF-8');
                                    $currentTextLen = mb_strlen($currentText, 'UTF-8');
                                    
                                    if ($currentTextLen > $lastTextLen) {
                                        $incrementalText = mb_substr($currentText, $lastTextLen, null, 'UTF-8');
                                        if (!empty($incrementalText)) {
                                            $onChunk($incrementalText);
                                        }
                                    }
                                } else {
                                    $onChunk($currentText);
                                }
                            }
                        }
                    } catch (\JsonException $e) {
                        error_log('JSON异常: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * 从响应中提取文本内容（支持多种格式）
     *
     * @param array|null $jsonData 解析后的JSON数据
     * @return string 提取的文本内容
     */
    private function extractContentFromResponse(?array $jsonData): string
    {
        // 如果数据为空，直接返回
        if (empty($jsonData)) {
            return '';
        }

        // 检查是否有错误
        if (isset($jsonData['code']) && isset($jsonData['message'])) {
            error_log("API返回错误: " . $jsonData['message'] . " (代码: " . $jsonData['code'] . ")");
            return '';
        }

        // ===== 应用API格式 =====
        
        // 应用API流式格式 - output.text
        if (isset($jsonData['output']['text'])) {
            return $jsonData['output']['text'];
        }
        
        // 应用API格式 - output.result
        if (isset($jsonData['output']['result'])) {
            return $jsonData['output']['result'];
        }
        
        // 应用API格式 - 直接在 text 字段
        if (isset($jsonData['text'])) {
            return $jsonData['text'];
        }

        // ===== 标准模型API格式 =====
        
        // 标准模型API格式 - choices[0].message.content
        if (isset($jsonData['output']['choices'][0]['message']['content'])) {
            return $jsonData['output']['choices'][0]['message']['content'];
        }

        // 标准模型API流式格式 - choices[0].delta.content
        if (isset($jsonData['output']['choices'][0]['delta']['content'])) {
            return $jsonData['output']['choices'][0]['delta']['content'];
        }

        // 直接在 output 中
        if (isset($jsonData['output']) && is_string($jsonData['output'])) {
            return $jsonData['output'];
        }

        // 直接在 content 字段
        if (isset($jsonData['content'])) {
            return $jsonData['content'];
        }

        // 无法识别的格式，记录完整响应
        error_log("未知的响应格式: " . json_encode($jsonData, JSON_UNESCAPED_UNICODE));
        
        return '';
    }

    /**
     * 同步调用阿里云百炼AI模型（非流式）
     *
     * @param string $message 用户消息
     * @param array $options 可选配置参数
     * @return string|null AI回复内容，失败返回null
     */
    public function chat(string $message, array $options = []): ?string
    {
        try {
            // 判断是否是应用API
            $isAppApi = strpos($this->baseUrl, '/apps/') !== false;
            
            // 应用API只支持流式，所以使用流式调用并收集结果
            if ($isAppApi) {
                $fullResponse = '';
                $success = $this->streamChat($message, function($chunk) use (&$fullResponse) {
                    $fullResponse .= $chunk;
                }, $options);
                
                if ($success && !empty($fullResponse)) {
                    return $fullResponse;
                }
                return null;
            }
            
            // 标准模型API的同步调用
            $body = $this->buildRequestBody($message, $options);
            
            echo "\n[DEBUG] 同步请求URL: {$this->baseUrl}\n";
            echo "[DEBUG] 同步请求体: " . json_encode($body, JSON_UNESCAPED_UNICODE) . "\n";

            $response = $this->client->post($this->baseUrl, [
                'headers' => $this->buildHeaders(),
                'json' => $body,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('API请求失败，状态码: ' . $response->getStatusCode());
            }

            $rawContent = $response->getBody()->getContents();

            $result = json_decode($rawContent, true);
            
            // 检查JSON解析是否成功
            if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            // 尝试多种响应格式
            $content = $this->extractContentFromResponse($result);
            
            if (!empty($content)) {
                return $content;
            }

            return null;

        } catch (\Exception $e) {
            echo "[ERROR] 同步聊天异常: " . $e->getMessage() . "\n";
            echo "[ERROR] 文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
            return null;
        }
    }
}
