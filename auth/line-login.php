<?php
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

// LINE Channel 資訊 (請確保 db_config.php 或環境變數中有定義這些常數)
$channelId = LINE_CHANNEL_ID;
$channelSecret = LINE_CHANNEL_SECRET;

// ---------------------------------------------------------
// 第四步：動態判定 Redirect URI (解決 400 Bad Request 關鍵)
// ---------------------------------------------------------
// 取得前端發送請求的來源 (例如 http://localhost:5173 或 https://tibamef2e.com)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// 判斷是否為正式環境
if (strpos($_SERVER['HTTP_HOST'], 'tibamef2e.com') !== false) {
    // 正式環境：直接寫死，確保 100% 與 LINE Console 一致
    // 檢查重點：s 是否有加、cjd102 是否小寫、最後有沒有斜線
    $redirectUri = 'https://tibamef2e.com/cjd102/g2/recimo/';
} else {
    // 本地環境
    $redirectUri = rtrim($origin, '/') . '/';
}

// ---------------------------------------------------------
// 第五步：步驟 A - 用 code 換取 Access Token
// ---------------------------------------------------------
$tokenUrl = 'https://api.line.me/oauth2/v2.1/token';
$postData = [
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => $redirectUri,
    'client_id'     => $channelId,
    'client_secret' => $channelSecret
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 開發環境建議，正式環境若有證書可設為 true

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(['status' => 'error', 'message' => 'cURL 錯誤: ' . curl_error($ch)]);
    exit;
}
curl_close($ch);

$tokenData = json_decode($result, true);

// 檢查是否成功取得 Token
if (!isset($tokenData['access_token'])) {
    echo json_encode([
        'status'  => 'error', 
        'message' => 'LINE 換取 Token 失敗', 
        'detail'  => $tokenData, // 幫助偵錯
        'debug_uri' => $redirectUri // 讓你知道後端現在是用哪個網址在驗證
    ]);
    exit;
}
$accessToken = $tokenData['access_token'];

// ---------------------------------------------------------
// 第六步：步驟 B - 用 Access Token 換取使用者 Profile
// ---------------------------------------------------------
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

// ---------------------------------------------------------
// 第七步：步驟 C - 資料庫邏輯
// ---------------------------------------------------------
try {
    // 檢查資料庫是否有此 LINE ID
    $stmt = $pdo->prepare("SELECT * FROM users WHERE line_id = ?");
    $stmt->execute([$lineUserId]);
    $user = $stmt->fetch();

    if ($user) {
        // 檢查是否被停權
        if ((int)$user['is_active'] === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => '您的帳號目前處於停權狀態，請聯繫管理員。'
            ]);
            exit; // 停權就中斷，不執行後面的 Session 寫入
        }
        // 已有帳號：登入
        $resUser = [
            'user_id'      => $user['user_id'],      // 改為 user_id
            'user_name'    => $user['user_name'],    // 建議對應資料庫命名
            'user_email'   => $user['user_email'],
            'user_url'     => $user['user_url'],     // 建議與 Google 登入回傳名稱一致
            'user_phone'   => $user['user_phone'],
            'user_address' => $user['user_address']
        ];
    } else {
        // 沒有帳號：自動註冊 (新註冊預設 is_active 為 1，所以不用檢查)
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
            'user_id'      => $newId,                // 改為 user_id
            'user_name'    => $displayName,
            'user_email'   => $userEmail,
            'user_url'     => $pictureUrl,
            'user_phone'   => '',
            'user_address' => ''
        ];
    }

    // 寫入 Session (保持原樣或同步更新)
    session_unset(); 
    $_SESSION['user_id']      = $resUser['user_id'];
    $_SESSION['user_name']    = $resUser['user_name'];
    $_SESSION['user_email']   = $resUser['user_email'];
    $_SESSION['user_phone']   = $resUser['user_phone'] ?? '';
    $_SESSION['user_address'] = $resUser['user_address'] ?? '';

    // 回傳成功結果
    echo json_encode([
        'status' => 'success',
        'user'   => $resUser
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => '資料庫錯誤：' . $e->getMessage()]);
}