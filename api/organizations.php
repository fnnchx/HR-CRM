<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['id']) && $_GET['id'] > 0) {
                $id = $_GET['id'];
                $stmt = $conn->prepare("SELECT * FROM organizations WHERE id = ?");
                $stmt->execute([$id]);
                $organization = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($organization);
            }
            elseif (isset($_GET['active']) && $_GET['active'] == 1) {
                $stmt = $conn->prepare("SELECT * FROM organizations WHERE is_active = 1 ORDER BY name");
                $stmt->execute();
                $organizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($organizations);
            }
            else {
                $stmt = $conn->prepare("
                    SELECT o.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as manager_name
                    FROM organizations o
                    LEFT JOIN users u ON o.manager_id = u.id
                    ORDER BY o.name
                ");
                $stmt->execute();
                $organizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($organizations);
            }
            break;
        
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['name'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Название организации обязательно'
                ]);
                exit();
            }
            
            $stmt = $conn->prepare("
                INSERT INTO organizations (name, short_name, legal_name, unp, okpo, address, legal_address, phone, email, director_name, manager_id, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['short_name'] ?? null,
                $data['legal_name'] ?? null,
                $data['unp'] ?? null,
                $data['okpo'] ?? null,
                $data['address'] ?? null,
                $data['legal_address'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['director_name'] ?? null,
                $data['manager_id'] ?? null,
                $data['is_active'] ?? 1
            ]);
            
            echo json_encode([
                'success' => true,
                'id' => $conn->lastInsertId(),
                'message' => 'Организация добавлена'
            ]);
            break;
        
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'ID организации обязательно'
                ]);
                exit();
            }
            
            $stmt = $conn->prepare("
                UPDATE organizations 
                SET name = ?,
                    short_name = ?,
                    legal_name = ?,
                    unp = ?,
                    okpo = ?,
                    address = ?,
                    legal_address = ?,
                    phone = ?,
                    email = ?,
                    director_name = ?,
                    manager_id = ?,
                    is_active = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['short_name'] ?? null,
                $data['legal_name'] ?? null,
                $data['unp'] ?? null,
                $data['okpo'] ?? null,
                $data['address'] ?? null,
                $data['legal_address'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['director_name'] ?? null,
                $data['manager_id'] ?? null,
                $data['is_active'] ?? 1,
                $data['id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Организация обновлена'
            ]);
            break;
        
        case 'DELETE':
            $id = $_GET['id'] ?? 0;
            
            if ($id <= 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Неверный ID организации'
                ]);
                exit();
            }
            
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM staff_requests WHERE organization_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['cnt'] > 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Невозможно удалить организацию, так как есть связанные заявки'
                ]);
                exit();
            }
            
            $stmt = $conn->prepare("DELETE FROM organizations WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Организация удалена'
            ]);
            break;
        
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Метод не поддерживается'
            ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка сервера: ' . $e->getMessage()
    ]);
}
?>