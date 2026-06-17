<?php

require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

$user_id = $data['user_id'] ?? 0;
$current = $data['current_password'] ?? '';
$new = $data['new_password'] ?? '';

if (!$user_id || !$current || !$new) {
    echo json_encode(['success' => false, 'error' => 'Все поля обязательны']);
    exit();
}

if (strlen($new) < 6) {
    echo json_encode(['success' => false, 'error' => 'Новый пароль должен быть не менее 6 символов']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
        exit();
    }
    
    if ($user['password_hash'] !== $current) {
        echo json_encode(['success' => false, 'error' => 'Неверный текущий пароль']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$new, $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Пароль изменен']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>