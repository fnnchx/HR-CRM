<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            $agency_id = $_GET['agency_id'] ?? 0;
            
            if ($agency_id) {
                $stmt = $conn->prepare("SELECT * FROM requisites WHERE agency_id = ? ORDER BY valid_from DESC");
                $stmt->execute([$agency_id]);
            } else {
                $stmt = $conn->query("SELECT r.*, a.name as agency_name FROM requisites r JOIN agencies a ON r.agency_id = a.id ORDER BY r.valid_from DESC");
            }
            
            $requisites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($requisites);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['is_current']) && $data['is_current']) {
                $stmt = $conn->prepare("UPDATE requisites SET valid_to = DATE_SUB(?, INTERVAL 1 DAY) WHERE agency_id = ? AND valid_to IS NULL");
                $stmt->execute([$data['valid_from'], $data['agency_id']]);
            }
            
            $sql = "INSERT INTO requisites 
                    (agency_id, bank_name, unp, okpo, bic, account_number, legal_address, director_name, valid_from, valid_to) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['agency_id'],
                $data['bank_name'] ?? null,
                $data['unp'] ?? null,
                $data['okpo'] ?? null,
                $data['bic'] ?? null,
                $data['account_number'] ?? null,
                $data['legal_address'] ?? null,
                $data['director_name'] ?? null,
                $data['valid_from'],
                $data['valid_to'] ?? null
            ]);
            
            echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            break;
            
        default:
            echo json_encode(['error' => 'Метод не поддерживается']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>