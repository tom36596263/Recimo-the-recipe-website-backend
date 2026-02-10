<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
require_once 'recipe_base_sql.php'; // 💡 引入第一個檔案定義的 $baseSelect
header('Content-Type: application/json; charset=utf-8');
$mode = $_GET['mode'] ?? 'public';

// 1. 使用第一個檔案定義的強大 SQL 主體
$sql = $baseSelect; 

// 2. 加上過濾條件
if ($mode === 'public') {
    $sql .= " WHERE r.status = 0 AND r.parent_recipe_id IS NULL";
} else {
    $sql .= " WHERE 1=1"; // admin 模式
}

// 💡 關鍵：因為第一個檔案用了聚合函數 (GROUP_CONCAT)，這裡必須補上 GROUP BY
$sql .= " GROUP BY r.recipe_id ORDER BY r.recipe_id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success', 
        'data' => $recipes
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>