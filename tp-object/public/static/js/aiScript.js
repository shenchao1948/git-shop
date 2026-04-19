// script.js - ThinkPHP8 + jQuery + LayUI AI 对话系统前端脚本

// 全局配置
const CONFIG = {
    WS_URL: 'http://www.shenchao.me:2346',
    MAX_MESSAGE_LENGTH: 500,
    RECONNECT_INTERVALS: [1000, 2000, 5000, 10000, 30000], // 指数退避重连间隔
    HEARTBEAT_INTERVAL: 25000, // 25秒发送一次心跳
    MESSAGE_COOLDOWN: 1000, // 消息冷却时间1秒
};

// 全局状态管理
const state = {
    ws: null,
    isConnected: false,
    isAuthenticating: false,
    isAuthenticated: false,
    isSending: false,
    currentUserId: null,
    currentRoomId: null,
    userToken: null,
    connectionId: null, // 当前WebSocket连接ID（由服务端分配）
    reconnectAttempts: 0,
    reconnectTimer: null,
    heartbeatTimer: null,
    lastMessageTime: 0,
    pendingMessages: [],
    lastUserMessage: null,
    typewriterQueue: '', // 打字机队列
    typewriterTimer: null, // 打字机定时器
    isTyping: false, // 是否正在打字
    hasReceivedFirstChunk: false // 是否已收到第一个内容块
};

// 页面加载完成后初始化
$(document).ready(function () {
    console.log('=== AI聊天系统初始化 ===');
    
    // 获取用户信息
    initializeUserInfo();
    console.log('用户信息:', {
        userToken: state.userToken,
        currentUserId: state.currentUserId,
        currentRoomId: state.currentRoomId
    });
    
    // 初始化WebSocket连接
    initWebSocket();
    
    // 绑定事件
    bindEvents();
    
    // 加载聊天历史记录
    loadChatHistory();
    
    // 设置字符计数器
    updateCharCount();
    
    // 初始化发送按钮状态
    updateSendButtonState();
    
    // 初始化状态标志
    state.hasReceivedFirstChunk = false;
    
    console.log('=== 初始化完成 ===');
});

// 初始化用户信息
function initializeUserInfo() {
    state.userToken = $("#currentUserId").val();
    state.currentUserId = state.userToken;
    state.currentRoomId = state.userToken;
    
    if (!state.userToken) {
        showSystemMessage("用户认证失败，请刷新页面", "error");
    }
}

// 初始化WebSocket连接
function initWebSocket() {
    if (state.ws) {
        state.ws.close();
    }
    
    try {
        state.ws = new WebSocket(CONFIG.WS_URL);
        
        state.ws.onopen = handleWebSocketOpen;
        state.ws.onmessage = handleWebSocketMessage;
        state.ws.onclose = handleWebSocketClose;
        state.ws.onerror = handleWebSocketError;
        
    } catch (e) {
        showSystemMessage("无法连接到服务器", "error");
        scheduleReconnect();
    }
}

// WebSocket连接成功
function handleWebSocketOpen(event) {
    state.isConnected = true;
    state.reconnectAttempts = 0;
    
    // 生成唯一的连接ID（使用时间戳+随机数）
    state.connectionId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    // 发送认证消息
    authenticateWebSocket();
    
    // 显示连接状态
    showSystemMessage("已连接到AI助手", "success");
    
    // 启动心跳
    startHeartbeat();
}

// WebSocket接收消息
function handleWebSocketMessage(event) {
    try {
        const data = JSON.parse(event.data);
        handleParsedMessage(data);
    } catch (e) {
        // 如果不是JSON，可能是纯文本消息
        if (event.data && event.data.trim() !== '') {
            appendMessageToChat('bot', event.data, false);
        }
    }
}

// 处理解析后的消息
function handleParsedMessage(data) {
    const messageType = data.type;
    const messageData = data.data;
    
    switch (messageType) {
        case 'ping':
            // 响应服务端的心跳检测
            sendPong();
            break;
            
        case 'system':
            handleSystemMessage(messageData);
            break;
            
        case 'chat':
            handleChatMessage(messageData);
            break;
            
        case 'typing_indicator':
            break;
            
        case 'presence_update':
            break;
            
        default:
            break;
    }
}

