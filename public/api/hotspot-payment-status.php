<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../../config/database.php';

$subscriptionId = (int)($_GET['sid'] ?? 0);
$mac = $_GET['mac'] ?? '';

if (!$subscriptionId && !$mac) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

$sessionSubId = (int)($_SESSION['pending_subscription_id'] ?? 0);
$sessionMAC = $_SESSION['clientMAC'] ?? '';

if ($subscriptionId && $sessionSubId && $subscriptionId !== $sessionSubId) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($mac && $sessionMAC) {
    $macClean = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
    $sessionMACClean = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $sessionMAC));
    if ($macClean !== $sessionMACClean) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
}

$lookupId = $subscriptionId ?: $sessionSubId;

if (!$lookupId && !$mac) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

try {
    $db = Database::getConnection();
    
    $subscription = null;
    
    if ($lookupId) {
        $stmt = $db->prepare("
            SELECT s.id, s.status, s.expiry_date, s.package_id,
                   p.name as package_name, p.download_speed, p.max_devices,
                   p.session_duration_hours, p.validity_days
            FROM radius_subscriptions s
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.id = ?
        ");
        $stmt->execute([$lookupId]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$subscription && $mac) {
        $macClean = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
        if (strlen($macClean) === 12) {
            $macFormatted = implode(':', str_split($macClean, 2));
        } else {
            $macFormatted = strtoupper($mac);
        }
        
        $stmt = $db->prepare("
            SELECT s.id, s.status, s.expiry_date, s.package_id,
                   p.name as package_name, p.download_speed, p.max_devices,
                   p.session_duration_hours, p.validity_days
            FROM radius_subscriptions s
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.mac_address = ?
            ORDER BY s.updated_at DESC LIMIT 1
        ");
        $stmt->execute([$macFormatted]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$subscription) {
            $stmt = $db->prepare("
                SELECT s.id, s.status, s.expiry_date, s.package_id,
                       p.name as package_name, p.download_speed, p.max_devices,
                       p.session_duration_hours, p.validity_days
                FROM radius_subscription_devices d
                JOIN radius_subscriptions s ON d.subscription_id = s.id
                LEFT JOIN radius_packages p ON s.package_id = p.id
                WHERE d.mac_address = ? AND d.is_active = true
                ORDER BY s.updated_at DESC LIMIT 1
            ");
            $stmt->execute([$macFormatted]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (!$subscription) {
        echo json_encode(['status' => 'not_found']);
        exit;
    }
    
    $isActive = $subscription['status'] === 'active';
    $isExpiredByDate = !empty($subscription['expiry_date']) && strtotime($subscription['expiry_date']) < time();
    
    if ($subscription['status'] === 'pending_payment') {
        $accountRefs = ['HS-' . $subscription['id'], 'radius_' . $subscription['id']];
        $placeholders = implode(',', array_fill(0, count($accountRefs), '?'));
        $txStmt = $db->prepare("
            SELECT id, amount, mpesa_receipt_number FROM mpesa_transactions 
            WHERE account_reference IN ({$placeholders})
            AND status = 'completed'
            AND created_at > NOW() - INTERVAL '10 minutes'
            ORDER BY id DESC LIMIT 1
        ");
        $txStmt->execute($accountRefs);
        $completedTx = $txStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($completedTx) {
            $sessionHours = !empty($subscription['session_duration_hours']) ? (float)$subscription['session_duration_hours'] : 0;
            if ($sessionHours > 0) {
                $durationSeconds = (int)($sessionHours * 3600);
                $expiryDate = date('Y-m-d H:i:s', time() + $durationSeconds);
            } else {
                $validityDays = $subscription['validity_days'] ?? 30;
                $expiryDate = date('Y-m-d H:i:s', strtotime("+{$validityDays} days"));
            }
            
            $activateStmt = $db->prepare("
                UPDATE radius_subscriptions 
                SET status = 'active', expiry_date = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND status = 'pending_payment'
            ");
            $activateStmt->execute([$expiryDate, $subscription['id']]);
            
            error_log("HOTSPOT-STATUS: Self-healed subscription ID={$subscription['id']}, activated via completed M-Pesa tx={$completedTx['mpesa_receipt_number']}");
            
            $subscription['status'] = 'active';
            $subscription['expiry_date'] = $expiryDate;
            $isActive = true;
            $isExpiredByDate = false;
        }
    }
    
    if ($isActive && !$isExpiredByDate) {
        echo json_encode([
            'status' => 'active',
            'activated' => true,
            'package_name' => $subscription['package_name'],
            'download_speed' => $subscription['download_speed'],
            'expiry_date' => $subscription['expiry_date'],
            'max_devices' => (int)($subscription['max_devices'] ?? 1),
        ]);
    } elseif ($subscription['status'] === 'pending_payment') {
        echo json_encode([
            'status' => 'pending_payment',
            'activated' => false,
            'waiting' => true,
        ]);
    } elseif ($isActive && $isExpiredByDate) {
        echo json_encode([
            'status' => 'expired',
            'activated' => false,
            'waiting' => true,
        ]);
    } else {
        echo json_encode([
            'status' => $subscription['status'],
            'activated' => false,
        ]);
    }
    
} catch (Exception $e) {
    error_log("Hotspot payment status error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
