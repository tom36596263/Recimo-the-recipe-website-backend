<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT * FROM meal_plan_cover_template ORDER BY cover_template_id ASC";
    $stmt = $pdo->query($sql);
    $covers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($covers);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
