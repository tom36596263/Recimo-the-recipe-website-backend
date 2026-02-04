<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

try {
    // 建立參數化查詢防止 SQL 注入
    $kwParam = "%$keyword%";


    $sql = "
    SELECT * FROM (
        -- 1. 食譜部分：將別名改成原本組件預期的名稱
        SELECT 
            r.recipe_id AS id,
            'recipe' AS source_type,
            r.recipe_title AS recipe_title, -- 改回原本的名稱
            r.recipe_image_url AS recipe_image_url,
            r.recipe_description AS recipe_description,
            r.recipe_kcal_per_100g AS recipe_kcal,
            r.linked_product_id AS linked_product_id, -- 確保這個欄位存在，統計才會準
            GROUP_CONCAT(DISTINCT t.tag_name) AS tag_names
        FROM recipes r
        LEFT JOIN recipe_tag rtr ON r.recipe_id = rtr.recipe_id
        LEFT JOIN tags t ON rtr.tag_id = t.tag_id
        WHERE (r.status = 0 AND r.parent_recipe_id IS NULL)
        GROUP BY r.recipe_id

        UNION ALL

        -- 2. 料理包部分：對應相同位置的欄位
        SELECT 
            p.product_id AS id,
            'product' AS source_type,
            p.product_name AS recipe_title,
            p.product_image AS recipe_image_url,
            p.product_description AS recipe_description,
            p.product_kcal AS recipe_kcal,
            p.product_id AS linked_product_id, -- 產品本身當然有產品 ID
            p.product_category AS tag_names
        FROM products p
        WHERE p.product_release = 1
    ) AS combined_search
    ";

    // 下方的 WHERE 條件也要對應改名
    if ($keyword !== '') {
        $sql .= " WHERE recipe_title LIKE :kw OR recipe_description LIKE :kw OR tag_names LIKE :kw";
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    if ($keyword !== '') {
        $stmt->bindParam(':kw', $kwParam);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 格式化輸出
    foreach ($results as &$row) {
        // 將標籤字串轉回陣列
        $row['tags'] = $row['tag_names'] ? explode(',', $row['tag_names']) : [];
        unset($row['tag_names']); // 移除原始字串欄位
        
        // 數值轉型
        $row['kcal'] = (float)$row['recipe_kcal'];
    }

    echo json_encode([
        'status' => 'success',
        'keyword' => $keyword,
        'count' => count($results),
        'data' => $results
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>