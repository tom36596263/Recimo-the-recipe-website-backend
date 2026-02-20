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
require_once '../config/db_config.php';

// ---------------------------------------------------------
// 第三步：補強設定 - 宣告回傳格式為 JSON
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// 接收前端 POST 資料
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$new_password = $data['new_password'] ?? '';

// 安全檢查：檢查 Session 標記
// 檢查是否有在 verify 階段存入的標記
if (!isset($_SESSION['reset_verified_email']) || $_SESSION['reset_verified_email'] !== $email) {
    echo json_encode([
        'status' => 'error', 
        'message' => '非法請求，請重新進行驗證碼驗證',
        // 'debug_session' => $_SESSION, // 方便檢查 Session 內容
        // 'debug_input_email' => $email
    ]);
    exit;
}

if (empty($email) || empty($new_password)) {
    echo json_encode(['status' => 'error', 'message' => '資料不完整']);
    exit;
}

try {
    // 密碼加密
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // 更新資料庫
    $stmt = $pdo->prepare("UPDATE users SET user_password = ? WHERE user_email = ?");
    $result = $stmt->execute([$hashed_password, $email]);

    if ($result) {
        if ($stmt->rowCount() === 0) {
            // 密碼雜湊後如果跟原本一模一樣，rowCount 有可能為 0
            echo json_encode(['status' => 'error', 'message' => '新密碼不可與舊密碼相同']);
        } else {
            // 成功後清空 Session 相關標記
            unset($_SESSION['reset_auth']);
            unset($_SESSION['reset_verified_email']);
            unset($_SESSION['reset_is_verified']);

            echo json_encode([
                'status' => 'success',
                'message' => '密碼重設成功，請使用新密碼登入'
            ]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => '更新失敗']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => '資料庫錯誤：' . $e->getMessage()
    ]);
}