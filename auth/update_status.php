<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "請使用 POST"]);
    exit;
}

try {
    // 取得前端傳來的 user_id 和新的狀態值
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? null;
    // 將布林值轉回資料庫存儲用的 1 或 0
    $is_active = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : null;

    if ($user_id === null || $is_active === null) {
        echo json_encode(["status" => "error", "message" => "參數缺失"]);
        exit;
    }

    $sql = "UPDATE users SET is_active = ? WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$is_active, $user_id]);

    if ($success) {
        // 根據 is_active 的數值決定顯示文字
        $status_text = ($is_active == 1) ? "已啟用" : "已停權";
        echo json_encode([
            "status" => "success", 
            "message" => "會員編號 " . $user_id . " " . $status_text
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "會員編號 " . $user_id . " 狀態更新失敗"]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "資料庫連線失敗"]);
}