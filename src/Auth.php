<?php

namespace App;

class Auth {
    private static array $userPermissions = [];
    
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(string $identifier, string $password): bool {
        $db = \Database::getConnection();
        
        $rolesTableExists = false;
        try {
            $check = $db->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'roles')");
            $rolesTableExists = $check->fetchColumn();
        } catch (\PDOException $e) {
            $rolesTableExists = false;
        }
        
        // Build all possible phone variants for Kenya numbers
        $phoneVariants = self::getKenyaPhoneVariants($identifier);
        
        // Build phone IN clause with proper column reference
        $phoneClauseRoles = '';  // For query with roles table (uses u. prefix)
        $phoneClauseSimple = ''; // For query without roles table
        
        if (count($phoneVariants) > 0) {
            $inPlaceholders = implode(',', array_fill(0, count($phoneVariants), '?'));
            $phoneClauseRoles = ' OR u.phone IN (' . $inPlaceholders . ')';
            $phoneClauseSimple = ' OR phone IN (' . $inPlaceholders . ')';
        }
        
        if ($rolesTableExists) {
            $stmt = $db->prepare("
                SELECT u.*, r.name as role_name, r.display_name as role_display_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.email = ?" . $phoneClauseRoles . "
            ");
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?" . $phoneClauseSimple);
        }
        
        $stmt->execute(array_merge([$identifier], $phoneVariants));
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role_name'] ?? $user['role'] ?? 'admin';
            $_SESSION['user_role_id'] = $user['role_id'] ?? null;
            $_SESSION['user_email'] = $user['email'];
            
            if ($rolesTableExists && !empty($user['role_id'])) {
                self::loadPermissions($user['role_id']);
            } else {
                $_SESSION['permissions'] = [];
            }
            self::regenerateToken();
            return true;
        }
        return false;
    }

    public static function logout(): void {
        $_SESSION = [];
        session_destroy();
    }
    
    /**
     * Generate all possible Kenya phone number variants for matching
     * Handles: 07xxx, +254xxx, 254xxx formats
     */
    public static function getKenyaPhoneVariants(string $input): array {
        // Remove spaces, dashes, and parentheses
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $input);
        
        // If it's an email (contains @), return empty array
        if (strpos($cleaned, '@') !== false) {
            return [];
        }
        
        // If it doesn't look like a phone number, return just the cleaned version
        if (!preg_match('/^[\+]?[0-9]{9,15}$/', $cleaned)) {
            return [$cleaned];
        }
        
        $variants = [$cleaned];
        
        // Extract the base 9 digits (after country code)
        $baseDigits = null;
        
        if (preg_match('/^\+254(\d{9})$/', $cleaned, $m)) {
            // Input: +254712345678
            $baseDigits = $m[1];
        } elseif (preg_match('/^254(\d{9})$/', $cleaned, $m)) {
            // Input: 254712345678
            $baseDigits = $m[1];
        } elseif (preg_match('/^0(\d{9})$/', $cleaned, $m)) {
            // Input: 0712345678
            $baseDigits = $m[1];
        } elseif (preg_match('/^(\d{9})$/', $cleaned, $m)) {
            // Input: 712345678 (just 9 digits)
            $baseDigits = $m[1];
        }
        
        if ($baseDigits) {
            // Generate all variants
            $variants = array_unique([
                $cleaned,
                '0' . $baseDigits,           // 0712345678
                '254' . $baseDigits,         // 254712345678
                '+254' . $baseDigits,        // +254712345678
            ]);
        }
        
        return array_values($variants);
    }

    public static function check(): bool {
        self::init();
        return isset($_SESSION['user_id']);
    }
    
    public static function isLoggedIn(): bool {
        return self::check();
    }

    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role'],
            'role_id' => $_SESSION['user_role_id'] ?? null,
            'email' => $_SESSION['user_email']
        ];
    }

    public static function userId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function isAdmin(): bool {
        $role = $_SESSION['user_role'] ?? '';
        return in_array($role, ['admin', 'administrator']);
    }
    
    public static function hasRole(string $role): bool {
        return ($_SESSION['user_role'] ?? '') === $role;
    }
    
    private static function loadPermissions(?int $roleId): void {
        if (!$roleId) {
            $_SESSION['permissions'] = [];
            return;
        }
        
        $db = \Database::getConnection();
        $stmt = $db->prepare("
            SELECT p.name 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$roleId]);
        $_SESSION['permissions'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    
    public static function can(string $permission): bool {
        if (!self::check()) {
            return false;
        }
        
        if (self::isAdmin()) {
            return true;
        }
        
        $permissions = $_SESSION['permissions'] ?? [];
        
        if (in_array($permission, $permissions)) {
            return true;
        }
        
        $parts = explode('.', $permission);
        if (count($parts) === 2) {
            $categoryWildcard = $parts[0] . '.*';
            if (in_array($categoryWildcard, $permissions)) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function canAny(array $permissions): bool {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }
        return false;
    }
    
    public static function canAll(array $permissions): bool {
        foreach ($permissions as $permission) {
            if (!self::can($permission)) {
                return false;
            }
        }
        return true;
    }
    
    public static function requirePermission(string $permission): void {
        self::requireLogin();
        if (!self::can($permission)) {
            header('Location: ?page=dashboard&error=access_denied');
            exit;
        }
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
    
    public static function getPermissions(): array {
        return $_SESSION['permissions'] ?? [];
    }
    
    public static function refreshPermissions(): void {
        $roleId = $_SESSION['user_role_id'] ?? null;
        if ($roleId) {
            self::loadPermissions($roleId);
        }
    }
}
