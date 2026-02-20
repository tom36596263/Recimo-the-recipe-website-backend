<?php
// 1. 引入跨域與資料庫設定
require_once '../config/cors.php';
require_once '../config/db_config.php';

// 2. 取得 Axios 傳來的 JSON 資料
// 注意：PHP 預設 $_POST 拿不到 JSON，必須從 php://input 讀取原始流
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// 3. 取得參數
$recipe_id = $data['recipe_id'] ?? null;
$status    = $data['status'] ?? null; // 預期值：0 (公開) 或 2 (下架)

// 4. 驗證參數是否存在
if ($recipe_id === null || $status === null) {
    echo json_encode([
        'status' => 'error',
        'message' => '缺少必要參數 recipe_id 或 status'
    ]);
    exit;
}

try {
    // 5. 執行更新語句
    $sql = "UPDATE recipes SET status = :status WHERE recipe_id = :id";
    $stmt = $pdo->prepare($sql);
    
    // 綁定參數，確保安全性
    $stmt->bindParam(':status', $status, PDO::PARAM_INT);
    $stmt->bindParam(':id', $recipe_id, PDO::PARAM_INT);
    
    $stmt->execute();

    // 6. 回傳成功訊息
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => '狀態更新成功',
            'updated_id' => $recipe_id,
            'new_status' => $status
        ]);
    } else {
        // 如果 rowCount 為 0，可能是 ID 不存在，或狀態本來就一樣
        echo json_encode([
            'status' => 'success',
            'message' => '狀態未變更（資料可能已是最新或 ID 不存在）'
        ]);
    }

} catch (PDOException $e) {
    // 7. 錯誤處理
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '資料庫更新失敗：' . $e->getMessage()
    ]);
}