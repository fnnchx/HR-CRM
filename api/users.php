<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $conn->prepare("
                    SELECT id, login, email, first_name, last_name, phone, role, organization_id, agency_id, is_active, created_at 
                    FROM users WHERE id = ?
                ");
                $stmt->execute([$_GET['id']]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            } else {
                $stmt = $conn->query("
                    SELECT id, login, email, first_name, last_name, phone, role, organization_id, agency_id, is_active, created_at 
                    FROM users ORDER BY id
                ");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $sql = "INSERT INTO users (login, email, password_hash, first_name, last_name, phone, role, organization_id, agency_id, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['login'],
                $data['email'],
                $data['password'], 
                $data['first_name'] ?? null,
                $data['last_name'] ?? null,
                $data['phone'] ?? null,
                $data['role'] ?? 'hr_specialist',
                $data['organization_id'] ?? null,
                $data['agency_id'] ?? null,
                $data['is_active'] ?? 1
            ]);
            
            echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['password']) && $data['password']) {
                $sql = "UPDATE users SET login=?, email=?, password_hash=?, first_name=?, last_name=?, phone=?, role=?, organization_id=?, agency_id=?, is_active=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['login'],
                    $data['email'],
                    $data['password'], 
                    $data['first_name'] ?? null,
                    $data['last_name'] ?? null,
                    $data['phone'] ?? null,
                    $data['role'],
                    $data['organization_id'] ?? null,
                    $data['agency_id'] ?? null,
                    $data['is_active'],
                    $data['id']
                ]);
            } else {
                $sql = "UPDATE users SET login=?, email=?, first_name=?, last_name=?, phone=?, role=?, organization_id=?, agency_id=?, is_active=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['login'],
                    $data['email'],
                    $data['first_name'] ?? null,
                    $data['last_name'] ?? null,
                    $data['phone'] ?? null,
                    $data['role'],
                    $data['organization_id'] ?? null,
                    $data['agency_id'] ?? null,
                    $data['is_active'],
                    $data['id']
                ]);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            $id = $_GET['id'] ?? 0;
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
            $admins = $stmt->fetch();
            
            $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if ($user && $user['role'] == 'admin' && $admins['count'] <= 1) {
                echo json_encode(['success' => false, 'error' => 'Нельзя удалить последнего администратора']);
                exit();
            }
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>