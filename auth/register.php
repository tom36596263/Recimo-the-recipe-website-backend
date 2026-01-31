<?php
// 排錯
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// ---------------------------------------------------------
// 第一步：引入 CORS 權限設定 (必須放在程式碼最上方)
// ---------------------------------------------------------
require_once '../config/cors.php';

// ---------------------------------------------------------
// 第二步：引入資料庫連線設定
// ---------------------------------------------------------
require_once '../config/db_config.php';

// ---------------------------------------------------------
// 第三步：補強設定 - 宣告回傳格式為 JSON (讓前端 Axios 自動解析)
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// 限制請求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // 告訴前端：方法錯誤
    echo json_encode(["status" => "error", "message" => "請使用 POST 方法請求"]);
    exit;
}

// ---------------------------------------------------------
// 第四步：撰寫 SQL 語句
// ---------------------------------------------------------

try {
    // 取得 JSON 資料
    $data = json_decode(file_get_contents('php://input'), true);
    $name     = $data['name'] ?? '';
    $email    = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    // 基礎防禦 (如果真的被繞過前端，給一個通用的錯誤)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($data['password']) < 8) {
        echo json_encode(["status" => "error", "message" => "資料格式不符"]);
        exit;
    }

    // 檢查 Email 是否已被註冊
    $checkSql = "SELECT user_id FROM users WHERE user_email = ?";
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        echo json_encode(["status" => "error", "message" => "此電子信箱已被註冊"]);
        exit;
    }

    // 寫入資料庫
    // 使用 password_hash($password, PASSWORD_DEFAULT) 來加密密碼
    $sql = "INSERT INTO users (user_name, user_email, user_password, user_startdate, is_verified, is_active) 
            VALUES (?, ?, ?, NOW(), 1, 1)";

    $insertStmt = $pdo->prepare($sql);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $success = $insertStmt->execute([$name, $email, $hashed_password]);

    // 直接回傳成功
    echo json_encode(["status" => "success", "message" => "註冊成功"]);

} catch (PDOException $e) {
    // 只要上方任何資料庫動作失敗，都會直接跳到這裡
    echo json_encode(["status" => "error", "message" => "資料庫連線失敗"]);
}
