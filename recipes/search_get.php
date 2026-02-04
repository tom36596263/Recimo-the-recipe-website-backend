<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

try {
    // 建立參數化查詢防止 SQL 注入
    $kwParam = "%$keyword%";

    /**
     * 使用 UNION ALL 合併食譜與產品的搜尋結果
     * 我們定義統一的欄位別名：id, type, title, image, description, tags, calories
     */
    $sql = "
    SELECT * FROM (
        -- 1. 搜尋食譜部分
        SELECT 
            r.recipe_id AS id,
            'recipe' AS source_type,
            r.recipe_title AS title,
            r.recipe_image_url AS cover_image,
            r.recipe_description AS description,
            r.recipe_kcal_per_100g AS kcal,
            GROUP_CONCAT(DISTINCT t.tag_name) AS tag_names
        FROM recipes r
        LEFT JOIN recipe_tag rtr ON r.recipe_id = rtr.recipe_id
        LEFT JOIN tags t ON rtr.tag_id = t.tag_id
        WHERE (r.status = 0 AND r.parent_recipe_id IS NULL)
        GROUP BY r.recipe_id

        UNION ALL

        -- 2. 搜尋料理包部分
        SELECT 
            p.product_id AS id,
            'product' AS source_type,
            p.product_name AS title,
            -- 💡 修正點：直接提取 JSON 欄位中的第一張圖片路徑
            p.product_image AS cover_image,
            p.product_description AS description,
            p.product_kcal AS kcal,
            p.product_category AS tag_names
        FROM products p
        WHERE p.product_release = 1
    ) AS combined_search
    ";

    // 關鍵字模糊比對：對標題、描述、以及標籤字串做比對
    if ($keyword !== '') {
        $sql .= " WHERE title LIKE :kw OR description LIKE :kw OR tag_names LIKE :kw";
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
        $row['kcal'] = (float)$row['kcal'];
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
// require_once '../config/cors.php';
// require_once '../config/db_config.php';
// require_once 'recipe_base_sql.php'; 

// $keyword = $_GET['keyword'] ?? '';
// $keyword = trim($keyword);

// try {
//     // 1. 執行基礎模糊比對搜尋 (包含食譜、產品名、分類、標籤)
//     $sql = $baseSelect;
//     $where = ["r.status = 0", "r.parent_recipe_id IS NULL"];
//     $params = [];

//     if ($keyword !== '') {
//         $where[] = "(
//             r.recipe_title LIKE :kw OR 
//             t.tag_name LIKE :kw OR 
//             p.product_name LIKE :kw OR 
//             p.product_category LIKE :kw
//         )";
//         $params[':kw'] = "%$keyword%";
//     }

//     $sql .= " WHERE " . implode(" AND ", $where);
//     $sql .= " GROUP BY r.recipe_id ORDER BY r.recipe_id";

//     $stmt = $pdo->prepare($sql);
//     $stmt->execute($params);
//     $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

//     // 2. 處理產品圖片 (針對每個有 linked_product_id 的結果)
//     foreach ($results as &$row) {
//         // 💡 處理標籤
//         $row['tag_list'] = $row['tag_names'] ? explode(',', $row['tag_names']) : [];
        
//         // 💡 產品圖片處理：
//         // 因為 JOIN 已經把 product_image 抓出來了，
//         // 如果前端需要的是陣列格式，我們手動包一下即可
//         if ($row['linked_product_id'] && !empty($row['product_image'])) {
//             $row['product_images_array'] = [
//                 ['image_url' => $row['product_image']] // 建立一致的格式
//             ];
//         } else {
//             $row['product_images_array'] = [];
//         }
//     }

//     echo json_encode([
//         'status' => 'success',
//         'keyword' => $keyword,
//         'count' => count($results),
//         'data' => $results
//     ]);

// } catch (PDOException $e) {
//     echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
// }
?>