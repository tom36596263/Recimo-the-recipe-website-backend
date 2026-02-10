<?php
// 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\recipe_post.php

require_once '../config/cors.php';
require_once '../config/db_config.php';
// header('Content-Type: application/json');

// 1. 取得 JSON 輸入
$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);
$recipe_id = $input['recipe_id'] ?? null;
$mode = $input['mode'] ?? 'create';

// 2. 基礎驗證
if (empty($input['title'])) {
    echo json_encode(["success" => false, "message" => "發布失敗：標題為必填"]);
    exit;
}

/**
 * 時間校正函式 (保持你原本的邏輯)
 */
function formatDbTime($timeInput) {
    if (empty($timeInput)) return '00:00:00';
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $timeInput, $matches)) {
        $h = (int)$matches[1]; $m = (int)$matches[2]; $s = (int)$matches[3];
        $m += floor($s / 60); $s %= 60;
        $h += floor($m / 60); $m %= 60;
    } else {
        $totalMinutes = intval($timeInput);
        $h = floor($totalMinutes / 60); $m = $totalMinutes % 60; $s = 0;
    }
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

/**
 * 圖片處理 (保持你原本的 Base64 邏輯)
 */
/**
 * 圖片處理：加入 uniqid() 解決瀏覽器快取問題
 */
function saveBase64Image($base64Data, $recipeId, $fileName) {
    // 1. 基本判定：如果是空的或是已經是路徑，直接回傳
    if (empty($base64Data) || !preg_match('/^data:image\/(\w+);base64,/', $base64Data)) {
        return $base64Data; 
    }

    // 2. 解析資料
    try {
        $parts = explode(',', $base64Data);
        if (count($parts) < 2) return $base64Data;
        $data = base64_decode($parts[1]);
        if (!$data) return $base64Data;

        // 3. 確保路徑存在 (使用絕對路徑更保險)
        // __DIR__ 會抓到目前 php 檔案所在目錄，確保路徑不會因為 MAMP 設定而跑掉
        $dir = __DIR__ . "/../img/recipes/" . $recipeId;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
            chmod($dir, 0777); // 強制給予最高權限
        }

        // 4. ✨ 產生新檔名 (加入 time() 確保不重複且解決快取)
        $uniqueName = $fileName . "_" . time() . ".png";
        $filePath = $dir . "/" . $uniqueName;
        $dbPath = "/img/recipes/" . $recipeId . "/" . $uniqueName;

        // 5. 寫入並確認結果
        if (file_put_contents($filePath, $data)) {
            chmod($filePath, 0666); // 確保檔案可讀寫
            return $dbPath;
        } else {
            error_log("PHP Error: 檔案無法寫入 " . $filePath);
            return $base64Data;
        }
    } catch (Exception $e) {
        error_log("PHP Error: " . $e->getMessage());
        return $base64Data;
    }
}
error_log("DEBUG - 食材資料: " . json_encode($input['ingredients']));
error_log("DEBUG - 步驟資料: " . json_encode($input['steps']));
try {
    $pdo->beginTransaction();
    $mainTotalTime = formatDbTime($input['totalTime'] ?? 0);
    $forcedStatus = 0;
    $target_id = null;
// 3. 插入食譜主表 (12個欄位對應 11個問號 + 1個 NOW())
    if($mode === 'update' && !empty($recipe_id)){
        $target_id = $recipe_id;

        $sqlUpdate = "UPDATE recipes SET 
            author_id = ?, 
            recipe_title = ?, 
            recipe_description = ?, 
            recipe_servings = ?, 
            recipe_total_time = ?, 
            recipe_difficulty = ?, 
            adaptation_title = ?, 
            adaptation_note = ?,
            status = ?
            WHERE recipe_id = ?";

        $stmt = $pdo->prepare($sqlUpdate);
        $stmt->execute([
            $input['author_id'] ?? 1,
            $input['title'],
            $input['description'] ?? '',
            $input['servings'] ?? 1,
            $mainTotalTime,
            $input['difficulty'] ?? 1,
            $input['adapt_title'] ?? $input['title'],
            $input['adapt_description'] ?? '',
            $forcedStatus,
            $target_id
        ]);

        // 💡 由於有了 CASCADE，刪除父項會自動清除子項
        $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM steps WHERE recipe_id = ?")->execute([$target_id]);
        // $target_id = $recipe_id;
        // $sqlMain = "INSERT INTO recipes (
        //     parent_recipe_id, 
        //     author_id, 
        //     recipe_title, 
        //     recipe_description, 
        //     recipe_image_url, 
        //     recipe_servings, 
        //     recipe_total_time, 
        //     recipe_difficulty, 
        //     adaptation_title, 
        //     adaptation_note,
        //     recipe_like_count, 
        //     status, 
        //     recipe_created_at
        // ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())";
        
        // $stmt = $pdo->prepare($sqlMain);
        // $mainTotalTime = formatDbTime($input['totalTime'] ?? 0);

        // $stmt->execute([
        //     $input['parent_recipe_id'] ?? null,
        //     $input['author_id'] ?? 1,
        //     $input['title'],
        //     $input['description'] ?? '', 
        //     '',
        //     $input['servings'] ?? 1,
        //     $mainTotalTime,
        //     $input['difficulty'] ?? 1,
        //     $input['adapt_title'] ?? $input['title'],
        //     $input['adapt_description'] ?? '',
        //     $input['status'] ?? 0
        // ]);
    }else{
        // --- B. 建立模式 ---
        $sqlMain = "INSERT INTO recipes (
            parent_recipe_id, author_id, recipe_title, recipe_description, 
            recipe_image_url, recipe_servings, recipe_total_time, recipe_difficulty, 
            adaptation_title, adaptation_note, recipe_like_count, status, recipe_created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())"; 

        $stmt = $pdo->prepare($sqlMain);
        $stmt->execute([
            $input['parent_recipe_id'] ?? null,
            $input['author_id'] ?? 1,
            $input['title'],
            $input['description'] ?? '', 
            '',
            $input['servings'] ?? 1,
            $mainTotalTime,
            $input['difficulty'] ?? 1,
            $input['adapt_title'] ?? $input['title'],
            $input['adapt_description'] ?? '',
            $forcedStatus
        ]);
        $target_id = $pdo->lastInsertId();
    }
    

    

    $new_recipe_id = $pdo->lastInsertId();

        // 4. 處理主圖
    $finalCoverPath = saveBase64Image($input['coverImg'] ?? '', $target_id, "cover");
    $pdo->prepare("UPDATE recipes SET recipe_image_url = ? WHERE recipe_id = ?")->execute([$finalCoverPath, $target_id]);

        //5. 插入食材 (對應你前端的 ingredients 結構)
    if (!empty($input['ingredients'])) {
        $sqlIng = "INSERT INTO recipe_ingredients (
        recipe_id, 
        ingredient_id,
        amount, 
        unit_name, remark
        ) VALUES (?, ?, ?, ?, ?)";

        $stmtIng = $pdo->prepare($sqlIng);
        foreach ($input['ingredients'] as $ing) {
            $stmtIng->execute([
            $target_id,
                (int)$ing['id'], // 前端傳來的是 id
                (!isset($ing['amount']) || $ing['amount'] === '') ? 0 : (float)$ing['amount'],
                $ing['unit'] ?? '',
                $ing['note'] ?? ''
            ]);
        }
    }
      // 6. 插入步驟
