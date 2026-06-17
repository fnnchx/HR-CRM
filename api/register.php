<?php

require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

$login = $data['login'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? ''; 
$first_name = $data['first_name'] ?? '';
$last_name = $data['last_name'] ?? '';
$phone = $data['phone'] ?? '';

if (!$login || !$email || !$password) {
    echo json_encode(['success' => false, 'error' => 'Заполните обязательные поля']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Неверный формат email']);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Пароль должен быть не менее 6 символов']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE login = ? OR email = ?");
    $stmt->execute([$login, $email]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Пользователь с таким логином или email уже существует']);
        exit();
    }
    
    $sql = "INSERT INTO users (login, email, password_hash, first_name, last_name, phone, role) VALUES (?, ?, ?, ?, ?, ?, 'user')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$login, $email, $password, $first_name, $last_name, $phone]);
    
    $user_id = $conn->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Регистрация успешна',
        'user' => [
            'id' => $user_id,
            'login' => $login,
            'email' => $email,
            'role' => 'user'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>