<?php

require_once 'config.php';

try {
    $stmt = $conn->query("SELECT * FROM services WHERE is_active = 1 ORDER BY name");
    $services = $stmt->fetchAll();
    echo json_encode($services);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>