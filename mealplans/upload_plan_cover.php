<?php
// C:\MAMP\htdocs\recimo_api\mealplans\upload_plan_cover.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$plan_id = $_POST['plan_id'] ?? null;
$user_id = $_POST['user_id'] ?? null;
$file    = $_FILES['cover_image'] ?? null;

// 🟢 這是新版的錯誤訊息，用來確認你有沒有更新成功
if (!$plan_id || !$user_id || !$file) {
    echo json_encode([
        "success" => false,
        "error" => "參數不足或未偵測到檔案 (New Version)",
        "debug_post" => $_POST,
        "debug_files" => $_FILES
    ]);
    exit;
}

try {
    // 1. 設定路徑 (img 與 mealplans 同級)
    $uploadDir = '../img/plan-covers/upload/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    // 2. 檔名處理
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = "plan_{$plan_id}_" . time() . ".{$ext}";
    $targetPath = $uploadDir . $fileName;

    // 3. 搬移
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $dbPath = "img/plan-covers/upload/" . $fileName;

        // 4. 更新 DB
        $sql = "UPDATE meal_plans 
                SET cover_type = 2, custom_cover_url = ?, cover_template_id = NULL 
                WHERE plan_id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dbPath, $plan_id, $user_id]);

        echo json_encode([
            "success" => true,
            "url" => $dbPath,
            "message" => "上傳成功"
        ]);
    } else {
        throw new Exception("搬移失敗");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
