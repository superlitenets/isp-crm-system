<?php

namespace App;

class Auth {
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(string $email, string $password): bool {
        $db = \Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            self::regenerateToken();
            return true;
        }
        return false;
    }

    public static function logout(): void {
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool {
        self::init();
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role'],
            'email' => $_SESSION['user_email']
        ];
    }

    public static function userId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function isAdmin(): bool {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    public static function generateToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function regenerateToken(): void {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function validateToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: ?page=login');
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ?page=dashboard&error=access_denied');
            exit;
        }
    }
}
