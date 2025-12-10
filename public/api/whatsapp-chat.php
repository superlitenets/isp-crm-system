<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/WhatsApp.php';
require_once __DIR__ . '/../../src/Customer.php';

use App\Auth;
use App\WhatsApp;
use App\Customer;

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$whatsapp = new WhatsApp();
$customer = new Customer();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'chats':
            $result = $whatsapp->getChats();
            
            if ($result['success']) {
                $chats = [];
                foreach ($result['chats'] as $chat) {
                    $phone = preg_replace('/@c\.us$/', '', $chat['id']);
                    $isGroup = strpos($chat['id'], '@g.us') !== false;
                    
                    $conversation = $whatsapp->getOrCreateConversation(
                        $chat['id'],
                        $phone,
                        $chat['name'] ?? null,
                        $isGroup
                    );
                    
                    $chats[] = [
                        'id' => $conversation['id'],
                        'chatId' => $chat['id'],
                        'phone' => $conversation['phone'],
                        'name' => $chat['name'] ?? $conversation['contact_name'],
                        'isGroup' => $isGroup,
                        'unreadCount' => $chat['unreadCount'] ?? $conversation['unread_count'] ?? 0,
                        'lastMessageAt' => $chat['lastMessageAt'] ?? strtotime($conversation['last_message_at'] ?? 'now'),
                        'lastMessagePreview' => $conversation['last_message_preview'] ?? '',
                        'customer_id' => $conversation['customer_id'],
                        'customer_name' => $conversation['customer_name'] ?? null,
                        'assigned_to' => $conversation['assigned_to']
                    ];
                }
                
                usort($chats, fn($a, $b) => ($b['lastMessageAt'] ?? 0) - ($a['lastMessageAt'] ?? 0));
                
                echo json_encode(['success' => true, 'chats' => $chats]);
            } else {
                $conversations = $whatsapp->getConversations(50);
                $chats = array_map(function($c) {
                    return [
                        'id' => $c['id'],
                        'chatId' => $c['chat_id'],
                        'phone' => $c['phone'],
                        'name' => $c['contact_name'],
                        'isGroup' => $c['is_group'],
                        'unreadCount' => $c['unread_count'] ?? 0,
                        'lastMessageAt' => strtotime($c['last_message_at'] ?? 'now'),
                        'lastMessagePreview' => $c['last_message_preview'] ?? '',
                        'customer_id' => $c['customer_id'],
                        'customer_name' => $c['customer_name'] ?? null,
                        'assigned_to' => $c['assigned_to']
                    ];
                }, $conversations);
                
                echo json_encode(['success' => true, 'chats' => $chats, 'source' => 'database']);
            }
            break;
            
        case 'messages':
            $chatId = $_GET['chatId'] ?? '';
            if (!$chatId) {
                throw new Exception('Chat ID required');
            }
            
            $conversation = null;
            if (is_numeric($chatId)) {
                $conversation = $whatsapp->getConversationById((int)$chatId);
            }
            
            if ($conversation && $conversation['chat_id']) {
                $result = $whatsapp->getChatMessages($conversation['chat_id'], 100);
                
                if ($result['success']) {
                    $messages = array_map(function($msg) {
                        return [
                            'id' => $msg['id'],
                            'body' => $msg['body'],
                            'type' => $msg['type'] ?? 'text',
                            'fromMe' => $msg['fromMe'] ?? false,
                            'timestamp' => $msg['timestamp'],
                            'senderName' => $msg['senderName'] ?? null
                        ];
                    }, $result['messages']);
                    
                    foreach ($result['messages'] as $msg) {
                        $whatsapp->storeMessage($conversation['id'], $msg);
                    }
                    
                    echo json_encode(['success' => true, 'messages' => $messages]);
                } else {
                    $messages = $whatsapp->getConversationMessages($conversation['id'], 100);
                    $formatted = array_map(function($m) {
                        return [
                            'id' => $m['id'],
                            'body' => $m['body'],
                            'type' => $m['message_type'] ?? 'text',
                            'fromMe' => $m['direction'] === 'outgoing',
                            'timestamp' => strtotime($m['timestamp']),
                            'senderName' => $m['sender_name']
                        ];
                    }, $messages);
                    echo json_encode(['success' => true, 'messages' => $formatted, 'source' => 'database']);
                }
            } else {
                echo json_encode(['success' => true, 'messages' => []]);
            }
            break;
            
        case 'send':
            $input = json_decode(file_get_contents('php://input'), true);
            $chatId = $input['chatId'] ?? '';
            $message = $input['message'] ?? '';
            
            if (!$chatId || !$message) {
                throw new Exception('Chat ID and message required');
            }
            
            $conversation = null;
            $waChatId = $chatId;
            
            if (is_numeric($chatId)) {
                $conversation = $whatsapp->getConversationById((int)$chatId);
                if ($conversation) {
                    $waChatId = $conversation['chat_id'];
                }
            }
            
            $result = $whatsapp->sendToChat($waChatId, $message);
            
            if ($result['success'] && $conversation) {
                $whatsapp->storeMessage($conversation['id'], [
                    'messageId' => $result['messageId'] ?? null,
                    'body' => $message,
                    'fromMe' => true,
                    'type' => 'text',
                    'timestamp' => $result['timestamp'] ?? time()
                ]);
            }
            
            echo json_encode($result);
            break;
            
        case 'mark-read':
            $input = json_decode(file_get_contents('php://input'), true);
            $chatId = $input['chatId'] ?? '';
            
            if (!$chatId) {
                throw new Exception('Chat ID required');
            }
            
            if (is_numeric($chatId)) {
                $conversation = $whatsapp->getConversationById((int)$chatId);
                if ($conversation) {
                    $whatsapp->markConversationAsRead($conversation['id']);
                    if ($conversation['chat_id']) {
                        $whatsapp->markChatAsRead($conversation['chat_id']);
                    }
                }
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'search-customers':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'customers' => []]);
                break;
            }
            
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT id, account_number, name, phone, email
                FROM customers
                WHERE name ILIKE ? OR phone ILIKE ? OR account_number ILIKE ?
                ORDER BY name
                LIMIT 20
            ");
            $searchTerm = "%$query%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'customers' => $customers]);
            break;
            
        case 'link-customer':
            $input = json_decode(file_get_contents('php://input'), true);
            $conversationId = $input['conversationId'] ?? 0;
            $customerId = $input['customerId'] ?? 0;
            
            if (!$conversationId || !$customerId) {
                throw new Exception('Conversation ID and Customer ID required');
            }
            
            $success = $whatsapp->linkConversationToCustomer((int)$conversationId, (int)$customerId);
            echo json_encode(['success' => $success]);
            break;
            
        case 'unread-count':
            $count = $whatsapp->getTotalUnreadCount();
            echo json_encode(['success' => true, 'count' => $count]);
            break;
            
        case 'status':
            $status = $whatsapp->getSessionStatus();
            echo json_encode($status);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
