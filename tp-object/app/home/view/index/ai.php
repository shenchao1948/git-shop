
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI智能助手 | 优化版</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="{$hostUrl}/static/js/jquery.js"></script>
    <script src="{$hostUrl}/static/js/aiScript.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                        accent: '#ec4899',
                        dark: '#1e293b',
                        light: '#f8fafc'
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .animate-pulse-custom {
            animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        .typing-indicator span {
            animation: typing 1.4s infinite ease-in-out;
        }
        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }
        .message-enter {
            animation: slideIn 0.3s ease-out forwards;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen">
<input type="hidden" id="currentUserId" value="{$token}">
<!-- 头部导航 -->
<header class="bg-white shadow-lg sticky top-0 z-50">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <i class="fas fa-robot text-3xl text-primary"></i>
            <h1 class="text-2xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
                AI智能助手 Pro
            </h1>
        </div>
        <div class="flex items-center space-x-4">
            <button id="settingsBtn" class="p-2 rounded-full hover:bg-gray-100 transition-colors">
                <i class="fas fa-cog text-xl text-gray-600"></i>
            </button>
            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white font-semibold">
                U
            </div>
        </div>
    </div>
</header>

<!-- 主容器 -->
<main class="container mx-auto px-4 py-6 max-w-4xl">
    <!-- 聊天区域 -->
    <div id="chatContainer" class="bg-white rounded-2xl shadow-xl overflow-hidden mb-6">
        <div id="chatHeader" class="bg-gradient-to-r from-primary to-secondary p-4 text-white">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">实时对话</h2>
                <div class="flex items-center space-x-2">
                        <span class="flex w-3 h-3">
                            <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                    <span class="text-sm">在线</span>
                </div>
            </div>
        </div>

        <div id="chatMessages" class="h-96 overflow-y-auto p-4 space-y-4 bg-gray-50">
            <!-- 欢迎消息 -->
            <div class="flex justify-start message-enter">
                <div class="max-w-xs lg:max-w-md bg-white rounded-2xl rounded-tl-none p-4 shadow-sm border">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-robot text-primary mr-2"></i>
                        <span class="font-medium text-gray-700">AI助手</span>
                    </div>
                    <p class="text-gray-600">您好！我是沈超的应聘助手，随时为您解答招聘问题。请问您有什么想问沈超的吗？</p>
                    <!--
                    <div class="flex flex-wrap gap-2 mt-3">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">编程帮助</span>
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm">学习辅导</span>
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">生活咨询</span>
                    </div>
                    -->
                </div>
            </div>
        </div>
    </div>

    <!-- 输入区域 -->
    <div class="bg-white rounded-2xl shadow-xl p-4">
        <div class="flex items-end space-x-3">
            <div class="flex-1">
                <label for="messageInput" class="block text-sm font-medium text-gray-700 mb-1">
                    输入您的问题
                </label>
                <div class="relative">
                        <textarea
                            id="messageInput"
                            rows="3"
                            placeholder="请输入您的问题... (支持Enter发送)"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent resize-none transition-all"
                        ></textarea>
                    <div class="absolute bottom-2 right-2 flex items-center space-x-2">
                        <span id="charCount" class="text-xs text-gray-500">0/500</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col space-y-2 pb-1">
                <button
                    id="sendButton"
                    class="bg-gradient-to-r from-primary to-secondary hover:from-primary hover:to-primary text-white p-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                    disabled
                >
                    <i class="fas fa-paper-plane"></i>
                </button>
                <button
                    id="clearButton"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 p-3 rounded-xl transition-colors"
                    title="清空对话"
                >
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>

        <!-- 加载状态指示器 -->
        <div id="loadingIndicator" class="hidden mt-4 flex items-center justify-center p-3 bg-blue-50 rounded-xl">
            <div class="typing-indicator flex space-x-1 text-primary">
                <span class="w-2 h-2 bg-current rounded-full"></span>
                <span class="w-2 h-2 bg-current rounded-full"></span>
                <span class="w-2 h-2 bg-current rounded-full"></span>
            </div>
            <span class="ml-3 text-primary font-medium">AI正在思考中...</span>
            <button id="cancelRequest" class="ml-4 text-red-500 hover:text-red-700">
                <i class="fas fa-times-circle"></i> 取消
            </button>
        </div>

        <!-- 错误提示 -->
        <div id="errorMessage" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-xl">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                <span id="errorText" class="text-red-700"></span>
            </div>
            <div class="mt-2 flex space-x-2">
                <button id="retryButton" class="px-3 py-1 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                    重新尝试
                </button>
                <button id="dismissError" class="px-3 py-1 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                    关闭
                </button>
            </div>
        </div>
    </div>

</main>

</body>
</html>
