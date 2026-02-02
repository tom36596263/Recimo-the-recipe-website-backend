<?php
// 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\recipe_adaptation_delete.php

require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents("php://input"), true);

// --- 修正：對齊 Vue 傳過來的 'user_id' ---
if (!isset($input['recipe_id']) || !isset($input['user_id'])) {
    echo json_encode([
        "success" => false, 
        "message" => "刪除失敗：缺少必要參數 (recipe_id 或 user_id)",
        "debug_received" => $input // 讓你確認收到的內容
    ]);
    exit;
}

try {
    // 檢查該食譜是否存在且屬於該用戶
    $check = $pdo->prepare("SELECT recipe_id FROM recipes WHERE recipe_id = ? AND author_id = ?");
    $check->execute([$input['recipe_id'], $input['user_id']]);
    
    if (!$check->fetch()) {
        echo json_encode(["success" => false, "message" => "刪除失敗：無此食譜或權限不足"]);
        exit;
    }

    $pdo->beginTransaction();

    // 刪除相關聯資料 (順序：子表 -> 主表)
    $pdo->prepare("DELETE FROM recipe_tag WHERE recipe_id = ?")->execute([$input['recipe_id']]);
    $pdo->prepare("DELETE FROM steps WHERE recipe_id = ?")->execute([$input['recipe_id']]);
    $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?")->execute([$input['recipe_id']]);
    $pdo->prepare("DELETE FROM recipes WHERE recipe_id = ?")->execute([$input['recipe_id']]);

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "改編食譜已成功刪除"]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "資料庫錯誤: " . $e->getMessage()]);
}