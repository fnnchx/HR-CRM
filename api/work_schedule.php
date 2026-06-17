<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['request_id']) && $_GET['request_id'] > 0) {
                $request_id = $_GET['request_id'];
                $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
                $date_to = $_GET['date_to'] ?? date('Y-m-d', strtotime('+60 days'));
                
                if (isset($_GET['group_by']) && $_GET['group_by'] === 'staff') {
                    $sql = "
                        SELECT 
                            es.id as staff_id,
                            es.full_name,
                            es.position,
                            COUNT(DISTINCT ws.id) as total_days,
                            SUM(CASE WHEN ws.status = 'confirmed' THEN 1 ELSE 0 END) as worked_days
                        FROM work_schedules ws
                        JOIN external_staff es ON ws.external_staff_id = es.id
                        WHERE ws.request_id = ?
                        GROUP BY es.id, es.full_name, es.position
                        ORDER BY es.full_name
                    ";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$request_id]);
                    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                    break;
                }
                
                $sql = "
                    SELECT ws.*, es.full_name, es.position
                    FROM work_schedules ws
                    JOIN external_staff es ON ws.external_staff_id = es.id
                    WHERE ws.request_id = ? AND ws.schedule_date BETWEEN ? AND ?
                    ORDER BY ws.schedule_date, ws.planned_start
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$request_id, $date_from, $date_to]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
            }
            
            $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
            $organization_id = isset($_GET['organization_id']) ? intval($_GET['organization_id']) : 0;
            $staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
            
            if ($date === 'today') {
                $date = date('Y-m-d');
            }
            if ($date === 'tomorrow') {
                $date = date('Y-m-d', strtotime('+1 day'));
            }
            
            if (isset($_GET['date_from']) && isset($_GET['date_to'])) {
                $date_from = $_GET['date_from'];
                $date_to = $_GET['date_to'];
                
                $sql = "
                    SELECT ws.*, 
                           es.full_name as staff_name, 
                           es.position,
                           es.phone,
                           a.name as agency_name,
                           o.name as organization_name
                    FROM work_schedules ws
                    INNER JOIN external_staff es ON ws.external_staff_id = es.id
                    LEFT JOIN agencies a ON es.agency_id = a.id
                    LEFT JOIN organizations o ON es.organization_id = o.id
                    WHERE ws.schedule_date BETWEEN :date_from AND :date_to
                ";
                $params = [':date_from' => $date_from, ':date_to' => $date_to];
                
                if ($organization_id > 0) {
                    $sql .= " AND es.organization_id = :organization_id";
                    $params[':organization_id'] = $organization_id;
                }
                
                $sql .= " ORDER BY ws.schedule_date, ws.planned_start";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
            }
            
            $sql = "
                SELECT ws.*, 
                       es.full_name as staff_name, 
                       es.position,
                       es.phone,
                       a.name as agency_name,
                       o.name as organization_name
                FROM work_schedules ws
                INNER JOIN external_staff es ON ws.external_staff_id = es.id
                LEFT JOIN agencies a ON es.agency_id = a.id
                LEFT JOIN organizations o ON es.organization_id = o.id
                WHERE ws.schedule_date = :date
            ";
            
            $params = [':date' => $date];
            
            if ($organization_id > 0) {
                $sql .= " AND es.organization_id = :organization_id";
                $params[':organization_id'] = $organization_id;
            }
            
            if ($staff_id > 0) {
                $sql .= " AND ws.external_staff_id = :staff_id";
                $params[':staff_id'] = $staff_id;
            }
            
            $sql .= " ORDER BY ws.planned_start ASC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($result);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['external_staff_id']) || empty($data['schedule_date'])) {
                echo json_encode(['success' => false, 'error' => 'Не указаны обязательные поля']);
                break;
            }
            
            $stmt = $conn->prepare("SELECT id FROM work_schedules WHERE external_staff_id = ? AND schedule_date = ?");
            $stmt->execute([$data['external_staff_id'], $data['schedule_date']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'График на эту дату уже существует']);
                break;
            }
            
            $sql = "INSERT INTO work_schedules (external_staff_id, request_id, schedule_date, planned_start, planned_end, notes) 
                    VALUES (:external_staff_id, :request_id, :schedule_date, :planned_start, :planned_end, :notes)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':external_staff_id' => $data['external_staff_id'],
                ':request_id' => $data['request_id'] ?? null,
                ':schedule_date' => $data['schedule_date'],
                ':planned_start' => $data['planned_start'] ?? null,
                ':planned_end' => $data['planned_end'] ?? null,
                ':notes' => $data['notes'] ?? null
            ]);
            
            echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID записи не указан']);
                break;
            }
            
            $updateFields = [];
            $params = [':id' => $data['id']];
            
            if (isset($data['actual_start'])) {
                $updateFields[] = "actual_start = :actual_start";
                $params[':actual_start'] = $data['actual_start'];
            }
            if (isset($data['actual_end'])) {
                $updateFields[] = "actual_end = :actual_end";
                $params[':actual_end'] = $data['actual_end'];
            }
            if (isset($data['status'])) {
                $updateFields[] = "status = :status";
                $params[':status'] = $data['status'];
            }
            if (isset($data['confirmed_by'])) {
                $updateFields[] = "confirmed_by = :confirmed_by";
                $updateFields[] = "confirmed_at = NOW()";
                $params[':confirmed_by'] = $data['confirmed_by'];
            }
            if (isset($data['notes'])) {
                $updateFields[] = "notes = :notes";
                $params[':notes'] = $data['notes'];
            }
            
            if (empty($updateFields)) {
                echo json_encode(['success' => false, 'error' => 'Нет данных для обновления']);
                break;
            }
            
            $sql = "UPDATE work_schedules SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'Данные обновлены']);
            break;
            
        case 'DELETE':
            if (isset($_GET['staff_id']) && isset($_GET['request_id'])) {
                $staff_id = $_GET['staff_id'];
                $request_id = $_GET['request_id'];
                $stmt = $conn->prepare("DELETE FROM work_schedules WHERE external_staff_id = ? AND request_id = ?");
                $stmt->execute([$staff_id, $request_id]);
                echo json_encode(['success' => true, 'message' => 'Сотрудник отстранен']);
                break;
            }
            
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Неверный ID']);
                break;
            }
            
            $stmt = $conn->prepare("DELETE FROM work_schedules WHERE id = :id");
            $stmt->execute([':id' => $id]);
            
            echo json_encode(['success' => true, 'message' => 'Запись удалена']);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
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