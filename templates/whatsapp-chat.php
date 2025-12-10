<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/WhatsApp.php';
require_once __DIR__ . '/../src/Customer.php';

use App\Auth;
use App\WhatsApp;
use App\Customer;

$auth = new Auth();
if (!$auth->check()) {
    header('Location: /login');
    exit;
}

$user = $auth->user();
$whatsapp = new WhatsApp();
$customer = new Customer();

$sessionStatus = $whatsapp->getSessionStatus();
$isConnected = ($sessionStatus['status'] ?? '') === 'connected';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Chat - ISP CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --wa-green: #25D366;
            --wa-dark: #128C7E;
            --wa-light: #DCF8C6;
            --wa-bg: #ECE5DD;
            --chat-header: #075E54;
        }
        
        body {
            height: 100vh;
            overflow: hidden;
        }
        
        .chat-container {
            display: flex;
            height: calc(100vh - 56px);
        }
        
        .chat-sidebar {
            width: 350px;
            border-right: 1px solid #dee2e6;
            display: flex;
            flex-direction: column;
            background: #fff;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--wa-bg);
        }
        
        .chat-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .chat-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .chat-item:hover, .chat-item.active {
            background: #f5f5f5;
        }
        
        .chat-item.active {
            border-left: 3px solid var(--wa-green);
        }
        
        .chat-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--wa-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .chat-item-content {
            flex: 1;
            min-width: 0;
        }
        
        .chat-name {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .chat-preview {
            color: #667781;
            font-size: 0.875rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-time {
            font-size: 0.75rem;
            color: #667781;
        }
        
        .unread-badge {
            background: var(--wa-green);
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }
        
        .chat-header {
            background: var(--chat-header);
            color: white;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath d='M0 0h100v100H0z' fill='%23e5ddd5'/%3E%3C/svg%3E");
        }
        
        .message {
            max-width: 65%;
            margin-bottom: 10px;
            clear: both;
        }
        
        .message-bubble {
            padding: 8px 12px;
            border-radius: 8px;
            position: relative;
            box-shadow: 0 1px 1px rgba(0,0,0,0.1);
        }
        
        .message.incoming {
            float: left;
        }
        
        .message.incoming .message-bubble {
            background: white;
            border-top-left-radius: 0;
        }
        
        .message.outgoing {
            float: right;
        }
        
        .message.outgoing .message-bubble {
            background: var(--wa-light);
            border-top-right-radius: 0;
        }
        
        .message-time {
            font-size: 0.7rem;
            color: #667781;
            text-align: right;
            margin-top: 3px;
        }
        
        .message-status {
            color: #53bdeb;
        }
        
        .chat-input-container {
            background: #f0f0f0;
            padding: 10px 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .chat-input {
            flex: 1;
            border: none;
            border-radius: 25px;
            padding: 10px 20px;
            outline: none;
            font-size: 0.95rem;
        }
        
        .send-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--wa-green);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .send-btn:hover {
            background: var(--wa-dark);
        }
        
        .no-chat-selected {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #667781;
        }
        
        .no-chat-selected i {
            font-size: 6rem;
            color: #c8c8c8;
            margin-bottom: 20px;
        }
        
        .customer-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-dot.connected { background: var(--wa-green); }
        .status-dot.disconnected { background: #dc3545; }
        
        .search-box {
            padding: 10px;
            background: #f6f6f6;
        }
        
        .search-input {
            border-radius: 20px;
            padding-left: 40px;
        }
        
        .search-icon {
            position: absolute;
            left: 25px;
            top: 50%;
            transform: translateY(-50%);
            color: #667781;
        }
        
        .sidebar-header {
            background: var(--chat-header);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .customer-link-modal .modal-body {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .customer-option {
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .customer-option:hover {
            background: #f8f9fa;
            border-color: var(--wa-green);
        }
        
        @media (max-width: 768px) {
            .chat-sidebar {
                width: 100%;
                display: block;
            }
            .chat-main {
                display: none;
            }
            .chat-container.chat-open .chat-sidebar {
                display: none;
            }
            .chat-container.chat-open .chat-main {
                display: flex;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">
                <i class="bi bi-router"></i> ISP CRM
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-light">
                    <span class="status-dot <?= $isConnected ? 'connected' : 'disconnected' ?>"></span>
                    <?= $isConnected ? 'Connected' : 'Disconnected' ?>
                </span>
                <a href="/settings?tab=whatsapp" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="/dashboard" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </nav>

    <div class="chat-container" id="chatContainer">
        <div class="chat-sidebar">
            <div class="sidebar-header">
                <h5 class="mb-0"><i class="bi bi-whatsapp me-2"></i>WhatsApp Chats</h5>
                <button class="btn btn-sm btn-outline-light" onclick="refreshChats()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="search-box position-relative">
                <i class="bi bi-search search-icon"></i>
                <input type="text" class="form-control search-input" id="searchChats" placeholder="Search chats...">
            </div>
            <div class="chat-list" id="chatList">
                <div class="text-center p-4 text-muted">
                    <i class="bi bi-arrow-clockwise spin"></i> Loading chats...
                </div>
            </div>
        </div>
        
        <div class="chat-main" id="chatMain">
            <div class="no-chat-selected" id="noChatSelected">
                <i class="bi bi-chat-dots"></i>
                <h4>WhatsApp Web Chat</h4>
                <p>Select a conversation to start messaging</p>
                <?php if (!$isConnected): ?>
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    WhatsApp is not connected. <a href="/settings?tab=whatsapp">Connect now</a>
                </div>
                <?php endif; ?>
            </div>
            
            <div id="activeChat" style="display: none; flex-direction: column; height: 100%;">
                <div class="chat-header">
                    <button class="btn btn-link text-white d-md-none" onclick="closeChat()">
                        <i class="bi bi-arrow-left"></i>
                    </button>
                    <div class="chat-avatar" id="chatAvatar">?</div>
                    <div class="flex-grow-1">
                        <div class="fw-bold" id="chatName">Contact Name</div>
                        <small id="chatPhone">+254...</small>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-link text-white" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="linkToCustomer()"><i class="bi bi-person-plus me-2"></i>Link to Customer</a></li>
                            <li><a class="dropdown-item" href="#" onclick="createTicket()"><i class="bi bi-ticket me-2"></i>Create Ticket</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="viewCustomer()"><i class="bi bi-person me-2"></i>View Customer</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                </div>
                
                <div class="chat-input-container">
                    <input type="text" class="chat-input" id="messageInput" placeholder="Type a message..." onkeypress="handleKeyPress(event)">
                    <button class="send-btn" onclick="sendMessage()">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="linkCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Link to Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="customerSearch" placeholder="Search customers by name, phone, or account...">
                    </div>
                    <div id="customerResults"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentChat = null;
        let chats = [];
        let pollingInterval = null;
        
        async function fetchAPI(url, options = {}) {
            const response = await fetch(url, {
                headers: { 'Content-Type': 'application/json', ...options.headers },
                ...options
            });
            return response.json();
        }
        
        async function refreshChats() {
            try {
                const data = await fetchAPI('/api/whatsapp-chat.php?action=chats');
                if (data.success && data.chats) {
                    chats = data.chats;
                    renderChatList(chats);
                }
            } catch (error) {
                console.error('Error loading chats:', error);
            }
        }
        
        function renderChatList(chatList) {
            const container = document.getElementById('chatList');
            if (!chatList.length) {
                container.innerHTML = '<div class="text-center p-4 text-muted">No conversations yet</div>';
                return;
            }
            
            container.innerHTML = chatList.map(chat => `
                <div class="chat-item d-flex align-items-center gap-3 ${currentChat?.id === chat.id ? 'active' : ''}" 
                     onclick="openChat(${JSON.stringify(chat).replace(/"/g, '&quot;')})">
                    <div class="chat-avatar">${getInitials(chat.name || chat.phone)}</div>
                    <div class="chat-item-content">
                        <div class="d-flex justify-content-between">
                            <span class="chat-name">${escapeHtml(chat.name || chat.phone)}</span>
                            <span class="chat-time">${formatTime(chat.lastMessageAt)}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="chat-preview">${escapeHtml(chat.lastMessagePreview || '')}</span>
                            ${chat.unreadCount > 0 ? `<span class="unread-badge">${chat.unreadCount}</span>` : ''}
                        </div>
                        ${chat.customer_name ? `<span class="customer-badge"><i class="bi bi-person"></i> ${escapeHtml(chat.customer_name)}</span>` : ''}
                    </div>
                </div>
            `).join('');
        }
        
        async function openChat(chat) {
            currentChat = chat;
            document.getElementById('chatContainer').classList.add('chat-open');
            document.getElementById('noChatSelected').style.display = 'none';
            document.getElementById('activeChat').style.display = 'flex';
            
            document.getElementById('chatAvatar').textContent = getInitials(chat.name || chat.phone);
            document.getElementById('chatName').textContent = chat.customer_name || chat.name || chat.phone;
            document.getElementById('chatPhone').textContent = chat.phone || '';
            
            renderChatList(chats);
            await loadMessages(chat.id);
            
            if (chat.unreadCount > 0) {
                await fetchAPI('/api/whatsapp-chat.php?action=mark-read', {
                    method: 'POST',
                    body: JSON.stringify({ chatId: chat.id })
                });
                chat.unreadCount = 0;
                renderChatList(chats);
            }
            
            startPolling();
        }
        
        function closeChat() {
            document.getElementById('chatContainer').classList.remove('chat-open');
            currentChat = null;
            stopPolling();
        }
        
        async function loadMessages(chatId) {
            const container = document.getElementById('chatMessages');
            container.innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-clockwise spin"></i> Loading...</div>';
            
            try {
                const data = await fetchAPI(`/api/whatsapp-chat.php?action=messages&chatId=${chatId}`);
                if (data.success) {
                    renderMessages(data.messages);
                }
            } catch (error) {
                container.innerHTML = '<div class="text-center p-4 text-danger">Error loading messages</div>';
            }
        }
        
        function renderMessages(messages) {
            const container = document.getElementById('chatMessages');
            container.innerHTML = messages.map(msg => `
                <div class="message ${msg.fromMe ? 'outgoing' : 'incoming'}">
                    <div class="message-bubble">
                        <div class="message-text">${escapeHtml(msg.body || '')}</div>
                        <div class="message-time">
                            ${formatMessageTime(msg.timestamp)}
                            ${msg.fromMe ? '<i class="bi bi-check2-all message-status"></i>' : ''}
                        </div>
                    </div>
                </div>
            `).join('') + '<div style="clear:both"></div>';
            
            container.scrollTop = container.scrollHeight;
        }
        
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !currentChat) return;
            
            input.value = '';
            
            const tempMsg = {
                body: message,
                fromMe: true,
                timestamp: Math.floor(Date.now() / 1000)
            };
            appendMessage(tempMsg);
            
            try {
                const data = await fetchAPI('/api/whatsapp-chat.php?action=send', {
                    method: 'POST',
                    body: JSON.stringify({
                        chatId: currentChat.chatId || currentChat.id,
                        message: message
                    })
                });
                
                if (!data.success) {
                    alert('Failed to send message: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error sending message: ' + error.message);
            }
        }
        
        function appendMessage(msg) {
            const container = document.getElementById('chatMessages');
            const div = document.createElement('div');
            div.className = `message ${msg.fromMe ? 'outgoing' : 'incoming'}`;
            div.innerHTML = `
                <div class="message-bubble">
                    <div class="message-text">${escapeHtml(msg.body || '')}</div>
                    <div class="message-time">
                        ${formatMessageTime(msg.timestamp)}
                        ${msg.fromMe ? '<i class="bi bi-check2 message-status"></i>' : ''}
                    </div>
                </div>
            `;
            container.insertBefore(div, container.lastChild);
            container.scrollTop = container.scrollHeight;
        }
        
        function handleKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }
        
        function startPolling() {
            stopPolling();
            pollingInterval = setInterval(async () => {
                if (currentChat) {
                    await loadMessages(currentChat.id);
                }
            }, 5000);
        }
        
        function stopPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }
        
        async function linkToCustomer() {
            const modal = new bootstrap.Modal(document.getElementById('linkCustomerModal'));
            modal.show();
        }
        
        document.getElementById('customerSearch').addEventListener('input', async function() {
            const query = this.value.trim();
            if (query.length < 2) {
                document.getElementById('customerResults').innerHTML = '';
                return;
            }
            
            try {
                const data = await fetchAPI(`/api/whatsapp-chat.php?action=search-customers&q=${encodeURIComponent(query)}`);
                if (data.success) {
                    document.getElementById('customerResults').innerHTML = data.customers.map(c => `
                        <div class="customer-option" onclick="selectCustomer(${c.id})">
                            <div class="fw-bold">${escapeHtml(c.name)}</div>
                            <small class="text-muted">${escapeHtml(c.phone)} | ${escapeHtml(c.account_number)}</small>
                        </div>
                    `).join('') || '<div class="text-muted">No customers found</div>';
                }
            } catch (error) {
                console.error('Error searching customers:', error);
            }
        });
        
        async function selectCustomer(customerId) {
            if (!currentChat) return;
            
            try {
                const data = await fetchAPI('/api/whatsapp-chat.php?action=link-customer', {
                    method: 'POST',
                    body: JSON.stringify({
                        conversationId: currentChat.id,
                        customerId: customerId
                    })
                });
                
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('linkCustomerModal')).hide();
                    refreshChats();
                    alert('Customer linked successfully!');
                } else {
                    alert('Failed to link customer: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        function createTicket() {
            if (currentChat && currentChat.customer_id) {
                window.open(`/tickets?action=new&customer_id=${currentChat.customer_id}`, '_blank');
            } else {
                alert('Please link this conversation to a customer first.');
            }
        }
        
        function viewCustomer() {
            if (currentChat && currentChat.customer_id) {
                window.open(`/customers?view=${currentChat.customer_id}`, '_blank');
            } else {
                alert('No customer linked to this conversation.');
            }
        }
        
        function getInitials(name) {
            if (!name) return '?';
            const parts = name.split(' ');
            return parts.map(p => p[0]).slice(0, 2).join('').toUpperCase();
        }
        
        function formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp * 1000);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 86400000 && date.getDate() === now.getDate()) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }
            if (diff < 604800000) {
                return date.toLocaleDateString([], { weekday: 'short' });
            }
            return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
        }
        
        function formatMessageTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(typeof timestamp === 'number' ? timestamp * 1000 : timestamp);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
        
        document.getElementById('searchChats').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const filtered = chats.filter(c => 
                (c.name || '').toLowerCase().includes(query) ||
                (c.phone || '').includes(query) ||
                (c.customer_name || '').toLowerCase().includes(query)
            );
            renderChatList(filtered);
        });
        
        refreshChats();
        setInterval(refreshChats, 30000);
    </script>
</body>
</html>