// 处理系统消息
function handleSystemMessage(messageData) {
    switch (messageData.event) {
        case 'authenticated':
            state.isAuthenticated = true;
            state.currentRoomId = messageData.room_id || state.currentRoomId;
            break;
            
        case 'message_sent':
            // 消息发送成功确认
            handleSendMessageSuccess();
            break;
            
        case 'error':
            showSystemMessage(messageData.message || "系统错误", "error");
            handleSendMessageError(messageData.message);
            break;
            
        default:
            break;
    }
}

// 处理聊天消息
function handleChatMessage(messageData) {
    // 如果是AI消息，直接显示
    if (messageData.is_ai) {
        // 处理流式输出
        if (messageData.isStreaming) {
            if (messageData.status === 'start') {
                // AI开始响应，创建显示"AI正在努力思考..."的消息气泡
                appendMessageToChat('bot', 'AI正在努力思考...', false, true, true);
            } else if (messageData.status === 'streaming') {
                // 流式输出中，将内容加入打字机队列
                if (messageData.content && messageData.content.length > 0) {
                    // 如果是第一次收到内容，清除"思考中"提示
                    if (!state.hasReceivedFirstChunk) {
                        clearThinkingMessage();
                        state.hasReceivedFirstChunk = true;
                    }
                    
                    addToTypewriterQueue(messageData.content);
                }
            } else if (messageData.status === 'end') {
                // AI响应结束
                handleSendMessageSuccess();
                // 重置标志
                state.hasReceivedFirstChunk = false;
            }
        } else {
            // 非流式AI消息，直接显示
            if (messageData.content && messageData.content.trim() !== '') {
                appendMessageToChat('bot', messageData.content, false);
            }
            handleSendMessageSuccess();
        }
        return;
    }
    
    // 如果是用户消息，检查是否是自己发送的
    // 通过 sender_connection_id 判断
    if (messageData.sender_id === state.userToken) {
        // 如果有连接ID且与当前连接相同，说明是自己发的，不显示
        if (messageData.sender_connection_id === state.connectionId) {
            return;
        }
        // 否则是其他浏览器发的，应该显示
    }
    
    // 显示其他用户的消息或同一用户其他浏览器的消息
    if (messageData.content && messageData.content.trim() !== '') {
        appendMessageToChat('user', messageData.content, false);
    }
}

// 发送pong响应
function sendPong() {
    if (state.ws && state.isConnected) {
        const pongData = {
            type: 'pong'
        };
        state.ws.send(JSON.stringify(pongData));
    }
}

// 启动心跳
function startHeartbeat() {
    stopHeartbeat();
    
    state.heartbeatTimer = setInterval(() => {
        if (state.ws && state.isConnected) {
            // 检查是否长时间没有收到消息
            const timeSinceLastMessage = Date.now() - state.lastMessageTime;
            if (timeSinceLastMessage > CONFIG.HEARTBEAT_INTERVAL * 2) {
                // 长时间未收到消息
            }
        }
    }, CONFIG.HEARTBEAT_INTERVAL);
}

// 停止心跳
function stopHeartbeat() {
    if (state.heartbeatTimer) {
        clearInterval(state.heartbeatTimer);
        state.heartbeatTimer = null;
    }
}

// WebSocket连接关闭
function handleWebSocketClose(event) {
    state.isConnected = false;
    state.isAuthenticated = false;
    stopHeartbeat();
    
    showSystemMessage("连接已断开，正在尝试重连...", "warning");
    
    // 安排重连
    scheduleReconnect();
}

// WebSocket错误
function handleWebSocketError(error) {
    showSystemMessage("WebSocket连接出错", "error");
}

// 安排重连（指数退避）
function scheduleReconnect() {
    if (state.reconnectTimer) {
        clearTimeout(state.reconnectTimer);
    }
    
    const delayIndex = Math.min(state.reconnectAttempts, CONFIG.RECONNECT_INTERVALS.length - 1);
    const delay = CONFIG.RECONNECT_INTERVALS[delayIndex];
    
    state.reconnectTimer = setTimeout(() => {
        state.reconnectAttempts++;
        initWebSocket();
    }, delay);
}

