<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$plan_id = $_POST['plan_id'] ?? null;
$user_id = $_POST['user_id'] ?? null;
$file    = $_FILES['cover_image'] ?? null;

// 1. 檢查參數是否完整
if (!$plan_id || !$user_id || !$file) {
    http_response_code(400); // 設定 HTTP 400 Bad Request
    echo json_encode([
        "success" => false,
        "error" => "缺少必要參數 (plan_id, user_id 或 cover_image)",
        // 除錯用：回傳收到的鍵值，方便前端 console 查看
        "debug_post_keys" => array_keys($_POST),
        "debug_file_keys" => array_keys($_FILES)
    ]);
    exit;
}

// 2. 檢查上傳錯誤
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "error" => "檔案上傳錯誤代碼: " . $file['error']]);
    exit;
}

try {
    // 設定路徑：與 mealplans 同層的 img 資料夾
    $uploadDir = '../img/plan-covers/upload/';

    // 檢查並建立資料夾
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception("無法建立資料夾，請檢查伺服器權限");
        }
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = "plan_{$plan_id}_" . time() . ".{$ext}";
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // 存入資料庫的路徑 (相對路徑)
        $dbPath = "img/plan-covers/upload/" . $fileName;

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
        throw new Exception("檔案搬移失敗");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
