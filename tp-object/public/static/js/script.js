// script.js - ThinkPHP8 + jQuery + LayUI AI 对话系统前端脚本

// 全局变量
let ws = null; // WebSocket 连接实例
let isWebSocketConnected = false; // WebSocket 连接状态
let currentUserId = 1; // 模拟当前用户ID (实际项目中应从后端获取)
let currentRoomId = 'default_room'; // 当前对话房间ID
let timer = null;

// 页面加载完成后初始化
$(document).ready(function () {
    currentUserId = $("#currentUserId").val();
    currentRoomId = currentUserId;
    console.log("脚本开始加载...");

    // 初始化WebSocket连接
    initWebSocket();

    // 绑定事件
    bindEvents();

    // 初始化聊天记录
    loadChatHistory();

    // 设置字符计数器
    updateCharCount();

    // 初始化发送按钮状态
    updateSendButtonState();

});

// 初始化WebSocket连接
function initWebSocket() {
    const wsUrl = 'ws://localhost:2346'; // 根据实际部署修改WebSocket地址
    console.log("尝试连接WebSocket服务器:", wsUrl);

    try {
        ws = new WebSocket(wsUrl);

        ws.onopen = function (event) {
            console.log("WebSocket连接已建立");
            isWebSocketConnected = true;
            // 连接成功后发送认证消息
            authenticateWebSocket();
            // 显示连接状态
            showSystemMessage("已连接到AI助手", "success");
            // 心跳
            setTimerPong();
        };

        ws.onmessage = function (event) {
            console.log("收到WebSocket消息:", event.data);
            try {
                const data = JSON.parse(event.data);
                handleWebSocketMessage(data);
            } catch (e) {
                console.error("解析WebSocket消息失败:", e);
                // 如果不是JSON，可能是流式数据的一部分
                if (event.data.trim() !== '') {
                    appendMessageToChat('bot', event.data, true); // 流式输出
                }
            }
        };

        ws.onclose = function (event) {
            console.log("WebSocket连接已关闭", event);
            isWebSocketConnected = false;
            showSystemMessage("连接已断开，正在尝试重连...", "warning");
            // 可以在这里添加重连逻辑
            setTimeout(initWebSocket, 5000); // 5秒后尝试重连
        };

        ws.onerror = function (error) {
            console.error("WebSocket错误:", error);
            showSystemMessage("WebSocket连接出错", "error");
        };

    } catch (e) {
        console.error("创建WebSocket连接失败:", e);
        showSystemMessage("无法连接到服务器", "error");
    }
}

// 认证WebSocket连接
function authenticateWebSocket() {
    if (ws && isWebSocketConnected) {
        const authData = {
            type: 'auth',
            data: {
                token: generateToken(currentUserId), // 生成模拟Token
                user_id: currentUserId
            }
        };
        ws.send(JSON.stringify(authData));
        console.log("发送认证消息:", authData);
    } else {
        console.warn("WebSocket未连接，无法发送认证消息");
    }
}

// 生成模拟Token (实际项目中应从后端获取)
function generateToken(userId) {
    // 简单的模拟Token生成 (应使用安全的算法和密钥)
    const timestamp = Math.floor(Date.now() / 1000);
    return btoa(userId + timestamp + "shenchao"); // Base64编码模拟
}

// 处理WebSocket消息
function handleWebSocketMessage(data) {
    const messageType = data.type;
    const messageData = data.data;

    switch (messageType) {
        case 'system':
            if (messageData.event === 'connected') {
                console.log("WebSocket连接成功");
            } else if (messageData.event === 'authenticated') {
                currentRoomId = messageData.roomID;
                console.log("WebSocket认证成功"+currentRoomId);
                //showSystemMessage("已连接到AI助手", "success");
            } else if (messageData.event === 'error') {
                console.error("WebSocket系统错误:", messageData.message);
                showSystemMessage(messageData.message || "系统错误", "error");
            } else if (messageData.event === 'message_sent') {
                //消息结束
                showLoadingIndicator(false);
            }
            break;

        case 'chat':
            console.log("收到聊天消息:", messageData);
            appendMessageToChat('bot', messageData.content, messageData.isStreaming);
            break;

        case 'typing_indicator':
            // 可以显示打字指示器
            console.log("收到打字指示器:", messageData);
            break;

        case 'presence_update':
            // 可以更新用户在线状态
            console.log("收到在线状态更新:", messageData);
            break;

        default:
            console.warn("未知的WebSocket消息类型:", messageType, data);
            // 如果是流式回复的一部分，直接处理
            if (messageData && typeof messageData === 'object' && messageData.content) {
                appendMessageToChat('bot', messageData.content, true);
            }
    }
}

