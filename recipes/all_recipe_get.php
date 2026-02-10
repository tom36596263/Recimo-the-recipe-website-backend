<?php
ob_start();

require_once '../config/cors.php';
require_once '../config/db_config.php';
require_once 'recipe_base_sql.php'; // 💡 引入定義好的 $baseSelect
header('Content-Type: application/json; charset=utf-8');

// 強制讓 PDO 錯誤顯示，方便除錯
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$method = $_SERVER['REQUEST_METHOD'];

try {
    // ---------------------------------------------------------
    // 1. 讀取功能 (GET) - 維持原本功能與 mode 邏輯
    // ---------------------------------------------------------
    if ($method === 'GET') {
        $mode = $_GET['mode'] ?? 'public';
        $sql = $baseSelect; 

        // 加上過濾條件 (維持你原本的邏輯)
        if ($mode === 'public') {
            // 這裡維持 0 是上架的邏輯
            $sql .= " WHERE r.status = 0 AND r.parent_recipe_id IS NULL";
        } else {
            // 後台模式 (admin)：我們不加 WHERE status，讓所有 0, 1, 2 都出來
            $sql .= " WHERE r.parent_recipe_id IS NULL"; 
        }

        $sql .= " GROUP BY r.recipe_id ORDER BY r.recipe_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($recipes as &$r) {
            if (isset($r['status'])) {
                // 🏆 核心修正：強制轉型，並確保 2 這種奇怪的數字在後台也能顯示
                $r['status'] = (int)$r['status']; 
            }
            // 處理標籤 (若 baseSelect 有 tag_names 欄位)
            if (isset($r['tag_names'])) {
                $tagsArray = $r['tag_names'] ? explode(',', $r['tag_names']) : [];
                $r['tags'] = $tagsArray;
                unset($r['tag_names']);
            }
            $r['source_type'] = 'recipe';
        }

        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'success', 'data' => $recipes], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---------------------------------------------------------
    // 2. 更新功能 (POST) - 新增的上下架邏輯
    // ---------------------------------------------------------
    else if ($method === 'POST') {
        // 取得 Axios 傳送的 JSON 資料
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // 取得 ID 與 狀態 (相容 JSON 或 FormData)
        $recipeId = $data['recipe_id'] ?? $_POST['recipe_id'] ?? null;
        $status = $data['status'] ?? $_POST['status'] ?? null;

        if ($recipeId === null || $status === null) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['status' => 'error', 'message' => '缺少食譜ID或狀態值']);
            exit;
        }

        // 執行資料庫更新
        $updateSql = "UPDATE recipes SET status = :status WHERE recipe_id = :id";
        $stmt = $pdo->prepare($updateSql);
        $stmt->execute([
            ':status' => (int)$status,
            ':id'     => (int)$recipeId
        ]);

        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'success', 'message' => '食譜狀態更新成功']);
        exit;
    }

} catch (PDOException $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

ob_end_flush();
?>


<?php
// ob_start();

// require_once '../config/cors.php';
// require_once '../config/db_config.php';
// require_once 'recipe_base_sql.php'; // 💡 引入第一個檔案定義的 $baseSelect
// header('Content-Type: application/json; charset=utf-8');
// $mode = $_GET['mode'] ?? 'public';
// $sql = $baseSelect; 

// // 加上過濾條件
// if ($mode === 'public') {
//     // 這裡維持 0 是上架的邏輯
//     $sql .= " WHERE r.status = 0 AND r.parent_recipe_id IS NULL";
// } else {
//     // 後台模式 (admin)：我們不加 WHERE status，讓所有 0, 1, 2 都跑出來，方便你修改
//     $sql .= " WHERE 1=1"; 
// }

// $sql .= " GROUP BY r.recipe_id ORDER BY r.recipe_id";

// try {
//     $stmt = $pdo->prepare($sql);
//     $stmt->execute();
//     $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
//     foreach ($recipes as &$r) {
//         if (isset($r['status'])) {
//             // 🏆 核心修正：強制轉型，並確保 2 這種奇怪的數字在後台也能顯示
//             $r['status'] = (int)$r['status']; 
//         }
//     }

//     if (ob_get_length()) ob_clean();
//     header('Content-Type: application/json; charset=utf-8');

//     echo json_encode([
//         'status' => 'success', 
//         'data' => $recipes
//     ], JSON_UNESCAPED_UNICODE);

// } catch (PDOException $e) {
//     if (ob_get_length()) ob_clean();
//     header('Content-Type: application/json; charset=utf-8');
//     echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
// }
// ob_end_flush();
?>