// 认证WebSocket连接
function authenticateWebSocket() {
    if (!state.ws || !state.isConnected) {
        return;
    }
    
    if (state.isAuthenticating) {
        return;
    }
    
    state.isAuthenticating = true;
    
    const authData = {
        type: 'auth',
        data: {
            token: state.userToken,
            user_id: state.currentUserId
        }
    };
    
    state.ws.send(JSON.stringify(authData));
    
    // 5秒后重置认证状态
    setTimeout(() => {
        state.isAuthenticating = false;
    }, 5000);
}

// 绑定页面事件
function bindEvents() {
    // 发送按钮点击事件
    $('#sendButton').on('click', function () {
        sendMessage();
    });
    
    // 回车发送（Shift+Enter换行）
    $('#messageInput').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            // 只检查是否正在发送，不检查 textarea 的 disabled 状态
            if (!state.isSending) {
                sendMessage();
            }
        }
    });
    
    // 输入框内容变化
    $('#messageInput').on('input', debounce(function () {
        updateCharCount();
        updateSendButtonState();
    }, 300));
    
    // 清空聊天按钮
    $('#clearButton').on('click', function () {
        clearChat();
    });
    
    // 取消请求按钮
    $('#cancelRequest').on('click', function () {
        cancelRequest();
    });
}

// 防抖函数
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// 添加消息到聊天框
function appendMessageToChat(sender, content, isStreaming = false, createNewBubble = false, isThinking = false) {
    const chatBox = $('#chatMessages');
    
    // 如果是流式输出且是bot消息，追加到最后一条bot消息
    if (isStreaming && sender === 'bot' && !createNewBubble) {
        const lastBotMessage = chatBox.find('.justify-start').last();
        if (lastBotMessage.length > 0) {
            const contentElement = lastBotMessage.find('p');
            contentElement.append(escapeHtml(content));
            chatBox.scrollTop(chatBox.get(0).scrollHeight);
            return;
        }
    }
    
    // 创建新消息元素
    const now = new Date();
    const timeStr = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
    
    // 如果是思考状态，添加特殊样式
    const thinkingClass = isThinking ? 'text-gray-500 italic' : '';
    
    const messageElement = $(`
        <div class="flex ${sender === 'user' ? 'justify-end' : 'justify-start'} message-enter">
            <div class="max-w-xs lg:max-w-md ${sender === 'user' ? 'bg-blue-500 text-white rounded-br-none' : 'bg-white border border-gray-200 rounded-bl-none'} rounded-2xl p-4 shadow-sm">
                <div class="flex items-center mb-1">
                    <i class="fas ${sender === 'user' ? 'fa-user' : 'fa-robot'} mr-2"></i>
                    <span class="font-medium">${sender === 'user' ? '你' : 'AI助手'}</span>
                    <span class="ml-auto text-xs opacity-70">${timeStr}</span>
                </div>
                <p class="whitespace-pre-wrap break-words ${thinkingClass}">${escapeHtml(content)}</p>
            </div>
        </div>
    `);
    
    chatBox.append(messageElement);
    chatBox.scrollTop(chatBox.get(0).scrollHeight);
}

// HTML转义，防止XSS攻击
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 更新字符计数
function updateCharCount() {
    const length = $('#messageInput').val().length;
    const charCountElement = $('#charCount');
    charCountElement.text(`${length}/${CONFIG.MAX_MESSAGE_LENGTH}`);
    
    if (length > CONFIG.MAX_MESSAGE_LENGTH * 0.9) {
        charCountElement.removeClass('text-gray-500').addClass('text-red-500');
    } else {
        charCountElement.removeClass('text-red-500').addClass('text-gray-500');
    }
}

// 更新发送按钮状态
function updateSendButtonState() {
    const message = $('#messageInput').val().trim();
    const sendButton = $('#sendButton');
    const isDisabled = message.length === 0 || 
                      message.length > CONFIG.MAX_MESSAGE_LENGTH || 
                      state.isSending ||
                      !state.isConnected ||
                      !state.isAuthenticated;
    
    sendButton.prop('disabled', isDisabled);
    
    if (isDisabled) {
        sendButton.addClass('opacity-50 cursor-not-allowed');
    } else {
        sendButton.removeClass('opacity-50 cursor-not-allowed');
    }
}

