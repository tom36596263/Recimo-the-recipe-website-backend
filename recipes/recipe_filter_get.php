<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
require_once 'recipe_base_sql.php';      // 拿 SELECT 模板
require_once 'recipe_query_helper.php';  // 拿 WHERE 產生器

$mode = $_GET['mode'] ?? 'public';

// 1. 取得組合後的 SQL
$sql = $baseSelect . buildRecipeWhereClause($mode);

// 2. 加上群組與排序
$sql .= " GROUP BY r.recipe_id ORDER BY r.recipe_id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'count'  => count($recipes),
        'data'   => $recipes
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>