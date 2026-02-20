<?php
// --- Debug 設定 (開發完成後可註解) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

try {
    // 1. 接收基本參數
    $recipe_id    = $_POST['recipe_id'] ?? null;
    $user_id      = $_POST['user_id'] ?? null;
    $actual_time  = $_POST['actual_time'] ?? '00:00:00';
    $satisfaction = $_POST['satisfaction_rating'] ?? 0;
    $technique    = $_POST['technique_rating'] ?? 0;
    $complexity   = $_POST['complexity_rating'] ?? 0;
    $summary      = $_POST['log_summary'] ?? '';

    if (!$recipe_id || !$user_id) {
        throw new Exception('缺少必要參數：recipe_id 或 user_id');
    }

    // 解析步驟筆記 JSON
    $step_notes = isset($_POST['step_notes']) ? json_decode($_POST['step_notes'], true) : [];

    // 開始資料庫交易
    $pdo->beginTransaction();

    // ==========================================
    // A. 寫入主表 (cooking_logs)
    // ==========================================
    $sql_log = "INSERT INTO cooking_logs 
        (recipe_id, user_id, actual_time, satisfaction_rating, technique_rating, complexity_rating, log_summary, logged_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql_log);
    $stmt->execute([$recipe_id, $user_id, $actual_time, $satisfaction, $technique, $complexity, $summary]);

    $cooking_log_id = $pdo->lastInsertId();

    // ==========================================
    // B. 建立資料夾結構 (對齊舊資料路徑格式)
    // 格式: ../img/logs/u{user_id}/log_{log_id}/steps/
    // ==========================================
    $baseRelativePath = "img/logs/u{$user_id}/log_{$cooking_log_id}/";
    $targetDir = "../" . $baseRelativePath;
    $stepDir = $targetDir . "steps/";

    if (!is_dir($stepDir)) {
        mkdir($stepDir, 0777, true);
    }

    // ==========================================
    // C. 處理主圖上傳 (cover)
    // ==========================================
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
        $filename = "cover." . $ext;
        $targetPath = $targetDir . $filename;

        if (move_uploaded_file($_FILES['main_image']['tmp_name'], $targetPath)) {
            $dbPath = $baseRelativePath . $filename;
            $stmt_img = $pdo->prepare("UPDATE cooking_logs SET log_image_url = ? WHERE cooking_log_id = ?");
            $stmt_img->execute([$dbPath, $cooking_log_id]);
        }
    }

    // ==========================================
    // D. 處理步驟筆記與圖片 (log_step_note)
    // ==========================================

    // 1. 收集所有有傳圖的 step_id
    $image_step_ids = [];
    foreach ($_FILES as $key => $file) {
        if (strpos($key, 'step_image_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
            $image_step_ids[] = str_replace('step_image_', '', $key);
        }
    }

    // 2. 合併有筆記與有圖片的 ID 並去重
    $all_step_ids = array_unique(array_merge(array_keys($step_notes), $image_step_ids));

    if (!empty($all_step_ids)) {
        // 請確保您的資料表名稱是 log_step_note (如截圖所示)
        $sql_step = "INSERT INTO log_step_note (cooking_log_id, step_id, step_note, step_image_url) VALUES (?, ?, ?, ?)";
        $stmt_step = $pdo->prepare($sql_step);

        foreach ($all_step_ids as $step_id) {
            $note = $step_notes[$step_id] ?? '';
            $step_image_url = null;
            $file_key = 'step_image_' . $step_id;

            // 處理該步驟的圖片
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                // 檔名改為 step_id (例: 1.png)，對齊舊資料格式
                $filename = $step_id . "." . $ext;
                $targetPath = $stepDir . $filename;

                if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $targetPath)) {
                    $step_image_url = $baseRelativePath . "steps/" . $filename;
                }
            }

            if (!empty($note) || !empty($step_image_url)) {
                $stmt_step->execute([$cooking_log_id, $step_id, $note, $step_image_url]);
            }
        }
    }

    // ==========================================
    // E. 複製食譜食材至日誌食材表
    // ==========================================
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
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