// 发送消息
function sendMessage() {
    const message = $('#messageInput').val().trim();
    
    if (!message || state.isSending) {
        return;
    }
    
    // 检查消息长度
    if (message.length > CONFIG.MAX_MESSAGE_LENGTH) {
        showSystemMessage(`消息长度不能超过${CONFIG.MAX_MESSAGE_LENGTH}个字符`, "error");
        return;
    }
    
    // 检查WebSocket连接状态
    if (!state.ws || !state.isConnected) {
        showSystemMessage("WebSocket未连接，正在尝试重连...", "warning");
        scheduleReconnect();
        return;
    }
    
    if (!state.isAuthenticated) {
        showSystemMessage("尚未完成认证，请稍后再试", "warning");
        return;
    }
    
    // 记录发送前的时间
    const sendTime = Date.now();
    state.lastMessageTime = sendTime;
    
    // 保存最后一条用户消息（用于重试）
    state.lastUserMessage = message;
    
    // 显示用户消息
    appendMessageToChat('user', message);
    
    // 清空输入框
    $('#messageInput').val('').trigger('input');
    
    // 显示加载状态
    showLoadingIndicator(true);
    
    // 更新状态
    state.isSending = true;
    updateSendButtonState();
    
    // 构造聊天消息 - 默认发送给AI
    const chatMessage = {
        type: 'chat',
        data: {
            content: message,
            target_user_id: 0,
            room_id: state.currentRoomId,
            is_ai: true // 标记为AI对话
        }
    };
    
    // 发送消息
    try {
        state.ws.send(JSON.stringify(chatMessage));
        
        // 设置超时保护，防止服务器无响应导致按钮一直禁用
        setTimeout(() => {
            if (state.isSending) {
                handleSendMessageSuccess();
            }
        }, 30000); // 30秒超时（AI响应可能较慢）
        
    } catch (e) {
        handleSendMessageError("发送失败: " + e.message);
    }
}

// 处理发送消息成功
function handleSendMessageSuccess() {
    showLoadingIndicator(false);
    state.isSending = false;
    updateSendButtonState();
}

// 处理发送消息错误
function handleSendMessageError(errorMessage) {
    showLoadingIndicator(false);
    state.isSending = false;
    updateSendButtonState();
    showError(errorMessage || "发送失败，请重试");
}

// 添加内容到打字机队列
function addToTypewriterQueue(text) {
    state.typewriterQueue += text;
    
    // 如果当前没有在打字，启动打字机
    if (!state.isTyping) {
        startTypewriter();
    }
}

// 启动打字机效果
function startTypewriter() {
    if (state.typewriterQueue.length === 0) {
        state.isTyping = false;
        return;
    }
    
    state.isTyping = true;
    
    // 每次显示一个字符
    const char = state.typewriterQueue.charAt(0);
    state.typewriterQueue = state.typewriterQueue.substring(1);
    
    // 追加到最后一个bot消息
    const chatBox = $('#chatMessages');
    const lastBotMessage = chatBox.find('.justify-start').last();
    
    if (lastBotMessage.length > 0) {
        const contentElement = lastBotMessage.find('p');
        
        // 如果内容是"AI正在努力思考..."，先清空
        const currentText = contentElement.text();
        if (currentText === 'AI正在努力思考...') {
            contentElement.empty();
        }
        
        contentElement.append(escapeHtml(char));
        chatBox.scrollTop(chatBox.get(0).scrollHeight);
    }
    
    // 设置下一个字符的延迟（30-80ms随机，模拟真实打字速度）
    const delay = Math.random() * 50 + 30;
    state.typewriterTimer = setTimeout(startTypewriter, delay);
}

// 停止打字机
function stopTypewriter() {
    if (state.typewriterTimer) {
        clearTimeout(state.typewriterTimer);
        state.typewriterTimer = null;
    }
    state.isTyping = false;
    state.typewriterQueue = '';
}

