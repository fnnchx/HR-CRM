<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            $request_id = $_GET['request_id'] ?? 0;
            
            if ($request_id <= 0) {
                echo json_encode([]);
                break;
            }
            
            $sql = "
                SELECT h.*, u.login as user_name, CONCAT(u.first_name, ' ', u.last_name) as full_name
                FROM staff_requests_history h
                LEFT JOIN users u ON h.user_id = u.id
                WHERE h.request_id = ?
                ORDER BY h.created_at DESC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$request_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['request_id']) || empty($data['action'])) {
                echo json_encode(['success' => false, 'error' => 'Не указаны обязательные поля']);
                break;
            }
            
            $sql = "INSERT INTO staff_requests_history (request_id, action, comment, user_id) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['request_id'],
                $data['action'],
                $data['comment'] ?? null,
                $data['user_id'] ?? 1
            ]);
            
            echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>