if (!empty($input['steps'])) {
    $sqlStep = "INSERT INTO steps (recipe_id, step_order, step_title, step_content, step_image_url, step_total_time, is_modified) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmtStep = $pdo->prepare($sqlStep);

    $sqlStepIng = "INSERT INTO step_ingredients (step_id, ingredient_id, step_ingredient_amount, unit_name) VALUES (?, ?, ?, ?)";
    $stmtStepIng = $pdo->prepare($sqlStepIng);

    foreach ($input['steps'] as $index => $step) {
        // --- ✨ 關鍵修正點 1: 處理圖片 ---
        $rawImage = $step['image'] ?? '';
        
        // 如果是 Base64，儲存它並取得新路徑
        // 如果是原本的路徑字串，這裏會直接回傳路徑
        $stepImgPath = saveBase64Image($rawImage, $target_id, "step_" . ($index + 1));
        
        // --- ✨ 關鍵修正點 2: 強制處理時間格式 ---
        $stepTime = formatDbTime($step['time'] ?? 0);

        $stmtStep->execute([
            $target_id,
            $index + 1,
            $step['title'] ?? '',
            $step['content'] ?? '',
            $stepImgPath, // 確保這裡存入的是正確的路徑
            $stepTime,
            $step['is_modified'] ?? 0
        ]);

        $current_step_id = $pdo->lastInsertId();

        // 處理步驟食材關聯 (tags)
        if (!empty($step['tags']) && is_array($step['tags'])) {
            $uniqueTags = array_unique($step['tags']);
            foreach ($uniqueTags as $ingId) {
                if (!empty($ingId)) {
                    $stmtStepIng->execute([
                        $current_step_id,
                        (int)$ingId,
                        0, 
                        ''
                    ]);
                }
            }
        }
    }
}

    // 7. 插入食譜標籤 (新增這段)
    if (isset($input['tags']) && is_array($input['tags'])) {
        // 如果是更新模式，先刪除舊標籤
        if ($mode === 'update') {
            $pdo->prepare("DELETE FROM recipe_tag WHERE recipe_id = ?")->execute([$target_id]);
        }

        $sqlTag = "INSERT INTO recipe_tag (recipe_id, tag_id) VALUES (?, ?)";
        $stmtTag = $pdo->prepare($sqlTag);
        
        foreach ($input['tags'] as $tagId) {
            if (!empty($tagId)) {
                $stmtTag->execute([$target_id, (int)$tagId]);
            }
        }
    }

    $sqlCalculateNutrition = "
        UPDATE recipes r
        SET 
            r.recipe_kcal_per_100g = (
                SELECT SUM(i.kcal_per_100g * (ri.amount / 100)) 
                FROM recipe_ingredients ri
                JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
                WHERE ri.recipe_id = r.recipe_id
            ),
            r.recipe_protein_per_100g = (
                SELECT SUM(i.protein_per_100g * (ri.amount / 100)) 
                FROM recipe_ingredients ri
                JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
                WHERE ri.recipe_id = r.recipe_id
            ),
            r.recipe_fat_per_100g = (
                SELECT SUM(i.fat_per_100g * (ri.amount / 100)) 
                FROM recipe_ingredients ri
                JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
                WHERE ri.recipe_id = r.recipe_id
            ),
            r.recipe_carbs_per_100g = (
                SELECT SUM(i.carbs_per_100g * (ri.amount / 100)) 
                FROM recipe_ingredients ri
                JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
                WHERE ri.recipe_id = r.recipe_id
            )
        WHERE r.recipe_id = ?
    ";

    $stmtNtr = $pdo->prepare($sqlCalculateNutrition);
    $stmtNtr->execute([$target_id]);
    $pdo->commit();
    echo json_encode(["success" => true, "message" => "食譜儲存成功", "recipe_id" => $target_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "儲存失敗: " . $e->getMessage()]);
}
?>