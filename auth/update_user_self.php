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
// 第三步：補強設定 - 宣告回傳格式為 JSON (讓前端 Axios 自動解析)
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");


try {
    // 判斷請求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("請使用 POST 方法請求");
    }

    // ---------------------------------------------------------
    // 取得前端傳來的 JSON 資料
    // ---------------------------------------------------------
    $inputJSON = file_get_contents('php://input');
    $params = json_decode($inputJSON, true);

    // 如果前端改用 FormData 傳送，這裡做個相容處理
    if (!$params) {
        $params = $_POST;
    }

    // 定義變數 (確保從 $params 取得)
    $post_user_id = $params['user_id'] ?? null;
    $user_name    = $params['user_name'] ?? '';
    $user_phone   = $params['user_phone'] ?? '';
    $user_address = $params['user_address'] ?? '';
    $user_password = $params['user_password'] ?? null;


    error_log("前端傳來的 ID: " . $post_user_id);
    error_log("Session 裡的 ID: " . ($_SESSION['user_id'] ?? '未定義'));

    if (!isset($_SESSION['user_id']) || (string)$post_user_id !== (string)$_SESSION['user_id']) {
        // 這裡可以把錯誤訊息改詳細一點，幫你抓 Bug
        throw new Exception("權限錯誤：前端 ID ($post_user_id) 與 Session ID (" . ($_SESSION['user_id'] ?? '空') . ") 不符");
    }

    // 安全檢查
    if (!$post_user_id) {
        throw new Exception("未接收到會員 ID");
    }

    // 額外保險：檢查 Session 是否與傳過來的 ID 相符 (防止 A 改 B 的資料)
    if (!isset($_SESSION['user_id']) || (string)$post_user_id !== (string)$_SESSION['user_id']) {
        throw new Exception("權限不足或連線逾期");
    }

    // 執行 SQL：先檢查 user_id 是否存在
    $checkSql = "SELECT user_id FROM users WHERE user_id = ?";
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute([$post_user_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("找不到該會員資料 (ID: $post_user_id)");
    }

    // ---------------------------------------------------------
    // 第四步：撰寫 SQL 語句
    // ---------------------------------------------------------
    // 構建動態 SQL (判斷是否有改密碼)
    $sql = "UPDATE users SET 
            user_name = ?, 
            user_phone = ?, 
            user_address = ?";

    $bindParams = [$user_name, $user_phone, $user_address];

    // 如果有填寫密碼，才加入更新
    if (!empty($user_password)) {
        $sql .= ", user_password = ?";
        // 使用 PHP 內建的安全性加密
        $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);
        $bindParams[] = $hashed_password;
    }

    $sql .= " WHERE user_id = ?";
    $bindParams[] = $post_user_id;

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($bindParams);

    // 回傳結果
    if ($result) {
        // 同步更新後端 Session (密碼不存 Session 以維護安全)
        $_SESSION['user_name'] = $user_name;
        $_SESSION['user_phone'] = $user_phone;
        $_SESSION['user_address'] = $user_address;

        // 從資料庫重新抓取「最準確」的 Email，而不是直接信賴 Session
        $getMailSql = "SELECT user_email FROM users WHERE user_id = ?";
        $mailStmt = $pdo->prepare($getMailSql);
        $mailStmt->execute([$post_user_id]);
        $realUser = $mailStmt->fetch(PDO::FETCH_ASSOC);
        $current_email = $realUser['user_email'] ?? '';

        // 準備回傳給前端的資料
        // 如果是 Google 登入，資料庫存的應該是 Gmail；
        // 如果還是出現 @line.com，代表資料庫該欄位「真的」被寫入過 LINE ID。
        $responseData = [
            'user_id'      => $post_user_id,
            'user_name'    => $user_name,
            'user_phone'   => $user_phone,
            'user_address' => $user_address,
            'user_email'   => $current_email
        ];

        echo json_encode([
            "status" => "success",
            "message" => "資料已成功更新",
            "data" => $responseData
        ]);
    } else {
        throw new Exception("資料庫執行失敗");
    }
} catch (Exception $e) {
    // 捕捉所有錯誤並以 JSON 格式回傳，前端才不會接到空回應
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
