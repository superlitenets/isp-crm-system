<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$db = \Database::getConnection();
$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$validToken = false;
$userEmail = '';

if (!empty($token)) {
    $stmt = $db->prepare("SELECT id, email, name FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $validToken = true;
        $userEmail = $user['email'];
    } else {
        $message = 'This reset link has expired or is invalid. Please contact your administrator.';
        $messageType = 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['token'])) {
    $postToken = $_POST['token'];
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters.';
        $messageType = 'danger';
        $validToken = true;
        $token = $postToken;
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'danger';
        $validToken = true;
        $token = $postToken;
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt->execute([$postToken]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $stmt->execute([$hash, $user['id']]);
            $message = 'Password updated successfully! You can now log in with your new password.';
            $messageType = 'success';
            $validToken = false;
        } else {
            $message = 'This reset link has expired. Please contact your administrator.';
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ISP CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
        }
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .reset-header i {
            font-size: 3rem;
            color: #4e73df;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="reset-header">
            <i class="bi bi-shield-lock"></i>
            <h4 class="mt-2">Reset Your Password</h4>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> mb-3">
            <?= htmlspecialchars($message) ?>
            <?php if ($messageType === 'success'): ?>
            <hr>
            <a href="/" class="btn btn-primary btn-sm">Go to Login</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($validToken): ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" name="password" required minlength="6" placeholder="Min 6 characters">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control" name="confirm_password" required minlength="6" placeholder="Re-enter password">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-circle me-1"></i> Set New Password
            </button>
        </form>
        <?php elseif (empty($message)): ?>
        <div class="alert alert-warning">
            No reset token provided. Please use the link sent to you via SMS or WhatsApp.
        </div>
        <?php endif; ?>

        <div class="text-center mt-3">
            <a href="/" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
        </div>
    </div>
</body>
</html>
