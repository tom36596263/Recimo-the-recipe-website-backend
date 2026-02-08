<?php
// 排錯
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// ---------------------------------------------------------
// 第一步：引入 CORS 權限設定 (必須放在程式碼最上方)
// ---------------------------------------------------------
require_once '../config/cors.php';
session_start();

// ---------------------------------------------------------
// 第二步：引入資料庫連線設定
// ---------------------------------------------------------
require_once '../config/db_config.php';

// ---------------------------------------------------------
// 第三步：補強設定 - 宣告回傳格式為 JSON (讓前端 Axios 自動解析)
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// 判斷請求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // 告訴前端：方法錯誤
    echo json_encode(["status" => "error", "message" => "請使用 POST 方法請求"]);
    exit; // 直接中斷，不執行後面的程式碼
}

// ---------------------------------------------------------
// 第四步：撰寫 SQL 語句
// ---------------------------------------------------------
// 查詢使用者
// 取得前端 Axios 傳來的 JSON 資料並解析
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
try {
    // 查詢使用者
    $sql = "SELECT user_id, user_name, user_email, user_phone, user_address, user_password, user_url FROM users WHERE user_email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    // 驗證邏輯
    if ($user) {
        // 使用 password_verify 驗證加密過的密碼
        // $password 是前端傳來的明碼，$user['user_password'] 是資料庫裡的雜湊值
        if (password_verify($password, $user['user_password'])) {
            // 密碼正確！
            $_SESSION['user_id'] = $user['user_id'];
            echo json_encode([
                "status" => "success",
                "message" => "登入成功",
                "user" => [
                    "user_id"      => $user['user_id'],      // 使用 user_id 與 Pinia 對接
                    "user_name"    => $user['user_name'],    // 會員姓名
                    "user_email"   => $user['user_email'],   // 電子信箱
                    "user_phone"   => $user['user_phone'],   // 會員電話
                    "user_address" => $user['user_address'], // 會員地址
                    "user_url"     => $user['user_url']      // 會員頭像
                ]
            ]);
            // 將 exit 放在最後確保輸出後停止
            exit;
        } else {
            // 不要講太細
            echo json_encode(["status" => "error", "message" => "帳號或密碼錯誤"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "此電子信箱尚未註冊"]);
    }
} catch (PDOException $e) {
    // 伺服器錯誤回傳
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "資料庫連線失敗"]);
}
