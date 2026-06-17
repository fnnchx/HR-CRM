<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$host = 'localhost';           
$dbname = 'hr_crm';            
$username = 'root';             
$password = '';                 

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]
    );
    
    error_log("Подключение к БД успешно");
    
} catch(PDOException $e) {
    error_log("Ошибка подключения: " . $e->getMessage());
    
    echo json_encode([
        'error' => 'Ошибка подключения к базе данных',
        'details' => $e->getMessage()
    ]);
    exit();
}
?>