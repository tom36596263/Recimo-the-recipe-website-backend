<?php
// ---------------------------------------------------------
// 第一步：引入 CORS 權限設定 (必須放在程式碼最上方)
// ---------------------------------------------------------
require_once '../config/cors.php';
session_start();
// 統一時區
date_default_timezone_set('Asia/Taipei');

// 引入 PHPMailer 類別
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// ---------------------------------------------------------
// 第二步：引入資料庫連線設定
// ---------------------------------------------------------
require_once '../config/db_config.php';

// ---------------------------------------------------------
// 第三步：補強設定 - 宣告回傳格式為 JSON
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// 接收並解析前端傳來的 JSON 資料
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';

// 檢查 Email 是否為空
if (empty($email)) {
    echo json_encode(['status' => 'error', 'message' => '請輸入電子信箱']);
    exit;
}

try {
    // 檢查資料庫是否有此帳號
    $stmt = $pdo->prepare("SELECT user_id, user_name FROM users WHERE user_email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // 安全考量：如果帳號不存在，回傳失敗
        echo json_encode(['status' => 'error', 'message' => '此信箱尚未註冊']);
        exit;
    }

    // 生成 6 位數隨機驗證碼
    $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = time() + 150; // 設定 150 秒後過期

    // 將資訊存入 Session
    // 這樣下一個 API (verify-reset-code.php) 才能從伺服器端拿出來比對
    $_SESSION['reset_auth'] = [
        'email' => $email,
        'code' => $code,
        'expires_at' => $expires_at
    ];

    // 開始執行 PHPMailer 發信
    // ------------------------------------------------------------
    $mail = new PHPMailer(true);

    try {
        // SMTP 設定
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';         // 使用 Gmail SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'recimo0210@gmail.com';     // 你的 Gmail 帳號
        $mail->Password   = 'fmvb mfuc olae etsx';         // 在 Google 申請的 16 位金鑰
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // 收件人與寄件人設定
        $mail->setFrom('recimo0210@gmail.com', 'Recimo 官方');
        $mail->addAddress($email, $user['user_name']);

        // 郵件內容
        $mail->isHTML(true);
        $mail->Subject = '【Recimo】您的密碼重置驗證碼';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd;'>
                <h2 style='color: #4a7c59;'>您好，{$user['user_name']}！</h2>
                <p>我們收到了您在 Recimo 的密碼重置請求。</p>
                <p>您的專屬驗證碼為：</p>
                <div style='background-color: #f4f4f4; padding: 15px; font-size: 24px; font-weight: bold; text-align: center; color: #e74c3c; border-radius: 5px;'>
                    $code
                </div>
                <p>請於 <b>150 秒內</b> 回到網頁完成驗證。</p>
                <p style='color: #888; font-size: 12px;'>若您並未要求重設密碼，請忽略此郵件。</p>
            </div>
        ";

        $mail->send();

        echo json_encode([
            'status' => 'success',
            'message' => '驗證碼已發送至您的信箱',
            // 'debug_code' => $code // 上線前刪除這行
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => '郵件發送失敗：' . $mail->ErrorInfo
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => '資料庫錯誤：' . $e->getMessage()]);
}
