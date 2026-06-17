<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['id']) && $_GET['id'] > 0) {
                $id = $_GET['id'];
                $sql = "
                    SELECT sr.*, 
                           o.name as organization_name, 
                           a.name as agency_name,
                           CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                           (SELECT COUNT(*) FROM external_staff WHERE agency_id = sr.agency_id AND position = sr.position AND is_active = 1) as available_staff_count
                    FROM staff_requests sr
                    LEFT JOIN organizations o ON sr.organization_id = o.id
                    LEFT JOIN agencies a ON sr.agency_id = a.id
                    LEFT JOIN users u ON sr.created_by = u.id
                    WHERE sr.id = ?
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Добавляем информацию о доступных сотрудниках
                if ($request) {
                    // Проверяем наличие сотрудников нужной должности
                    $stmt2 = $conn->prepare("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                            SUM(CASE WHEN is_active = 1 AND id NOT IN (
                                SELECT DISTINCT external_staff_id FROM work_schedules 
                                WHERE schedule_date BETWEEN ? AND ? AND status IN ('pending', 'confirmed')
                            ) THEN 1 ELSE 0 END) as available
                        FROM external_staff 
                        WHERE agency_id = ? AND position = ? AND is_active = 1
                    ");
                    $stmt2->execute([
                        $request['start_date'],
                        $request['end_date'] ?? $request['start_date'],
                        $request['agency_id'],
                        $request['position']
                    ]);
                    $staff_stats = $stmt2->fetch(PDO::FETCH_ASSOC);
                    
                    $request['staff_stats'] = $staff_stats;
                }
                
                echo json_encode($request);
                break;
            }
            
            // Получение списка заявок
            $role = $_GET['role'] ?? '';
            $user_id = $_GET['user_id'] ?? 0;
            $agency_id = $_GET['agency_id'] ?? 0;
            $organization_id = $_GET['organization_id'] ?? 0;

            $sql = "
                SELECT sr.*, 
                       o.name as organization_name, 
                       a.name as agency_name,
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM staff_requests sr
                LEFT JOIN organizations o ON sr.organization_id = o.id
                LEFT JOIN agencies a ON sr.agency_id = a.id
                LEFT JOIN users u ON sr.created_by = u.id
                WHERE 1=1
            ";

            $params = [];
            
            if ($role === 'shop_manager' && $organization_id) {
                $sql .= " AND sr.organization_id = :org_id";
                $params[':org_id'] = $organization_id;
            } elseif ($role === 'agency' && $agency_id) {
                $sql .= " AND sr.agency_id = :agency_id";
                $params[':agency_id'] = $agency_id;
            } elseif ($organization_id) {
                $sql .= " AND sr.organization_id = :org_id";
                $params[':org_id'] = $organization_id;
            }

            $sql .= " ORDER BY sr.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['organization_id']) || empty($data['agency_id']) || empty($data['position']) || empty($data['start_date'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Заполните все обязательные поля'
                ]);
                exit();
            }
            
            // Генерация номера заявки
            $year = date('Y');
            $month = date('m');
            $stmt = $conn->prepare("SELECT COUNT(*) + 1 as seq FROM staff_requests WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?");
            $stmt->execute([$year, $month]);
            $seq = $stmt->fetch(PDO::FETCH_ASSOC)['seq'];
            $request_number = 'Z-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            
            $sql = "INSERT INTO staff_requests (
                request_number, organization_id, agency_id, position, department, category, 
                quantity, priority, start_date, end_date, notes, status, created_by
            ) VALUES (
                :request_number, :organization_id, :agency_id, :position, :department, :category,
                :quantity, :priority, :start_date, :end_date, :notes, 'sent', :created_by
            )";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':request_number' => $request_number,
                ':organization_id' => $data['organization_id'],
                ':agency_id' => $data['agency_id'],
                ':position' => $data['position'],
                ':department' => $data['department'] ?? null,
                ':category' => $data['category'] ?? null,
                ':quantity' => $data['quantity'] ?? 1,
                ':priority' => $data['priority'] ?? 'normal',
                ':start_date' => $data['start_date'],
                ':end_date' => $data['end_date'] ?? null,
                ':notes' => $data['notes'] ?? null,
                ':created_by' => $data['created_by'] ?? 1
            ]);
            
            $request_id = $conn->lastInsertId();
            
            // Запись в историю
            $stmt = $conn->prepare("INSERT INTO staff_requests_history (request_id, action, comment, user_id) VALUES (?, 'created', 'Заявка создана', ?)");
            $stmt->execute([$request_id, $data['created_by'] ?? 1]);
            
            echo json_encode([
                'success' => true,
                'id' => $request_id,
                'request_number' => $request_number,
                'message' => 'Заявка создана'
            ]);
            break;
        
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'ID заявки обязательно'
                ]);
                exit();
            }
            
            $updateFields = [];
            $params = [':id' => $data['id']];
            
            if (isset($data['status'])) {
                $updateFields[] = "status = :status";
                $params[':status'] = $data['status'];
                
                // Запись в историю
                $statusText = '';
                switch($data['status']) {
                    case 'confirmed': $statusText = 'Заявка подтверждена'; break;
                    case 'rejected': $statusText = 'Заявка отклонена: ' . ($data['rejected_reason'] ?? 'без причины'); break;
                    case 'completed': $statusText = 'Заявка выполнена'; break;
                    case 'cancelled': $statusText = 'Заявка отменена'; break;
                    default: $statusText = 'Статус изменен на ' . $data['status'];
                }
                
                $stmt = $conn->prepare("INSERT INTO staff_requests_history (request_id, action, comment, user_id) VALUES (?, 'status_change', ?, ?)");
                $stmt->execute([$data['id'], $statusText, $data['updated_by'] ?? 1]);
            }
            
            if (isset($data['rejected_reason'])) {
                $updateFields[] = "rejected_reason = :rejected_reason";
                $params[':rejected_reason'] = $data['rejected_reason'];
            }
            
            if (isset($data['confirmed_by'])) {
                $updateFields[] = "confirmed_by = :confirmed_by";
                $updateFields[] = "confirmed_at = NOW()";
                $params[':confirmed_by'] = $data['confirmed_by'];
            }
            
            if (isset($data['agency_response'])) {
                $updateFields[] = "agency_response = :agency_response";
                $params[':agency_response'] = $data['agency_response'];
            }
            
            if (empty($updateFields)) {
                echo json_encode(['success' => false, 'error' => 'Нет данных для обновления']);
                exit();
            }
            
            $sql = "UPDATE staff_requests SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'Заявка обновлена'
            ]);
            break;
        
        case 'DELETE':
            $id = $_GET['id'] ?? 0;
            
            if ($id <= 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Неверный ID заявки'
                ]);
                exit();
            }
            
            // Проверка наличия связанных записей
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM work_schedules WHERE request_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['cnt'] > 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Невозможно удалить заявку, так как есть связанный график работы'
                ]);
                exit();
            }
            
            $stmt = $conn->prepare("DELETE FROM staff_requests WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Заявка удалена'
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