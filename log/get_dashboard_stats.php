<?php
// recimo_api/log/get_dashboard_stats.php

// 1. 處理跨域 (CORS)
require_once '../config/cors.php';

// 2. 開啟錯誤顯示 (開發階段方便除錯，上線可關閉)
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// 3. 連接資料庫
require_once '../config/db_config.php';

// 4. 接收參數
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// 將預設的 start_date 改到很早以前，end_date 改到未來
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '2000-01-01';
$end_date   = isset($_GET['end_date'])   ? $_GET['end_date']   : '2099-12-31';

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid User ID']);
    exit;
}

try {
    // 為了確保包含當天的所有時間，補上時分秒
    $query_start = $start_date . ' 00:00:00';
    $query_end   = $end_date . ' 23:59:59';

    // --- 查詢 1. 該區間內的累積專注時間與日誌數 ---
    $sql_basic = "
        SELECT 
            COALESCE(SUM(TIME_TO_SEC(actual_time)) / 60, 0) as total_minutes,
            COUNT(*) as total_logs
        FROM cooking_logs 
        WHERE user_id = ? 
        AND logged_at BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($sql_basic);
    $stmt->execute([$user_id, $query_start, $query_end]);
    $basic_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- 查詢 2. 該區間內的常用食材 Top 5 ---
    $sql_ingredients = "
        SELECT 
            i.ingredient_id as id,
            i.ingredient_name as name,
            i.ingredient_image_url as image,
            COUNT(li.ingredient_id) as count
        FROM log_ingredients li
        JOIN cooking_logs cl ON li.cooking_log_id = cl.cooking_log_id
        JOIN ingredients i ON li.ingredient_id = i.ingredient_id
        WHERE cl.user_id = ?
        AND cl.logged_at BETWEEN ? AND ?
        GROUP BY li.ingredient_id
        ORDER BY count DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($sql_ingredients);
    $stmt->execute([$user_id, $query_start, $query_end]);
    $top_ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 查詢 3. 該區間內的烹飪節奏 (標籤統計) ---
    // 這裡直接回傳 Tag -> Count 的對照表
    $sql_rhythm = "
        SELECT t.tag_name, COUNT(*) as count
        FROM cooking_logs cl
        JOIN recipe_tag rt ON cl.recipe_id = rt.recipe_id
        JOIN tags t ON rt.tag_id = t.tag_id
        WHERE cl.user_id = ? 
        AND cl.logged_at BETWEEN ? AND ?
        GROUP BY t.tag_name
    ";
    $stmt = $pdo->prepare($sql_rhythm);
    $stmt->execute([$user_id, $query_start, $query_end]);
    $rhythm_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 轉換資料格式： [ {tag_name: "中式", count: 3}, ... ]  =>  { "中式": 3, ... }
    $rhythm_data = [];
    foreach ($rhythm_rows as $row) {
        $rhythm_data[$row['tag_name']] = (int)$row['count'];
    }

    // --- 5. 輸出結果 ---
    echo json_encode([
        'status' => 'success',
        'date_range' => [ // (選用) 回傳確認收到的日期範圍，方便前端除錯
            'start' => $query_start,
            'end' => $query_end
        ],
        'total_minutes' => (float)$basic_stats['total_minutes'],
        'total_logs' => (int)$basic_stats['total_logs'],
        'top_ingredients' => $top_ingredients,
        'rhythm_data' => $rhythm_data
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
}
