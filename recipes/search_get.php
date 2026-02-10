<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

// 強制讓 PDO 錯誤顯示
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // ---------------------------------------------------------
    // 1. 原有的搜尋與讀取功能 (保持原本前台抓取邏輯)
    // ---------------------------------------------------------
    if ($method === 'GET') {
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $kwParam = "%$keyword%";

        // 💡 維持你原本的子查詢邏輯
        $sql = "
        SELECT 
            r.recipe_id,
            r.recipe_title AS display_title, 
            r.recipe_image_url AS display_image,
            r.recipe_description AS display_description,
            r.linked_product_id AS product_id,
            r.status,
            p.product_category,
            GROUP_CONCAT(DISTINCT t.tag_name) AS tag_names
        FROM recipes r
        LEFT JOIN recipe_tag rtr ON r.recipe_id = rtr.recipe_id
        LEFT JOIN tags t ON rtr.tag_id = t.tag_id
        LEFT JOIN products p ON r.linked_product_id = p.product_id
        WHERE r.recipe_id IN (
            SELECT DISTINCT r2.recipe_id
            FROM recipes r2
            LEFT JOIN recipe_tag rtr2 ON r2.recipe_id = rtr2.recipe_id
            LEFT JOIN tags t2 ON rtr2.tag_id = t2.tag_id
            LEFT JOIN products p2 ON r2.linked_product_id = p2.product_id
            WHERE r2.status = 0 AND r2.parent_recipe_id IS NULL
        ";

        if ($keyword !== '') {
            $sql .= " AND (
                r2.recipe_title LIKE :kw 
                OR t2.tag_name LIKE :kw
                OR p2.product_category LIKE :kw) ";
        }

        $sql .= " ) "; 

        $sql .= " GROUP BY 
            r.recipe_id, 
            r.recipe_title, 
            r.recipe_image_url, 
            r.recipe_description, 
            r.linked_product_id,
            r.status,
            p.product_category";

        $sql .= " ORDER BY r.recipe_id DESC ";

        $stmt = $pdo->prepare($sql);
        if ($keyword !== '') { $stmt->bindParam(':kw', $kwParam); }
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 💡 維持你原本的資料格式處理 (tags 陣列化)
        foreach ($results as &$row) {
            $tagsArray = $row['tag_names'] ? explode(',', $row['tag_names']) : [];
            
            if (!empty($row['product_category'])) {
                if (!in_array($row['product_category'], $tagsArray)) {
                    array_unshift($tagsArray, $row['product_category']);
                }
            }
            
            $row['tags'] = $tagsArray;
            $row['source_type'] = 'recipe';
            unset($row['tag_names']);
            unset($row['product_category']);
        }

        echo json_encode(['status' => 'success', 'data' => $results]);
        exit;
    }

    // ---------------------------------------------------------
    // 2. 新增的上下架更新功能 (POST)
    // ---------------------------------------------------------
    else if ($method === 'POST' && $action === 'update') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // 優先從 JSON 取值，若無則從 $_POST 取值
        $recipeId = $data['recipe_id'] ?? $_POST['recipe_id'] ?? null;
        $status = $data['status'] ?? $_POST['status'] ?? null;

        if ($recipeId === null) {
            echo json_encode(['status' => 'error', 'message' => '缺少食譜ID']);
            exit;
        }

        // 準備更新
        $updateFields = [];
        $params = [':id' => $recipeId];

        // 檢查是否有傳送「狀態」
        if ($status !== null) {
            $updateFields[] = "status = :status";
            $params[':status'] = (int)$status;
        }

        if (!empty($updateFields)) {
            $sql = "UPDATE recipes SET " . implode(', ', $updateFields) . " WHERE recipe_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['status' => 'success', 'message' => '狀態更新成功']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '未提供更新狀態']);
        }
        exit;
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>


<?php
// require_once '../config/cors.php';
// require_once '../config/db_config.php';

// $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
// try {
//     $kwParam = "%$keyword%";

//     // 💡 修正邏輯：使用子查詢
//     // 先在子查詢中找出符合條件的 recipe_id，確保外層查詢能抓到該 ID 的所有標籤
//     $sql = "
//     SELECT 
//         r.recipe_id,
//         r.recipe_title AS display_title, 
//         r.recipe_image_url AS display_image,
//         r.recipe_description AS display_description,
//         r.linked_product_id AS product_id,
//         p.product_category,
//         GROUP_CONCAT(DISTINCT t.tag_name) AS tag_names
//     FROM recipes r
//     LEFT JOIN recipe_tag rtr ON r.recipe_id = rtr.recipe_id
//     LEFT JOIN tags t ON rtr.tag_id = t.tag_id
//     LEFT JOIN products p ON r.linked_product_id = p.product_id
//     WHERE r.recipe_id IN (
//         -- 這裡只負責找符合條件的 ID
//         SELECT DISTINCT r2.recipe_id
//         FROM recipes r2
//         LEFT JOIN recipe_tag rtr2 ON r2.recipe_id = rtr2.recipe_id
//         LEFT JOIN tags t2 ON rtr2.tag_id = t2.tag_id
//         LEFT JOIN products p2 ON r2.linked_product_id = p2.product_id
//         WHERE r2.status = 0 AND r2.parent_recipe_id IS NULL
//     ";

//     if ($keyword !== '') {
//         $sql .= " AND (
//             r2.recipe_title LIKE :kw 
//             OR t2.tag_name LIKE :kw
//             OR p2.product_category LIKE :kw) ";
//     }

//     $sql .= " ) "; // 結束子查詢

//     // 外層 GROUP BY 確保抓到所有標籤
//     $sql .= " GROUP BY 
//         r.recipe_id, 
//         r.recipe_title, 
//         r.recipe_image_url, 
//         r.recipe_description, 
//         r.linked_product_id,
//         p.product_category";

//     $sql .= " ORDER BY r.recipe_id DESC ";

//     $stmt = $pdo->prepare($sql);
//     if ($keyword !== '') { $stmt->bindParam(':kw', $kwParam); }
//     $stmt->execute();
//     $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

//     foreach ($results as &$row) {
//         $tagsArray = $row['tag_names'] ? explode(',', $row['tag_names']) : [];
        
//         if (!empty($row['product_category'])) {
//             if (!in_array($row['product_category'], $tagsArray)) {
//                 array_unshift($tagsArray, $row['product_category']);
//             }
//         }
        
//         $row['tags'] = $tagsArray;
//         $row['source_type'] = 'recipe';
//         unset($row['tag_names']);
//         unset($row['product_category']);
//     }

//     echo json_encode(['status' => 'success', 'data' => $results]);
// } catch (PDOException $e) {
//     echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
// }
?>