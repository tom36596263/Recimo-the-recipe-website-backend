<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json');

$log_id = $_GET['log_id'] ?? null;

if (!$log_id) {
    echo json_encode(['status' => 'error', 'message' => '缺少日誌 ID']);
    exit;
}

try {
    // 1. 查詢日誌主表與食譜標題
    $sql_main = "SELECT cl.*, r.recipe_title 
                 FROM cooking_logs cl
                 JOIN recipes r ON cl.recipe_id = r.recipe_id
                 WHERE cl.cooking_log_id = ?";
    $stmt = $pdo->prepare($sql_main);
    $stmt->execute([$log_id]);
    $log_main = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log_main) {
        echo json_encode(['status' => 'error', 'message' => '找不到該日誌']);
        exit;
    }

    // 2. 查詢該次烹飪的步驟筆記與圖片
    // 注意：這裡 JOIN steps 是為了拿到步驟的標題和原始序號
    $sql_steps = "SELECT lsn.*, s.step_title, s.step_order
                FROM log_step_note lsn
                JOIN steps s ON lsn.step_id = s.step_id
                WHERE lsn.cooking_log_id = ?
                ORDER BY s.step_order ASC";
    $stmt_steps = $pdo->prepare($sql_steps);
    $stmt_steps->execute([$log_id]);
    $log_steps = $stmt_steps->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'main' => $log_main,
            'steps' => $log_steps
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
