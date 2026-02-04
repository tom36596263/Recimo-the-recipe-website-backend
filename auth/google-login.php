<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }


// 接收 Token 並向 Google 請求資料
$input = json_decode(file_get_contents('php://input'), true);
$access_token = $input['access_token'] ?? null;

if (!$access_token) {
    echo json_encode(['status' => 'error', 'message' => '未接收到 Token']);
    exit;
}

$google_url = "https://www.googleapis.com/oauth2/v3/userinfo?access_token=" . $access_token;
$user_info = json_decode(@file_get_contents($google_url), true);

if (!isset($user_info['email'])) {
    echo json_encode(['status' => 'error', 'message' => '無效的 Google Token']);
    exit;
}

// ---------------------------------------------------------
// 第四步：撰寫 SQL 語句
// ---------------------------------------------------------
// 比對資料庫
$email = $user_info['email'];
$name  = $user_info['name'];
$picture = $user_info['picture'] ?? null; // Google 大頭照

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_email = ?");
$stmt->execute([$email]);
$db_user = $stmt->fetch();

if ($db_user) {
    // A. 已經是會員：更新頭像或資訊
    $final_user = $db_user;
} else {
    // B. 新用戶：自動幫他註冊
    // 密碼部分：因為是 Google 登入，存入一段隨機字串，防止直接被猜中
    $random_password = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
    
    $insert_sql = "INSERT INTO users (
        user_name, user_email, user_password, user_url, 
        user_startdate, is_verified, is_active
    ) VALUES (?, ?, ?, ?, NOW(), 1, 1)";
    
    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->execute([$name, $email, $random_password, $picture]);
    
    $new_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$new_id]);
    $final_user = $stmt->fetch();
}

// 建立 Session 或回傳資料
// 找到或註冊完使用者後
session_start();
// 統一存入 $_SESSION['user'] 陣列
$_SESSION['user'] = [
    'user_id' => $final_user['user_id'], 
    'user_name' => $final_user['user_name'],
    'user_email' => $final_user['user_email']
];
// 為了相容原本的寫法，也存一份獨立的 user_id
$_SESSION['user_id'] = $final_user['user_id']; 

echo json_encode([
    'status' => 'success',
    'user' => [
        'id'    => $final_user['user_id'], // 前端 Pinia 用 id
        'name'  => $final_user['user_name'],
        'email' => $final_user['user_email'],
        'image' => $final_user['user_url']
    ]
]);