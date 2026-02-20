<?php
ob_start(); // 增加緩衝，預防意外輸出破壞 JSON 格式

require_once '../config/cors.php';
require_once '../config/db_config.php';
require_once 'recipe_base_sql.php';      // 拿 SELECT 模板
require_once 'recipe_query_helper.php';  // 拿 WHERE 產生器

header('Content-Type: application/json; charset=utf-8');

// 強制讓 PDO 錯誤顯示，方便除錯
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$method = $_SERVER['REQUEST_METHOD'];

try {
    // ---------------------------------------------------------
    // 1. 讀取功能 (GET) - 維持原本功能
    // ---------------------------------------------------------
    if ($method === 'GET') {
        $mode = $_GET['mode'] ?? 'public';

        // 取得組合後的 SQL
        $sql = $baseSelect . buildRecipeWhereClause($mode);

        // 加上群組與排序
        $sql .= " GROUP BY r.recipe_id ORDER BY r.recipe_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 格式處理：確保 status 是數字類型
        foreach ($recipes as &$r) {
            if (isset($r['status'])) {
                $r['status'] = (int)$r['status'];
            }
            // 如果 SQL 模板中有標籤欄位，可在此統一處理格式
            if (isset($r['tag_names'])) {
                $r['tags'] = $r['tag_names'] ? explode(',', $r['tag_names']) : [];
                // unset($r['tag_names']);
            }
        }

        if (ob_get_length()) ob_clean();
        echo json_encode([
            'status' => 'success',
            'count'  => count($recipes),
            'data'   => $recipes
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---------------------------------------------------------
    // 2. 更新功能 (POST) - 新增的上下架功能
    // ---------------------------------------------------------
    else if ($method === 'POST') {
        // 取得前端傳送的資料
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // 取得 ID 與狀態 (相容 JSON 或傳統 POST)
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
// require_once '../config/cors.php';
// require_once '../config/db_config.php';
// require_once 'recipe_base_sql.php';      // 拿 SELECT 模板
// require_once 'recipe_query_helper.php';  // 拿 WHERE 產生器

// header('Content-Type: application/json; charset=utf-8');

// $mode = $_GET['mode'] ?? 'public';

// // 1. 取得組合後的 SQL
// $sql = $baseSelect . buildRecipeWhereClause($mode);

// // 2. 加上群組與排序
// $sql .= " GROUP BY r.recipe_id ORDER BY r.recipe_id";

// try {
//     $stmt = $pdo->prepare($sql);
//     $stmt->execute();
//     $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
//     echo json_encode([
//         'status' => 'success',
//         'count'  => count($recipes),
//         'data'   => $recipes
//     ]);
// } catch (PDOException $e) {
//     echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
// }
?>