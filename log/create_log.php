<?php

require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

try {
    // 1. 接收基本參數
    $recipe_id = $_POST['recipe_id'] ?? null;
    $user_id = $_POST['user_id'] ?? null;
    $actual_time = $_POST['actual_time'] ?? '00:00:00'; // HH:MM:SS
    $satisfaction = $_POST['satisfaction_rating'] ?? 0;
    $technique = $_POST['technique_rating'] ?? 0;
    $complexity = $_POST['complexity_rating'] ?? 0;
    $summary = $_POST['log_summary'] ?? '';

    // 參數防呆檢查
    if (!$recipe_id || !$user_id) {
        throw new Exception('缺少必要參數：recipe_id 或 user_id');
    }

    // 步驟筆記 (JSON 字串 -> 陣列)
    $step_notes = isset($_POST['step_notes']) ? json_decode($_POST['step_notes'], true) : [];

    // 開始交易 (Transaction) - 確保全部成功才寫入
    $pdo->beginTransaction();

    // ==========================================
    // A. 寫入主表 (cooking_logs)
    // ==========================================
    $sql_log = "INSERT INTO cooking_logs 
        (recipe_id, user_id, actual_time, satisfaction_rating, technique_rating, complexity_rating, log_summary, logged_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql_log);
    $stmt->execute([$recipe_id, $user_id, $actual_time, $satisfaction, $technique, $complexity, $summary]);

    $cooking_log_id = $pdo->lastInsertId(); // 取得剛產生的 ID

    // 設定上傳路徑
    $uploadDir = '../uploads/logs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // ==========================================
    // B. 處理主圖上傳 (如果有的話)
    // ==========================================
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
        // 使用 uniqid 避免快取或同名覆蓋問題
        $filename = 'log_' . $cooking_log_id . '_main_' . uniqid() . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['main_image']['tmp_name'], $targetPath)) {
            // 更新資料庫的路徑 (存相對路徑)
            $dbPath = 'uploads/logs/' . $filename;
            $stmt_img = $pdo->prepare("UPDATE cooking_logs SET log_image_url = ? WHERE cooking_log_id = ?");
            $stmt_img->execute([$dbPath, $cooking_log_id]);
        }
    }

    // ==========================================
    // C. 寫入步驟筆記與圖片 (log_step_notes)
    // ==========================================

    // 🟢 1. 找出所有「有上傳圖片」的 step_id
    $image_step_ids = [];
    foreach ($_FILES as $key => $file) {
        // 尋找 key 名稱為 step_image_ 開頭的檔案
        if (strpos($key, 'step_image_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
            // 從 "step_image_5" 中提取出數字 "5"
            $step_id = str_replace('step_image_', '', $key);
            $image_step_ids[] = $step_id;
        }
    }

    // 🟢 2. 合併「有文字的 ID」與「有圖片的 ID」並去重複
    // 這樣不論使用者是「只打字」、「只傳圖」、還是「打字＋傳圖」，都不會漏掉！
    $all_step_ids = array_unique(array_merge(array_keys($step_notes), $image_step_ids));

    if (!empty($all_step_ids)) {
        $sql_step = "INSERT INTO log_step_note (cooking_log_id, step_id, step_note, step_image_url) VALUES (?, ?, ?, ?)";
        $stmt_step = $pdo->prepare($sql_step);

        foreach ($all_step_ids as $step_id) {
            // 取出文字，若無則為空字串
            $note = isset($step_notes[$step_id]) ? $step_notes[$step_id] : '';
            $step_image_url = null;

            $file_key = 'step_image_' . $step_id;

            // 處理圖片上傳
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                $filename = 'log_' . $cooking_log_id . '_step_' . $step_id . '_' . uniqid() . '.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $targetPath)) {
                    $step_image_url = 'uploads/logs/' . $filename;
                }
            }

            // 只要有文字或有圖片，就寫入資料庫
            if (!empty($note) || !empty($step_image_url)) {
                $stmt_step->execute([$cooking_log_id, $step_id, $note, $step_image_url]);
            }
        }
    }

    // ==========================================
    // D. 複製食材 (log_ingredients)
    // ==========================================
    // 直接從 recipe_ingredients 複製過來
    $sql_ing = "INSERT INTO log_ingredients (cooking_log_id, ingredient_id)
                SELECT ?, ingredient_id 
                FROM recipe_ingredients 
                WHERE recipe_id = ?";
    $stmt_ing = $pdo->prepare($sql_ing);
    $stmt_ing->execute([$cooking_log_id, $recipe_id]);

    // 提交交易
    $pdo->commit();

    echo json_encode(['status' => 'success', 'log_id' => $cooking_log_id]);
} catch (Exception $e) {
    // 發生錯誤，回滾交易 (復原所有變更)
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
