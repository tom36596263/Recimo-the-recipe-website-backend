<?php
// 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\recipe_adaptation_add.php

require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

// 偵錯驗證
$required_fields = ['author_id', 'recipe_title']; 
if (!isset($input['author_id']) || empty($input['recipe_title'])) {
    echo json_encode(["success" => false, "message" => "發布失敗：缺少必要參數"]);
    exit;
}

/**
 * 時間格式校正函式
 * 解決 00:60:00 導致的資料庫錯誤，自動進位為正確的時分秒
 */
function formatDbTime($timeInput) {
    if (empty($timeInput)) return '00:05:00';
    
    // 處理格式如 "00:60:00" 或 "60"
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $timeInput, $matches)) {
        $h = (int)$matches[1];
        $m = (int)$matches[2];
        $s = (int)$matches[3];
        
        // 分秒進位處理
        $m += floor($s / 60);
        $s = $s % 60;
        $h += floor($m / 60);
        $m = $m % 60;
    } else {
        // 如果是純數字(分鐘)
        $totalMinutes = intval($timeInput);
        $h = floor($totalMinutes / 60);
        $m = $totalMinutes % 60;
        $s = 0;
    }
    
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

/**
 * 圖片處理函式
 * 將 Base64 轉換為實體檔案存入 img/recipes/{id} 資料夾
 */
function saveBase64Image($base64Data, $recipeId, $fileName) {
    if (empty($base64Data) || !preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
        return $base64Data; // 如果不是 base64 格式，直接回傳原值
    }
    
    $data = substr($base64Data, strpos($base64Data, ',') + 1);
    $data = base64_decode($data);
    
    // 設定儲存路徑
    $dir = "../img/recipes/" . $recipeId;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $filePath = $dir . "/" . $fileName . ".png";
    file_put_contents($filePath, $data);
    
    // 回傳存入資料庫的路徑格式
    return "img/recipes/" . $recipeId . "/" . $fileName . ".png";
}

try {
    $pdo->beginTransaction();

    // 1. 先插入食譜獲取 ID
    $sqlMain = "INSERT INTO recipes (
        parent_recipe_id, author_id, recipe_title, recipe_description, 
        recipe_image_url, recipe_total_time, recipe_difficulty, recipe_servings,
        recipe_like_count, adaptation_title, adaptation_note, status, recipe_created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"; 

    $stmt = $pdo->prepare($sqlMain);
    
    // 同時校正主食譜的總時間
    $mainTotalTime = formatDbTime($input['total_time'] ?? $input['recipe_total_time'] ?? '00:30:00');

    $stmt->execute([
        $input['parent_recipe_id'] ?? null,
        $input['author_id'],
        $input['recipe_title'],
        $input['recipe_description'] ?? '', 
        '', // 圖片路徑先留空，等拿到 ID 後再存
        $mainTotalTime,
        $input['recipe_difficulty'] ?? $input['difficulty'] ?? 1,
        $input['recipe_servings'] ?? 1,
        0, 
        $input['adaptation_title'] ?? $input['recipe_title'], 
        $input['adaptation_note'] ?? '',
        0  
    ]);

    $new_recipe_id = $pdo->lastInsertId();

    // --- 處理主圖儲存 ---
    $finalCoverPath = saveBase64Image($input['recipe_image_url'] ?? '', $new_recipe_id, "cover");
    $pdo->prepare("UPDATE recipes SET recipe_image_url = ? WHERE recipe_id = ?")->execute([$finalCoverPath, $new_recipe_id]);

    // 2. 插入食材
    if (!empty($input['ingredients'])) {
        $sqlIng = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, amount, unit_name, remark, is_modified) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtIng = $pdo->prepare($sqlIng);
        foreach ($input['ingredients'] as $ing) {
            $stmtIng->execute([
                $new_recipe_id,
                $ing['ingredient_id'],
                $ing['amount'] ?? 0,
                $ing['unit_name'] ?? '份',
                $ing['remark'] ?? '',
                (isset($ing['is_modified']) && $ing['is_modified'] === true) ? 1 : 0
            ]);
        }
    }

    // 3. 插入步驟 (包含步驟圖片處理與時間格式化)
    if (!empty($input['steps'])) {
        $sqlStep = "INSERT INTO steps (recipe_id, step_order, step_title, step_content, step_image_url, step_total_time, is_modified) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtStep = $pdo->prepare($sqlStep);
        foreach ($input['steps'] as $index => $step) {
            // 處理步驟圖片
            $stepImgPath = saveBase64Image($step['step_image_url'] ?? '', $new_recipe_id, ($index + 1));
            
            // 處理步驟時間 (校正 00:60:00 錯誤)
            $stepTime = formatDbTime($step['step_total_time'] ?? '00:05:00');

            $stmtStep->execute([
                $new_recipe_id,
                $index + 1,
                $step['step_title'] ?? '',
                $step['step_content'] ?? '',
                $stepImgPath,
                $stepTime,
                (isset($step['is_modified']) && $step['is_modified'] === true) ? 1 : 0
            ]);
        }
    }

    // 4. 插入標籤
    if (!empty($input['tags'])) {
        $sqlTag = "INSERT INTO recipe_tag (recipe_id, tag_id) VALUES (?, ?)";
        $stmtTag = $pdo->prepare($sqlTag);
        foreach ($input['tags'] as $tag_id) {
            $stmtTag->execute([$new_recipe_id, $tag_id]);
        }
    }

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "改編食譜已成功發布", "recipe_id" => $new_recipe_id]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "資料庫錯誤: " . $e->getMessage()]);
}