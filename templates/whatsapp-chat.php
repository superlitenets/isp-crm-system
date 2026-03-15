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
        --wa-green: #00a884;
        --wa-green-dark: #008069;
        --wa-teal: #075e54;
        --wa-teal-dark: #054640;
        --wa-light-green: #d9fdd3;
        --wa-bg: #efeae2;
        --wa-bg-pattern: #ddd6cb;
        --wa-sidebar-bg: #fff;
        --wa-sidebar-header: #f0f2f5;
        --wa-hover: #f5f6f6;
        --wa-active: #2a3942;
        --wa-border: #e9edef;
        --wa-text: #111b21;
        --wa-text-secondary: #667781;
        --wa-input-bg: #f0f2f5;
        --wa-bubble-out: #d9fdd3;
        --wa-bubble-in: #ffffff;
        --wa-panel-header: #f0f2f5;
    }

    .whatsapp-page {
        height: calc(100vh - 56px);
        display: flex;
        flex-direction: column;
        margin: -1.5rem;
        background: #111b21;
    }

    .whatsapp-container {
        flex: 1;
        display: flex;
        overflow: hidden;
        max-height: 100%;
    }

    .wa-sidebar {
        width: 420px;
        min-width: 340px;
        max-width: 420px;
        display: flex;
        flex-direction: column;
        background: var(--wa-sidebar-bg);
        border-right: 1px solid var(--wa-border);
    }

    .wa-sidebar-header {
        background: var(--wa-sidebar-header);
        padding: 10px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 59px;
        flex-shrink: 0;
    }

    .wa-sidebar-header .wa-user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #dfe5e7;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #cfd4d6;
        font-size: 1.3rem;
    }

    .wa-sidebar-actions {
        display: flex;
        gap: 8px;
    }

    .wa-sidebar-actions button {
        background: none;
        border: none;
        color: #54656f;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.15rem;
    }

    .wa-sidebar-actions button:hover {
        background: rgba(0,0,0,0.05);
    }

    .wa-search-container {
        padding: 8px 12px;
        background: var(--wa-sidebar-bg);
        border-bottom: 1px solid var(--wa-border);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .wa-search-box {
        flex: 1;
        display: flex;
        align-items: center;
        background: var(--wa-input-bg);
        border-radius: 8px;
        padding: 0 12px;
        height: 35px;
    }

    .wa-search-box i {
        color: var(--wa-text-secondary);
        font-size: 0.85rem;
        margin-right: 12px;
        transition: all 0.15s;
    }

    .wa-search-box input {
        flex: 1;
        border: none;
        background: none;
        outline: none;
        font-size: 0.9rem;
        color: var(--wa-text);
        height: 100%;
    }

    .wa-search-box input::placeholder {
        color: var(--wa-text-secondary);
    }

    .wa-filter-btn {
        background: none;
        border: none;
        color: var(--wa-text-secondary);
        cursor: pointer;
        padding: 6px;
        border-radius: 50%;
        font-size: 1.1rem;
    }

    .wa-filter-btn:hover {
        background: rgba(0,0,0,0.05);
    }

    .wa-chat-list {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .wa-chat-list::-webkit-scrollbar {
        width: 6px;
    }

    .wa-chat-list::-webkit-scrollbar-thumb {
        background: rgba(0,0,0,0.2);
        border-radius: 3px;
    }

    .wa-chat-item {
        display: flex;
        align-items: center;
        padding: 0 15px;
        cursor: pointer;
        transition: background 0.1s;
        height: 72px;
        position: relative;
    }

    .wa-chat-item::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: 15px;
        left: 75px;
        height: 1px;
        background: var(--wa-border);
    }

    .wa-chat-item:hover {
        background: var(--wa-hover);
    }

    .wa-chat-item.active {
        background: var(--wa-hover);
    }

    .wa-avatar {
        width: 49px;
        height: 49px;
        border-radius: 50%;
        background: #dfe5e7;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        font-size: 1.1rem;
        flex-shrink: 0;
        margin-right: 13px;
        overflow: hidden;
        position: relative;
    }

    .wa-avatar.has-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .wa-avatar-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #00a884 0%, #25d366 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 500;
        font-size: 1.2rem;
    }

    .wa-chat-content {
        flex: 1;
        min-width: 0;
        padding: 13px 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
        height: 100%;
    }

    .wa-chat-top {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin-bottom: 3px;
    }

    .wa-chat-name {
        font-size: 1rem;
        color: var(--wa-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1;
        margin-right: 6px;
    }

    .wa-chat-time {
        font-size: 0.75rem;
        color: var(--wa-text-secondary);
        flex-shrink: 0;
    }

    .wa-chat-time.unread {
        color: var(--wa-green);
    }

    .wa-chat-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .wa-chat-preview {
        font-size: 0.875rem;
        color: var(--wa-text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1;
        margin-right: 8px;
        line-height: 1.3;
    }

    .wa-chat-preview .preview-icon {
        color: var(--wa-text-secondary);
        margin-right: 2px;
    }

    .wa-unread-badge {
        background: var(--wa-green);
        color: white;
        border-radius: 50%;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 500;
        padding: 0 5px;
        flex-shrink: 0;
    }

    .wa-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        position: relative;
    }

    .wa-chat-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--wa-bg);
        background-image: url("data:image/svg+xml,%3Csvg width='400' height='400' viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23c8b9ad' fill-opacity='0.15'%3E%3Cpath d='M10 10h5v5h-5zM30 30h5v5h-5zM50 10h5v5h-5zM70 30h5v5h-5z'/%3E%3C/g%3E%3C/svg%3E");
        opacity: 0.06;
        z-index: 0;
    }

    .wa-main > * {
        position: relative;
        z-index: 1;
    }

    .wa-main-header {
        background: var(--wa-panel-header);
        padding: 10px 16px;
        display: flex;
        align-items: center;
        gap: 13px;
        height: 59px;
        flex-shrink: 0;
        border-bottom: 1px solid var(--wa-border);
    }

    .wa-main-header .wa-avatar {
        width: 40px;
        height: 40px;
        margin-right: 0;
        cursor: pointer;
    }

    .wa-main-header .wa-avatar .wa-avatar-placeholder {
        font-size: 1rem;
    }

    .wa-header-info {
        flex: 1;
        min-width: 0;
        cursor: pointer;
    }

    .wa-header-name {
        font-size: 1rem;
        color: var(--wa-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .wa-header-status {
        font-size: 0.8rem;
        color: var(--wa-text-secondary);
    }

    .wa-header-actions {
        display: flex;
        gap: 4px;
    }

    .wa-header-actions button {
        background: none;
        border: none;
        color: #54656f;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.2rem;
    }

    .wa-header-actions button:hover {
        background: rgba(0,0,0,0.05);
    }

    .wa-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px 60px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        background: var(--wa-bg);
        background-image: url("data:image/svg+xml,%3Csvg width='200' height='200' viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23c2b9ae' fill-opacity='0.08'%3E%3Ccircle cx='3' cy='3' r='1'/%3E%3Ccircle cx='23' cy='13' r='1'/%3E%3Ccircle cx='43' cy='3' r='1'/%3E%3C/g%3E%3C/svg%3E");
    }

    .wa-messages::-webkit-scrollbar {
        width: 6px;
    }

    .wa-messages::-webkit-scrollbar-thumb {
        background: rgba(0,0,0,0.2);
        border-radius: 3px;
    }

    .wa-date-divider {
        align-self: center;
        background: #e1ddd5;
        color: #54656f;
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 0.775rem;
        margin: 12px 0;
        box-shadow: 0 1px 0.5px rgba(11,20,26,0.13);
        text-transform: uppercase;
        letter-spacing: 0.01em;
    }

    .wa-message {
        max-width: 65%;
        clear: both;
        margin-bottom: 1px;
    }

    .wa-message.incoming {
        align-self: flex-start;
    }

    .wa-message.outgoing {
        align-self: flex-end;
    }

    .wa-bubble {
        padding: 6px 7px 8px 9px;
        border-radius: 7.5px;
        box-shadow: 0 1px 0.5px rgba(11,20,26,0.13);
        position: relative;
        min-width: 80px;
    }

    .wa-message.incoming .wa-bubble {
        background: var(--wa-bubble-in);
        border-top-left-radius: 0;
    }

    .wa-message.outgoing .wa-bubble {
        background: var(--wa-bubble-out);
        border-top-right-radius: 0;
    }

    .wa-message.incoming .wa-bubble::before {
        content: '';
        position: absolute;
        top: 0;
        left: -8px;
        width: 0;
        height: 0;
        border-top: 0px solid transparent;
        border-bottom: 13px solid transparent;
        border-right: 8px solid var(--wa-bubble-in);
    }

    .wa-message.outgoing .wa-bubble::before {
        content: '';
        position: absolute;
        top: 0;
        right: -8px;
        width: 0;
        height: 0;
        border-top: 0px solid transparent;
        border-bottom: 13px solid transparent;
        border-left: 8px solid var(--wa-bubble-out);
    }

    .wa-msg-text {
        word-break: break-word;
        white-space: pre-wrap;
        line-height: 1.35;
        font-size: 0.925rem;
        color: var(--wa-text);
    }

    .wa-msg-text a {
        color: #027eb5;
        text-decoration: none;
    }

    .wa-msg-text a:hover {
        text-decoration: underline;
    }

    .wa-msg-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        margin-top: 1px;
        float: right;
        margin-left: 10px;
        position: relative;
        top: 5px;
    }

    .wa-msg-time {
        font-size: 0.6875rem;
        color: var(--wa-text-secondary);
        line-height: 1;
    }

    .wa-msg-status {
        font-size: 1rem;
        line-height: 1;
    }

    .wa-msg-status.sent { color: #667781; }
    .wa-msg-status.delivered { color: #667781; }
    .wa-msg-status.read { color: #53bdeb; }

    .wa-media {
        margin-bottom: 4px;
        position: relative;
        border-radius: 6px;
        overflow: hidden;
    }

    .wa-media-img {
        max-width: 330px;
        max-height: 330px;
        width: 100%;
        border-radius: 6px;
        cursor: pointer;
        display: block;
        object-fit: cover;
    }

    .wa-media-video {
        max-width: 330px;
        max-height: 330px;
        width: 100%;
        border-radius: 6px;
    }

    .wa-media-audio {
        width: 280px;
        max-width: 100%;
        height: 36px;
    }

    .wa-media-doc {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0,0,0,0.04);
        padding: 10px 12px;
        border-radius: 8px;
        margin-bottom: 4px;
        cursor: pointer;
        min-width: 240px;
    }

    .wa-media-doc:hover {
        background: rgba(0,0,0,0.06);
    }

    .wa-media-doc-icon {
        width: 40px;
        height: 46px;
        background: #e8453c;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .wa-media-doc-icon.pdf { background: #e8453c; }
    .wa-media-doc-icon.doc { background: #4285f4; }
    .wa-media-doc-icon.xls { background: #0f9d58; }
    .wa-media-doc-icon.zip { background: #f4b400; }
    .wa-media-doc-icon.other { background: #667781; }

    .wa-media-doc-info {
        flex: 1;
        min-width: 0;
    }

    .wa-media-doc-name {
        font-size: 0.875rem;
        color: var(--wa-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .wa-media-doc-meta {
        font-size: 0.75rem;
        color: var(--wa-text-secondary);
        margin-top: 2px;
    }

    .wa-media-placeholder {
        background: rgba(0,0,0,0.04);
        padding: 25px;
        border-radius: 8px;
        text-align: center;
        color: var(--wa-text-secondary);
        font-size: 0.85rem;
        margin-bottom: 4px;
    }

    .wa-media-placeholder i {
        font-size: 2rem;
        display: block;
        margin-bottom: 6px;
        opacity: 0.5;
    }

    .wa-input-area {
        background: var(--wa-panel-header);
        padding: 5px 10px;
        display: flex;
        align-items: flex-end;
        gap: 8px;
        min-height: 62px;
        flex-shrink: 0;
    }

    .wa-input-actions {
        display: flex;
        gap: 2px;
        padding-bottom: 10px;
    }

    .wa-input-actions button {
        background: none;
        border: none;
        color: #54656f;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.25rem;
        position: relative;
    }

    .wa-input-actions button:hover {
        background: rgba(0,0,0,0.05);
    }

    .wa-input-wrap {
        flex: 1;
        display: flex;
        align-items: flex-end;
        background: #fff;
        border-radius: 8px;
        padding: 0 12px;
        min-height: 42px;
        max-height: 120px;
        border: none;
    }

    .wa-input {
        flex: 1;
        border: none;
        background: none;
        outline: none;
        font-size: 0.9375rem;
        color: var(--wa-text);
        resize: none;
        line-height: 1.35;
        padding: 10px 0;
        max-height: 100px;
        overflow-y: auto;
        font-family: inherit;
    }

    .wa-input::placeholder {
        color: var(--wa-text-secondary);
    }

    .wa-send-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: none;
        color: #54656f;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.3rem;
        flex-shrink: 0;
        margin-bottom: 5px;
        transition: color 0.15s;
    }

    .wa-send-btn:hover {
        color: var(--wa-green);
    }

    .wa-send-btn.has-text {
        color: var(--wa-green);
    }

    .wa-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        background: var(--wa-panel-header);
        text-align: center;
        padding: 40px;
    }

    .wa-empty-icon {
        width: 260px;
        height: 260px;
        margin-bottom: 28px;
        opacity: 0.3;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .wa-empty-icon i {
        font-size: 6rem;
        color: #aebac1;
    }

    .wa-empty-state h3 {
        font-weight: 300;
        font-size: 2rem;
        color: #41525d;
        margin-bottom: 12px;
    }

    .wa-empty-state p {
        color: #667781;
        font-size: 0.9rem;
        max-width: 500px;
        line-height: 1.5;
    }

    .wa-status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        margin-top: 16px;
    }

    .wa-status-indicator.connected {
        background: rgba(0, 168, 132, 0.08);
        color: var(--wa-green);
    }

    .wa-status-indicator.disconnected {
        background: rgba(234, 67, 53, 0.08);
        color: #ea4335;
    }

    .wa-status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
    }

    .wa-status-dot.connected {
        background: var(--wa-green);
        animation: pulse-green 2s infinite;
    }

    .wa-status-dot.disconnected {
        background: #ea4335;
    }

    @keyframes pulse-green {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }

    .wa-image-viewer {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.92);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }

    .wa-image-viewer.active {
        display: flex;
    }

    .wa-image-viewer-header {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        padding: 12px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(rgba(0,0,0,0.6), transparent);
        z-index: 2;
    }

    .wa-image-viewer-info {
        color: #fff;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .wa-image-viewer-info .name {
        font-size: 0.9rem;
        font-weight: 500;
    }

    .wa-image-viewer-info .date {
        font-size: 0.8rem;
        opacity: 0.7;
    }

    .wa-image-viewer-actions {
        display: flex;
        gap: 8px;
    }

    .wa-image-viewer-actions button {
        background: none;
        border: none;
        color: #fff;
        font-size: 1.3rem;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .wa-image-viewer-actions button:hover {
        background: rgba(255,255,255,0.1);
    }

    .wa-image-viewer img {
        max-width: 90vw;
        max-height: 85vh;
        object-fit: contain;
        border-radius: 2px;
        z-index: 1;
    }

    .wa-attach-menu {
        display: none;
        position: absolute;
        bottom: 65px;
        left: 10px;
        background: #fff;
        border-radius: 12px;
        padding: 8px 0;
        box-shadow: 0 2px 16px rgba(0,0,0,0.15);
        z-index: 100;
        min-width: 180px;
    }

    .wa-attach-menu.show {
        display: block;
        animation: fadeInUp 0.15s ease-out;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .wa-attach-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 20px;
        cursor: pointer;
        color: var(--wa-text);
        font-size: 0.9rem;
        transition: background 0.1s;
    }

    .wa-attach-item:hover {
        background: var(--wa-hover);
    }

    .wa-attach-item i {
        font-size: 1.2rem;
        width: 24px;
        text-align: center;
    }

    .wa-attach-item .icon-photo { color: #007bfc; }
    .wa-attach-item .icon-doc { color: #5157ae; }
    .wa-attach-item .icon-camera { color: #f5386d; }

    .wa-typing {
        font-size: 0.8rem;
        color: var(--wa-green);
        font-style: italic;
    }

    .wa-emoji-text { font-size: 2.5rem; line-height: 1.2; }

    .wa-alert-bar {
        background: #fef3cd;
        color: #856404;
        padding: 8px 16px;
        text-align: center;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .wa-alert-bar a {
        color: #533f03;
        font-weight: 500;
    }

    .wa-contact-panel {
        width: 0;
        overflow: hidden;
        transition: width 0.2s ease;
        background: var(--wa-sidebar-bg);
        border-left: 1px solid var(--wa-border);
        flex-shrink: 0;
    }

    .wa-contact-panel.open {
        width: 340px;
    }

    .wa-contact-panel-header {
        background: var(--wa-panel-header);
        height: 59px;
        display: flex;
        align-items: center;
        padding: 0 20px;
        gap: 20px;
    }

    .wa-contact-panel-header button {
        background: none;
        border: none;
        color: var(--wa-text);
        font-size: 1.2rem;
        cursor: pointer;
        padding: 4px;
    }

    .wa-contact-panel-header span {
        font-size: 1rem;
        font-weight: 500;
        color: var(--wa-text);
    }

    .wa-contact-detail {
        padding: 28px 20px;
        text-align: center;
    }

    .wa-contact-detail .wa-avatar-large {
        width: 200px;
        height: 200px;
        border-radius: 50%;
        margin: 0 auto 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
        color: white;
        background: linear-gradient(135deg, #00a884 0%, #25d366 100%);
    }

    .wa-contact-detail .contact-name {
        font-size: 1.4rem;
        color: var(--wa-text);
        margin-bottom: 4px;
    }

    .wa-contact-detail .contact-phone {
        color: var(--wa-text-secondary);
        font-size: 0.9rem;
        margin-bottom: 16px;
    }

    .wa-contact-actions {
        display: flex;
        justify-content: center;
        gap: 20px;
        padding: 16px 0;
        border-bottom: 1px solid var(--wa-border);
    }

    .wa-contact-actions .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        color: var(--wa-green);
        text-decoration: none;
        font-size: 0.75rem;
        cursor: pointer;
        border: none;
        background: none;
    }

    .wa-contact-actions .action-btn i {
        font-size: 1.3rem;
    }

    @media (max-width: 1200px) {
        .wa-messages { padding: 15px 30px; }
        .wa-sidebar { width: 340px; min-width: 300px; }
    }

    @media (max-width: 992px) {
        .wa-sidebar { width: 300px; min-width: 280px; }
        .wa-messages { padding: 15px 20px; }
        .wa-contact-panel.open { width: 0; }
    }

    @media (max-width: 768px) {
        .whatsapp-container { flex-direction: column; }
        .wa-sidebar { width: 100%; max-width: 100%; height: 280px; }
        .wa-main { flex: 1; }
        .wa-messages { padding: 10px 12px; }
        .wa-message { max-width: 85%; }
    }

    input[type="file"].wa-file-input {
        display: none;
    }
</style>

<div class="whatsapp-page">
    <?php if (!$isConnected): ?>
    <div class="wa-alert-bar">
        <i class="bi bi-exclamation-triangle-fill"></i>
        WhatsApp is not connected.
        <a href="?page=settings&tab=whatsapp">Connect now</a>
    </div>
    <?php endif; ?>

    <div class="whatsapp-container">
        <div class="wa-sidebar">
            <div class="wa-sidebar-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="wa-user-avatar"><i class="bi bi-person-fill"></i></div>
                    <span class="wa-status-indicator <?= $isConnected ? 'connected' : 'disconnected' ?>">
                        <span class="wa-status-dot <?= $isConnected ? 'connected' : 'disconnected' ?>"></span>
                        <?= $isConnected ? 'Online' : 'Offline' ?>
                    </span>
                </div>
                <div class="wa-sidebar-actions">
                    <button onclick="refreshChats()" title="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
                    <button onclick="startNewChat()" title="New chat"><i class="bi bi-chat-left-text"></i></button>
                </div>
            </div>
            <div class="wa-search-container">
                <div class="wa-search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchChats" placeholder="Search or start new chat">
                </div>
            </div>
            <div class="wa-chat-list" id="chatList">
                <div class="d-flex align-items-center justify-content-center p-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" style="color: var(--wa-green)"></div>
                    <span>Loading chats...</span>
                </div>
            </div>
        </div>

        <div class="wa-main" id="waMain">
            <div class="wa-empty-state" id="noChat">
                <div class="wa-empty-icon">
                    <i class="bi bi-whatsapp"></i>
                </div>
                <h3>Quick Chat</h3>
                <p>Send and receive messages from your customers. Select a conversation from the left to get started.</p>
                <span class="wa-status-indicator <?= $isConnected ? 'connected' : 'disconnected' ?>" style="margin-top: 24px;">
                    <span class="wa-status-dot <?= $isConnected ? 'connected' : 'disconnected' ?>"></span>
                    <?= $isConnected ? 'WhatsApp Connected' : 'WhatsApp Disconnected' ?>
                </span>
            </div>

            <div id="activeChat" style="display: none; flex-direction: column; height: 100%;">
                <div class="wa-main-header">
                    <div class="wa-avatar" onclick="toggleContactPanel()">
                        <div class="wa-avatar-placeholder" id="chatAvatar">?</div>
                    </div>
                    <div class="wa-header-info" onclick="toggleContactPanel()">
                        <div class="wa-header-name" id="chatName">Contact Name</div>
                        <div class="wa-header-status" id="chatStatus">click here for contact info</div>
                    </div>
                    <div class="wa-header-actions">
                        <button onclick="refreshCurrentChat()" title="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
                        <div class="dropdown">
                            <button data-bs-toggle="dropdown" title="Menu"><i class="bi bi-three-dots-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="toggleContactPanel()"><i class="bi bi-person me-2"></i>Contact info</a></li>
                                <li><a class="dropdown-item" href="#" onclick="linkToCustomer()"><i class="bi bi-person-plus me-2"></i>Link to Customer</a></li>
                                <li><a class="dropdown-item" href="#" onclick="createTicket()"><i class="bi bi-ticket-perforated me-2"></i>Create Ticket</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" onclick="viewCustomer()"><i class="bi bi-eye me-2"></i>View Customer</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="wa-messages" id="chatMessages"></div>

                <div class="wa-input-area" style="position: relative;">
                    <div class="wa-attach-menu" id="attachMenu">
                        <label class="wa-attach-item" for="fileImage">
                            <i class="bi bi-image icon-photo"></i> Photos & Videos
                        </label>
                        <label class="wa-attach-item" for="fileDoc">
                            <i class="bi bi-file-earmark icon-doc"></i> Document
                        </label>
                    </div>
                    <input type="file" class="wa-file-input" id="fileImage" accept="image/*,video/*" onchange="handleFileSelect(this, 'image')">
                    <input type="file" class="wa-file-input" id="fileDoc" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip" onchange="handleFileSelect(this, 'document')">

                    <div class="wa-input-actions">
                        <button onclick="toggleAttachMenu()" title="Attach"><i class="bi bi-plus-lg"></i></button>
                    </div>
                    <div class="wa-input-wrap">
                        <textarea class="wa-input" id="messageInput" placeholder="Type a message" rows="1" onkeydown="handleKeyDown(event)" oninput="autoResizeInput(this)"></textarea>
                    </div>
                    <button class="wa-send-btn" id="sendBtn" onclick="sendMessage()" title="Send">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="wa-contact-panel" id="contactPanel">
            <div class="wa-contact-panel-header">
                <button onclick="toggleContactPanel()"><i class="bi bi-x-lg"></i></button>
                <span>Contact info</span>
            </div>
            <div id="contactPanelContent"></div>
        </div>
    </div>
</div>

<div class="wa-image-viewer" id="imageViewer" onclick="closeImageViewer(event)">
    <div class="wa-image-viewer-header">
        <div class="wa-image-viewer-info">
            <span class="name" id="viewerName"></span>
            <span class="date" id="viewerDate"></span>
        </div>
        <div class="wa-image-viewer-actions">
            <button onclick="downloadViewerImage()" title="Download"><i class="bi bi-download"></i></button>
            <button onclick="closeImageViewer()" title="Close"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
    <img id="viewerImage" src="" alt="Image">
</div>

<div class="modal fade" id="linkCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--wa-panel-header);">
                <h6 class="modal-title"><i class="bi bi-person-plus me-2"></i>Link to Customer</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="customerSearch" placeholder="Search by name, phone, or account number...">
                </div>
                <div id="customerResults" style="max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--wa-panel-header);">
                <h6 class="modal-title"><i class="bi bi-chat-left-text me-2"></i>New Chat</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-muted">Phone Number (with country code)</label>
                    <input type="text" class="form-control" id="newChatPhone" placeholder="e.g. 254712345678">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted">Message</label>
                    <textarea class="form-control" id="newChatMessage" rows="3" placeholder="Type your message..."></textarea>
                </div>
                <button class="btn w-100" style="background: var(--wa-green); color: #fff;" onclick="sendNewChat()">
                    <i class="bi bi-send me-2"></i>Send Message
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentChat = null;
let chats = [];
let pollingInterval = null;
let chatPollingInterval = null;
let lastMessageTimestamp = 0;
let viewerImageSrc = '';
let contactPanelOpen = false;

async function fetchAPI(url, options = {}) {
    const controller = new AbortController();
    const timeoutMs = options.timeout || 15000;
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const response = await fetch(url, {
            headers: { 'Content-Type': 'application/json', ...options.headers },
            signal: controller.signal,
            ...options
        });
        clearTimeout(timer);
        return response.json();
    } catch (e) {
        clearTimeout(timer);
        if (e.name === 'AbortError') throw new Error('Request timed out');
        throw e;
    }
}

async function refreshChats(silent = false) {
    try {
        const data = await fetchAPI('/api/whatsapp-chat.php?action=chats');
        if (data.success && data.chats) {
            const oldChats = chats;
            chats = data.chats;
            if (!silent || oldChats.length !== chats.length || hasChatsChanged(oldChats, chats)) {
                renderChatList(chats);
            }
        } else if (data.error && !silent) {
            document.getElementById('chatList').innerHTML = `
                <div class="d-flex flex-column align-items-center p-4 text-muted">
                    <i class="bi bi-exclamation-circle mb-2" style="font-size:1.5rem; opacity:0.5"></i>
                    <span style="font-size:0.85rem">${data.error}</span>
                </div>`;
        }
    } catch (error) {
        if (!silent) {
            document.getElementById('chatList').innerHTML = `
                <div class="d-flex flex-column align-items-center p-4 text-muted">
                    <i class="bi bi-wifi-off mb-2" style="font-size:1.5rem; opacity:0.5"></i>
                    <span style="font-size:0.85rem">Connection error</span>
                </div>`;
        }
    }
}

function hasChatsChanged(oldChats, newChats) {
    if (oldChats.length !== newChats.length) return true;
    for (let i = 0; i < Math.min(oldChats.length, 10); i++) {
        if (oldChats[i].id !== newChats[i].id || oldChats[i].unreadCount !== newChats[i].unreadCount || oldChats[i].lastMessageAt !== newChats[i].lastMessageAt) return true;
    }
    return false;
}

function renderChatList(chatList) {
    const container = document.getElementById('chatList');
    if (!chatList.length) {
        container.innerHTML = `
            <div class="d-flex flex-column align-items-center p-4 text-muted" style="margin-top:60px">
                <i class="bi bi-chat-square-text mb-3" style="font-size:3rem; opacity:0.2"></i>
                <span style="font-size:0.9rem">No conversations yet</span>
                <span style="font-size:0.8rem; margin-top:4px; opacity:0.6">Messages will appear here</span>
            </div>`;
        return;
    }

    container.innerHTML = chatList.map(chat => {
        const isActive = currentChat?.id === chat.id;
        const hasUnread = chat.unreadCount > 0;
        const preview = getPreviewText(chat);
        return `
        <div class="wa-chat-item ${isActive ? 'active' : ''}" onclick='openChat(${JSON.stringify(chat).replace(/'/g, "\\'")})'>
            <div class="wa-avatar">
                <div class="wa-avatar-placeholder">${getInitials(chat.name || chat.phone)}</div>
            </div>
            <div class="wa-chat-content">
                <div class="wa-chat-top">
                    <span class="wa-chat-name">${escapeHtml(chat.customer_name || chat.name || chat.phone)}</span>
                    <span class="wa-chat-time ${hasUnread ? 'unread' : ''}">${formatTime(chat.lastMessageAt)}</span>
                </div>
                <div class="wa-chat-bottom">
                    <span class="wa-chat-preview">${preview}</span>
                    ${hasUnread ? `<span class="wa-unread-badge">${chat.unreadCount}</span>` : ''}
                </div>
            </div>
        </div>`;
    }).join('');
}

function getPreviewText(chat) {
    let text = chat.lastMessagePreview || '';
    if (!text) return '';
    if (text.length > 50) text = text.substring(0, 50) + '...';
    return escapeHtml(text);
}

async function openChat(chat) {
    currentChat = chat;
    document.getElementById('noChat').style.display = 'none';
    document.getElementById('activeChat').style.display = 'flex';

    document.getElementById('chatAvatar').textContent = getInitials(chat.name || chat.phone);
    document.getElementById('chatName').textContent = chat.customer_name || chat.name || chat.phone;
    document.getElementById('chatStatus').textContent = chat.customer_name ? chat.phone : 'click here for contact info';

    renderChatList(chats);
    await loadMessages(chat.id);

    if (chat.unreadCount > 0) {
        fetchAPI('/api/whatsapp-chat.php?action=mark-read', {
            method: 'POST',
            body: JSON.stringify({ chatId: chat.id })
        });
        chat.unreadCount = 0;
        renderChatList(chats);
    }

    startPolling();
    document.getElementById('messageInput').focus();
}

async function loadMessages(chatId, isInitial = true) {
    const container = document.getElementById('chatMessages');

    if (isInitial) {
        container.innerHTML = '<div class="d-flex align-items-center justify-content-center p-4"><div class="spinner-border spinner-border-sm" style="color: var(--wa-green)"></div></div>';
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
                renderMessages(data.messages || []);
            } else if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => appendMessage(msg));
            }

            if (data.messages && data.messages.length > 0) {
                const lastMsg = data.messages[data.messages.length - 1];
                lastMessageTimestamp = lastMsg.timestamp || 0;
            }
        }
    } catch (error) {
        if (isInitial) {
            container.innerHTML = '<div class="d-flex flex-column align-items-center p-4 text-muted"><i class="bi bi-exclamation-circle mb-2"></i>Error loading messages</div>';
        }
    }
}

function renderMessages(messages) {
    const container = document.getElementById('chatMessages');
    if (!messages.length) {
        container.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                <i class="bi bi-shield-lock mb-2" style="font-size:3rem; opacity:0.15"></i>
                <span style="font-size:0.85rem; opacity:0.6">No messages yet</span>
            </div>`;
        return;
    }

    let html = '';
    let lastDate = '';

    messages.forEach(msg => {
        const msgDate = formatDateDivider(msg.timestamp);
        if (msgDate !== lastDate) {
            html += `<div class="wa-date-divider">${msgDate}</div>`;
            lastDate = msgDate;
        }
        html += buildMessageHtml(msg);
    });

    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
}

function buildMessageHtml(msg) {
    const isEmoji = isOnlyEmoji(msg.body);
    const textClass = isEmoji ? 'wa-msg-text wa-emoji-text' : 'wa-msg-text';
    const bodyHtml = msg.body ? linkify(escapeHtml(msg.body)) : '';

    return `
    <div class="wa-message ${msg.fromMe ? 'outgoing' : 'incoming'}">
        <div class="wa-bubble">
            ${renderMediaContent(msg)}
            ${bodyHtml ? `<div class="${textClass}">${bodyHtml}<span class="wa-msg-footer">
                <span class="wa-msg-time">${formatMessageTime(msg.timestamp)}</span>
                ${msg.fromMe ? `<i class="bi bi-check2-all wa-msg-status read"></i>` : ''}
            </span></div>` : `<div class="wa-msg-footer" style="float:none;justify-content:flex-end;margin-top:2px;">
                <span class="wa-msg-time">${formatMessageTime(msg.timestamp)}</span>
                ${msg.fromMe ? `<i class="bi bi-check2-all wa-msg-status read"></i>` : ''}
            </div>`}
        </div>
    </div>`;
}

function renderMediaContent(msg) {
    if (!msg.hasMedia && !msg.mediaData) {
        if (['image', 'video', 'audio', 'document', 'sticker'].includes(msg.type)) {
            const icons = { image: 'image', video: 'camera-video-fill', audio: 'mic-fill', document: 'file-earmark-fill', sticker: 'emoji-smile' };
            return `<div class="wa-media-placeholder"><i class="bi bi-${icons[msg.type] || 'file-earmark'}"></i>${msg.type === 'image' ? 'Photo' : msg.type === 'video' ? 'Video' : msg.type === 'audio' ? 'Voice message' : msg.type === 'sticker' ? 'Sticker' : 'Document'}</div>`;
        }
        return '';
    }

    if (msg.mediaData && msg.mimetype) {
        const dataUrl = `data:${msg.mimetype};base64,${msg.mediaData}`;

        if (msg.mimetype.startsWith('image/')) {
            return `<div class="wa-media"><img src="${dataUrl}" class="wa-media-img" onclick="openImageViewer(this.src, '${escapeHtml(currentChat?.name || '')}')" alt="Photo" loading="lazy"></div>`;
        } else if (msg.mimetype.startsWith('video/')) {
            return `<div class="wa-media"><video src="${dataUrl}" class="wa-media-video" controls preload="metadata"></video></div>`;
        } else if (msg.mimetype.startsWith('audio/')) {
            return `<div class="wa-media"><audio src="${dataUrl}" controls class="wa-media-audio"></audio></div>`;
        } else {
            const filename = msg.filename || 'Document';
            const ext = filename.split('.').pop().toLowerCase();
            const iconClass = ext === 'pdf' ? 'pdf' : ['doc','docx'].includes(ext) ? 'doc' : ['xls','xlsx','csv'].includes(ext) ? 'xls' : ['zip','rar','7z'].includes(ext) ? 'zip' : 'other';
            return `
            <a href="${dataUrl}" download="${escapeHtml(filename)}" class="wa-media-doc" style="text-decoration:none">
                <div class="wa-media-doc-icon ${iconClass}"><i class="bi bi-file-earmark-fill"></i></div>
                <div class="wa-media-doc-info">
                    <div class="wa-media-doc-name">${escapeHtml(filename)}</div>
                    <div class="wa-media-doc-meta">${ext.toUpperCase()}</div>
                </div>
                <i class="bi bi-download" style="color:var(--wa-text-secondary)"></i>
            </a>`;
        }
    }

    return '';
}

function appendMessage(msg) {
    const container = document.getElementById('chatMessages');
    const emptyState = container.querySelector('.h-100');
    if (emptyState) container.innerHTML = '';

    const div = document.createElement('div');
    div.innerHTML = buildMessageHtml(msg);
    container.appendChild(div.firstElementChild);
    container.scrollTop = container.scrollHeight;
}

async function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    if (!message || !currentChat) return;

    input.value = '';
    input.style.height = 'auto';
    updateSendButton();

    const tempMsg = { body: message, fromMe: true, timestamp: Math.floor(Date.now() / 1000), type: 'text' };
    appendMessage(tempMsg);

    try {
        const data = await fetchAPI('/api/whatsapp-chat.php?action=send', {
            method: 'POST',
            body: JSON.stringify({ chatId: currentChat.chatId || currentChat.id, message })
        });

        if (!data.success) {
            showToast('Failed to send: ' + (data.error || 'Unknown error'), 'danger');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'danger');
    }
}

function handleKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

function autoResizeInput(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 100) + 'px';
    updateSendButton();
}

function updateSendButton() {
    const btn = document.getElementById('sendBtn');
    const input = document.getElementById('messageInput');
    btn.classList.toggle('has-text', input.value.trim().length > 0);
}

function startPolling() {
    stopPolling();
    chatPollingInterval = setInterval(async () => {
        if (currentChat) {
            await loadMessages(currentChat.id, false);
        }
    }, 3000);
}

function stopPolling() {
    if (chatPollingInterval) {
        clearInterval(chatPollingInterval);
        chatPollingInterval = null;
    }
}

async function refreshCurrentChat() {
    if (currentChat) {
        await loadMessages(currentChat.id, true);
    }
}

function openImageViewer(src, name) {
    viewerImageSrc = src;
    document.getElementById('viewerImage').src = src;
    document.getElementById('viewerName').textContent = name || 'Photo';
    document.getElementById('viewerDate').textContent = new Date().toLocaleString();
    document.getElementById('imageViewer').classList.add('active');
    document.addEventListener('keydown', imageViewerKeyHandler);
}

function closeImageViewer(event) {
    if (event && event.target && !event.target.closest('.wa-image-viewer-header') && event.target.tagName !== 'IMG' || !event) {
        document.getElementById('imageViewer').classList.remove('active');
        document.removeEventListener('keydown', imageViewerKeyHandler);
    }
}

function imageViewerKeyHandler(e) {
    if (e.key === 'Escape') closeImageViewer();
}

function downloadViewerImage() {
    const a = document.createElement('a');
    a.href = viewerImageSrc;
    a.download = 'whatsapp-image.jpg';
    a.click();
}

function toggleAttachMenu() {
    document.getElementById('attachMenu').classList.toggle('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.wa-input-actions') && !e.target.closest('.wa-attach-menu')) {
        document.getElementById('attachMenu').classList.remove('show');
    }
});

async function handleFileSelect(input, type) {
    const file = input.files[0];
    if (!file || !currentChat) return;
    document.getElementById('attachMenu').classList.remove('show');

    const reader = new FileReader();
    reader.onload = async function(e) {
        const base64 = e.target.result.split(',')[1];
        const mimetype = file.type;

        try {
            const data = await fetchAPI('/api/whatsapp-chat.php?action=send-media', {
                method: 'POST',
                body: JSON.stringify({
                    chatId: currentChat.chatId || currentChat.id,
                    mediaData: base64,
                    mimetype: mimetype,
                    filename: file.name,
                    type: type
                })
            });
            if (data.success) {
                await refreshCurrentChat();
            } else {
                showToast('Failed to send file: ' + (data.error || 'Unknown error'), 'danger');
            }
        } catch (error) {
            showToast('Error sending file', 'danger');
        }
    };
    reader.readAsDataURL(file);
    input.value = '';
}

function toggleContactPanel() {
    const panel = document.getElementById('contactPanel');
    contactPanelOpen = !contactPanelOpen;
    panel.classList.toggle('open', contactPanelOpen);

    if (contactPanelOpen && currentChat) {
        document.getElementById('contactPanelContent').innerHTML = `
            <div class="wa-contact-detail">
                <div class="wa-avatar-large">${getInitials(currentChat.name || currentChat.phone)}</div>
                <div class="contact-name">${escapeHtml(currentChat.customer_name || currentChat.name || currentChat.phone)}</div>
                <div class="contact-phone">${escapeHtml(currentChat.phone || '')}</div>
                ${currentChat.customer_name ? `<span class="badge" style="background: rgba(0,168,132,0.1); color: var(--wa-green);"><i class="bi bi-person-check me-1"></i>Linked Customer</span>` : `<span class="badge bg-light text-muted"><i class="bi bi-person-x me-1"></i>Not linked</span>`}
            </div>
            <div class="wa-contact-actions">
                ${currentChat.customer_id ? `<a class="action-btn" href="?page=customers&action=view&id=${currentChat.customer_id}"><i class="bi bi-person"></i><span>Customer</span></a>` : `<button class="action-btn" onclick="linkToCustomer()"><i class="bi bi-person-plus"></i><span>Link</span></button>`}
                <button class="action-btn" onclick="createTicket()"><i class="bi bi-ticket-perforated"></i><span>Ticket</span></button>
            </div>`;
    }
}

function startNewChat() {
    const modal = new bootstrap.Modal(document.getElementById('newChatModal'));
    modal.show();
    document.getElementById('newChatPhone').value = '';
    document.getElementById('newChatMessage').value = '';
}

async function sendNewChat() {
    const phone = document.getElementById('newChatPhone').value.trim().replace(/[^0-9]/g, '');
    const message = document.getElementById('newChatMessage').value.trim();
    if (!phone || !message) {
        showToast('Please enter both phone number and message', 'warning');
        return;
    }

    try {
        const chatId = phone + '@c.us';
        const data = await fetchAPI('/api/whatsapp-chat.php?action=send', {
            method: 'POST',
            body: JSON.stringify({ chatId: chatId, message: message })
        });

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
            showToast('Message sent!', 'success');
            await refreshChats();
        } else {
            showToast('Failed: ' + (data.error || 'Unknown error'), 'danger');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'danger');
    }
}

async function linkToCustomer() {
    const modal = new bootstrap.Modal(document.getElementById('linkCustomerModal'));
    modal.show();
    document.getElementById('customerSearch').value = '';
    document.getElementById('customerResults').innerHTML = '';
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
                <div class="d-flex align-items-center gap-3 p-2 border rounded mb-2" style="cursor:pointer" onclick="selectCustomer(${c.id})" onmouseover="this.style.background='var(--wa-hover)'" onmouseout="this.style.background=''">
                    <div class="wa-avatar" style="width:36px;height:36px;margin:0"><div class="wa-avatar-placeholder" style="font-size:0.8rem">${getInitials(c.name)}</div></div>
                    <div>
                        <div class="fw-semibold" style="font-size:0.9rem">${escapeHtml(c.name)}</div>
                        <small class="text-muted">${escapeHtml(c.phone || '')} ${c.account_number ? '| ' + escapeHtml(c.account_number) : ''}</small>
                    </div>
                </div>
            `).join('') || '<div class="text-center text-muted p-3" style="font-size:0.85rem">No customers found</div>';
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
            body: JSON.stringify({ conversationId: currentChat.id, customerId: customerId })
        });

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('linkCustomerModal')).hide();
            showToast('Customer linked successfully!', 'success');
            await refreshChats();
        } else {
            showToast('Failed: ' + (data.error || 'Unknown error'), 'danger');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'danger');
    }
}

