<?php
// 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\recipe_adaptation_delete.php

require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['recipe_id']) || !isset($input['user_id'])) {
    echo json_encode([
        "success" => false, 
        "message" => "刪除失敗：缺少必要參數 (recipe_id 或 user_id)"
    ]);
    exit;
}

/**
 * 🏆 新增：遞迴刪除資料夾及其內容
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? deleteDirectory("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
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

    // 1. 刪除相關聯資料 (資料庫部分)
    // 🏆 如果你有 step_ingredients 表，建議也補上刪除，以免遺留孤兒數據
    $pdo->prepare("DELETE FROM step_ingredients WHERE step_id IN (SELECT step_id FROM steps WHERE recipe_id = ?)")->execute([$input['recipe_id']]);
    $pdo->prepare("DELETE FROM recipe_tag WHERE recipe_id = ?")->execute([$input['recipe_id']]);
    $pdo->prepare("DELETE FROM steps WHERE recipe_id = ?")->execute([$input['recipe_id']]);
    $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?")->execute([$input['recipe_id']]);
    $pdo->prepare("DELETE FROM recipes WHERE recipe_id = ?")->execute([$input['recipe_id']]);

    // 2. 🏆 刪除實體檔案資料夾
    $recipeFolder = "../img/recipes/" . $input['recipe_id'];
    if (is_dir($recipeFolder)) {
        deleteDirectory($recipeFolder);
    }

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "改編食譜及其檔案已成功刪除"]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "資料庫錯誤: " . $e->getMessage()]);
}