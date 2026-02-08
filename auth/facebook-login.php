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
// 第三步：補強設定 - 宣告回傳格式為 JSON
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['access_token'] ?? '';

// 向 FB 拿資料
$fb_url = "https://graph.facebook.com/me?fields=id,name,email,picture.type(large)&access_token=" . $token;
$res = file_get_contents($fb_url);
$fbUser = json_decode($res, true);

if (isset($fbUser['email'])) {
    $email = $fbUser['email'];
    $name  = $fbUser['name'];
    // 取得頭貼網址
    $picture = $fbUser['picture']['data']['url'] ?? '';

    // 檢查資料庫是否有此 Email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // ---------------------------------------------------------
    // 第四步：撰寫 SQL 語句
    // ---------------------------------------------------------
    if (!$user) {
        // 第一次登入，依照欄位自動註冊
        $sql = "INSERT INTO users (user_name, user_email, user_url, user_password, user_startdate, is_verified, is_active) 
                VALUES (?, ?, ?, ?, NOW(), 1, 1)";

        // FB 登入沒密碼，給一個隨機的雜湊值
        $randomPass = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);

        $insert = $pdo->prepare($sql);
        $insert->execute([$name, $email, $picture, $randomPass]);
    } else {
        // 如果使用者已存在，也可以更新一下頭貼
        $update = $pdo->prepare("UPDATE users SET user_url = ? WHERE user_email = ?");
        $update->execute([$picture, $email]);
    }

    // 重新抓取新用戶
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 啟動 Session 並寫入登入狀態
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // 清除舊 Session 確保乾淨
    session_unset();

    // 存入 Session，讓後續的 update_user_self.php 認得這個人
    $_SESSION['user_id']      = $user['user_id'];
    $_SESSION['user_name']    = $user['user_name'];
    $_SESSION['user_email']   = $user['user_email'];
    $_SESSION['user_phone']   = $user['user_phone'] ?? '';
    $_SESSION['user_address'] = $user['user_address'] ?? '';

    //  統一回傳給前端的資料包格式
    echo json_encode([
        'status' => 'success', 
        'user'   => [
            'id'           => $user['user_id'],      // 對應 Pinia 的 id
            'name'         => $user['user_name'],
            'email'        => $user['user_email'],
            'image'        => $user['user_url'],
            'user_phone'   => $user['user_phone'] ?? '',
            'user_address' => $user['user_address'] ?? ''
        ]
    ]);
}