// 绑定页面事件
function bindEvents() {
    // 发送按钮点击事件
    $('#sendButton').on('click', function () {
        sendMessage();
    });

    // 回车发送
    $('#messageInput').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault(); // 阻止默认换行
            if (!$(this).prop('disabled') && !isSending) {
                sendMessage();
            }
        }
    });

    // 输入框内容变化
    $('#messageInput').on('input', function () {
        updateCharCount();
        updateSendButtonState();
    });

    // 清空聊天按钮
    $('#clearButton').on('click', function () {
        clearChat();
    });

    // 取消请求按钮
    $('#cancelRequest').on('click', function () {
        cancelRequest();
    });

    // 重试按钮
    $('#retryButton').on('click', function () {
        retryLastMessage();
    });

    // 关闭错误提示
    $('#dismissError').on('click', function () {
        hideError();
    });

    // 设置按钮
    $('#settingsBtn').on('click', function () {
        $('#settingsModal').removeClass('hidden').addClass('flex');
    });

    // 关闭设置模态框
    $('#closeSettings').on('click', function () {
        $('#settingsModal').addClass('hidden').removeClass('flex');
    });

    // 点击模态框外部关闭
    $('#settingsModal').on('click', function (e) {
        if (e.target === this) {
            $(this).addClass('hidden').removeClass('flex');
        }
    });
}

// 更新字符计数
function updateCharCount() {
    const length = $('#messageInput').val().length;
    const charCountElement = $('#charCount');
    charCountElement.text(`${length}/500`);
    if (length > 450) {
        charCountElement.removeClass('text-gray-500').addClass('text-red-500');
    } else {
        charCountElement.removeClass('text-red-500').addClass('text-gray-500');
    }
}

// 更新发送按钮状态
function updateSendButtonState() {
    const message = $('#messageInput').val().trim();
    const sendButton = $('#sendButton');
    const isDisabled = message.length === 0 || message.length > 500 || isSending;
    sendButton.prop('disabled', isDisabled);
    if (isDisabled) {
        sendButton.addClass('opacity-50 cursor-not-allowed');
    } else {
        sendButton.removeClass('opacity-50 cursor-not-allowed');
    }
}

function setTimerPong(){
    clearInterval(timer);
    if (ws && isWebSocketConnected) {
        timer = setInterval(function (){
            const chatMessage = {
                type: 'pong',
                data: {
                    content: 'message',
                    target_user_id: 0, // 0表示群聊或广播
                    room_id: currentRoomId
                }
            };
            ws.send(JSON.stringify(chatMessage));
            console.log("发送心跳消息:", chatMessage);
        }, 40000);
    }
}

// 发送消息
function sendMessage() {
    const message = $('#messageInput').val().trim();
    if (!message || isSending) return;

    // 显示用户消息
    appendMessageToChat('user', message);

    // 清空输入框
    $('#messageInput').val('').trigger('input'); // 触发input事件更新计数和按钮

    // 显示加载状态
    showLoadingIndicator(true);

    // 发送消息到WebSocket服务器
    if (ws && isWebSocketConnected) {
        const chatMessage = {
            type: 'chat',
            data: {
                content: message,
                target_user_id: 0, // 0表示群聊或广播
                room_id: currentRoomId,
                is_ai: true // 标记为AI对话
            }
        };
        ws.send(JSON.stringify(chatMessage));
        console.log("发送AI聊天消息:", chatMessage);
        // 心跳
        setTimerPong();
    } else {
        // 如果WebSocket未连接，尝试通过AJAX发送
        console.warn("WebSocket未连接，尝试通过AJAX发送消息");
        //sendViaAjax(message);
    }
}

// 通过AJAX发送消息 (备用方案)
function sendViaAjax(message) {
    $.ajax({
        url: '/chat/send-message', // 对应ThinkPHP控制器方法
        method: 'POST',
        data: { message: message },
        beforeSend: function () {
            isSending = true;
            updateSendButtonState();
        },
        success: function (response) {
            console.log("AJAX响应:", response);
            // 这里应该处理后端返回的完整响应，而不是流式数据
            // 如果后端返回的是完整回复，可以这样处理
            if (response && response.content) {
                appendMessageToChat('bot', response.content);
            } else {
                // 假设后端返回的是JSON格式的回复
                appendMessageToChat('bot', JSON.stringify(response));
            }
        },
        error: function (xhr, status, error) {
            console.error("AJAX发送失败:", status, error);
            showSystemMessage("发送失败: " + (error || status), "error");
        },
        complete: function () {
            isSending = false;
            updateSendButtonState();
            showLoadingIndicator(false);
        }
    });
}

