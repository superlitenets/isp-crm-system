<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/RadiusBilling.php';
require_once __DIR__ . '/../src/Mpesa.php';

$db = Database::getConnection();
$radiusBilling = new \App\RadiusBilling($db);

function getClientIP(): string {
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getClientMAC(): string {
    return $_GET['mac'] ?? $_POST['mac'] ?? $_SERVER['HTTP_X_CLIENT_MAC'] ?? '';
}

$clientIP = getClientIP();
$clientMAC = getClientMAC();
$message = '';
$messageType = 'info';
$loginSuccess = false;
$stkPushSent = false;
$subscription = null;
$deviceStatus = 'unknown'; // 'active', 'expired', 'new'

// MikroTik hotspot variables
$linkLogin = $_GET['link-login'] ?? '';
$linkLoginOnly = $_GET['link-login-only'] ?? $_GET['loginLink'] ?? '';
$linkOrig = $_GET['link-orig'] ?? $_GET['dst'] ?? '';
$errorMsg = $_GET['error'] ?? '';

// CHAP authentication variables from MikroTik
$chapId = $_GET['chapID'] ?? $_GET['chap-id'] ?? '';
$chapChallenge = $_GET['chapChallenge'] ?? $_GET['chap-challenge'] ?? '';

// Store CHAP vars in session for form submissions
session_start();
if ($chapId) $_SESSION['chapId'] = $chapId;
if ($chapChallenge) $_SESSION['chapChallenge'] = $chapChallenge;
if ($linkLoginOnly) $_SESSION['linkLoginOnly'] = $linkLoginOnly;
if ($clientMAC) $_SESSION['clientMAC'] = $clientMAC;

// Restore from session if not in URL
$chapId = $chapId ?: ($_SESSION['chapId'] ?? '');
$chapChallenge = $chapChallenge ?: ($_SESSION['chapChallenge'] ?? '');
$linkLoginOnly = $linkLoginOnly ?: ($_SESSION['linkLoginOnly'] ?? '');
$clientMAC = $clientMAC ?: ($_SESSION['clientMAC'] ?? '');

// Get ISP settings
$ispName = $radiusBilling->getSetting('isp_name') ?: 'WiFi Hotspot';
$ispLogo = $radiusBilling->getSetting('isp_logo') ?: '';
$hotspotWelcome = $radiusBilling->getSetting('hotspot_welcome') ?: 'Connect to the internet';
$mpesaPaybill = $radiusBilling->getSetting('mpesa_paybill') ?: '';

// M-Pesa will be initialized after NAS detection (below) to use NAS-specific config
$mpesa = null;
$mpesaEnabled = false;

// Detect NAS device and MAC from URL path (/hotspot/{nas_ip}/{mac}), query params, or session
$nasIP = $_GET['nas'] ?? $_GET['server'] ?? $_GET['nasip'] ?? '';
$pathMAC = '';
if (empty($nasIP)) {
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/hotspot/([0-9.:]+)(?:/([0-9a-fA-F:.-]+))?#', $requestUri, $matches)) {
        $nasIP = $matches[1];
        if (!empty($matches[2])) {
            $pathMAC = $matches[2];
        }
    }
}
if ($nasIP) {
    $nasIP = preg_replace('/:\d+$/', '', $nasIP);
    $_SESSION['nasIP'] = $nasIP;
}
$nasIP = $nasIP ?: ($_SESSION['nasIP'] ?? '');

if (!empty($pathMAC) && empty($clientMAC)) {
    // Ignore MikroTik variable placeholders that weren't replaced
    if (preg_match('/^[0-9a-fA-F]{2}([:\-.])[0-9a-fA-F]{2}/', $pathMAC)) {
        $clientMAC = $pathMAC;
    }
}

$currentNAS = null;
if (!empty($nasIP)) {
    $currentNAS = $radiusBilling->getNASByIP($nasIP);
}

// Initialize M-Pesa with NAS-specific config if available, fallback to global
if ($currentNAS && !empty($currentNAS['mpesa_account_id'])) {
    $mpesa = \App\Mpesa::forNAS($currentNAS['id']);
    $mpesaPaybill = $mpesa->getShortcode();
} else {
    $mpesa = new \App\Mpesa();
}
$mpesaEnabled = $mpesa->isConfigured();

