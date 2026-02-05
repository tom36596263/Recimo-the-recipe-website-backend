<?php
// C:\MAMP\htdocs\recimo_api\recipes\get_all_search_tags.php

require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. 抓取食譜標籤名稱
    $sqlTags = "SELECT tag_name FROM tags";
    $stmtTags = $pdo->query($sqlTags);
    $recipeTags = $stmtTags->fetchAll(PDO::FETCH_COLUMN);

    // 2. 抓取產品不重複的分類
    $sqlCats = "SELECT DISTINCT product_category FROM products WHERE product_category IS NOT NULL AND product_category != ''";
    $stmtCats = $pdo->query($sqlCats);
    $productCats = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

    // 合併所有標籤
    $allTags = array_unique(array_merge($recipeTags, $productCats));

    echo json_encode([
        'status' => 'success',
        'data' => array_values($allTags) // 重新建立索引
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>