// 添加消息到聊天框
function appendMessageToChat(sender, content, isStreaming = false) {
    const chatBox = $('#chatMessages');
    
    // 如果是流式输出且是bot消息
    if (isStreaming && sender === 'bot') {
        const lastBotMessage = chatBox.find('.message-enter:last .bg-white').last();
        if (lastBotMessage.length) {
            lastBotMessage.find('p').append(content);
        } else {
            // 如果没有之前的bot消息，则添加新消息
            const messageElement = $(`
                <div class="flex justify-start message-enter">
                    <div class="max-w-xs lg:max-w-md bg-white border border-gray-200 rounded-bl-none rounded-2xl p-4 shadow-sm">
                        <div class="flex items-center mb-1">
                            <i class="fas fa-robot mr-2"></i>
                            <span class="font-medium">AI助手</span>
                        </div>
                        <p class="whitespace-pre-wrap">${content}</p>
                    </div>
                </div>
            `);
            chatBox.append(messageElement);
        }
    } else {
        const messageElement = $(`
            <div class="flex ${sender === 'user' ? 'justify-end' : 'justify-start'} message-enter">
                <div class="max-w-xs lg:max-w-md ${sender === 'user' ? 'bg-blue-500 text-white rounded-br-none' : 'bg-white border border-gray-200 rounded-bl-none'} rounded-2xl p-4 shadow-sm">
                    <div class="flex items-center mb-1">
                        <i class="fas ${sender === 'user' ? 'fa-user' : 'fa-robot'} mr-2"></i>
                        <span class="font-medium">${sender === 'user' ? '你' : 'AI助手'}</span>
                    </div>
                    <p class="whitespace-pre-wrap">${content}</p>
                </div>
            </div>
        `);
        chatBox.append(messageElement);
    }

    // 滚动到底部
    chatBox.scrollTop(chatBox.get(0).scrollHeight);
}

// 显示系统消息
function showSystemMessage(message, type = 'info') {
    const chatBox = $('#chatMessages');
    const messageElement = $(`
        <div class="flex justify-center my-2">
            <div class="inline-block px-4 py-2 rounded-full text-sm font-medium ${type === 'success' ? 'bg-green-100 text-green-800' : type === 'warning' ? 'bg-yellow-100 text-yellow-800' : type === 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'}">
                ${message}
            </div>
        </div>
    `);
    chatBox.append(messageElement);
    chatBox.scrollTop(chatBox.scrollHeight);
}

// 显示加载指示器
function showLoadingIndicator(show = true) {
    const loadingIndicator = $('#loadingIndicator');
    if (show) {
        loadingIndicator.removeClass('hidden');
        isSending = true;
        updateSendButtonState();
    } else {
        loadingIndicator.addClass('hidden');
        isSending = false;
        updateSendButtonState();
    }
}

// 取消请求
function cancelRequest() {
    console.log("取消当前请求");
    showLoadingIndicator(false);
    // 可以在这里添加取消WebSocket请求的逻辑
    // 注意：WebSocket本身没有直接取消的方法，需要后端配合
}

// 重试上次消息
function retryLastMessage() {
    console.log("重试上次消息");
    hideError();
    // 可以记录上次发送的消息，然后重新发送
    // 这里简化处理，直接重新发送
    const lastMessage = $('#messageInput').val().trim();
    if (lastMessage) {
        sendMessage();
    }
}

// 隐藏错误提示
function hideError() {
    $('#errorMessage').addClass('hidden');
}

// 清空聊天
function clearChat() {
    $('#chatMessages').empty();
    // 可以添加欢迎消息
    appendMessageToChat('bot', "您好！我是沈超的应聘助手，随时为您解答招聘问题。请问您有什么想问沈超的吗？");
    console.log("聊天记录已清空");
}

// 加载聊天历史 (模拟)
function loadChatHistory() {
    console.log("加载聊天历史记录");
    // 可以通过AJAX从后端获取历史记录
    // 这里模拟添加欢迎消息
    //appendMessageToChat('bot', "您好！我是您的AI助手，随时为您解答问题。请问有什么可以帮助您的吗？");
}

// 全局变量用于控制发送状态
let isSending = false;