// Get available hotspot packages (filtered by NAS if detected)
$packages = [];
if ($currentNAS) {
    // Get packages assigned to this NAS
    $packages = $radiusBilling->getNASPackages($currentNAS['id']);
}
if (empty($packages)) {
    // Fallback to all active hotspot packages if no NAS-specific packages
    $stmt = $db->query("SELECT * FROM radius_packages WHERE is_active = true AND package_type = 'hotspot' ORDER BY price ASC");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if MAC authentication is enabled
$macAuthEnabled = $radiusBilling->getSetting('hotspot_mac_auth') === 'true';

// Check device status by MAC (using multi-device lookup)
if (!empty($clientMAC) && $macAuthEnabled) {
    $subscription = $radiusBilling->getSubscriptionByDeviceMAC($clientMAC);
    
    if ($subscription) {
        if (!$subscription['is_expired'] && $subscription['status'] === 'active') {
            // Auto-login for active subscription - redirect to MikroTik login
            $deviceStatus = 'active';
            $loginSuccess = true;
        } else {
            $deviceStatus = 'expired';
        }
    } else {
        $deviceStatus = 'new';
    }
} elseif (!$macAuthEnabled) {
    // MAC auth disabled - treat all as new
    $deviceStatus = 'new';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'voucher':
            $code = trim($_POST['voucher_code'] ?? '');
            if (empty($code)) {
                $message = 'Please enter a voucher code';
                $messageType = 'danger';
            } elseif (empty($clientMAC)) {
                $message = 'Unable to detect your device. Please reconnect to the WiFi.';
                $messageType = 'danger';
            } else {
                $result = $radiusBilling->redeemVoucherForMAC($code, $clientMAC);
                if ($result['success']) {
                    $loginSuccess = true;
                    $message = $result['message'];
                    $messageType = 'success';
                    $subscription = $radiusBilling->getSubscriptionByMAC($clientMAC);
                } else {
                    $message = $result['error'] ?? 'Invalid voucher';
                    $messageType = 'danger';
                }
            }
            break;
            
        case 'register':
            $phone = $_POST['phone'] ?? '';
            $packageId = (int)($_POST['package_id'] ?? 0);
            
            if (empty($phone)) {
                $message = 'Please enter your phone number';
                $messageType = 'danger';
            } elseif ($packageId <= 0) {
                $message = 'Please select a package';
                $messageType = 'danger';
            } elseif (empty($clientMAC)) {
                $message = 'Unable to detect your device. Please reconnect to the WiFi.';
                $messageType = 'danger';
            } else {
                $result = $radiusBilling->registerHotspotDeviceByPhone($phone, $clientMAC, $packageId);
                if ($result['success']) {
                    // Send M-Pesa STK Push
                    try {
                        $stkResult = $mpesa->stkPush($phone, (int)$result['amount'], 
                            'HS-' . $result['subscription_id'], 
                            "WiFi - {$result['package']['name']}");
                        if ($stkResult && !empty($stkResult['success'])) {
                            $message = "Payment request sent! Enter your M-Pesa PIN on your phone.";
                            $messageType = 'success';
                            $stkPushSent = true;
                            $_SESSION['pending_subscription_id'] = $result['subscription_id'];
                            $subscription = $radiusBilling->getSubscriptionByMAC($clientMAC);
                            $deviceStatus = 'pending';
                        } else {
                            $message = $stkResult['message'] ?? 'Failed to send payment request. Use voucher instead.';
                            $messageType = 'warning';
                        }
                    } catch (Exception $e) {
                        $message = 'Payment service error. Please use a voucher instead.';
                        $messageType = 'warning';
                    }
                } else {
                    $message = $result['error'];
                    $messageType = 'danger';
                }
            }
            break;
            
        case 'renew':
            $phone = $_POST['phone'] ?? '';
            $newPackageId = (int)($_POST['package_id'] ?? 0);
            
            if (empty($phone)) {
                $message = 'Please enter your phone number';
                $messageType = 'danger';
            } elseif (!$subscription) {
                $message = 'No subscription found for this device';
                $messageType = 'danger';
            } else {
                try {
                    $renewPkgName = $subscription['package_name'];
                    $amount = (int)($subscription['package_price'] ?? 0);
                    $subId = $subscription['id'];
                    $priorStatus = $subscription['status'] ?? 'active';

                    if ($newPackageId > 0 && $newPackageId != ($subscription['package_id'] ?? 0)) {
                        $pkgStmt = $db->prepare("SELECT id, name, price FROM radius_packages WHERE id = ? AND is_active = true");
                        $pkgStmt->execute([$newPackageId]);
                        $newPkg = $pkgStmt->fetch(PDO::FETCH_ASSOC);
                        if ($newPkg) {
                            $amount = (int)$newPkg['price'];
                            $renewPkgName = $newPkg['name'];
                            $db->prepare("UPDATE radius_subscriptions SET package_id = ? WHERE id = ?")->execute([$newPackageId, $subId]);
                        }
                    }

                    $db->prepare("UPDATE radius_subscriptions SET status = 'pending_payment', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$subId]);

                    if ($amount <= 0) {
                        $db->prepare("UPDATE radius_subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$priorStatus, $subId]);
                        $message = 'Package price not configured. Please use a voucher.';
                        $messageType = 'warning';
                    } else {
                        $stkResult = $mpesa->stkPush($phone, $amount, 
                            'HS-' . $subId, 
                            "Renew - {$renewPkgName}");
                        if ($stkResult && !empty($stkResult['success'])) {
                            $message = "Payment request sent! Enter your M-Pesa PIN on your phone.";
                            $messageType = 'success';
                            $stkPushSent = true;
                            $_SESSION['pending_subscription_id'] = $subId;
                        } else {
                            $db->prepare("UPDATE radius_subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$priorStatus, $subId]);
                            $message = $stkResult['message'] ?? 'Failed to send payment request. Use voucher instead.';
                            $messageType = 'warning';
                        }
                    }
                } catch (Exception $e) {
                    $db->prepare("UPDATE radius_subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$priorStatus ?? 'active', $subId ?? 0]);
                    $message = 'Payment service error. Please use a voucher instead.';
                    $messageType = 'warning';
                }
            }
            break;
            
        case 'add_device':
            // Customer adding another device to their subscription
            $phone = $_POST['phone'] ?? '';
            $newMAC = trim($_POST['new_mac'] ?? '');
            $deviceName = trim($_POST['device_name'] ?? '');
            
            if (empty($phone) || empty($newMAC)) {
                $message = 'Please provide phone number and device MAC address';
                $messageType = 'danger';
            } else {
                // Find subscription by phone number
                $stmt = $db->prepare("
                    SELECT rs.*, rp.max_devices 
                    FROM radius_subscriptions rs
                    JOIN radius_packages rp ON rs.package_id = rp.id
                    WHERE rs.phone = ? AND rs.access_type = 'hotspot' AND rs.status = 'active'
                    ORDER BY rs.created_at DESC LIMIT 1
                ");
                $stmt->execute([$phone]);
                $subByPhone = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$subByPhone) {
                    $message = 'No active subscription found for this phone number';
                    $messageType = 'danger';
                } else {
                    $result = $radiusBilling->addSubscriptionDevice(
                        $subByPhone['id'], 
                        strtoupper(preg_replace('/[^a-fA-F0-9:]/', '', $newMAC)),
                        $deviceName
                    );
                    if ($result['success']) {
                        $message = 'Device added successfully! You can now connect.';
                        $messageType = 'success';
                        $subscription = $radiusBilling->getSubscriptionByDeviceMAC($newMAC);
                        $deviceStatus = 'active';
                    } else {
                        $message = $result['error'];
                        $messageType = 'danger';
                    }
                }
            }
            break;
    }
}

// Handle MikroTik error messages
if ($errorMsg && empty($message)) {
    $errorMessages = [
        'chap-missing' => 'Authentication error. Please try again.',
        'invalid username or password' => 'Session expired. Please pay to reconnect.',
        'user already logged in' => 'You are already logged in on another device',
        'radius server is not responding' => 'Server temporarily unavailable. Please try again.',
    ];
    $message = $errorMessages[strtolower($errorMsg)] ?? $errorMsg;
    $messageType = 'danger';
}

// Helper: format validity
function formatDuration($hours) {
    $hours = (float)$hours;
    if ($hours < 1) return round($hours * 60) . ' min';
    if ($hours == 1) return '1 Hour';
    if ($hours < 24) return (int)$hours . ' Hours';
    if ($hours == 24) return '24 Hours';
    $days = $hours / 24;
    if ($days == 7) return '7 Days';
    if ($days == 30) return '30 Days';
    return (int)$days . ' Day' . ($days > 1 ? 's' : '');
}

function formatValidity($days, $pkg = null) {
    if (!empty($pkg['session_duration_hours'])) {
        return formatDuration($pkg['session_duration_hours']);
    }
    if (isset($pkg['validity_hours']) && $pkg['validity_hours'] > 0 && $pkg['validity_hours'] < 24) {
        return $pkg['validity_hours'] . ' hour' . ($pkg['validity_hours'] > 1 ? 's' : '');
    }
    if ($days == 1) return '24 Hours';
    if ($days == 7) return '7 Days';
    if ($days == 30) return '30 Days';
    return $days . ' day' . ($days > 1 ? 's' : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($ispName) ?> - WiFi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#050a18;min-height:100vh;font-family:'Inter',system-ui,-apple-system,sans-serif;color:#e2e8f0;overflow-x:hidden;-webkit-font-smoothing:antialiased}
        .page-bg{min-height:100vh;position:relative;padding-bottom:40px}
        .page-bg::before{content:'';position:fixed;top:0;left:0;right:0;bottom:0;background:radial-gradient(ellipse 80% 50% at 50% -20%,rgba(99,102,241,0.15),transparent),radial-gradient(ellipse 60% 40% at 80% 60%,rgba(139,92,246,0.08),transparent);pointer-events:none;z-index:0}

        .hero{padding:24px 20px 48px;text-align:center;position:relative;z-index:1}
        .brand-row{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:4px}
        .brand-logo{width:32px;height:32px;border-radius:8px;object-fit:cover;border:1px solid rgba(255,255,255,0.12)}
        .brand-icon{width:32px;height:32px;background:rgba(99,102,241,0.2);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#a5b4fc;border:1px solid rgba(99,102,241,0.3)}
        .brand-name{font-size:17px;font-weight:700;color:#fff;letter-spacing:-0.02em}
        .hero-sub{color:rgba(255,255,255,0.45);font-size:13px;font-weight:400}

        .main-content{max-width:420px;margin:-20px auto 0;padding:0 16px;position:relative;z-index:2}

        .glass-card{background:rgba(255,255,255,0.04);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,0.07);border-radius:20px;margin-bottom:14px;overflow:hidden}
        .glass-card-body{padding:20px}

        .alert-banner{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:12px;font-size:13px;line-height:1.5}
        .alert-warning-custom{background:rgba(251,191,36,0.1);color:#fbbf24;border:1px solid rgba(251,191,36,0.15)}
        .alert-danger-custom{background:rgba(239,68,68,0.1);color:#fca5a5;border:1px solid rgba(239,68,68,0.15)}
        .alert-success-custom{background:rgba(16,185,129,0.1);color:#6ee7b7;border:1px solid rgba(16,185,129,0.15)}
        .alert-info-custom{background:rgba(99,102,241,0.1);color:#a5b4fc;border:1px solid rgba(99,102,241,0.15)}
        .alert-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px}

        .section-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.3);margin-bottom:14px}

        .pkg-grid{display:flex;flex-direction:column;gap:10px}
        .pkg-card{background:rgba(255,255,255,0.035);border:1px solid rgba(255,255,255,0.07);border-radius:16px;padding:16px;transition:all 0.2s ease;cursor:pointer;position:relative;overflow:hidden}
        .pkg-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#6366f1,#a855f7);opacity:0;transition:opacity 0.2s}
        .pkg-card:hover{border-color:rgba(99,102,241,0.3);background:rgba(99,102,241,0.06)}
        .pkg-card:hover::before{opacity:1}
        .pkg-card:active{transform:scale(0.98)}
        .pkg-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px}
        .pkg-name{font-size:15px;font-weight:600;color:#f1f5f9}
        .pkg-price{font-size:20px;font-weight:800;color:#34d399;white-space:nowrap;letter-spacing:-0.02em}
        .pkg-price small{font-size:11px;font-weight:400;color:rgba(255,255,255,0.35)}
        .pkg-details{display:flex;gap:6px;flex-wrap:wrap}
        .pkg-tag{background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.55);padding:4px 8px;border-radius:6px;font-size:11px;font-weight:500;display:inline-flex;align-items:center;gap:4px;border:1px solid rgba(255,255,255,0.05)}
        .pkg-tag i{font-size:10px;color:#818cf8}
        .pkg-multi-badge{display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,rgba(99,102,241,0.15),rgba(139,92,246,0.15));color:#c4b5fd;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:600;margin-bottom:8px;border:1px solid rgba(139,92,246,0.15)}
        .pkg-multi-badge i{font-size:11px}
        .pkg-buy-btn{display:block;width:100%;margin-top:12px;padding:11px;background:linear-gradient(135deg,#6366f1,#7c3aed);color:white;border:none;border-radius:12px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;text-align:center;font-family:inherit;letter-spacing:-0.01em}
        .pkg-buy-btn:hover{box-shadow:0 8px 24px rgba(99,102,241,0.25);transform:translateY(-1px)}
        .pkg-card-faded{opacity:0.35;pointer-events:none}

        .form-input{width:100%;padding:13px 16px;border:1px solid rgba(255,255,255,0.1);border-radius:12px;font-size:15px;font-family:inherit;color:#f1f5f9;background:rgba(255,255,255,0.05);transition:all 0.2s;outline:none}
        .form-input:focus{border-color:rgba(99,102,241,0.5);background:rgba(99,102,241,0.08);box-shadow:0 0 0 3px rgba(99,102,241,0.1)}
        .form-input::placeholder{color:rgba(255,255,255,0.25)}

        .btn-main{width:100%;padding:14px;border:none;border-radius:12px;font-size:15px;font-weight:600;font-family:inherit;cursor:pointer;color:white;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:8px}
        .btn-main:hover{transform:translateY(-1px)}
        .btn-mpesa{background:linear-gradient(135deg,#059669,#047857)}
        .btn-mpesa:hover{box-shadow:0 8px 20px rgba(5,150,105,0.3)}
        .btn-voucher{background:linear-gradient(135deg,#d97706,#b45309)}
        .btn-voucher:hover{box-shadow:0 8px 20px rgba(217,119,6,0.3)}
        .btn-primary{background:linear-gradient(135deg,#6366f1,#7c3aed)}
        .btn-primary:hover{box-shadow:0 8px 20px rgba(99,102,241,0.3)}
        .btn-outline{background:transparent;border:1px solid rgba(255,255,255,0.12);color:rgba(255,255,255,0.6);padding:13px}
        .btn-outline:hover{border-color:rgba(99,102,241,0.4);color:#a5b4fc}

        .divider{display:flex;align-items:center;gap:12px;margin:18px 0;color:rgba(255,255,255,0.2);font-size:12px;font-weight:500}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,0.08)}

        .success-state{text-align:center;padding:28px 20px}
        .success-glow{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:36px;color:white;position:relative;animation:popIn 0.5s ease-out}
        .success-glow.green{background:linear-gradient(135deg,#059669,#10b981);box-shadow:0 0 40px rgba(16,185,129,0.3)}
        .success-glow.blue{background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 0 40px rgba(99,102,241,0.3)}
        @keyframes popIn{0%{transform:scale(0)}70%{transform:scale(1.1)}100%{transform:scale(1)}}
        .info-chip{background:rgba(255,255,255,0.06);padding:6px 14px;border-radius:100px;font-size:12px;color:rgba(255,255,255,0.6);display:inline-flex;align-items:center;gap:6px;margin:3px;border:1px solid rgba(255,255,255,0.06)}
        .info-chip i{color:#818cf8;font-size:12px}

        .expired-banner{background:linear-gradient(135deg,rgba(239,68,68,0.15),rgba(220,38,38,0.1));border:1px solid rgba(239,68,68,0.2);color:#fca5a5;padding:18px;border-radius:14px;text-align:center}
        .expired-banner h3{font-size:16px;font-weight:700;margin-bottom:2px;color:#fca5a5}
        .expired-banner p{font-size:12px;opacity:0.7;margin:0}

        .stk-waiting{text-align:center;padding:28px 20px}
        .spinner-ring{width:80px;height:80px;border-radius:50%;border:3px solid rgba(99,102,241,0.15);border-top-color:#6366f1;animation:spin 1s linear infinite;margin:0 auto 8px;display:flex;align-items:center;justify-content:center}
        .spinner-ring .inner{width:64px;height:64px;border-radius:50%;border:3px solid rgba(139,92,246,0.1);border-top-color:#a855f7;animation:spin 1.5s linear infinite reverse;display:flex;align-items:center;justify-content:center}
        .spinner-ring .inner .phone-icon{font-size:24px;color:#a5b4fc;animation:noneSpin 1.5s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        @keyframes noneSpin{to{transform:rotate(-360deg)}}
        .stk-dots{display:flex;justify-content:center;gap:6px;margin:16px 0}
        .stk-dots span{width:8px;height:8px;border-radius:50%;background:rgba(99,102,241,0.3);animation:dotPulse 1.4s ease-in-out infinite}
        .stk-dots span:nth-child(2){animation-delay:0.2s}
        .stk-dots span:nth-child(3){animation-delay:0.4s}
        @keyframes dotPulse{0%,80%,100%{background:rgba(99,102,241,0.2);transform:scale(0.8)}40%{background:#6366f1;transform:scale(1.2)}}
        @keyframes progressPulse{0%,100%{opacity:1}50%{opacity:0.5}}

        .modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:100;align-items:flex-end;justify-content:center}
        .modal-overlay.active{display:flex}
        .modal-sheet{background:#111827;border:1px solid rgba(255,255,255,0.08);border-radius:24px 24px 0 0;width:100%;max-width:420px;padding:24px;animation:slideUp 0.3s ease-out;max-height:90vh;overflow-y:auto}
        @keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
        .modal-handle{width:36px;height:4px;background:rgba(255,255,255,0.15);border-radius:2px;margin:0 auto 20px}
        .modal-pkg-summary{background:rgba(255,255,255,0.04);border-radius:14px;padding:16px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.07)}

        .footer{text-align:center;padding:24px 20px;color:rgba(255,255,255,0.2);font-size:11px;position:relative;z-index:1}
        .footer strong{color:rgba(255,255,255,0.35)}

        .add-device-section{background:rgba(255,255,255,0.03);border-radius:14px;padding:16px;border:1px solid rgba(255,255,255,0.06)}

        @media(max-width:380px){.hero{padding:20px 16px 44px}.brand-name{font-size:16px}.pkg-price{font-size:18px}}
    </style>
</head>
<body>
    <div class="page-bg">
        <?php if ($loginSuccess): ?>
        <?php 
        $mikrotikLoginUrl = '';
        $mikrotikLoginUser = '';
        $mikrotikLoginPass = '';
        $mikrotikDst = $linkOrig ?: '';
        $useChap = false;
        if (!empty($linkLoginOnly) && $subscription) {
            $mikrotikLoginUser = $subscription['username'] ?? $clientMAC;
            $plainPass = $subscription['password'] ?? $clientMAC;
            $mikrotikLoginUrl = $linkLoginOnly;
            
            if (!empty($chapId) && !empty($chapChallenge)) {
                $chapIdBin = stripcslashes($chapId);
                $chapChallengeBin = stripcslashes($chapChallenge);
                $chapHash = md5($chapIdBin . $plainPass . $chapChallengeBin);
                $mikrotikLoginPass = $chapHash;
                $useChap = true;
            } else {
                $mikrotikLoginPass = $plainPass;
            }
        }
        ?>
        <?php
        $subMaxDevices = (int)($subscription['max_devices'] ?? 1);
        $subDevices = [];
        $subDeviceCount = 1;
        if ($subscription && $subMaxDevices > 1) {
            $subDevices = $radiusBilling->getSubscriptionDevices($subscription['id']);
            $subDeviceCount = count($subDevices);
        }
        $canAddMore = $subMaxDevices > 1 && $subDeviceCount < $subMaxDevices;
        ?>
        <?php if (!empty($mikrotikLoginUrl)): ?>
        <form id="mikrotikLoginForm" method="POST" action="<?= htmlspecialchars($mikrotikLoginUrl) ?>" style="display:none;">
            <input type="hidden" name="username" value="<?= htmlspecialchars($mikrotikLoginUser) ?>">
            <input type="hidden" name="password" value="<?= htmlspecialchars($mikrotikLoginPass) ?>">
            <?php if (!empty($mikrotikDst)): ?>
            <input type="hidden" name="dst" value="<?= htmlspecialchars($mikrotikDst) ?>">
            <?php endif; ?>
        </form>
        <script>setTimeout(function(){document.getElementById('mikrotikLoginForm').submit();},<?= $canAddMore ? '4000' : '2000' ?>);</script>
        <?php endif; ?>
        
        <div class="hero">
            <div class="brand-row">
                <?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt="" class="brand-logo"><?php else: ?><div class="brand-icon"><i class="bi bi-wifi"></i></div><?php endif; ?>
                <span class="brand-name"><?= htmlspecialchars($ispName) ?></span>
            </div>
        </div>
        <div class="main-content">
            <div class="glass-card">
                <div class="success-state">
                    <div class="success-glow green"><i class="bi bi-wifi"></i></div>
                    <h2 style="font-size:22px;font-weight:800;margin-bottom:4px;color:#f1f5f9">You're Connected!</h2>
                    <p style="color:rgba(255,255,255,0.45);margin-bottom:16px;font-size:13px">Enjoy browsing the internet</p>
                    
                    <?php if ($subscription): ?>
                    <div style="margin-bottom:18px">
                        <span class="info-chip"><i class="bi bi-speedometer2"></i> <?= htmlspecialchars($subscription['download_speed'] ?? 'Unlimited') ?></span>
                        <?php if (!empty($subscription['expiry_date'])): ?>
                        <span class="info-chip"><i class="bi bi-clock"></i> <?= date('M j, g:i A', strtotime($subscription['expiry_date'])) ?></span>
                        <?php endif; ?>
                        <?php if ($subMaxDevices > 1): ?>
                        <span class="info-chip"><i class="bi bi-people"></i> <?= $subDeviceCount ?>/<?= $subMaxDevices ?> devices</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($mikrotikLoginUrl)): ?>
                    <p style="color:rgba(255,255,255,0.35);font-size:12px;margin-bottom:12px">Redirecting to network...</p>
                    <button type="button" onclick="document.getElementById('mikrotikLoginForm').submit();" class="btn-main btn-primary">
                        <i class="bi bi-arrow-right-circle"></i> Click Here if Not Redirected
                    </button>
                    <?php elseif (!empty($linkOrig)): ?>
                    <a href="<?= htmlspecialchars($linkOrig) ?>" class="btn-main btn-primary" style="text-decoration:none">
                        <i class="bi bi-globe"></i> Continue Browsing
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($canAddMore): ?>
            <div class="glass-card">
                <div class="glass-card-body">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
                        <div style="width:40px;height:40px;background:linear-gradient(135deg,rgba(99,102,241,0.2),rgba(139,92,246,0.2));border:1px solid rgba(99,102,241,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="bi bi-people-fill" style="color:#a5b4fc;font-size:18px"></i>
                        </div>
                        <div>
                            <div style="font-size:14px;font-weight:700;color:#f1f5f9">Add More Devices</div>
                            <div style="font-size:11px;color:rgba(255,255,255,0.4)"><?= $subMaxDevices ?> devices supported, <?= $subMaxDevices - $subDeviceCount ?> slots left</div>
                        </div>
                    </div>
                    
                    <div style="background:rgba(99,102,241,0.08);border-radius:10px;padding:10px 12px;margin-bottom:14px;border:1px solid rgba(99,102,241,0.12)">
                        <p style="font-size:12px;color:#a5b4fc;margin:0">
                            <i class="bi bi-info-circle"></i>
                            Share your phone number <strong style="color:#c4b5fd"><?= htmlspecialchars($subscription['customer_phone'] ?? '') ?></strong> with others to connect their devices.
                        </p>
                    </div>
                    
                    <?php if (!empty($subDevices)): ?>
                    <div style="margin-bottom:14px">
                        <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px">Connected Devices</div>
                        <?php foreach ($subDevices as $dev): ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:rgba(255,255,255,0.03);border-radius:10px;margin-bottom:5px;border:1px solid rgba(255,255,255,0.05)">
                            <i class="bi bi-phone" style="color:#818cf8"></i>
                            <div style="flex:1">
                                <div style="font-size:13px;font-weight:500;color:#e2e8f0"><?= htmlspecialchars($dev['device_name'] ?: 'Device') ?></div>
                                <div style="font-size:10px;color:rgba(255,255,255,0.3)"><?= htmlspecialchars($dev['mac_address']) ?></div>
                            </div>
                            <?php if ($dev['mac_address'] === strtoupper(preg_replace('/[^A-Fa-f0-9:]/', '', $clientMAC))): ?>
                            <span style="font-size:10px;background:rgba(16,185,129,0.15);color:#6ee7b7;padding:2px 8px;border-radius:6px;border:1px solid rgba(16,185,129,0.15)">This device</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <p style="font-size:11px;color:rgba(255,255,255,0.3);margin-bottom:0">
                        <i class="bi bi-lightbulb" style="color:#fbbf24"></i>
                        Other devices can connect via this WiFi page using <strong style="color:rgba(255,255,255,0.5)">"Already have a plan?"</strong>
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($stkPushSent): ?>
        <?php $pendingSubId = $_SESSION['pending_subscription_id'] ?? 0; ?>
        <div class="hero">
            <div class="brand-row">
                <?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt="" class="brand-logo"><?php else: ?><div class="brand-icon"><i class="bi bi-wifi"></i></div><?php endif; ?>
                <span class="brand-name"><?= htmlspecialchars($ispName) ?></span>
            </div>
        </div>
        <div class="main-content">
            <div class="glass-card" id="stkWaitingCard">
                <div class="stk-waiting">
                    <div class="spinner-ring">
                        <div class="inner">
                            <span class="phone-icon"><i class="bi bi-phone"></i></span>
                        </div>
                    </div>
                    <h2 style="font-size:20px;font-weight:700;margin:14px 0 6px;color:#f1f5f9">Check Your Phone</h2>
                    <p style="color:rgba(255,255,255,0.45);font-size:13px;margin-bottom:6px">Enter your M-Pesa PIN to complete payment</p>
                    
                    <div class="stk-dots"><span></span><span></span><span></span></div>
                    
                    <div class="alert-banner alert-info-custom" style="margin-bottom:16px;text-align:left" id="stkStatusBanner">
                        <div class="alert-icon" style="background:rgba(99,102,241,0.15)"><i class="bi bi-hourglass-split" id="stkStatusIcon"></i></div>
                        <div id="stkStatusText" style="font-size:12px">Waiting for payment confirmation...</div>
                    </div>
                    
                    <div id="stkProgressBar" style="background:rgba(255,255,255,0.06);border-radius:100px;height:4px;margin-bottom:18px;overflow:hidden">
                        <div id="stkProgressFill" style="background:linear-gradient(90deg,#6366f1,#a855f7);height:100%;width:0%;border-radius:100px;transition:width 3s linear"></div>
                    </div>
                    
                    <a href="<?= $_SERVER['REQUEST_URI'] ?>" class="btn-main btn-primary" style="text-decoration:none;display:none" id="stkRefreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh Page
                    </a>
                    
                    <div class="divider">or use voucher</div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="voucher">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        <div style="display:flex;gap:8px">
                            <input type="text" name="voucher_code" class="form-input" placeholder="Voucher code" style="text-transform:uppercase;flex:1">
                            <button type="submit" class="btn-main btn-voucher" style="width:auto;padding:13px 18px;font-size:13px">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="glass-card" id="stkSuccessCard" style="display:none">
                <div class="success-state">
                    <div class="success-glow green"><i class="bi bi-check-lg"></i></div>
                    <h2 style="font-size:22px;font-weight:800;margin-bottom:4px;color:#f1f5f9">Payment Received!</h2>
                    <p style="color:rgba(255,255,255,0.45);margin-bottom:16px;font-size:13px" id="stkSuccessPkg"></p>
                    <div style="margin-bottom:18px" id="stkSuccessInfo"></div>
                    <p style="color:rgba(255,255,255,0.3);font-size:12px;margin-bottom:10px">Connecting you to the internet...</p>
                    <div style="background:rgba(255,255,255,0.06);border-radius:100px;height:3px;overflow:hidden">
                        <div style="background:linear-gradient(90deg,#059669,#10b981);height:100%;width:100%;animation:progressPulse 1.5s ease-in-out infinite"></div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        (function() {
            var subId = <?= (int)$pendingSubId ?>;
            var mac = '<?= htmlspecialchars($clientMAC) ?>';
            var pollInterval = 3000;
            var maxPolls = 40;
            var pollCount = 0;
            var confirmed = false;
            
            function updateProgress() {
                var pct = Math.min((pollCount / maxPolls) * 100, 100);
                var fill = document.getElementById('stkProgressFill');
                if (fill) fill.style.width = pct + '%';
            }
            
            function showSuccess(data) {
                confirmed = true;
                document.getElementById('stkWaitingCard').style.display = 'none';
                var successCard = document.getElementById('stkSuccessCard');
                successCard.style.display = 'block';
                
                if (data.package_name) {
                    document.getElementById('stkSuccessPkg').textContent = data.package_name + ' activated!';
                }
                
                var infoHtml = '';
                if (data.download_speed) {
                    infoHtml += '<span class="info-chip"><i class="bi bi-speedometer2"></i> ' + data.download_speed + '</span>';
                }
                if (data.expiry_date) {
                    infoHtml += '<span class="info-chip"><i class="bi bi-clock"></i> ' + new Date(data.expiry_date).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}) + '</span>';
                }
                if (data.max_devices > 1) {
                    infoHtml += '<span class="info-chip"><i class="bi bi-people"></i> ' + data.max_devices + ' devices</span>';
                }
                document.getElementById('stkSuccessInfo').innerHTML = infoHtml;
                
                setTimeout(function() {
                    var dst = '<?= htmlspecialchars($linkOrig ?: "http://detectportal.firefox.com/") ?>';
                    if (dst.indexOf('http://') === 0) {
                        window.location.href = dst;
                    } else {
                        window.location.href = '<?= $_SERVER['REQUEST_URI'] ?>';
                    }
                }, 3000);
            }
            
            function showTimeout() {
                var banner = document.getElementById('stkStatusBanner');
                banner.className = 'alert-banner alert-warning-custom';
                banner.querySelector('.alert-icon').style.background = 'rgba(251,191,36,0.15)';
                document.getElementById('stkStatusIcon').className = 'bi bi-exclamation-triangle';
                document.getElementById('stkStatusText').innerHTML = 'Payment not yet confirmed. Tap <strong>Refresh</strong> to check again.';
                document.getElementById('stkRefreshBtn').style.display = 'flex';
                document.getElementById('stkProgressBar').style.display = 'none';
                var dots = document.querySelector('.stk-dots');
                if (dots) dots.style.display = 'none';
            }
            
            function pollStatus() {
                if (confirmed) return;
                pollCount++;
                updateProgress();
                
                var url = '/api/hotspot-payment-status.php?';
                if (subId) url += 'sid=' + subId + '&';
                if (mac) url += 'mac=' + encodeURIComponent(mac);
                
                fetch(url)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.activated) {
                            showSuccess(data);
                        } else if (data.status === 'error' && data.message === 'Unauthorized') {
                            showTimeout();
                        } else if (data.status === 'not_found' && pollCount > 10) {
                            showTimeout();
                        } else if (pollCount >= maxPolls) {
                            showTimeout();
                        } else {
                            setTimeout(pollStatus, pollInterval);
                        }
                    })
                    .catch(function() {
                        if (pollCount >= maxPolls) {
                            showTimeout();
                        } else {
                            setTimeout(pollStatus, pollInterval);
                        }
                    });
            }
            
            setTimeout(pollStatus, pollInterval);
        })();
        </script>

        <?php elseif ($deviceStatus === 'expired'): ?>
        <div class="hero">
            <div class="brand-row">
                <?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt="" class="brand-logo"><?php else: ?><div class="brand-icon"><i class="bi bi-wifi"></i></div><?php endif; ?>
                <span class="brand-name"><?= htmlspecialchars($ispName) ?></span>
            </div>
        </div>
        <div class="main-content">
            <?php if ($message): ?>
            <div class="glass-card"><div class="glass-card-body">
                <div class="alert-banner alert-<?= $messageType === 'danger' ? 'danger' : ($messageType === 'success' ? 'success' : 'info') ?>-custom">
                    <div class="alert-icon" style="background:<?= $messageType === 'danger' ? 'rgba(239,68,68,0.15)' : ($messageType === 'success' ? 'rgba(16,185,129,0.15)' : 'rgba(99,102,241,0.15)') ?>">
                        <i class="bi bi-<?= $messageType === 'danger' ? 'x-circle' : ($messageType === 'success' ? 'check-circle' : 'info-circle') ?>"></i>
                    </div>
                    <div><?= htmlspecialchars($message) ?></div>
                </div>
            </div></div>
            <?php endif; ?>
            
            <div class="glass-card">
                <div class="glass-card-body">
                    <div class="expired-banner" style="margin-bottom:18px">
                        <i class="bi bi-clock-history" style="font-size:28px;margin-bottom:6px;display:block"></i>
                        <h3>Subscription Expired</h3>
                        <p>Renew your plan to get back online</p>
                    </div>
                    
                    <?php if ($subscription): ?>
                    <div style="background:rgba(255,255,255,0.04);border-radius:12px;padding:14px;margin-bottom:16px;border:1px solid rgba(255,255,255,0.06)">
                        <div style="display:flex;justify-content:space-between;align-items:center">
                            <div>
                                <div style="font-weight:600;color:#e2e8f0;font-size:14px">Previous: <?= htmlspecialchars($subscription['package_name']) ?></div>
                                <div style="font-size:11px;color:rgba(255,255,255,0.35)"><?= htmlspecialchars($subscription['download_speed']) ?> / <?= !empty($subscription['session_duration_hours']) ? formatDuration($subscription['session_duration_hours']) : ($subscription['validity_days'] ?? '0') . ' days' ?></div>
                            </div>
                            <div class="pkg-price">KES <?= number_format($subscription['package_price'] ?? 0) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($packages)): ?>
                    <div class="section-label">Choose a Plan</div>
                    <div class="pkg-grid">
                    <?php foreach ($packages as $i => $pkg): ?>
                    <?php
                        $validityText = formatValidity($pkg['validity_days'] ?? 0, $pkg);
                        $hasData = !empty($pkg['data_quota_mb']);
                        $dataText = $hasData ? number_format($pkg['data_quota_mb'] / 1024, 1) . ' GB' : 'Unlimited';
                        $maxDevices = (int)($pkg['max_devices'] ?? 1);
                        $isCurrentPkg = $subscription && ($pkg['id'] == ($subscription['package_id'] ?? 0));
                    ?>
                    <div class="pkg-card" onclick="openExpiredPayment(<?= $pkg['id'] ?>, '<?= htmlspecialchars(addslashes($pkg['name'])) ?>', <?= $pkg['price'] ?>, '<?= htmlspecialchars(addslashes($pkg['download_speed'] ?? '')) ?>', '<?= htmlspecialchars($validityText) ?>', '<?= htmlspecialchars($dataText) ?>', <?= $maxDevices ?>)">
                        <?php if ($isCurrentPkg): ?>
                        <div style="position:absolute;top:8px;right:8px"><span class="pkg-tag" style="background:rgba(99,102,241,0.15);color:#a5b4fc;font-size:10px;border-color:rgba(99,102,241,0.2)">Previous Plan</span></div>
                        <?php endif; ?>
                        <?php if ($maxDevices > 1): ?>
                        <div class="pkg-multi-badge"><i class="bi bi-people-fill"></i> <?= $maxDevices ?> Devices</div>
                        <?php endif; ?>
                        <div class="pkg-top">
                            <div class="pkg-name"><?= htmlspecialchars($pkg['name']) ?></div>
                            <div class="pkg-price">KES <?= number_format($pkg['price']) ?></div>
                        </div>
                        <div class="pkg-details">
                            <span class="pkg-tag"><i class="bi bi-speedometer2"></i> <?= htmlspecialchars($pkg['download_speed'] ?? 'N/A') ?></span>
                            <span class="pkg-tag"><i class="bi bi-clock"></i> <?= $validityText ?></span>
                            <?php if ($hasData): ?>
                            <span class="pkg-tag"><i class="bi bi-database"></i> <?= $dataText ?></span>
                            <?php endif; ?>
                            <?php if ($maxDevices > 1): ?>
                            <span class="pkg-tag"><i class="bi bi-phone"></i> <?= $maxDevices ?> devices</span>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="pkg-buy-btn">
                            <i class="bi bi-cart-check"></i> <?= $isCurrentPkg ? 'Renew' : 'Buy Now' ?> - KES <?= number_format($pkg['price']) ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div id="expired-payment-modal" style="display:none;background:rgba(99,102,241,0.06);border-radius:14px;padding:16px;margin-top:14px;border:1px solid rgba(99,102,241,0.15)">
                        <h5 style="margin-bottom:12px;color:#e2e8f0;font-size:15px"><i class="bi bi-bag-check" style="color:#818cf8"></i> <span id="exp-pkg-name"></span></h5>
                        <div style="font-size:12px;color:rgba(255,255,255,0.4);margin-bottom:12px">
                            <span id="exp-pkg-speed"></span> | <span id="exp-pkg-validity"></span> | <span id="exp-pkg-data"></span>
                        </div>
                        <?php if ($mpesaEnabled): ?>
                        <form method="POST" style="margin-bottom:10px">
                            <input type="hidden" name="action" value="renew">
                            <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                            <input type="hidden" name="package_id" id="exp-package-id" value="">
                            <input type="tel" name="phone" class="form-input" placeholder="M-Pesa Phone (e.g., 0712345678)" value="<?= htmlspecialchars($subscription['customer_phone'] ?? '') ?>" required style="margin-bottom:12px">
                            <button type="submit" class="btn-main btn-mpesa">
                                <i class="bi bi-phone"></i> Pay KES <span id="exp-pkg-price"></span> via M-Pesa
                            </button>
                        </form>
                        <?php endif; ?>
                        <button type="button" onclick="document.getElementById('expired-payment-modal').style.display='none'" class="btn-main btn-outline" style="font-size:13px;padding:10px">Cancel</button>
                    </div>
                    
                    <div class="divider">or use voucher</div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="voucher">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        <input type="text" name="voucher_code" class="form-input" placeholder="Enter voucher code" style="text-transform:uppercase;margin-bottom:12px" required>
                        <button type="submit" class="btn-main btn-voucher">
                            <i class="bi bi-ticket-perforated"></i> Redeem Voucher
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="hero">
            <div class="brand-row">
                <?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt="" class="brand-logo"><?php else: ?><div class="brand-icon"><i class="bi bi-wifi"></i></div><?php endif; ?>
                <span class="brand-name"><?= htmlspecialchars($ispName) ?></span>
            </div>
            <p class="hero-sub"><?= htmlspecialchars($hotspotWelcome) ?></p>
        </div>
        <div class="main-content">
            <?php if ($message): ?>
            <div class="glass-card"><div class="glass-card-body">
                <div class="alert-banner alert-<?= $messageType === 'danger' ? 'danger' : ($messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'info')) ?>-custom">
                    <div class="alert-icon" style="background:<?= $messageType === 'danger' ? 'rgba(239,68,68,0.15)' : ($messageType === 'success' ? 'rgba(16,185,129,0.15)' : ($messageType === 'warning' ? 'rgba(251,191,36,0.15)' : 'rgba(99,102,241,0.15)')) ?>">
                        <i class="bi bi-<?= $messageType === 'danger' ? 'x-circle' : ($messageType === 'success' ? 'check-circle' : 'info-circle') ?>"></i>
                    </div>
                    <div><?= htmlspecialchars($message) ?></div>
                </div>
            </div></div>
            <?php endif; ?>
            
            <?php if (empty($clientMAC)): ?>
            <div class="glass-card"><div class="glass-card-body">
                <div class="alert-banner alert-warning-custom">
                    <div class="alert-icon" style="background:rgba(251,191,36,0.15)"><i class="bi bi-wifi-off"></i></div>
                    <div>
                        <strong>Device not detected</strong><br>
                        <span style="font-size:12px;opacity:0.8">Connect to <strong><?= htmlspecialchars($ispName) ?></strong> WiFi first, then this page loads automatically.</span>
                    </div>
                </div>
            </div></div>
            <?php endif; ?>
            
            <?php if (!empty($packages)): ?>
            <div class="glass-card">
                <div class="glass-card-body">
                    <div class="section-label">
                        <?= !empty($clientMAC) ? 'Choose a Plan' : 'Available Plans' ?>
                    </div>
                    
                    <div class="pkg-grid">
                    <?php foreach ($packages as $i => $pkg): ?>
                    <?php
                        $validityText = formatValidity($pkg['validity_days'], $pkg);
                        $hasData = !empty($pkg['data_quota_mb']);
                        $dataText = $hasData ? number_format($pkg['data_quota_mb'] / 1024, 1) . ' GB' : 'Unlimited';
                        $maxDevices = (int)($pkg['max_devices'] ?? 1);
                    ?>
                    <div class="pkg-card <?= empty($clientMAC) ? 'pkg-card-faded' : '' ?>" <?= !empty($clientMAC) ? 'onclick="openPayment(' . $pkg['id'] . ', \'' . htmlspecialchars(addslashes($pkg['name'])) . '\', ' . $pkg['price'] . ', \'' . htmlspecialchars(addslashes($pkg['download_speed'] ?? '')) . '\', \'' . htmlspecialchars($validityText) . '\', \'' . htmlspecialchars($dataText) . '\', ' . $maxDevices . ')"' : '' ?>>
                        <?php if ($maxDevices > 1): ?>
                        <div class="pkg-multi-badge"><i class="bi bi-people-fill"></i> <?= $maxDevices ?> Devices</div>
                        <?php endif; ?>
                        <div class="pkg-top">
                            <div class="pkg-name"><?= htmlspecialchars($pkg['name']) ?></div>
                            <div class="pkg-price">KES <?= number_format($pkg['price']) ?></div>
                        </div>
                        <div class="pkg-details">
                            <span class="pkg-tag"><i class="bi bi-speedometer2"></i> <?= htmlspecialchars($pkg['download_speed'] ?? 'N/A') ?></span>
                            <span class="pkg-tag"><i class="bi bi-clock"></i> <?= $validityText ?></span>
                            <?php if ($hasData): ?>
                            <span class="pkg-tag"><i class="bi bi-database"></i> <?= $dataText ?></span>
                            <?php endif; ?>
                            <?php if ($maxDevices > 1): ?>
                            <span class="pkg-tag"><i class="bi bi-phone"></i> <?= $maxDevices ?> devices</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($clientMAC)): ?>
                        <button type="button" class="pkg-buy-btn">
                            <i class="bi bi-cart-check"></i> Buy Now - KES <?= number_format($pkg['price']) ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="glass-card"><div class="glass-card-body">
                <div class="alert-banner alert-info-custom">
                    <div class="alert-icon" style="background:rgba(99,102,241,0.15)"><i class="bi bi-info-circle"></i></div>
                    <div>No packages available for this hotspot.</div>
                </div>
            </div></div>
            <?php endif; ?>
            
            <?php if (!empty($clientMAC)): ?>
            <div class="glass-card">
                <div class="glass-card-body">
                    <div class="section-label">Have a Voucher?</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="voucher">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        <input type="text" name="voucher_code" class="form-input" placeholder="Enter voucher code" style="text-transform:uppercase;margin-bottom:12px" required>
                        <button type="submit" class="btn-main btn-voucher">
                            <i class="bi bi-ticket-perforated"></i> Redeem Voucher
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="glass-card">
                <div class="glass-card-body">
                    <div class="add-device-section">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                            <div style="width:38px;height:38px;background:linear-gradient(135deg,rgba(99,102,241,0.2),rgba(139,92,246,0.2));border:1px solid rgba(99,102,241,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="bi bi-phone-fill" style="color:#a5b4fc;font-size:16px"></i>
                            </div>
                            <div>
                                <div style="font-size:14px;font-weight:700;color:#f1f5f9">Already have a plan?</div>
                                <div style="font-size:11px;color:rgba(255,255,255,0.35)">Add this device to your existing subscription</div>
                            </div>
                        </div>
                        <p style="font-size:11px;color:rgba(255,255,255,0.3);margin-bottom:12px;background:rgba(99,102,241,0.06);padding:10px 12px;border-radius:10px;border:1px solid rgba(99,102,241,0.1)">
                            <i class="bi bi-info-circle" style="color:#818cf8"></i>
                            If someone shared their plan with you, enter their registered phone number below to connect this device.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_device">
                            <input type="hidden" name="new_mac" value="<?= htmlspecialchars($clientMAC) ?>">
                            <input type="tel" name="phone" class="form-input" placeholder="Registered phone number" required style="margin-bottom:8px;font-size:14px">
                            <input type="text" name="device_name" class="form-input" placeholder="Device name (e.g., My Phone)" style="margin-bottom:10px;font-size:14px">
                            <button type="submit" class="btn-main btn-primary" style="font-size:14px;padding:12px">
                                <i class="bi bi-plus-circle"></i> Add This Device
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <i class="bi bi-shield-check"></i>
            Powered by <strong><?= htmlspecialchars($ispName) ?></strong>
            <?php if (!empty($mpesaPaybill)): ?>
            <br>Paybill: <?= htmlspecialchars($mpesaPaybill) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($clientMAC)): ?>
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-sheet">
            <div class="modal-handle"></div>
            <h3 style="font-size:17px;font-weight:700;margin-bottom:16px;color:#f1f5f9">Complete Purchase</h3>
            
            <div class="modal-pkg-summary">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <div id="modalPkgName" style="font-weight:600;color:#e2e8f0;font-size:14px"></div>
                        <div id="modalPkgDetails" style="font-size:11px;color:rgba(255,255,255,0.4);margin-top:4px"></div>
                    </div>
                    <div id="modalPkgPrice" class="pkg-price"></div>
                </div>
                <div id="modalMultiDevice" style="display:none;margin-top:12px;background:rgba(99,102,241,0.08);border-radius:10px;padding:10px 14px;border:1px solid rgba(99,102,241,0.12)">
                    <div style="display:flex;align-items:center;gap:8px">
                        <i class="bi bi-people-fill" style="color:#818cf8;font-size:15px"></i>
                        <div>
                            <div style="font-size:12px;font-weight:600;color:#a5b4fc" id="modalDeviceText"></div>
                            <div style="font-size:10px;color:rgba(255,255,255,0.35)">You can add more devices after purchase</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST" id="paymentForm">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                <input type="hidden" name="package_id" id="modalPackageId">
                
                <label style="font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);display:block;margin-bottom:8px">M-Pesa Phone Number</label>
                <input type="tel" name="phone" class="form-input" id="phoneInput" placeholder="e.g., 0712345678" required style="margin-bottom:16px">
                
                <button type="submit" class="btn-main btn-mpesa" style="margin-bottom:10px">
                    <i class="bi bi-phone"></i> Pay with M-Pesa
                </button>
                <button type="button" class="btn-main btn-outline" onclick="closePayment()">Cancel</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function openExpiredPayment(pkgId, name, price, speed, validity, data, maxDevices) {
        document.getElementById('exp-package-id').value = pkgId;
        document.getElementById('exp-pkg-name').textContent = name;
        document.getElementById('exp-pkg-price').textContent = price.toLocaleString();
        document.getElementById('exp-pkg-speed').textContent = speed;
        document.getElementById('exp-pkg-validity').textContent = validity;
        document.getElementById('exp-pkg-data').textContent = data;
        var modal = document.getElementById('expired-payment-modal');
        modal.style.display = 'block';
        modal.scrollIntoView({behavior: 'smooth', block: 'center'});
    }

    function openPayment(pkgId, name, price, speed, validity, data, maxDevices) {
        document.getElementById('modalPackageId').value = pkgId;
        document.getElementById('modalPkgName').textContent = name;
        document.getElementById('modalPkgPrice').textContent = 'KES ' + price.toLocaleString();
        document.getElementById('modalPkgDetails').textContent = speed + ' \u00b7 ' + validity + (data !== 'Unlimited' ? ' \u00b7 ' + data : '');
        var multiEl = document.getElementById('modalMultiDevice');
        if (maxDevices > 1) {
            multiEl.style.display = 'block';
            document.getElementById('modalDeviceText').textContent = 'Supports up to ' + maxDevices + ' devices';
        } else {
            multiEl.style.display = 'none';
        }
        document.getElementById('paymentModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(function() { document.getElementById('phoneInput').focus(); }, 300);
    }
    
    function closePayment() {
        document.getElementById('paymentModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    
    var pm = document.getElementById('paymentModal');
    if (pm) pm.addEventListener('click', function(e) { if (e.target === this) closePayment(); });
    </script>
</body>
</html>
