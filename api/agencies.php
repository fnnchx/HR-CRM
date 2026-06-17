<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['id']) && $_GET['id'] > 0) {
                $id = $_GET['id'];
                
                $stmt = $conn->prepare("CALL sp_agencies_get_by_id(?)");
                $stmt->execute([$id]);
                $agency = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt->closeCursor();
                
                echo json_encode($agency);
            } 
            elseif (isset($_GET['active']) && $_GET['active'] == 1) {
                $stmt = $conn->prepare("CALL sp_agencies_get_active()");
                $stmt->execute();
                $agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                
                echo json_encode($agencies);
            }
            elseif (isset($_GET['search']) && $_GET['search']) {
                $search = $_GET['search'];
                
                $stmt = $conn->prepare("CALL sp_agencies_search(?)");
                $stmt->execute([$search]);
                $agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                
                echo json_encode($agencies);
            }

            else {
                $stmt = $conn->prepare("CALL sp_agencies_get_all()");
                $stmt->execute();
                $agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                
                echo json_encode($agencies);
            }
            break;
        
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['name'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Название агентства обязательно'
                ]);
                exit();
            }
            
            $stmt = $conn->prepare("CALL sp_agencies_insert(?, ?, ?, ?, ?, ?, @new_id)");
            $stmt->execute([
                $data['name'],
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['contact_person'] ?? null,
                $data['contact_phone'] ?? null,
                $data['is_active'] ?? 1
            ]);
            
            $stmt = $conn->query("SELECT @new_id as new_id");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $newId = $result['new_id'];
            $stmt->closeCursor();
            
            echo json_encode([
                'success' => true,
                'id' => $newId,
                'message' => 'Агентство добавлено'
            ]);
            break;
        
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'ID агентства обязательно'
                ]);
                exit();
            }
            
            if (empty($data['name'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Название агентства обязательно'
                ]);
                exit();
            }
            
            $stmt = $conn->prepare("CALL sp_agencies_update(?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['id'],
                $data['name'],
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['contact_person'] ?? null,
                $data['contact_phone'] ?? null,
                $data['is_active'] ?? 1
            ]);
            $stmt->closeCursor();
            
            echo json_encode([
                'success' => true,
                'message' => 'Агентство обновлено'
            ]);
            break;
        
        case 'DELETE':
            $id = $_GET['id'] ?? 0;
            
            if ($id <= 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Неверный ID агентства'
                ]);
                exit();
            }
            
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM contracts WHERE agency_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            if ($result['cnt'] > 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Невозможно удалить агентство, так как у него есть договоры'
                ]);
                exit();
            }
            
            $stmt = $conn->prepare("CALL sp_agencies_delete(?)");
            $stmt->execute([$id]);
            $stmt->closeCursor();
            
            echo json_encode([
                'success' => true,
                'message' => 'Агентство удалено'
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