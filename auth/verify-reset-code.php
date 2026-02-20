<?php
// ---------------------------------------------------------
// 第一步：引入 CORS 權限設定 (必須放在程式碼最上方)
// ---------------------------------------------------------
require_once '../config/cors.php';
session_start();
// 統一時區
date_default_timezone_set('Asia/Taipei');

// ---------------------------------------------------------
// 第二步：引入資料庫連線設定
// ---------------------------------------------------------
// require_once '../config/db_config.php';

// ---------------------------------------------------------
// 第三步：補強設定 - 宣告回傳格式為 JSON
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// 先接收並解析資料 (這一步會定義出 $email)
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? ''; // <--- 在這裡定義了 $email
$code = $data['code'] ?? '';

// 檢查 Session 是否存在
if (!isset($_SESSION['reset_auth'])) {
    echo json_encode(['status' => 'error', 'message' => '請先獲取驗證碼']);
    exit;
}

$auth = $_SESSION['reset_auth'];

// 檢查是否過期 (假設發送時有存入 expire_at)
if (!isset($auth['expires_at']) || time() > $auth['expires_at']) {
    unset($_SESSION['reset_auth']); 
    echo json_encode(['status' => 'error', 'message' => '驗證碼已過期，請重新發送']);
    exit;
}

// 進行比對 (這時候使用 $email 才是安全的)
if (trim($auth['email']) !== trim($email)) {
    echo json_encode(['status' => 'error', 'message' => '電子信箱不符']);
    exit;
}

if ($auth['code'] !== $code) {
    echo json_encode(['status' => 'error', 'message' => '驗證碼錯誤']);
    exit;
}

// 驗證通過，將 $email 存入另一個 Session 標記中，供「重設密碼」API 使用
$_SESSION['reset_verified_email'] = $email; 
// 這裡也可以加一個操作時效給第三步（重設密碼）用
$_SESSION['reset_verified_expire'] = time() + 300; 

echo json_encode([
    'status' => 'success',
    'message' => '驗證成功'
]);