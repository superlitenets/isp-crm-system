<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/RadiusBilling.php';
require_once __DIR__ . '/../src/SMSGateway.php';

header('Content-Type: application/json');

$db = getDbConnection();
$radiusBilling = new \App\RadiusBilling($db);

$phone = $_POST['phone'] ?? $_GET['phone'] ?? '';
$message = strtolower(trim($_POST['message'] ?? $_GET['message'] ?? ''));

$phone = preg_replace('/^254/', '0', $phone);
$phone = preg_replace('/[^0-9]/', '', $phone);

if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Phone number required']);
    exit;
}

$stmt = $db->prepare("
    SELECT s.*, c.name as customer_name, p.name as package_name, p.price, p.data_quota_mb
    FROM radius_subscriptions s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN radius_packages p ON s.package_id = p.id
    WHERE c.phone LIKE ?
    ORDER BY s.created_at DESC LIMIT 1
");
$stmt->execute(['%' . substr($phone, -9)]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    echo json_encode(['success' => false, 'error' => 'No subscription found for this phone']);
    exit;
}

$response = '';

if (str_contains($message, 'bal') || str_contains($message, 'status') || $message === '') {
    $daysLeft = 0;
    if ($subscription['expiry_date']) {
        $daysLeft = max(0, floor((strtotime($subscription['expiry_date']) - time()) / 86400));
    }
    
    $response = "Hi {$subscription['customer_name']}!\n";
    $response .= "Package: {$subscription['package_name']}\n";
    $response .= "Status: " . ucfirst($subscription['status']) . "\n";
    $response .= "Expires: " . ($subscription['expiry_date'] ? date('M j, Y', strtotime($subscription['expiry_date'])) : 'N/A') . "\n";
    $response .= "Days Left: {$daysLeft}\n";
    
    if ($subscription['data_quota_mb']) {
        $usedGB = number_format($subscription['data_used_mb'] / 1024, 2);
        $totalGB = number_format($subscription['data_quota_mb'] / 1024, 0);
        $remainingGB = number_format(max(0, $subscription['data_quota_mb'] - $subscription['data_used_mb']) / 1024, 2);
        $response .= "Data: {$usedGB}/{$totalGB} GB used ({$remainingGB} GB left)\n";
    }
    
    $response .= "\nRenew: KES " . number_format($subscription['price']);
    
} elseif (str_contains($message, 'usage') || str_contains($message, 'data')) {
    if ($subscription['data_quota_mb']) {
        $usedGB = number_format($subscription['data_used_mb'] / 1024, 2);
        $totalGB = number_format($subscription['data_quota_mb'] / 1024, 0);
        $remainingGB = number_format(max(0, $subscription['data_quota_mb'] - $subscription['data_used_mb']) / 1024, 2);
        $percent = round(($subscription['data_used_mb'] / $subscription['data_quota_mb']) * 100, 0);
        
        $response = "Data Usage for {$subscription['customer_name']}:\n";
        $response .= "Used: {$usedGB} GB ({$percent}%)\n";
        $response .= "Remaining: {$remainingGB} GB\n";
        $response .= "Total: {$totalGB} GB";
    } else {
        $usedGB = number_format($subscription['data_used_mb'] / 1024, 2);
        $response = "Data Usage: {$usedGB} GB\n";
        $response .= "Your package has unlimited data.";
    }
    
} elseif (str_contains($message, 'renew') || str_contains($message, 'pay')) {
    $response = "To renew {$subscription['package_name']}:\n";
    $response .= "Amount: KES " . number_format($subscription['price']) . "\n\n";
    $response .= "Pay via M-Pesa:\n";
    $response .= "1. Go to M-Pesa\n";
    $response .= "2. Lipa na M-Pesa\n";
    $response .= "3. Paybill: [YOUR_PAYBILL]\n";
    $response .= "4. Account: {$subscription['username']}\n";
    $response .= "5. Amount: " . number_format($subscription['price']);
    
} elseif (str_contains($message, 'help')) {
    $response = "ISP SMS Commands:\n";
    $response .= "BAL - Check balance & status\n";
    $response .= "USAGE - Check data usage\n";
    $response .= "RENEW - Payment instructions\n";
    $response .= "HELP - Show this menu";
    
} else {
    $response = "Unknown command. Reply HELP for available commands.";
}

try {
    $sms = new \App\SMSGateway();
    $result = $sms->send($phone, $response);
    echo json_encode(['success' => true, 'response' => $response]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'response' => $response]);
}
