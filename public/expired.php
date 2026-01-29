<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Mpesa.php';
require_once __DIR__ . '/../src/RadiusBilling.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$db = Database::getConnection();

function getClientIP(): string {
    $headers = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$clientIP = getClientIP();
$subscription = null;
$customer = null;
$package = null;
$message = '';
$messageType = 'info';
$stkPushSent = false;
$lookupMode = false;

$radiusBilling = new \App\RadiusBilling($db);
$ispName = $radiusBilling->getSetting('isp_name') ?: 'Your ISP';
$ispPhone = $radiusBilling->getSetting('isp_contact_phone') ?: '';
$ispPhoneFormatted = $ispPhone ? preg_replace('/[^0-9]/', '', $ispPhone) : '';
$ispWhatsApp = $ispPhoneFormatted ? '254' . substr($ispPhoneFormatted, -9) : '';
$ispLogo = $radiusBilling->getSetting('isp_logo') ?: '';
$mpesaPaybill = $radiusBilling->getSetting('mpesa_shortcode') ?: '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lookup') {
    $lookupValue = trim($_POST['lookup_value'] ?? '');
    if (!empty($lookupValue)) {
        $phone = preg_replace('/[^0-9]/', '', $lookupValue);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        $stmt = $db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                   p.name as package_name, p.price as package_price, p.validity_days,
                   p.download_speed, p.upload_speed
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.username = ? 
               OR REPLACE(REPLACE(c.phone, '+', ''), ' ', '') = ?
               OR REPLACE(REPLACE(c.phone, '+', ''), ' ', '') LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$lookupValue, $phone, '%' . substr($phone, -9)]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$subscription) {
            $message = "No subscription found for '{$lookupValue}'. Please check your username or phone number.";
            $messageType = 'warning';
            $lookupMode = true;
        }
    }
}

