<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['id']) && $_GET['id'] > 0) {
                $id = $_GET['id'];
                $stmt = $conn->prepare("
                    SELECT es.*, a.name as agency_name, o.name as organization_name
                    FROM external_staff es
                    LEFT JOIN agencies a ON es.agency_id = a.id
                    LEFT JOIN organizations o ON es.organization_id = o.id
                    WHERE es.id = ?
                ");
                $stmt->execute([$id]);
                $staff = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($staff);
            }
            else {
                $sql = "
                    SELECT es.*, 
                           a.name as agency_name, 
                           o.name as organization_name,
                           (SELECT COUNT(*) FROM work_schedules WHERE external_staff_id = es.id AND status = 'confirmed') as work_days
                    FROM external_staff es
                    LEFT JOIN agencies a ON es.agency_id = a.id
                    LEFT JOIN organizations o ON es.organization_id = o.id
                    WHERE 1=1
                ";
                
                $params = [];
                
                if (isset($_GET['agency_id']) && $_GET['agency_id'] > 0) {
                    $sql .= " AND es.agency_id = :agency_id";
                    $params[':agency_id'] = $_GET['agency_id'];
                }
                
                if (isset($_GET['organization_id']) && $_GET['organization_id'] > 0) {
                    $sql .= " AND es.organization_id = :organization_id";
                    $params[':organization_id'] = $_GET['organization_id'];
                }
                
                if (isset($_GET['is_active'])) {
                    $sql .= " AND es.is_active = :is_active";
                    $params[':is_active'] = $_GET['is_active'];
                }
                
                if (isset($_GET['search']) && $_GET['search']) {
                    $sql .= " AND (es.full_name LIKE :search OR es.position LIKE :search OR es.department LIKE :search)";
                    $params[':search'] = '%' . $_GET['search'] . '%';
                }
                
                $sql .= " ORDER BY es.full_name";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($staff);
            }
            break;
        
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['full_name'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'ФИО сотрудника обязательно'
                ]);
                exit();
            }
            
            if (empty($data['agency_id'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Агентство обязательно'
                ]);
                exit();
            }
            
            if (empty($data['organization_id'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Организация обязательна'
                ]);
                exit();
            }
            
            $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(staff_number, 4) AS UNSIGNED)) as max_num FROM external_staff");
            $max = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_num = ($max['max_num'] ?? 0) + 1;
            $staff_number = 'ES-' . str_pad($next_num, 6, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                INSERT INTO external_staff (
                    staff_number, full_name, position, department, category,
                    agency_id, organization_id, phone, email,
                    passport_series, passport_number, passport_issued_by, passport_issued_date,
                    tax_id, social_security_number, birth_date, education, skills,
                    hire_date, notes, is_active, created_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $staff_number,
                $data['full_name'],
                $data['position'] ?? null,
                $data['department'] ?? null,
                $data['category'] ?? null,
                $data['agency_id'],
                $data['organization_id'],
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['passport_series'] ?? null,
                $data['passport_number'] ?? null,
                $data['passport_issued_by'] ?? null,
                $data['passport_issued_date'] ?? null,
                $data['tax_id'] ?? null,
                $data['social_security_number'] ?? null,
                $data['birth_date'] ?? null,
                $data['education'] ?? null,
                $data['skills'] ?? null,
                $data['hire_date'] ?? date('Y-m-d'),
                $data['notes'] ?? null,
                $data['is_active'] ?? 1,
                $data['created_by'] ?? 1
            ]);
            
            echo json_encode([
                'success' => true,
                'id' => $conn->lastInsertId(),
                'staff_number' => $staff_number,
                'message' => 'Сотрудник добавлен'
            ]);
            break;
        
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'ID сотрудника обязательно'
                ]);
                exit();
            }
            
            $updateFields = [];
            $params = [':id' => $data['id']];
            
            $allowedFields = [
                'full_name', 'position', 'department', 'category', 'agency_id', 'organization_id',
                'phone', 'email', 'passport_series', 'passport_number', 'passport_issued_by',
                'passport_issued_date', 'tax_id', 'social_security_number', 'birth_date',
                'education', 'skills', 'hire_date', 'fire_date', 'notes', 'is_active'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            $updateFields[] = "updated_by = :updated_by";
            $params[':updated_by'] = $data['updated_by'] ?? 1;
            
            if (empty($updateFields)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Нет данных для обновления'
                ]);
                exit();
            }
            
            $sql = "UPDATE external_staff SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'Данные сотрудника обновлены'
            ]);
            break;
        
        case 'DELETE':
            $id = $_GET['id'] ?? 0;
            
            if ($id <= 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Неверный ID сотрудника'
                ]);
                exit();
            }
            
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM work_schedules WHERE external_staff_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['cnt'] > 0) {
                $stmt = $conn->prepare("UPDATE external_staff SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode([
                    'success' => true,
                    'message' => 'Сотрудник деактивирован (есть записи в графике)'
                ]);
            } else {
                $stmt = $conn->prepare("DELETE FROM external_staff WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode([
                    'success' => true,
                    'message' => 'Сотрудник удален'
                ]);
            }
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