<?php
// --- Debug 設定 ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// ----------------

require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

try {
    // 1. 接收基本參數
    $recipe_id = $_POST['recipe_id'];
    $user_id = $_POST['user_id'];
    $actual_time = $_POST['actual_time']; // HH:MM:SS
    $satisfaction = $_POST['satisfaction_rating'];
    $technique = $_POST['technique_rating'];
    $complexity = $_POST['complexity_rating'];
    $summary = $_POST['log_summary'];

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

    // ==========================================
    // B. 處理主圖上傳 (如果有的話)
    // ==========================================
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/logs/'; // 確保資料夾存在
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $ext = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
        $filename = 'log_' . $cooking_log_id . '_main.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['main_image']['tmp_name'], $targetPath)) {
            // 更新資料庫的路徑 (存相對路徑或完整 URL)
            $dbPath = 'uploads/logs/' . $filename;
            $stmt = $pdo->prepare("UPDATE cooking_logs SET log_image_url = ? WHERE cooking_log_id = ?");
            $stmt->execute([$dbPath, $cooking_log_id]);
        }
    }

    // ==========================================
    // C. 寫入步驟筆記與圖片 (log_step_notes)
    // ==========================================
    if (!empty($step_notes)) {
        $sql_step = "INSERT INTO log_step_notes (cooking_log_id, step_id, step_note, step_image_url) VALUES (?, ?, ?, ?)";
        $stmt_step = $pdo->prepare($sql_step);

        foreach ($step_notes as $step_id => $note) {
            $step_image_url = null;

            // 檢查是否有對應這個步驟的上傳圖片
            // 前端 formData key 設為 "step_image_{step_id}"
            $file_key = 'step_image_' . $step_id;

            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                $filename = 'log_' . $cooking_log_id . '_step_' . $step_id . '.' . $ext;
                $targetPath = '../uploads/logs/' . $filename; // 這裡假設都存同一個資料夾

                if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $targetPath)) {
                    $step_image_url = 'uploads/logs/' . $filename;
                }
            }

            // 只有當「有筆記」或「有圖片」時才寫入
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
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
