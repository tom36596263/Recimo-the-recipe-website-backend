<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT * FROM meal_plan_templates ORDER BY template_id ASC";
    $stmt = $pdo->query($sql);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($templates);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
