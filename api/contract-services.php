<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            $contract_id = $_GET['contract_id'] ?? 0;
            
            $sql = "SELECT cs.*, s.name as service_name 
                    FROM contract_services cs 
                    JOIN services s ON cs.service_id = s.id 
                    WHERE cs.contract_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$contract_id]);
            echo json_encode($stmt->fetchAll());
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $sql = "INSERT INTO contract_services (contract_id, service_id, price, quantity, description) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['contract_id'],
                $data['service_id'],
                $data['price'],
                $data['quantity'],
                $data['description'] ?? null
            ]);
            
            echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            break;
            
        case 'DELETE':
            $id = $_GET['id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM contract_services WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>