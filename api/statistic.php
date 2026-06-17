<?php

require_once 'config.php';

$type = $_GET['type'] ?? '';

try {
    switch($type) {
        case 'dashboard':
            $stats = [];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM agencies");
            $stats['total_agencies'] = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM contracts");
            $stats['total_contracts'] = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM contracts WHERE status = 'active'");
            $stats['active_contracts'] = $stmt->fetch()['count'];
            
            $stmt = $conn->query("
                SELECT COUNT(*) as count 
                FROM contracts 
                WHERE status = 'active' 
                  AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ");
            $stats['expiring_soon'] = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT SUM(total_amount) as total FROM contracts");
            $stats['total_amount'] = $stmt->fetch()['total'] ?? 0;
            
            echo json_encode($stats);
            break;
            
        case 'monthly':
            $year = 2026;
            $sql = "
                SELECT 
                    MONTH(signing_date) as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(total_amount) as amount
                FROM contracts
                WHERE YEAR(signing_date) = $year
                GROUP BY MONTH(signing_date)
                ORDER BY month
            ";
            $stmt = $conn->query($sql);
            echo json_encode($stmt->fetchAll());
            break;
            
        default:
            echo json_encode(['error' => 'Не указан тип статистики']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>