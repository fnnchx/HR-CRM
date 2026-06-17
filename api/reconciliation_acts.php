<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['id']) && $_GET['id'] > 0) {
                $stmt = $conn->prepare("
                    SELECT ra.*, a.name as agency_name, o.name as organization_name
                    FROM reconciliation_acts ra
                    LEFT JOIN agencies a ON ra.agency_id = a.id
                    LEFT JOIN organizations o ON ra.organization_id = o.id
                    WHERE ra.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
                break;
            }
            
            $organization_id = $_GET['organization_id'] ?? 0;
            $sql = "
                SELECT ra.*, a.name as agency_name, o.name as organization_name
                FROM reconciliation_acts ra
                LEFT JOIN agencies a ON ra.agency_id = a.id
                LEFT JOIN organizations o ON ra.organization_id = o.id
                WHERE 1=1
            ";
            if ($organization_id > 0) {
                $sql .= " AND ra.organization_id = :org_id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':org_id' => $organization_id]);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->execute();
            }
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $year = date('Y');
            $stmt = $conn->prepare("SELECT COUNT(*) + 1 as seq FROM reconciliation_acts WHERE YEAR(created_at) = ?");
            $stmt->execute([$year]);
            $seq = $stmt->fetch(PDO::FETCH_ASSOC)['seq'];
            $act_number = 'A-' . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(TIMESTAMPDIFF(HOUR, actual_start, actual_end)), 0) as total_hours
                FROM work_schedules ws
                JOIN external_staff es ON ws.external_staff_id = es.id
                WHERE es.organization_id = ? 
                    AND es.agency_id = ?
                    AND ws.schedule_date BETWEEN ? AND ?
                    AND ws.status = 'confirmed'
            ");
            $stmt->execute([
                $data['organization_id'],
                $data['agency_id'],
                $data['period_from'],
                $data['period_to']
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_hours = $result['total_hours'];
            
            $stmt = $conn->prepare("
                SELECT price FROM price_lists 
                WHERE agency_id = ? AND valid_from <= ? AND (valid_to IS NULL OR valid_to >= ?)
                ORDER BY valid_from DESC LIMIT 1
            ");
            $stmt->execute([$data['agency_id'], $data['period_to'], $data['period_to']]);
            $price_result = $stmt->fetch(PDO::FETCH_ASSOC);
            $hourly_rate = $price_result['price'] ?? 0;
            $total_amount = $total_hours * $hourly_rate;
            
            $sql = "INSERT INTO reconciliation_acts (
                act_number, organization_id, agency_id, period_from, period_to, 
                total_hours, total_amount, notes, status, created_by
            ) VALUES (
                :act_number, :organization_id, :agency_id, :period_from, :period_to,
                :total_hours, :total_amount, :notes, 'draft', :created_by
            )";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':act_number' => $act_number,
                ':organization_id' => $data['organization_id'],
                ':agency_id' => $data['agency_id'],
                ':period_from' => $data['period_from'],
                ':period_to' => $data['period_to'],
                ':total_hours' => $total_hours,
                ':total_amount' => $total_amount,
                ':notes' => $data['notes'] ?? null,
                ':created_by' => $data['created_by'] ?? 1
            ]);
            
            echo json_encode(['success' => true, 'id' => $conn->lastInsertId(), 'act_number' => $act_number]);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID акта не указан']);
                break;
            }
            
            $sql = "UPDATE reconciliation_acts SET status = :status";
            $params = [':status' => $data['status'], ':id' => $data['id']];
            
            if ($data['status'] === 'confirmed') {
                $sql .= ", confirmed_by = :confirmed_by, confirmed_at = NOW()";
                $params[':confirmed_by'] = $data['confirmed_by'] ?? 1;
            }
            
            $sql .= " WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>