<?php
// recipes/admin_update_status.php (後台上下架)
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

// 防止瀏覽器快取 (非常重要！)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$json = file_get_contents("php://input");
$data = json_decode($json, true);

$id = isset($data['id']) ? intval($data['id']) : 0;
$isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 0;

if ($id <= 0) { exit(json_encode(['status'=>'error', 'message'=>'無效 ID'])); }

try {
    // 1. 先查一下現在資料庫叫什麼名字？(確認沒連錯棚)
    $dbNameStmt = $pdo->query("SELECT DATABASE()");
    $currentDb = $dbNameStmt->fetchColumn();

    // 2. 修改前，先偷看一眼原本的狀態
    $checkStmt = $pdo->prepare("SELECT is_active FROM ingredients WHERE ingredient_id = ?");
    $checkStmt->execute([$id]);
    $beforeValue = $checkStmt->fetchColumn();

    // 3. 執行 UPDATE
    $sql = "UPDATE ingredients SET is_active = :status WHERE ingredient_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':status', $isActive, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $affected = $stmt->rowCount();

    // 4. 修改後，立刻再查一次確認有沒有寫入！
    $checkStmt->execute([$id]);
    $afterValue = $checkStmt->fetchColumn();

    // 5. 回傳超詳細偵錯資訊
    echo json_encode([
        'status' => 'success',
        'message' => '偵測完畢',
        'debug_info' => [
            'database_name' => $currentDb,   // ★ 請檢查這個名字對不對！
            'target_id'     => $id,
            'value_before'  => $beforeValue, // 修改前的值
            'action_update' => "設為 $isActive",
            'rows_affected' => $affected,    // 影響幾筆
            'value_after'   => $afterValue   // 修改後的值 (如果是 1 代表寫入成功)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
