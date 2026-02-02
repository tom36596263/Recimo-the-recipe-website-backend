<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
require_once 'recipe_base_sql.php'; 

$keyword = $_GET['keyword'] ?? '';
$keyword = trim($keyword);

try {
    // 1. 執行基礎模糊比對搜尋 (包含食譜、產品名、分類、標籤)
    $sql = $baseSelect;
    $where = ["r.status = 0", "r.parent_recipe_id IS NULL"];
    $params = [];

    if ($keyword !== '') {
        $where[] = "(
            r.recipe_title LIKE :kw OR 
            t.tag_name LIKE :kw OR 
            p.product_name LIKE :kw OR 
            p.product_category LIKE :kw
        )";
        $params[':kw'] = "%$keyword%";
    }

    $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " GROUP BY r.recipe_id ORDER BY r.recipe_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. 處理產品圖片 (針對每個有 linked_product_id 的結果)
    foreach ($results as &$row) {
        // 💡 處理標籤
        $row['tag_list'] = $row['tag_names'] ? explode(',', $row['tag_names']) : [];
        
        // 💡 產品圖片處理：
        // 因為 JOIN 已經把 product_image 抓出來了，
        // 如果前端需要的是陣列格式，我們手動包一下即可
        if ($row['linked_product_id'] && !empty($row['product_image'])) {
            $row['product_images_array'] = [
                ['image_url' => $row['product_image']] // 建立一致的格式
            ];
        } else {
            $row['product_images_array'] = [];
        }
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