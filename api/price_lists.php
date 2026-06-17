<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            $agency_id = $_GET['agency_id'] ?? 0;
            $date = $_GET['date'] ?? date('Y-m-d');
            
            if ($agency_id <= 0) {
                echo json_encode(['error' => 'Агентство не указано']);
                break;
            }
            
            $sql = "
                SELECT price, valid_from, valid_to
                FROM price_lists 
                WHERE agency_id = ? 
                  AND valid_from <= ? 
                  AND (valid_to IS NULL OR valid_to >= ?)
                  AND is_active = 1
                ORDER BY valid_from DESC
                LIMIT 1
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$agency_id, $date, $date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['error' => 'Метод не поддерживается']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>