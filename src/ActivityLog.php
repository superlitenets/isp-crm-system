<?php

namespace App;

use PDO;

class ActivityLog {
    private PDO $db;

    public function __construct() {
        $this->db = \Database::getConnection();
    }

    public function log(string $actionType, string $entityType, ?int $entityId = null, ?string $entityReference = null, $details = null): bool {
        if (!Auth::isLoggedIn()) {
            return false;
        }

        $jsonDetails = null;
        if ($details !== null) {
            if (is_string($details)) {
                $jsonDetails = json_encode(['message' => $details]);
            } elseif (is_array($details)) {
                $jsonDetails = json_encode($details);
            } else {
                $jsonDetails = json_encode(['data' => $details]);
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (user_id, action_type, entity_type, entity_id, entity_reference, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $actionType,
            $entityType,
            $entityId,
            $entityReference,
            $jsonDetails,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }

    public function getActivities(array $filters = []): array {
        $sql = "
            SELECT al.*, u.name as user_name, u.email as user_email
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action_type'])) {
            $sql .= " AND al.action_type = ?";
            $params[] = $filters['action_type'];
        }

        if (!empty($filters['entity_type'])) {
            $sql .= " AND al.entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (al.entity_reference ILIKE ? OR al.details ILIKE ? OR u.name ILIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }

        $sql .= " ORDER BY al.created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        if (!empty($filters['offset'])) {
            $sql .= " OFFSET " . (int)$filters['offset'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countActivities(array $filters = []): int {
        $sql = "SELECT COUNT(*) FROM activity_logs al WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action_type'])) {
            $sql .= " AND al.action_type = ?";
            $params[] = $filters['action_type'];
        }

        if (!empty($filters['entity_type'])) {
            $sql .= " AND al.entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getUserActivityStats(?int $userId = null, ?string $dateFrom = null, ?string $dateTo = null): array {
        $sql = "
            SELECT 
                al.user_id,
                u.name as user_name,
                al.action_type,
                al.entity_type,
                COUNT(*) as count
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($userId) {
            $sql .= " AND al.user_id = ?";
            $params[] = $userId;
        }

        if ($dateFrom) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $sql .= " GROUP BY al.user_id, u.name, al.action_type, al.entity_type ORDER BY count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActionTypes(): array {
        $stmt = $this->db->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getEntityTypes(): array {
        $stmt = $this->db->query("SELECT DISTINCT entity_type FROM activity_logs ORDER BY entity_type");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getRecentByEntity(string $entityType, int $entityId, int $limit = 10): array {
        $stmt = $this->db->prepare("
            SELECT al.*, u.name as user_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.entity_type = ? AND al.entity_id = ?
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$entityType, $entityId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
