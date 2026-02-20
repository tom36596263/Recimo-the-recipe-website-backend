<?php
// ---------------------------------------------------------
// 第一步：引入 CORS 權限設定 (必須放在程式碼最上方)
// ---------------------------------------------------------
require_once '../config/cors.php';
// 主要管理員才有權限
session_start();

// ---------------------------------------------------------
// 第二步：引入資料庫連線設定
// ---------------------------------------------------------
require_once '../config/db_config.php';

// ---------------------------------------------------------
// 第三步：補強設定 - 宣告回傳格式為 JSON (讓前端 Axios 自動解析)
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// 開啟錯誤回報 (開發環境建議)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 獲取管理員等級
$admin_level = 0;

// 優先判斷 POST (因為這是前端從 localStorage 傳過來最即時的)
if (isset($_POST['current_admin_level']) && $_POST['current_admin_level'] !== '') {
    $admin_level = (int)$_POST['current_admin_level'];
}
// 如果 POST 沒傳，再看 Session
elseif (isset($_SESSION['admin_level'])) {
    $admin_level = (int)$_SESSION['admin_level'];
}

if ($admin_level < 2) {
    echo json_encode([
        "status" => "error",
        "message" => "權限不足，您的等級是：" . $admin_level
    ]);
    exit;
}

// debug用
file_put_contents(
    __DIR__ . '/debug_log.txt',
    "Time: " . date('Y-m-d H:i:s') . "\n" .
        "FILES: " . print_r($_FILES, true) . "\n" .
        "Target Path: " . $targetPath . "\n",
    FILE_APPEND
);

try {
    // 確保 ID 是純數字且去掉可能的多餘空白
    $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : null;

    if (!$user_id) {
        echo json_encode(["status" => "error", "message" => "PHP 端沒收到 ID"]);
        exit;
    }

    $target_url = $_POST['user_url'] ?? ''; // 預設沿用舊的

    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    // 從 api/auth/ 往上一層到 api/，再進入 img/profile/
    $uploadDir = __DIR__ . '/../img/profile/'; 
    
    if (!is_dir($uploadDir)) {
        // 如果資料夾不存在就建立它 (這裡會建立在 api/img/profile/)
        mkdir($uploadDir, 0777, true);
    }

    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $fileName = 'user_' . $user_id . '_avatar_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        // 存入資料庫的路徑 (這部分維持不變)
        // 因為對前端來說，它是從 api/ 開始抓，所以存 img/profile/filename 是對的
        $target_url = 'img/profile/' . $fileName;
    }
}
    // ---------------------------------------------------------
    // 第四步：撰寫 SQL 語句
    // ---------------------------------------------------------
    // 檢查是否有資料變動的邏輯
    $sql = "UPDATE users SET 
                user_name = ?, 
                user_phone = ?, 
                user_address = ?, 
                user_url = ? 
            WHERE user_id = ?";

    $stmt = $pdo->prepare($sql);

    // 這裡的順序必須跟上面的問號 (?) 一模一樣
    $params = [
        $_POST['user_name'] ?? '',
        $_POST['user_phone'] ?? '',
        $_POST['user_address'] ?? '',
        $target_url, // 處理後的頭貼路徑
        $user_id     // WHERE 條件的 ID
    ];

    $success = $stmt->execute($params);

    // 在 MySQL 中，如果資料「完全沒變」，rowCount 會是 0。
    // 應該判斷 $success 是否為 true。
    if ($success) {
        echo json_encode([
            "status" => "success",
            "message" => "會員編號 " . $user_id . " 資料修改成功",
            "data" => [
                "user_id" => $user_id,
                "user_name" => $_POST['user_name'],
                "user_phone" => $_POST['user_phone'],
                "user_address" => $_POST['user_address'],
                "user_url" => $target_url
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "SQL 執行失敗"]);
    }
} catch (PDOException $e) {
    // 這裡會抓到例如：欄位名稱寫錯、型態不對等錯誤
    echo json_encode([
        "status" => "success",
        "message" => "修改成功"
    ]);
}
