<?php
/**
 * 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\fix_all_recipes.php
 */

// 1. 根據你提供的成功路徑進行引入
require_once '../config/db_config.php';
require_once './nutritionhelper.php';

// 設定網頁顯示編碼，避免亂碼
header('Content-Type: text/html; charset=utf-8');

try {
    // 檢查 $pdo 是否存在 (請確認 db_config.php 裡面定義的變數名稱是 $pdo)
    if (!isset($pdo)) {
        die("❌ 錯誤：已引入 db_config.php，但找不到 \$pdo 變數。請檢查該檔案內的連線變數名稱。");
    }

    // --- 開始執行更新邏輯 ---
    // 取得所有食譜 ID 和原始份數
    $stmt = $pdo->query("SELECT recipe_id, recipe_servings FROM recipes");
    $allRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>--- 營養成分批次更新工具 ---</h2>";
    echo "偵測到共 " . count($allRecipes) . " 筆食譜，準備開始執行...<br><hr>";

    foreach ($allRecipes as $row) {
        $recipeId = $row['recipe_id'];

        // 2. 抓取該食譜對應的所有食材
        $stmtIng = $pdo->prepare("SELECT ingredient_id, amount, unit_name FROM recipe_ingredients WHERE recipe_id = ?");
        $stmtIng->execute([$recipeId]);
        $ingredients = $stmtIng->fetchAll(PDO::FETCH_ASSOC);

        // 3. 呼叫營養計算小幫手算出「單份」數值
        $result = nutritionhelper::calculate($row, $ingredients, $pdo);

        // 4. 更新回 recipes 資料表
        $updateStmt = $pdo->prepare("
            UPDATE recipes SET 
                recipe_kcal_per_100g = ?, 
                recipe_protein_per_100g = ?, 
                recipe_fat_per_100g = ?, 
                recipe_carbs_per_100g = ? 
            WHERE recipe_id = ?
        ");

        $updateStmt->execute([
            $result['recipe_kcal_per_100g'],
            $result['recipe_protein_per_100g'],
            $result['recipe_fat_per_100g'],
            $result['recipe_carbs_per_100g'],
            $recipeId
        ]);

        echo "✅ 食譜 ID: $recipeId 更新完畢。<br>";
    }

    echo "<hr><h3>🏆 全部 70+ 道食譜已全數修正為單份數據！</h3>";
    echo "請前往前端確認數值是否正確顯示。";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ 執行失敗:</h3> " . $e->getMessage();
}