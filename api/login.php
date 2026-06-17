<?php

require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$login = $data['login'] ?? '';
$password = $data['password'] ?? '';

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE login = ? AND is_active = 1");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && $user['password_hash'] === $password) {
        $redirect = 'dashboard.html';
        switch ($user['role']) {
            case 'shop_manager':
                $redirect = 'shop_dashboard.html';
                break;
            case 'security':
                $redirect = 'security_dashboard.html';
                break;
            case 'hr_specialist':
                $redirect = 'hr_dashboard.html';
                break;
            case 'agency':
                $redirect = 'agency_dashboard.html';
                break;
            case 'admin':
            default:
                $redirect = 'dashboard.html';
                break;
        }
        
        echo json_encode([
            'success' => true,
            'redirect' => $redirect,
            'user' => [
                'id' => $user['id'],
                'login' => $user['login'],
                'email' => $user['email'],
                'role' => $user['role'],
                'organization_id' => $user['organization_id'],
                'agency_id' => $user['agency_id']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Неверный логин или пароль'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка сервера: ' . $e->getMessage()
    ]);
}
?>