// 显示系统消息
function showSystemMessage(message, type = 'info') {
    const chatBox = $('#chatMessages');
    const iconClass = {
        'success': 'fa-check-circle text-green-500',
        'warning': 'fa-exclamation-triangle text-yellow-500',
        'error': 'fa-times-circle text-red-500',
        'info': 'fa-info-circle text-blue-500'
    }[type] || 'fa-info-circle text-blue-500';
    
    const bgColorClass = {
        'success': 'bg-green-100 text-green-800',
        'warning': 'bg-yellow-100 text-yellow-800',
        'error': 'bg-red-100 text-red-800',
        'info': 'bg-blue-100 text-blue-800'
    }[type] || 'bg-blue-100 text-blue-800';
    
    const messageElement = $(`
        <div class="flex justify-center my-2 animate-fade-in">
            <div class="inline-block px-4 py-2 rounded-full text-sm font-medium ${bgColorClass} flex items-center">
                <i class="fas ${iconClass} mr-2"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        </div>
    `);
    
    chatBox.append(messageElement);
    chatBox.scrollTop(chatBox.get(0).scrollHeight);
    
    // 3秒后自动隐藏非错误消息
    if (type !== 'error') {
        setTimeout(() => {
            messageElement.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
}

// 显示加载指示器
function showLoadingIndicator(show = true) {
    const loadingIndicator = $('#loadingIndicator');
    if (show) {
        loadingIndicator.removeClass('hidden');
    } else {
        loadingIndicator.addClass('hidden');
    }
}

// 取消请求
function cancelRequest() {
    stopTypewriter(); // 停止打字机
    clearThinkingMessage(); // 清除思考提示
    showLoadingIndicator(false);
    state.isSending = false;
    state.hasReceivedFirstChunk = false; // 重置标志
    updateSendButtonState();
    showSystemMessage("已取消请求", "info");
}

// 清空聊天
function clearChat() {
    if (confirm('确定要清空所有聊天记录吗？')) {
        stopTypewriter(); // 停止打字机
        $('#chatMessages').empty();
        
        // 添加欢迎消息
        appendMessageToChat('bot', "您好！我是沈超的应聘助手，随时为您解答招聘问题。请问您有什么想问沈超的吗？");
        
        state.hasReceivedFirstChunk = false; // 重置标志
        
        showSystemMessage("聊天记录已清空", "info");
    }
}

// 加载聊天历史
function loadChatHistory() {
    var historyUrl = $('#currentUserId').data('url');
    $.ajax({
        url: historyUrl,
        type: 'GET',
        data: {
            page: 1,
            page_size: 50
        },
        success: function(response) {
            if (response.code === 200 && response.data && response.data.length > 0) {
                console.log('加载历史记录:', response.data.length, '条');
                
                // 清空欢迎消息
                $('#chatMessages').empty();
                
                // 遍历历史记录并显示
                let userCount = 0;
                let aiCount = 0;
                
                response.data.forEach(function(item) {
                    const sender = item.message_type === 'user' ? 'user' : 'bot';
                    if (sender === 'user') {
                        userCount++;
                    } else {
                        aiCount++;
                    }
                    appendMessageToChat(sender, item.content, false);
                });
                
                showSystemMessage(`已加载 ${response.data.length} 条历史记录（${userCount}条用户消息，${aiCount}条AI回复）`, "success");
                console.log(`历史记录加载完成：${userCount}条用户消息，${aiCount}条AI回复`);
            } else {
                console.log('没有历史记录，显示欢迎消息');
                // 没有历史记录，显示欢迎消息
                appendMessageToChat('bot', "您好！我是沈超的应聘助手，随时为您解答招聘问题。请问您有什么想问沈超的吗？");
            }
        },
        error: function(xhr, status, error) {
            console.error('加载聊天历史失败:', error);
            console.error('响应状态:', xhr.status);
            console.error('响应内容:', xhr.responseText);
            // 出错时显示欢迎消息
            appendMessageToChat('bot', "您好！我是沈超的应聘助手，随时为您解答招聘问题。请问您有什么想问沈超的吗？");
        }
    });
}

// 页面卸载时清理资源
$(window).on('beforeunload', function() {
    stopTypewriter(); // 停止打字机
    if (state.ws) {
        state.ws.close();
    }
    stopHeartbeat();
    if (state.reconnectTimer) {
        clearTimeout(state.reconnectTimer);
    }
});

// 清除"思考中"消息
function clearThinkingMessage() {
    const chatBox = $('#chatMessages');
    const lastBotMessage = chatBox.find('.justify-start').last();
    
    if (lastBotMessage.length > 0) {
        const contentElement = lastBotMessage.find('p');
        const currentText = contentElement.text();
        
        // 如果当前内容是"AI正在努力思考..."，则清空
        if (currentText.includes('AI正在努力思考')) {
            contentElement.empty();
        }
    }
}
