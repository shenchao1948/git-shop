<?php
return [
    // 阿里云百炼 API Key（用于AI模型调用）
    'api_key' => '',
    
    // 阿里云访问密钥（用于其他阿里云服务）
    'access_key_id' => 'your_access_key_id',
    'access_key_secret' => 'your_access_key_secret',
    
    // 区域和端点配置
    'region_id' => 'cn-hangzhou',
    'endpoint' => 'http://ecs.aliyuncs.com',
    
    // 超时配置（秒）
    'timeout' => 30.0,
    'connect_timeout' => 10.0,
    
    // 阿里云百炼 API 基础URL
    //'base_url' => 'https://dashscope.aliyuncs.com/api/v1/services/aigc/text-generation/generation',
    'base_url' => '',

    // 默认模型配置
    //'default_model' => 'qwen-turbo',
    'default_model' => 'qwen-plus-latest',
    'temperature' => 0.7,
    'top_p' => 0.8,
    'max_tokens' => 1500,
];
