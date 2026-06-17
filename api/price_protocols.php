<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['id']) && $_GET['id'] > 0) {
                $stmt = $conn->prepare("
                    SELECT pp.*, a.name as agency_name
                    FROM price_protocols pp
                    LEFT JOIN agencies a ON pp.agency_id = a.id
                    WHERE pp.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            } else {
                $sql = "
                    SELECT pp.*, a.name as agency_name
                    FROM price_protocols pp
                    LEFT JOIN agencies a ON pp.agency_id = a.id
                    ORDER BY pp.created_at DESC
                ";
                $stmt = $conn->query($sql);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;
            
        case 'POST':
            if (isset($_FILES['file'])) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = time() . '_' . basename($_FILES['file']['name']);
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                    $file_db_path = $file_name;
                } else {
                    $file_db_path = null;
                }
            }
            
            $protocol_number = $_POST['protocol_number'] ?? '';
            $agency_id = $_POST['agency_id'] ?? 0;
            $signing_date = $_POST['signing_date'] ?? date('Y-m-d');
            $notes = $_POST['notes'] ?? null;
            $created_by = $_POST['created_by'] ?? 1;
            
            $stmt = $conn->prepare("
                INSERT INTO price_protocols (protocol_number, agency_id, signing_date, valid_from, file_name, notes, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)
            ");
            $stmt->execute([
                $protocol_number,
                $agency_id,
                $signing_date,
                $signing_date,
                $file_db_path ?? null,
                $notes,
                $created_by
            ]);
            
            echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['status']) && $data['status'] === 'approved') {
                $conn->beginTransaction();
                
                $stmt = $conn->prepare("SELECT agency_id, valid_from FROM price_protocols WHERE id = ?");
                $stmt->execute([$data['id']]);
                $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $conn->prepare("
                    UPDATE price_lists 
                    SET valid_to = DATE_SUB(?, INTERVAL 1 DAY), is_active = 0
                    WHERE agency_id = ? AND valid_to IS NULL
                ");
                $stmt->execute([$protocol['valid_from'], $protocol['agency_id']]);
                
                $stmt = $conn->prepare("
                    UPDATE price_lists 
                    SET is_active = 1, valid_from = ?, valid_to = NULL
                    WHERE protocol_id = ?
                ");
                $stmt->execute([$protocol['valid_from'], $data['id']]);
                
                $stmt = $conn->prepare("
                    UPDATE price_protocols 
                    SET status = 'approved', approved_by = ?, approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$data['approved_by'] ?? 1, $data['id']]);
                
                $conn->commit();
            } else {
                $stmt = $conn->prepare("
                    UPDATE price_protocols 
                    SET status = ?, notes = CONCAT(notes, '\n', ?)
                    WHERE id = ?
                ");
                $stmt->execute([$data['status'], $data['notes'] ?? '', $data['id']]);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            $id = $_GET['id'] ?? 0;
            
            // Получаем имя файла
            $stmt = $conn->prepare("SELECT file_name FROM price_protocols WHERE id = ?");
            $stmt->execute([$id]);
            $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($protocol && $protocol['file_name']) {
                $file_path = 'uploads/' . $protocol['file_name'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            $stmt = $conn->prepare("DELETE FROM price_protocols WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>