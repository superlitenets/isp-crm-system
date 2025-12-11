<?php
require_once __DIR__ . '/../src/WhatsApp.php';
require_once __DIR__ . '/../src/Customer.php';

use App\WhatsApp;
use App\Customer;

$whatsapp = new WhatsApp();
$customer = new Customer();

$sessionStatus = $whatsapp->getSessionStatus();
$isConnected = ($sessionStatus['status'] ?? '') === 'connected';
?>
<style>
    :root {
        --wa-green: #25D366;
        --wa-dark: #128C7E;
        --wa-light: #DCF8C6;
        --wa-bg: #ECE5DD;
        --chat-header: #075E54;
    }
    
    .whatsapp-page {
        height: calc(100vh - 120px);
        display: flex;
        flex-direction: column;
    }
    
    .whatsapp-container {
        flex: 1;
        display: flex;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        background: #fff;
        min-height: 0;
    }
    
    .wa-sidebar {
        width: 320px;
        border-right: 1px solid #e9ecef;
        display: flex;
        flex-direction: column;
        background: #fff;
    }
    
    .wa-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: var(--wa-bg);
        min-width: 0;
    }
    
    .wa-sidebar-header {
        background: #f8f9fa;
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .wa-search {
        position: relative;
    }
    
    .wa-search input {
        padding-left: 38px;
        border-radius: 20px;
        border: 1px solid #e9ecef;
        background: #fff;
    }
    
    .wa-search i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }
    
    .wa-chat-list {
        flex: 1;
        overflow-y: auto;
    }
    
    .wa-chat-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 15px;
        cursor: pointer;
        border-bottom: 1px solid #f5f5f5;
        transition: background 0.2s;
    }
    
    .wa-chat-item:hover {
        background: #f8f9fa;
    }
    
    .wa-chat-item.active {
        background: #e7f5ff;
        border-left: 3px solid var(--wa-green);
    }
    
    .wa-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--wa-green) 0%, var(--wa-dark) 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1rem;
        flex-shrink: 0;
    }
    
    .wa-chat-info {
        flex: 1;
        min-width: 0;
    }
    
    .wa-chat-name {
        font-weight: 500;
        color: #212529;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .wa-chat-preview {
        font-size: 0.85rem;
        color: #6c757d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .wa-chat-meta {
        text-align: right;
        flex-shrink: 0;
    }
    
    .wa-chat-time {
        font-size: 0.75rem;
        color: #6c757d;
    }
    
    .wa-unread {
        background: var(--wa-green);
        color: white;
        border-radius: 50%;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 600;
        margin-top: 4px;
        margin-left: auto;
    }
    
    .wa-customer-tag {
        display: inline-block;
        background: #e3f2fd;
        color: #1976d2;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.7rem;
        margin-top: 4px;
    }
    
    .wa-main-header {
        background: #fff;
        padding: 12px 20px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .wa-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .wa-message {
        max-width: 70%;
        clear: both;
    }
    
    .wa-message.incoming {
        align-self: flex-start;
    }
    
    .wa-message.outgoing {
        align-self: flex-end;
    }
    
    .wa-bubble {
        padding: 10px 14px;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        position: relative;
    }
    
    .wa-message.incoming .wa-bubble {
        background: #fff;
        border-top-left-radius: 4px;
    }
    
    .wa-message.outgoing .wa-bubble {
        background: var(--wa-light);
        border-top-right-radius: 4px;
    }
    
    .wa-msg-text {
        word-break: break-word;
        line-height: 1.4;
    }
    
    .wa-msg-time {
        font-size: 0.7rem;
        color: #667781;
        text-align: right;
        margin-top: 4px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
    }
    
    .wa-msg-status {
        color: #53bdeb;
    }
    
    .wa-media {
        margin-bottom: 8px;
    }
    
    .wa-media-img {
        max-width: 100%;
        max-height: 300px;
        border-radius: 8px;
        cursor: pointer;
        display: block;
    }
    
    .wa-media-video {
        max-width: 100%;
        max-height: 300px;
        border-radius: 8px;
    }
    
    .wa-media-audio {
        width: 100%;
        max-width: 250px;
    }
    
    .wa-media-file {
        margin-bottom: 8px;
    }
    
    .wa-media-placeholder {
        background: rgba(0,0,0,0.05);
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 8px;
    }
    
    .wa-media-placeholder i {
        font-size: 1.5rem;
        display: block;
        margin-bottom: 5px;
    }
    
    .wa-input-area {
        background: #f0f2f5;
        padding: 12px 20px;
        display: flex;
        gap: 12px;
        align-items: center;
    }
    
    .wa-input {
        flex: 1;
        border: none;
        border-radius: 24px;
        padding: 12px 20px;
        font-size: 0.95rem;
        outline: none;
        background: #fff;
    }
    
    .wa-send-btn {
        width: 44px;
        height: 44px;
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
    
    .wa-send-btn:hover {
        background: var(--wa-dark);
    }
    
    .wa-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #6c757d;
        text-align: center;
        padding: 40px;
    }
    
    .wa-empty i {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 20px;
    }
    
    .wa-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
    }
    
    .wa-status-badge.connected {
        background: rgba(37, 211, 102, 0.1);
        color: var(--wa-green);
    }
    
    .wa-status-badge.disconnected {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    
    .wa-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    
    .wa-status-dot.connected {
        background: var(--wa-green);
    }
    
    .wa-status-dot.disconnected {
        background: #dc3545;
    }
    
    @media (max-width: 992px) {
        .wa-sidebar {
            width: 280px;
        }
    }
    
    @media (max-width: 768px) {
        .whatsapp-container {
            flex-direction: column;
            height: auto;
        }
        .wa-sidebar {
            width: 100%;
            height: 300px;
        }
        .wa-main {
            height: 400px;
        }
    }
</style>

<div class="whatsapp-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-chat-dots text-success me-2"></i>Quick Chat</h4>
            <p class="text-muted mb-0 small">Manage customer conversations directly from your CRM</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="wa-status-badge <?= $isConnected ? 'connected' : 'disconnected' ?>">
                <span class="wa-status-dot <?= $isConnected ? 'connected' : 'disconnected' ?>"></span>
                <?= $isConnected ? 'Connected' : 'Disconnected' ?>
            </span>
            <button class="btn btn-outline-secondary btn-sm" onclick="refreshChats()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>
    
    <?php if (!$isConnected): ?>
    <div class="alert alert-warning d-flex align-items-center mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>
            WhatsApp is not connected. <a href="?page=settings&tab=whatsapp">Go to Settings to connect</a>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="whatsapp-container">
        <div class="wa-sidebar">
            <div class="wa-sidebar-header">
                <div class="wa-search">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control" id="searchChats" placeholder="Search conversations...">
                </div>
            </div>
            <div class="wa-chat-list" id="chatList">
                <div class="text-center p-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                </div>
            </div>
        </div>
        
        <div class="wa-main" id="waMain">
            <div class="wa-empty" id="noChat">
                <i class="bi bi-chat-square-text"></i>
                <h5>Select a conversation</h5>
                <p class="mb-0">Choose a chat from the list to start messaging</p>
            </div>
            
            <div id="activeChat" style="display: none; flex-direction: column; height: 100%;">
                <div class="wa-main-header">
                    <div class="wa-avatar" id="chatAvatar">?</div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" id="chatName">Contact Name</div>
                        <small class="text-muted" id="chatPhone">+254...</small>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm" data-bs-toggle="dropdown">
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
                
                <div class="wa-messages" id="chatMessages"></div>
                
                <div class="wa-input-area">
                    <input type="text" class="wa-input" id="messageInput" placeholder="Type a message..." onkeypress="handleKeyPress(event)">
                    <button class="wa-send-btn" onclick="sendMessage()">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
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
                <div id="customerResults" style="max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<script>
let currentChat = null;
let chats = [];
let pollingInterval = null;
let lastMessageTimestamp = 0;

async function fetchAPI(url, options = {}) {
    const response = await fetch(url, {
        headers: { 'Content-Type': 'application/json', ...options.headers },
        ...options
    });
    return response.json();
}

async function refreshChats(silent = false) {
    try {
        const data = await fetchAPI('/api/whatsapp-chat.php?action=chats');
        if (data.success && data.chats) {
            const oldChats = chats;
            chats = data.chats;
            
            // Only re-render if first load or changes detected
            if (!silent || oldChats.length !== chats.length || hasChatsChanged(oldChats, chats)) {
                renderChatList(chats);
            }
        } else if (data.error && !silent) {
            document.getElementById('chatList').innerHTML = `<div class="text-center p-4 text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</div>`;
        }
    } catch (error) {
        if (!silent) {
            document.getElementById('chatList').innerHTML = '<div class="text-center p-4 text-danger"><i class="bi bi-exclamation-circle"></i> Connection error</div>';
        }
    }
}

function hasChatsChanged(oldChats, newChats) {
    if (oldChats.length !== newChats.length) return true;
    for (let i = 0; i < oldChats.length; i++) {
        const o = oldChats[i];
        const n = newChats[i];
        if (o.id !== n.id || o.unreadCount !== n.unreadCount || 
            o.lastMessageAt !== n.lastMessageAt || o.lastMessagePreview !== n.lastMessagePreview) {
            return true;
        }
    }
    return false;
}

function renderChatList(chatList) {
    const container = document.getElementById('chatList');
    if (!chatList.length) {
        container.innerHTML = '<div class="text-center p-4 text-muted"><i class="bi bi-chat-dots d-block mb-2" style="font-size:2rem"></i>No conversations yet</div>';
        return;
    }
    
    container.innerHTML = chatList.map(chat => `
        <div class="wa-chat-item ${currentChat?.id === chat.id ? 'active' : ''}" 
             onclick='openChat(${JSON.stringify(chat).replace(/'/g, "\\'")})'">
            <div class="wa-avatar">${getInitials(chat.name || chat.phone)}</div>
            <div class="wa-chat-info">
                <div class="wa-chat-name">${escapeHtml(chat.name || chat.phone)}</div>
                <div class="wa-chat-preview">${escapeHtml(chat.lastMessagePreview || '')}</div>
                ${chat.customer_name ? `<span class="wa-customer-tag"><i class="bi bi-person-fill"></i> ${escapeHtml(chat.customer_name)}</span>` : ''}
            </div>
            <div class="wa-chat-meta">
                <div class="wa-chat-time">${formatTime(chat.lastMessageAt)}</div>
                ${chat.unreadCount > 0 ? `<div class="wa-unread">${chat.unreadCount}</div>` : ''}
            </div>
        </div>
    `).join('');
}

async function openChat(chat) {
    currentChat = chat;
    document.getElementById('noChat').style.display = 'none';
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

async function loadMessages(chatId, isInitial = true) {
    const container = document.getElementById('chatMessages');
    
    if (isInitial) {
        container.innerHTML = '<div class="text-center p-4"><div class="spinner-border spinner-border-sm"></div></div>';
        lastMessageTimestamp = 0;
    }
    
    try {
        let url = `/api/whatsapp-chat.php?action=messages&chatId=${chatId}`;
        if (!isInitial && lastMessageTimestamp > 0) {
            url += `&since=${lastMessageTimestamp}`;
        }
        
        const data = await fetchAPI(url);
        if (data.success) {
            if (isInitial) {
                renderMessages(data.messages);
            } else if (data.messages && data.messages.length > 0) {
                // Append only new messages
                data.messages.forEach(msg => appendMessage(msg));
            }
            
            // Update last timestamp
            if (data.messages && data.messages.length > 0) {
                const lastMsg = data.messages[data.messages.length - 1];
                lastMessageTimestamp = lastMsg.timestamp || 0;
            }
        }
    } catch (error) {
        if (isInitial) {
            container.innerHTML = '<div class="text-center p-4 text-danger">Error loading messages</div>';
        }
    }
}

function renderMessages(messages) {
    const container = document.getElementById('chatMessages');
    container.innerHTML = messages.map(msg => `
        <div class="wa-message ${msg.fromMe ? 'outgoing' : 'incoming'}">
            <div class="wa-bubble">
                ${renderMediaContent(msg)}
                ${msg.body ? `<div class="wa-msg-text">${escapeHtml(msg.body)}</div>` : ''}
                <div class="wa-msg-time">
                    ${formatMessageTime(msg.timestamp)}
                    ${msg.fromMe ? '<i class="bi bi-check2-all wa-msg-status"></i>' : ''}
                </div>
            </div>
        </div>
    `).join('');
    
    container.scrollTop = container.scrollHeight;
}

function renderMediaContent(msg) {
    if (!msg.hasMedia && !msg.mediaData) {
        if (msg.type === 'image' || msg.type === 'video' || msg.type === 'audio' || msg.type === 'document' || msg.type === 'sticker') {
            return `<div class="wa-media-placeholder"><i class="bi bi-${getMediaIcon(msg.type)}"></i> ${msg.type}</div>`;
        }
        return '';
    }
    
    if (msg.mediaData && msg.mimetype) {
        const dataUrl = `data:${msg.mimetype};base64,${msg.mediaData}`;
        
        if (msg.mimetype.startsWith('image/')) {
            return `<div class="wa-media"><img src="${dataUrl}" class="wa-media-img" onclick="window.open(this.src, '_blank')" alt="Image"></div>`;
        } else if (msg.mimetype.startsWith('video/')) {
            return `<div class="wa-media"><video src="${dataUrl}" class="wa-media-video" controls></video></div>`;
        } else if (msg.mimetype.startsWith('audio/')) {
            return `<div class="wa-media"><audio src="${dataUrl}" controls class="wa-media-audio"></audio></div>`;
        } else {
            const filename = msg.filename || 'Document';
            return `<div class="wa-media-file"><a href="${dataUrl}" download="${filename}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark"></i> ${escapeHtml(filename)}</a></div>`;
        }
    }
    
    return '';
}

function getMediaIcon(type) {
    const icons = { image: 'image', video: 'camera-video', audio: 'mic', document: 'file-earmark', sticker: 'emoji-smile' };
    return icons[type] || 'file-earmark';
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
            alert('Failed to send: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

function appendMessage(msg) {
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = `wa-message ${msg.fromMe ? 'outgoing' : 'incoming'}`;
    div.innerHTML = `
        <div class="wa-bubble">
            ${renderMediaContent(msg)}
            ${msg.body ? `<div class="wa-msg-text">${escapeHtml(msg.body)}</div>` : ''}
            <div class="wa-msg-time">
                ${formatMessageTime(msg.timestamp)}
                ${msg.fromMe ? '<i class="bi bi-check2 wa-msg-status"></i>' : ''}
            </div>
        </div>
    `;
    container.appendChild(div);
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
            await loadMessages(currentChat.id, false);
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
                <div class="p-2 border rounded mb-2 cursor-pointer hover-bg-light" style="cursor:pointer" onclick="selectCustomer(${c.id})">
                    <div class="fw-bold">${escapeHtml(c.name)}</div>
                    <small class="text-muted">${escapeHtml(c.phone)} | ${escapeHtml(c.account_number)}</small>
                </div>
            `).join('') || '<div class="text-muted">No customers found</div>';
        }
    } catch (error) {
        console.error('Error:', error);
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
            alert('Failed: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

function createTicket() {
    if (currentChat && currentChat.customer_id) {
        window.location.href = `?page=tickets&action=create&customer_id=${currentChat.customer_id}`;
    } else {
        alert('Please link this conversation to a customer first.');
    }
}

function viewCustomer() {
    if (currentChat && currentChat.customer_id) {
        window.location.href = `?page=customers&action=view&id=${currentChat.customer_id}`;
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
setInterval(() => refreshChats(true), 30000);
</script>
