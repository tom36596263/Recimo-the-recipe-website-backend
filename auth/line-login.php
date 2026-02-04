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

// 取得前端傳來的 code
$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? null;

if (!$code) {
    echo json_encode(['status' => 'error', 'message' => '缺少授權碼']);
    exit;
}

// === 設定您的 LINE Channel 資訊 ===
$channelId = '2009040716';
$channelSecret = '2b5f90987a7b8e94a0ef5f341f281bfd';

// 💡 【關鍵修改】：動態取得 Redirect URI
// 根據前端發送 API 請求的來源 (HTTP_ORIGIN) 自動組合 callback 網址
// 例如從 http://localhost:5174 發來，這就會變成 http://localhost:5174/auth/callback
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
$redirectUri = rtrim($origin, '/') . '/auth/callback';

// --- 步驟 A：用 code 換取 Access Token ---
$tokenUrl = 'https://api.line.me/oauth2/v2.1/token';
$postData = [
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => $redirectUri, // 這裡現在是動態的了
    'client_id'     => $channelId,
    'client_secret' => $channelSecret
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 開發環境跳過 SSL

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(['status' => 'error', 'message' => 'cURL 錯誤 (A): ' . curl_error($ch)]);
    exit;
}
curl_close($ch);

$tokenData = json_decode($result, true);

// 檢查是否成功取得 Token
if (!isset($tokenData['access_token'])) {
    echo json_encode([
        'status'  => 'error', 
        'message' => 'LINE 換取 Token 失敗', 
        'detail'  => $tokenData, // 幫助老師您除錯，看看是哪種網址出問題
        'sent_redirect_uri' => $redirectUri // 顯示後端當下使用的 URI
    ]);
    exit;
}
$accessToken = $tokenData['access_token'];

// --- 步驟 B：用 Access Token 換取使用者 Profile ---
$profileUrl = 'https://api.line.me/v2/profile';
$ch = curl_init($profileUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$profileResult = curl_exec($ch);
$profile = json_decode($profileResult, true);
curl_close($ch);

$lineUserId  = $profile['userId'] ?? null;
$displayName = $profile['displayName'] ?? 'LINE使用者';
$pictureUrl  = $profile['pictureUrl'] ?? '';

if (!$lineUserId) {
    echo json_encode(['status' => 'error', 'message' => '無法取得 LINE 使用者資訊']);
    exit;
}

// --- 步驟 C：資料庫邏輯 ---
try {
    // 1. 檢查資料庫是否有此 LINE ID
    $stmt = $pdo->prepare("SELECT * FROM users WHERE line_id = ?");
    $stmt->execute([$lineUserId]);
    $user = $stmt->fetch();

    if ($user) {
        // --- 已有帳號：直接取資料庫內容 ---
        $resUser = [
            'id'     => $user['user_id'],
            'name'   => $user['user_name'],
            'email'  => $user['user_email'],
            'avatar' => $user['user_url']
        ];
    } else {
        // --- 沒有帳號：自動註冊 ---
        // 優先使用 LINE 可能提供的 Email，若無則用 ID 拼湊
        $userEmail = $profile['email'] ?? ($lineUserId . "@line.com");
        $dummyPassword = password_hash(uniqid(), PASSWORD_DEFAULT);

        $insert = $pdo->prepare("INSERT INTO users (user_name, user_email, line_id, user_url, user_password, user_startdate, is_verified, is_active) VALUES (?, ?, ?, ?, ?, NOW(), 1, 1)");

        $insert->execute([
            $displayName,
            $userEmail,
            $lineUserId,
            $pictureUrl,
            $dummyPassword
        ]);

        $newId = $pdo->lastInsertId();
        $resUser = [
            'id'     => $newId,
            'name'   => $displayName,
            'email'  => $userEmail,
            'avatar' => $pictureUrl
        ];
    }

    // 統一回傳格式
    echo json_encode([
        'status' => 'success',
        'user'   => $resUser
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => '資料庫錯誤：' . $e->getMessage()]);
}