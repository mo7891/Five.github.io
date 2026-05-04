<?php
// chat.php - 主聊天室页面
session_start();

// 简单的用户名校验
if (empty($_SESSION['username']) && empty($_GET['username'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>加入聊天室</title>
        <style>
            body { font-family: Arial; margin: 50px; text-align: center; }
            input { padding: 8px; margin: 5px; }
        </style>
    </head>
    <body>
        <h2>加入聊天室</h2>
        <form method="get">
            <input type="text" name="username" placeholder="输入你的昵称" required>
            <button type="submit">进入</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// 设置会话用户名
if (isset($_GET['username'])) {
    $_SESSION['username'] = htmlspecialchars(trim($_GET['username']));
}
$username = $_SESSION['username'];

// 消息存储文件
$messageFile = 'messages.json';

// 处理发送消息 (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    header('Content-Type: application/json');
    $message = trim($_POST['message'] ?? '');
    if ($message !== '') {
        $messages = [];
        if (file_exists($messageFile)) {
            $json = file_get_contents($messageFile);
            $messages = json_decode($json, true) ?? [];
        }
        // 添加新消息
        $messages[] = [
            'username' => $username,
            'message' => htmlspecialchars($message),
            'time' => date('H:i:s')
        ];
        // 保留最近100条消息
        if (count($messages) > 100) {
            $messages = array_slice($messages, -100);
        }
        file_put_contents($messageFile, json_encode($messages));
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '消息不能为空']);
    }
    exit;
}

// 获取消息 (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'get') {
    header('Content-Type: application/json');
    if (file_exists($messageFile)) {
        $json = file_get_contents($messageFile);
        $messages = json_decode($json, true) ?? [];
        echo json_encode($messages);
    } else {
        echo json_encode([]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>简易聊天室 - <?php echo $username; ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: #f0f0f0; }
        .chat-container { max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); overflow: hidden; }
        .chat-header { background: #4CAF50; color: white; padding: 15px; text-align: center; font-size: 1.2em; }
        .chat-messages { height: 400px; overflow-y: auto; padding: 15px; background: #f9f9f9; border-bottom: 1px solid #ddd; }
        .message { margin-bottom: 12px; padding: 8px 12px; border-radius: 8px; background: white; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .message strong { color: #4CAF50; margin-right: 8px; }
        .message-time { font-size: 0.7em; color: #aaa; margin-left: 10px; }
        .chat-input { display: flex; padding: 15px; background: white; gap: 10px; }
        .chat-input input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em; }
        .chat-input button { padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; }
        .chat-input button:hover { background: #45a049; }
        .status { font-size: 0.8em; text-align: center; padding: 5px; color: #666; background: #eee; }
        .logout { float: right; font-size: 0.8em; background: #f44336; padding: 5px 10px; border-radius: 5px; color: white; text-decoration: none; }
        .logout:hover { background: #d32f2f; }
    </style>
</head>
<body>
<div class="chat-container">
    <div class="chat-header">
        简易聊天室 (当前用户: <?php echo $username; ?>)
        <a href="?logout=1" class="logout" onclick="return confirm('退出聊天？')">退出</a>
    </div>
    <div class="chat-messages" id="messages">
        <div style="text-align:center; color:#888;">加载消息中...</div>
    </div>
    <div class="chat-input">
        <input type="text" id="messageInput" placeholder="输入消息..." autocomplete="off">
        <button id="sendBtn">发送</button>
    </div>
    <div class="status" id="status">在线</div>
</div>

<script>
    const messagesDiv = document.getElementById('messages');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const statusSpan = document.getElementById('status');

    // 获取并显示消息
    async function loadMessages() {
        try {
            const response = await fetch('chat.php?action=get');
            const messages = await response.json();
            if (messages.length === 0) {
                messagesDiv.innerHTML = '<div style="text-align:center; color:#888;">暂无消息，发一条吧！</div>';
                return;
            }
            let html = '';
            messages.forEach(msg => {
                html += `<div class="message">
                            <strong>${escapeHtml(msg.username)}</strong>
                            <span>${escapeHtml(msg.message)}</span>
                            <span class="message-time">${msg.time}</span>
                         </div>`;
            });
            messagesDiv.innerHTML = html;
            // 自动滚动到底部
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        } catch (err) {
            console.error('加载消息失败', err);
            statusSpan.innerText = '连接错误';
        }
    }

    // 简单的防XSS（后端已做，前端再保一次）
    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        }).replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, function(c) {
            return c;
        });
    }

    // 发送消息
    async function sendMessage() {
        const message = messageInput.value.trim();
        if (message === '') {
            statusSpan.innerText = '不能发送空消息';
            setTimeout(() => { statusSpan.innerText = '在线'; }, 1500);
            return;
        }
        statusSpan.innerText = '发送中...';
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'send');
            formData.append('message', message);
            const response = await fetch('chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            const result = await response.json();
            if (result.status === 'ok') {
                messageInput.value = '';
                statusSpan.innerText = '已发送';
                setTimeout(() => { statusSpan.innerText = '在线'; }, 1000);
                loadMessages(); // 立即刷新消息
            } else {
                statusSpan.innerText = '发送失败';
            }
        } catch (err) {
            console.error('发送失败', err);
            statusSpan.innerText = '网络错误';
        }
    }

    // 自动刷新消息 (轮询)
    let lastLoadTime = 0;
    function startAutoRefresh() {
        loadMessages();
        setInterval(() => {
            loadMessages();
        }, 2000); // 每2秒获取一次新消息
    }

    // 绑定事件
    sendBtn.addEventListener('click', sendMessage);
    messageInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // 处理退出
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('logout')) {
        window.location.href = 'chat.php'; // 清除session需要后端处理，这里简单重定向到入口
    }

    startAutoRefresh();
</script>
<?php
// 处理退出逻辑
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: chat.php');
    exit;
}
?>
</body>
</html>