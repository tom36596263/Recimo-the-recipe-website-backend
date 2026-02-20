<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$plan_id     = $input['plan_id'] ?? null;
$template_id = $input['template_id'] ?? null;
$user_id     = $input['user_id'] ?? null;

if (!$plan_id || !$template_id || !$user_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "參數不足"]);
    exit;
}

try {
    // 開啟交易模式 (確保資料完整性)
    $pdo->beginTransaction();

    // 1. 取得模板的基本資訊 (主要是天數)
    // 假設 meal_plan_templates 表中有個 days 欄位，或從 items 算最大天數
    $stmtTemp = $pdo->prepare("SELECT MAX(day_number) as total_days FROM meal_plan_template_items WHERE template_id = ?");
    $stmtTemp->execute([$template_id]);
    $tempInfo = $stmtTemp->fetch(PDO::FETCH_ASSOC);
    $total_days = $tempInfo['total_days'] ?: 1;

    // 2. 取得原計畫的開始日期以計算新的結束日期
    $stmtPlan = $pdo->prepare("SELECT start_date FROM meal_plans WHERE plan_id = ? AND user_id = ?");
    $stmtPlan->execute([$plan_id, $user_id]);
    $planBase = $stmtPlan->fetch(PDO::FETCH_ASSOC);

    if (!$planBase) throw new Exception("找不到對應計畫");

    $start_date = new DateTime($planBase['start_date']);
    $new_end_date = clone $start_date;
    $new_end_date->modify("+" . ($total_days - 1) . " days");

    // 3. 更新計畫日期範圍
    $updatePlan = $pdo->prepare("UPDATE meal_plans SET end_date = ? WHERE plan_id = ?");
    $updatePlan->execute([$new_end_date->format('Y-m-d'), $plan_id]);

    // 4. 清除該計畫舊有的所有食譜項目 (重新開始)
    $delItems = $pdo->prepare("DELETE FROM meal_plan_items WHERE plan_id = ?");
    $delItems->execute([$plan_id]);

    // 5. 從模板項目表抓取食譜並匯入計畫項目表
    $getTempItems = $pdo->prepare("SELECT * FROM meal_plan_template_items WHERE template_id = ?");
    $getTempItems->execute([$template_id]);
    $templateItems = $getTempItems->fetchAll(PDO::FETCH_ASSOC);

    $insertItem = $pdo->prepare("INSERT INTO meal_plan_items (plan_id, recipe_id, planned_date, meal_type, sort_order) VALUES (?, ?, ?, ?, ?)");

    foreach ($templateItems as $item) {
        // 計算該食譜應該在哪一天
        $item_date = clone $start_date;
        $day_offset = $item['day_number'] - 1;
        $item_date->modify("+$day_offset days");

        $insertItem->execute([
            $plan_id,
            $item['recipe_id'],
            $item_date->format('Y-m-d'),
            $item['meal_type'],
            $item['sort_order']
        ]);
    }

    // 提交交易
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "new_end_date" => $new_end_date->format('Y-m-d'),
        "message" => "已成功套用模板並更新日期"
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