function createTicket() {
    if (currentChat && currentChat.customer_id) {
        window.location.href = `?page=tickets&action=create&customer_id=${currentChat.customer_id}`;
    } else {
        showToast('Link this conversation to a customer first', 'warning');
    }
}

function viewCustomer() {
    if (currentChat && currentChat.customer_id) {
        window.location.href = `?page=customers&action=view&id=${currentChat.customer_id}`;
    } else {
        showToast('No customer linked to this conversation', 'warning');
    }
}

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    return parts.map(p => p[0]).slice(0, 2).join('').toUpperCase();
}

function formatTime(timestamp) {
    if (!timestamp) return '';
    const date = new Date(timestamp * 1000);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const msgDay = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const diff = (today - msgDay) / 86400000;

    if (diff === 0) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    if (diff === 1) return 'Yesterday';
    if (diff < 7) return date.toLocaleDateString([], { weekday: 'short' });
    return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function formatMessageTime(timestamp) {
    if (!timestamp) return '';
    const date = new Date(typeof timestamp === 'number' ? timestamp * 1000 : timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatDateDivider(timestamp) {
    if (!timestamp) return '';
    const date = new Date(typeof timestamp === 'number' ? timestamp * 1000 : timestamp);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const msgDay = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const diff = (today - msgDay) / 86400000;

    if (diff === 0) return 'TODAY';
    if (diff === 1) return 'YESTERDAY';
    return date.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }).toUpperCase();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function linkify(text) {
    return text.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
}

function isOnlyEmoji(text) {
    if (!text || text.trim().length > 8) return false;
    try {
        const stripped = text.replace(/[\s\uFE0F\u200D\u20E3]/g, '');
        return stripped.length > 0 && /^\p{Emoji}+$/u.test(stripped);
    } catch(e) {
        return false;
    }
}

function showToast(message, type = 'info') {
    const colors = { success: 'var(--wa-green)', danger: '#dc3545', warning: '#ffc107', info: '#0d6efd' };
    const textColor = type === 'warning' ? '#000' : '#fff';
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:${colors[type]};color:${textColor};padding:8px 20px;border-radius:8px;font-size:0.875rem;z-index:10001;box-shadow:0 4px 12px rgba(0,0,0,0.2);opacity:0;transition:opacity 0.2s;`;
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.style.opacity = '1');
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 200);
    }, 3000);
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

refreshChats().then(() => {
    if (chats.length === 0) {
        syncChatsInBackground();
    }
}).catch(() => {
    syncChatsInBackground();
});
setInterval(() => refreshChats(true), 10000);
setInterval(() => syncChatsInBackground(), 180000);

let syncing = false;
async function syncChatsInBackground() {
    if (syncing) return;
    syncing = true;
    const chatList = document.getElementById('chatList');
    if (chats.length === 0) {
        chatList.innerHTML = `
            <div class="d-flex flex-column align-items-center p-4 text-muted" style="margin-top:40px">
                <div class="spinner-border spinner-border-sm mb-3" style="color: var(--wa-green)"></div>
                <span style="font-size:0.85rem">Syncing conversations...</span>
                <span style="font-size:0.75rem; margin-top:4px; opacity:0.6">This may take a moment on first load</span>
            </div>`;
    }
    try {
        await fetch('/api/whatsapp-chat.php?action=sync');
        await refreshChats(true);
    } catch (e) {}
    syncing = false;
}
</script>
