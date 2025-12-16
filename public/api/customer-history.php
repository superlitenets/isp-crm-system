<?php
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if (!$customerId) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID required']);
    exit;
}

try {
    $ticket = new \App\Ticket();
    
    $assignmentHistory = $ticket->getCustomerAssignmentHistory($customerId);
    $ticketHistory = $ticket->getCustomerTicketHistory($customerId, 5);
    
    echo json_encode([
        'success' => true,
        'suggested_assignment' => $assignmentHistory,
        'recent_tickets' => $ticketHistory
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
