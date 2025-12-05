<?php

namespace App;

use PDO;

class Reports {
    private PDO $db;

    public function __construct() {
        $this->db = \Database::getConnection();
    }

    public function getTicketStats(array $filters = []): array {
        $dateWhere = "";
        $params = [];

        if (!empty($filters['date_from'])) {
            $dateWhere .= " AND t.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $dateWhere .= " AND t.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                COUNT(CASE WHEN t.status = 'open' THEN 1 END) as open_tickets,
                COUNT(CASE WHEN t.status = 'in_progress' THEN 1 END) as in_progress_tickets,
                COUNT(CASE WHEN t.status = 'resolved' THEN 1 END) as resolved_tickets,
                COUNT(CASE WHEN t.status = 'closed' THEN 1 END) as closed_tickets,
                COUNT(CASE WHEN t.priority = 'critical' THEN 1 END) as critical_tickets,
                COUNT(CASE WHEN t.sla_response_breached = true THEN 1 END) as sla_breached,
                AVG(EXTRACT(EPOCH FROM (COALESCE(t.resolved_at, NOW()) - t.created_at))/3600) as avg_resolution_hours
            FROM tickets t
            WHERE 1=1 $dateWhere
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTicketsByUser(array $filters = []): array {
        $dateWhere = "";
        $params = [];

        if (!empty($filters['date_from'])) {
            $dateWhere .= " AND t.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $dateWhere .= " AND t.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare("
            SELECT 
                u.id as user_id,
                u.name as user_name,
                COUNT(*) as assigned_count,
                COUNT(CASE WHEN t.status = 'resolved' THEN 1 END) as resolved_count,
                COUNT(CASE WHEN t.status = 'in_progress' THEN 1 END) as in_progress_count,
                COUNT(CASE WHEN t.sla_response_breached = true THEN 1 END) as sla_breached_count,
                ROUND(AVG(EXTRACT(EPOCH FROM (COALESCE(t.resolved_at, NOW()) - t.created_at))/3600)::numeric, 1) as avg_resolution_hours
            FROM tickets t
            JOIN users u ON t.assigned_to = u.id
            WHERE t.assigned_to IS NOT NULL $dateWhere
            GROUP BY u.id, u.name
            ORDER BY assigned_count DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderStats(array $filters = []): array {
        $dateWhere = "";
        $params = [];

        if (!empty($filters['date_from'])) {
            $dateWhere .= " AND o.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $dateWhere .= " AND o.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN o.order_status = 'new' THEN 1 END) as new_orders,
                COUNT(CASE WHEN o.order_status = 'confirmed' THEN 1 END) as confirmed_orders,
                COUNT(CASE WHEN o.order_status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN o.order_status = 'cancelled' THEN 1 END) as cancelled_orders,
                COALESCE(SUM(o.amount), 0) as total_revenue,
                COUNT(CASE WHEN o.payment_status = 'paid' THEN 1 END) as paid_orders
            FROM orders o
            WHERE 1=1 $dateWhere
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getOrdersBySalesperson(array $filters = []): array {
        $dateWhere = "";
        $params = [];

        if (!empty($filters['date_from'])) {
            $dateWhere .= " AND o.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $dateWhere .= " AND o.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare("
            SELECT 
                s.id as salesperson_id,
                s.name as salesperson_name,
                COUNT(*) as order_count,
                COUNT(CASE WHEN o.order_status = 'completed' THEN 1 END) as completed_count,
                COALESCE(SUM(o.amount), 0) as total_sales,
                COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.amount ELSE 0 END), 0) as paid_amount
            FROM orders o
            JOIN salespersons s ON o.salesperson_id = s.id
            WHERE o.salesperson_id IS NOT NULL $dateWhere
            GROUP BY s.id, s.name
            ORDER BY total_sales DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getComplaintStats(array $filters = []): array {
        $dateWhere = "";
        $params = [];

        if (!empty($filters['date_from'])) {
            $dateWhere .= " AND c.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $dateWhere .= " AND c.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_complaints,
                COUNT(CASE WHEN c.status = 'pending' THEN 1 END) as pending_complaints,
                COUNT(CASE WHEN c.status = 'approved' THEN 1 END) as approved_complaints,
                COUNT(CASE WHEN c.status = 'rejected' THEN 1 END) as rejected_complaints,
                COUNT(CASE WHEN c.status = 'converted' THEN 1 END) as converted_complaints
            FROM complaints c
            WHERE 1=1 $dateWhere
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getComplaintsByReviewer(array $filters = []): array {
        $dateWhere = "";
        $params = [];

        if (!empty($filters['date_from'])) {
            $dateWhere .= " AND c.reviewed_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $dateWhere .= " AND c.reviewed_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare("
            SELECT 
                u.id as user_id,
                u.name as user_name,
                COUNT(*) as reviewed_count,
                COUNT(CASE WHEN c.status = 'approved' THEN 1 END) as approved_count,
                COUNT(CASE WHEN c.status = 'rejected' THEN 1 END) as rejected_count,
                COUNT(CASE WHEN c.status = 'converted' THEN 1 END) as converted_count
            FROM complaints c
            JOIN users u ON c.reviewed_by = u.id
            WHERE c.reviewed_by IS NOT NULL $dateWhere
            GROUP BY u.id, u.name
            ORDER BY reviewed_count DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserSummary(array $filters = []): array {
        $dateFrom = $filters['date_from'] ?? date('Y-m-01');
        $dateTo = $filters['date_to'] ?? date('Y-m-d');

        $sql = "
            SELECT 
                u.id,
                u.name,
                u.email,
                r.name as role_name,
                (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.id AND t.created_at BETWEEN ? AND ?) as tickets_assigned,
                (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.id AND t.status = 'resolved' AND t.resolved_at BETWEEN ? AND ?) as tickets_resolved,
                (SELECT COUNT(*) FROM complaints c WHERE c.reviewed_by = u.id AND c.reviewed_at BETWEEN ? AND ?) as complaints_reviewed,
                (SELECT COUNT(*) FROM ticket_comments tc WHERE tc.user_id = u.id AND tc.created_at BETWEEN ? AND ?) as comments_added,
                (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id AND al.created_at BETWEEN ? AND ?) as total_activities
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY total_activities DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDailyActivity(array $filters = []): array {
        $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $filters['date_to'] ?? date('Y-m-d');

        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as activity_count
            FROM activity_logs
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopActions(array $filters = [], int $limit = 10): array {
        $dateWhere = "";
        $params = [];

        if (!empty($filters['date_from'])) {
            $dateWhere .= " AND created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $dateWhere .= " AND created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $params[] = $limit;

        $stmt = $this->db->prepare("
            SELECT action_type, entity_type, COUNT(*) as count
            FROM activity_logs
            WHERE 1=1 $dateWhere
            GROUP BY action_type, entity_type
            ORDER BY count DESC
            LIMIT ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllUsers(): array {
        $stmt = $this->db->query("SELECT id, name, email FROM users ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
