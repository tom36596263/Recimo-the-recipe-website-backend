<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

// 1. 取得 JSON 輸入
$input = json_decode(file_get_contents('php://input'), true);

$old_plan_id = $input['plan_id'] ?? null;
$user_id     = $input['user_id'] ?? null;

if (!$old_plan_id || !$user_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "參數不足"]);
    exit;
}

try {
    // 💡 開啟交易模式：確保「計畫」、「食譜」、「熱量目標」要嘛全部複製成功，要嘛全部失敗
    $pdo->beginTransaction();

    // 2. 取得原始計畫內容
    $stmt = $pdo->prepare("SELECT * FROM meal_plans WHERE plan_id = ? AND user_id = ?");
    $stmt->execute([$old_plan_id, $user_id]);
    $old_plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old_plan) {
        throw new Exception("找不到該計畫或無權限複製");
    }

    // 3. 建立新的計畫主體 (標題加上 " (複製)")
    $new_title = $old_plan['title'] . " (複製)";
    $insertPlan = $pdo->prepare("INSERT INTO meal_plans 
        (user_id, title, start_date, end_date, cover_type, cover_template_id, custom_cover_url, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

    $insertPlan->execute([
        $user_id,
        $new_title,
        $old_plan['start_date'],
        $old_plan['end_date'],
        $old_plan['cover_type'],
        $old_plan['cover_template_id'],
        $old_plan['custom_cover_url']
    ]);

    $new_plan_id = $pdo->lastInsertId();

    // 4. 複製食譜項目 (meal_plan_items)
    // 使用 INSERT INTO ... SELECT 語法，效率最高
    $copyItems = $pdo->prepare("INSERT INTO meal_plan_items (plan_id, recipe_id, planned_date, meal_type, sort_order)
                                SELECT ?, recipe_id, planned_date, meal_type, sort_order 
                                FROM meal_plan_items 
                                WHERE plan_id = ?");
    $copyItems->execute([$new_plan_id, $old_plan_id]);

    // 5. 複製每日目標熱量 (meal_plan_daily_targets)
    $copyTargets = $pdo->prepare("INSERT INTO meal_plan_daily_targets (plan_id, target_date, target_kcal)
        SELECT ?, target_date, target_kcal 
        FROM meal_plan_daily_targets 
        WHERE plan_id = ?");
    $copyTargets->execute([$new_plan_id, $old_plan_id]);

    // 6. 提交交易
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "new_plan_id" => $new_plan_id,
        "message" => "計畫已成功複製"
    ]);
} catch (Exception $e) {
    // 🔴 發生任何錯誤則復原資料庫狀態
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