if (!$subscription && !$lookupMode) {
    $stmt = $db->prepare("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
               p.name as package_name, p.price as package_price, p.validity_days,
               p.download_speed, p.upload_speed
        FROM radius_subscriptions s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN radius_packages p ON s.package_id = p.id
        LEFT JOIN radius_sessions rs ON rs.subscription_id = s.id
        WHERE rs.framed_ip_address = ? OR s.static_ip = ?
        ORDER BY rs.started_at DESC
        LIMIT 1
    ");
    $stmt->execute([$clientIP, $clientIP]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        $stmt = $db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                   p.name as package_name, p.price as package_price, p.validity_days,
                   p.download_speed, p.upload_speed, rs.framed_ip_address
            FROM radius_sessions rs
            LEFT JOIN radius_subscriptions s ON rs.subscription_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE rs.framed_ip_address = ?
            ORDER BY rs.started_at DESC
            LIMIT 1
        ");
        $stmt->execute([$clientIP]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'stk_push' && $subscription) {
        $phone = $_POST['phone'] ?? $subscription['customer_phone'] ?? '';
        $amount = (int)($subscription['package_price'] ?? 0);
        $walletBalance = (float)($subscription['credit_balance'] ?? 0);
        
        if ($walletBalance > 0 && $walletBalance < $amount) {
            $amount = $amount - floor($walletBalance);
        }
        
        $accountRef = $subscription['username'];
        
        if (empty($phone)) {
            $message = "Please enter your M-Pesa phone number.";
            $messageType = 'danger';
        } elseif ($amount < 1) {
            $message = "Invalid package price.";
            $messageType = 'danger';
        } else {
            try {
                $mpesa = new \App\Mpesa();
                if ($mpesa->isConfigured()) {
                    $result = $mpesa->stkPush($phone, $amount, $accountRef, "Internet Renewal - {$subscription['package_name']}");
                    if ($result && isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
                        $message = "Payment request sent! Check your phone for the M-Pesa prompt.";
                        $messageType = 'success';
                        $stkPushSent = true;
                    } else {
                        $message = "Failed to send payment request: " . ($result['errorMessage'] ?? $result['ResponseDescription'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                } else {
                    $message = "M-Pesa STK Push not configured. Please pay manually via Paybill.";
                    $messageType = 'info';
                }
            } catch (Exception $e) {
                $message = "Payment error: " . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

$mpesaConfigured = false;
try {
    $mpesa = new \App\Mpesa();
    $mpesaConfigured = $mpesa->isConfigured();
} catch (Exception $e) {
    $mpesaConfigured = false;
}

$isSuspended = $subscription && ($subscription['status'] === 'suspended');
$walletBalance = $subscription ? (float)($subscription['credit_balance'] ?? 0) : 0;
$packagePrice = $subscription ? (float)($subscription['package_price'] ?? 0) : 0;
$amountNeeded = max(0, $packagePrice - $walletBalance);

$daysExpired = 0;
if ($subscription && $subscription['expiry_date']) {
    $expiryTime = strtotime($subscription['expiry_date']);
    $daysExpired = max(0, floor((time() - $expiryTime) / 86400));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $isSuspended ? 'Account Suspended' : 'Subscription Expired' ?> - <?= htmlspecialchars($ispName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: <?= $isSuspended ? '#f59e0b' : '#ef4444' ?>;
            --primary-dark: <?= $isSuspended ? '#d97706' : '#dc2626' ?>;
            --success: #10b981;
            --success-dark: #059669;
            --bg-dark: #0f172a;
            --bg-card: rgba(255, 255, 255, 0.95);
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: rgba(255, 255, 255, 0.1);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, rgba(168, 85, 247, 0.1) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
            z-index: 0;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-2%, 2%) rotate(5deg); }
        }
        
        .page-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 24px;
            animation: fadeDown 0.6s ease-out;
        }
        
        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo-img {
            max-width: 180px;
            max-height: 60px;
            object-fit: contain;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
        }
        
        .isp-name-header {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .main-card {
            background: var(--bg-card);
            border-radius: 28px;
            box-shadow: 0 25px 100px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            animation: slideUp 0.5s ease-out 0.1s both;
            backdrop-filter: blur(20px);
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .status-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .status-header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.08'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .status-header > * {
            position: relative;
            z-index: 1;
        }
        
        .days-pill {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: white;
            font-weight: 500;
        }
        
        .status-icon-wrap {
            width: 90px;
            height: 90px;
            margin: 0 auto 20px;
            position: relative;
        }
        
        .status-icon-bg {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            animation: pulse-ring 2s ease-out infinite;
        }
        
        @keyframes pulse-ring {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.4); opacity: 0; }
        }
        
        .status-icon {
            position: relative;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            color: white;
        }
        
        .status-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .status-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            margin-bottom: 20px;
        }
        
        .package-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 30px;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .package-badge i {
            font-size: 1.1rem;
        }
        
        .card-content {
            padding: 28px;
        }
        
        .alert-box {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 0.9rem;
        }
        
        .alert-box.success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }
        
        .alert-box.danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
        }
        
        .alert-box.warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }
        
        .alert-box.info {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
        }
        
        .alert-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .account-info {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .info-label i {
            width: 20px;
            text-align: center;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .info-value.expired {
            color: var(--primary);
        }
        
        .info-value.suspended {
            color: #f59e0b;
        }
        
        .suspended-box {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid #f59e0b;
            border-radius: 0 16px 16px 0;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .suspended-box h4 {
            color: #92400e;
            font-size: 1rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .suspended-box p {
            color: #a16207;
            font-size: 0.875rem;
            margin: 0;
            line-height: 1.5;
        }
        
        .wallet-box {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid #bbf7d0;
        }
        
        .wallet-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #166534;
            margin-bottom: 4px;
        }
        
        .wallet-amount {
            font-size: 2rem;
            font-weight: 800;
            color: #15803d;
        }
        
        .wallet-status {
            font-size: 0.8rem;
            color: #16a34a;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .payment-box {
            background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
            border-radius: 20px;
            padding: 24px;
            text-align: center;
            color: white;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        
        .payment-box::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
        }
        
        .payment-box > * {
            position: relative;
            z-index: 1;
        }
        
        .payment-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.9;
            margin-bottom: 4px;
        }
        
        .payment-amount {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -2px;
            line-height: 1;
            margin-bottom: 8px;
        }
        
        .payment-validity {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .waiting-section {
            text-align: center;
            padding: 30px 0;
        }
        
        .waiting-spinner {
            width: 70px;
            height: 70px;
            margin: 0 auto 24px;
            position: relative;
        }
        
        .waiting-spinner::before,
        .waiting-spinner::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 4px solid transparent;
        }
        
        .waiting-spinner::before {
            border-top-color: var(--success);
            animation: spin 1s linear infinite;
        }
        
        .waiting-spinner::after {
            border-right-color: var(--success);
            animation: spin 1.5s linear infinite reverse;
            inset: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .waiting-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .waiting-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .check-btn {
            margin-top: 20px;
            padding: 12px 28px;
            border: 2px solid var(--success);
            background: transparent;
            color: var(--success);
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .check-btn:hover {
            background: var(--success);
            color: white;
        }
        
        .phone-field {
            position: relative;
            margin-bottom: 16px;
        }
        
        .phone-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.25rem;
        }
        
        .phone-input {
            width: 100%;
            padding: 18px 20px 18px 52px;
            font-size: 1.1rem;
            font-weight: 500;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: all 0.2s;
            background: white;
        }
        
        .phone-input:focus {
            outline: none;
            border-color: var(--success);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        .pay-button {
            width: 100%;
            padding: 20px;
            border: none;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
            color: white;
            font-size: 1.15rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s;
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.3);
        }
        
        .pay-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(16, 185, 129, 0.4);
        }
        
        .pay-button:active {
            transform: translateY(0);
        }
        
        .mpesa-badge {
            background: white;
            color: #00a650;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        
        .paybill-section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .paybill-header {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 16px;
            font-size: 0.95rem;
        }
        
        .paybill-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .paybill-row:last-child {
            border-bottom: none;
        }
        
        .paybill-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        .paybill-value {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .copy-btn {
            background: transparent;
            border: none;
            color: var(--success);
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 1rem;
        }
        
        .copy-btn:hover {
            background: rgba(16, 185, 129, 0.1);
        }
        
        .copy-btn.copied {
            color: #059669;
        }
        
        .contact-section {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            margin-top: 24px;
        }
        
        .contact-text {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 16px;
        }
        
        .contact-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .contact-btn-call {
            background: #f1f5f9;
            color: #475569;
        }
        
        .contact-btn-call:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .contact-btn-whatsapp {
            background: #25d366;
            color: white;
        }
        
        .contact-btn-whatsapp:hover {
            background: #1ebe57;
            color: white;
        }
        
        .not-found-section {
            text-align: center;
            padding: 50px 30px;
        }
        
        .not-found-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: white;
            box-shadow: 0 15px 40px rgba(245, 158, 11, 0.3);
        }
        
        .not-found-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
        }
        
        .not-found-text {
            color: var(--text-secondary);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
        }
        
        .search-input {
            flex: 1;
            padding: 16px 20px;
            font-size: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            transition: all 0.2s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .search-btn {
            padding: 16px 24px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }
        
        .ip-info {
            margin-top: 30px;
            padding: 12px 20px;
            background: #f1f5f9;
            border-radius: 10px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .footer {
            text-align: center;
            margin-top: 28px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
            animation: fadeIn 0.5s ease-out 0.3s both;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .footer strong {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1e293b;
            color: white;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 500;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        
        .toast.success {
            background: var(--success);
        }
        
        @media (max-width: 480px) {
            body { padding: 16px; }
            .status-header { padding: 32px 24px; }
            .card-content { padding: 24px; }
            .status-title { font-size: 1.5rem; }
            .payment-amount { font-size: 2.5rem; }
            .contact-buttons { flex-direction: column; }
            .contact-btn { justify-content: center; }
            .search-form { flex-direction: column; }
            .search-btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="logo-section">
            <?php if ($ispLogo): ?>
                <img src="<?= htmlspecialchars($ispLogo) ?>" alt="<?= htmlspecialchars($ispName) ?>" class="logo-img">
            <?php else: ?>
                <div class="isp-name-header"><?= htmlspecialchars($ispName) ?></div>
            <?php endif; ?>
        </div>
        
        <div class="main-card">
            <?php if ($subscription): ?>
            <div class="status-header">
                <?php if ($daysExpired > 0 && !$isSuspended): ?>
                <div class="days-pill">
                    <i class="bi bi-clock-history me-1"></i>
                    <?= $daysExpired ?> day<?= $daysExpired > 1 ? 's' : '' ?> ago
                </div>
                <?php endif; ?>
                
                <div class="status-icon-wrap">
                    <div class="status-icon-bg"></div>
                    <div class="status-icon">
                        <i class="bi <?= $isSuspended ? 'bi-pause-circle-fill' : 'bi-wifi-off' ?>"></i>
                    </div>
                </div>
                
                <h1 class="status-title">
                    <?= $isSuspended ? 'Account Suspended' : 'Subscription Expired' ?>
                </h1>
                <p class="status-subtitle">
                    <?= $isSuspended ? 'Your account has been temporarily suspended' : 'Renew now to restore your internet connection' ?>
                </p>
                
                <div class="package-badge">
                    <i class="bi bi-speedometer2"></i>
                    <?= htmlspecialchars($subscription['package_name'] ?? 'Package') ?>
                    <?php if (!empty($subscription['download_speed'])): ?>
                    <span style="opacity:0.6">|</span>
                    <?= htmlspecialchars($subscription['download_speed']) ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-content">
                <?php if ($message): ?>
                <div class="alert-box <?= $messageType ?>">
                    <i class="bi bi-<?= $messageType === 'success' ? 'check-circle-fill' : ($messageType === 'danger' ? 'exclamation-circle-fill' : ($messageType === 'warning' ? 'exclamation-triangle-fill' : 'info-circle-fill')) ?> alert-icon"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="account-info">
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-person-fill"></i> Account Holder</span>
                        <span class="info-value"><?= htmlspecialchars($subscription['customer_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-key-fill"></i> Username</span>
                        <span class="info-value"><?= htmlspecialchars($subscription['username']) ?></span>
                    </div>
                    <?php if (!$isSuspended): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-calendar-x-fill"></i> Expired On</span>
                        <span class="info-value expired">
                            <?= $subscription['expiry_date'] ? date('M j, Y', strtotime($subscription['expiry_date'])) : 'N/A' ?>
                        </span>
                    </div>
                    <?php else: ?>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-shield-x-fill"></i> Status</span>
                        <span class="info-value suspended">
                            <i class="bi bi-pause-circle-fill me-1"></i> Suspended
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($isSuspended): ?>
                <div class="suspended-box">
                    <h4><i class="bi bi-exclamation-triangle-fill"></i> Account Suspended</h4>
                    <p>Your account has been suspended. Please contact <?= htmlspecialchars($ispName) ?> support to resolve this issue and reactivate your service.</p>
                </div>
                <?php else: ?>
                
                <?php if ($walletBalance > 0): ?>
                <div class="wallet-box">
                    <div class="wallet-label">Wallet Balance</div>
                    <div class="wallet-amount">KES <?= number_format($walletBalance, 0) ?></div>
                    <?php if ($walletBalance >= $packagePrice): ?>
                    <div class="wallet-status"><i class="bi bi-check-circle-fill"></i> Sufficient for renewal!</div>
                    <?php else: ?>
                    <div class="wallet-status" style="color: #f59e0b;"><i class="bi bi-info-circle-fill"></i> Need KES <?= number_format($amountNeeded, 0) ?> more</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="payment-box">
                    <div class="payment-label">Amount to Pay</div>
                    <div class="payment-amount">KES <?= number_format($amountNeeded, 0) ?></div>
                    <?php if ($subscription['validity_days']): ?>
                    <div class="payment-validity"><i class="bi bi-calendar-check me-1"></i> <?= $subscription['validity_days'] ?> days validity</div>
                    <?php endif; ?>
                </div>
                
                <?php if ($stkPushSent): ?>
                <div class="waiting-section">
                    <div class="waiting-spinner"></div>
                    <h3 class="waiting-title">Waiting for Payment</h3>
                    <p class="waiting-text">Check your phone for the M-Pesa prompt<br>and enter your PIN to complete payment</p>
                    <button onclick="location.reload()" class="check-btn">
                        <i class="bi bi-arrow-clockwise me-2"></i>Check Payment Status
                    </button>
                </div>
                <script>setTimeout(function() { location.reload(); }, 30000);</script>
                <?php else: ?>
                <form method="post">
                    <input type="hidden" name="action" value="stk_push">
                    <div class="phone-field">
                        <i class="bi bi-phone-fill phone-icon"></i>
                        <input type="tel" name="phone" class="phone-input" 
                               value="<?= htmlspecialchars($subscription['customer_phone'] ?? '') ?>" 
                               placeholder="0712 345 678" required
                               pattern="^(07|01|2547|2541)[0-9]{7,8}$">
                    </div>
                    <button type="submit" class="pay-button">
                        <span class="mpesa-badge">M-PESA</span>
                        Pay KES <?= number_format($amountNeeded, 0) ?> Now
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>
                
                <?php if ($mpesaPaybill): ?>
                <div class="paybill-section">
                    <div class="paybill-header">
                        <i class="bi bi-credit-card-2-front"></i>
                        Or pay manually via Paybill
                    </div>
                    <div class="paybill-row">
                        <span class="paybill-label">Paybill Number</span>
                        <span class="paybill-value">
                            <?= htmlspecialchars($mpesaPaybill) ?>
                            <button class="copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($mpesaPaybill) ?>', this)" title="Copy">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </span>
                    </div>
                    <div class="paybill-row">
                        <span class="paybill-label">Account Number</span>
                        <span class="paybill-value">
                            <?= htmlspecialchars($subscription['username']) ?>
                            <button class="copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($subscription['username']) ?>', this)" title="Copy">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </span>
                    </div>
                    <div class="paybill-row">
                        <span class="paybill-label">Amount</span>
                        <span class="paybill-value">KES <?= number_format($amountNeeded, 0) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($ispPhone): ?>
                <div class="contact-section">
                    <p class="contact-text">Need assistance? We're here to help!</p>
                    <div class="contact-buttons">
                        <a href="tel:<?= htmlspecialchars($ispPhoneFormatted) ?>" class="contact-btn contact-btn-call">
                            <i class="bi bi-telephone-fill"></i>
                            Call Us
                        </a>
                        <?php if ($ispWhatsApp): ?>
                        <a href="https://wa.me/<?= htmlspecialchars($ispWhatsApp) ?>?text=Hi%2C%20I%20need%20help%20with%20my%20internet%20account%20(<?= urlencode($subscription['username']) ?>)" 
                           class="contact-btn contact-btn-whatsapp" target="_blank">
                            <i class="bi bi-whatsapp"></i>
                            WhatsApp
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <div class="not-found-section">
                <div class="not-found-icon">
                    <i class="bi bi-question-lg"></i>
                </div>
                <h2 class="not-found-title">Account Not Found</h2>
                <p class="not-found-text">We couldn't automatically identify your account.<br>Please enter your details below to find it.</p>
                
                <?php if ($message): ?>
                <div class="alert-box <?= $messageType ?>" style="text-align: left; margin-bottom: 24px;">
                    <i class="bi bi-exclamation-circle-fill alert-icon"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
                <?php endif; ?>
                
                <form method="post" class="search-form">
                    <input type="hidden" name="action" value="lookup">
                    <input type="text" name="lookup_value" class="search-input" 
                           placeholder="Username or phone number" required
                           value="<?= htmlspecialchars($_POST['lookup_value'] ?? '') ?>">
                    <button type="submit" class="search-btn">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
                
                <?php if ($ispPhone): ?>
                <div class="contact-section" style="border-top: none; margin-top: 32px; padding-top: 0;">
                    <p class="contact-text">Can't find your account? Contact us</p>
                    <div class="contact-buttons">
                        <a href="tel:<?= htmlspecialchars($ispPhoneFormatted) ?>" class="contact-btn contact-btn-call">
                            <i class="bi bi-telephone-fill"></i> Call
                        </a>
                        <?php if ($ispWhatsApp): ?>
                        <a href="https://wa.me/<?= htmlspecialchars($ispWhatsApp) ?>?text=Hi%2C%20I%20need%20help%20finding%20my%20internet%20account" 
                           class="contact-btn contact-btn-whatsapp" target="_blank">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="ip-info">
                    <i class="bi bi-geo-alt-fill"></i>
                    Your IP: <?= htmlspecialchars($clientIP) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <strong><?= htmlspecialchars($ispName) ?></strong>
        </div>
    </div>
    
    <div class="toast" id="toast">
        <i class="bi bi-check-circle-fill"></i>
        <span id="toast-message">Copied!</span>
    </div>
    
    <script>
        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(function() {
                const icon = btn.querySelector('i');
                icon.className = 'bi bi-check-lg';
                btn.classList.add('copied');
                
                showToast('Copied: ' + text);
                
                setTimeout(function() {
                    icon.className = 'bi bi-clipboard';
                    btn.classList.remove('copied');
                }, 2000);
            });
        }
        
        function showToast(message) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            toastMessage.textContent = message;
            toast.classList.add('show', 'success');
            
            setTimeout(function() {
                toast.classList.remove('show', 'success');
            }, 2500);
        }
    </script>
</body>
</html>
