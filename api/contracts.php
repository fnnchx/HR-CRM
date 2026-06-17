<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            $sql = "
                SELECT c.*, a.name as agency_name 
                FROM contracts c 
                LEFT JOIN agencies a ON c.agency_id = a.id 
                ORDER BY c.created_at DESC
            ";
            
            if (isset($_GET['status']) && $_GET['status']) {
                $sql = "
                    SELECT c.*, a.name as agency_name 
                    FROM contracts c 
                    LEFT JOIN agencies a ON c.agency_id = a.id 
                    WHERE c.status = '{$_GET['status']}'
                    ORDER BY c.created_at DESC
                ";
            }
            
            if (isset($_GET['agency_id']) && $_GET['agency_id']) {
                $sql = "
                    SELECT c.*, a.name as agency_name 
                    FROM contracts c 
                    LEFT JOIN agencies a ON c.agency_id = a.id 
                    WHERE c.agency_id = {$_GET['agency_id']}
                    ORDER BY c.created_at DESC
                ";
            }
            
            $stmt = $conn->query($sql);
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($contracts);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $year = date('Y');
            $month = date('m');
            $stmt = $conn->query("SELECT COUNT(*) as count FROM contracts WHERE YEAR(created_at) = $year AND MONTH(created_at) = $month");
            $count = $stmt->fetch()['count'] + 1;
            $number = "Д-$year-$month-" . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            $sql = "INSERT INTO contracts (number, agency_id, user_id, signing_date, expiration_date, status, total_amount, terms, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $number,
                $data['agency_id'],
                $data['user_id'],
                $data['signing_date'] ?? date('Y-m-d'),
                $data['expiration_date'] ?? null,
                $data['status'] ?? 'active',
                $data['total_amount'] ?? 0,
                $data['terms'] ?? null,
                $data['notes'] ?? null
            ]);
            
            $contractId = $conn->lastInsertId();
            
            if (isset($data['services']) && is_array($data['services'])) {
                $sql = "INSERT INTO contract_services (contract_id, service_id, price, quantity, description) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                foreach ($data['services'] as $service) {
                    $stmt->execute([
                        $contractId,
                        $service['service_id'],
                        $service['price'],
                        $service['quantity'] ?? 1,
                        $service['description'] ?? null
                    ]);
                }
            }
            
            echo json_encode([
                'success' => true,
                'id' => $contractId,
                'number' => $number,
                'message' => 'Договор создан'
            ]);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $sql = "UPDATE contracts SET 
                    agency_id = ?, 
                    signing_date = ?, 
                    expiration_date = ?, 
                    status = ?, 
                    total_amount = ?,
                    terms = ?,
                    notes = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['agency_id'],
                $data['signing_date'] ?? null,
                $data['expiration_date'] ?? null,
                $data['status'],
                $data['total_amount'],
                $data['terms'] ?? null,
                $data['notes'] ?? null,
                $data['id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Договор обновлен'
            ]);
            break;
            
        case 'DELETE':
            $id = $_GET['id'] ?? 0;
            
            $sql = "DELETE FROM contracts WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Договор удален'
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>