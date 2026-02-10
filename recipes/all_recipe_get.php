<?php
ob_start();

require_once '../config/cors.php';
require_once '../config/db_config.php';
require_once 'recipe_base_sql.php'; // 💡 引入第一個檔案定義的 $baseSelect
header('Content-Type: application/json; charset=utf-8');
$mode = $_GET['mode'] ?? 'public';
$sql = $baseSelect; 

// 加上過濾條件
if ($mode === 'public') {
    // 這裡維持 0 是上架的邏輯
    $sql .= " WHERE r.status = 0 AND r.parent_recipe_id IS NULL";
} else {
    // 後台模式 (admin)：我們不加 WHERE status，讓所有 0, 1, 2 都跑出來，方便你修改
    $sql .= " WHERE 1=1"; 
}

$sql .= " GROUP BY r.recipe_id ORDER BY r.recipe_id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recipes as &$r) {
        if (isset($r['status'])) {
            // 🏆 核心修正：強制轉型，並確保 2 這種奇怪的數字在後台也能顯示
            $r['status'] = (int)$r['status']; 
        }
    }

    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => 'success', 
        'data' => $recipes
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
ob_end_flush();
?>