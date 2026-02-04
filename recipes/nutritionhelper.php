<?php
/**
 * 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\nutritionhelper.php
 * 功能：計算食譜「單份」的營養成分
 */

class nutritionhelper {
    /**
     * @param array $rawRecipe 包含 recipe_servings 的原始資料
     */
    public static function calculate($rawRecipe, $ingredients, $pdo) {
        $totalKcal = 0; $totalP = 0; $totalF = 0; $totalC = 0;

        if (empty($ingredients)) return self::emptyResult();

        // 1. 先計算所有食材加總的總量
        foreach ($ingredients as $ing) {
            $ingId = $ing['ingredient_id'] ?? null;
            if (!$ingId) continue;

            $stmt = $pdo->prepare("SELECT kcal_per_100g, protein_per_100g, fat_per_100g, carbs_per_100g, gram_conversion FROM ingredients WHERE ingredient_id = ?");
            $stmt->execute([$ingId]);
            $base = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($base) {
                $amt = (float)($ing['amount'] ?? 0);
                
                // 防錯：處理單位轉換
                $unit = mb_strtolower(trim($ing['unit_name'] ?? ''));
                $conv = ($unit === '克' || $unit === 'g') ? 1.0 : (float)($base['gram_conversion'] ?? 1);

                $weight = $amt * $conv; 
                $ratio = $weight / 100; 

                $totalKcal += (float)$base['kcal_per_100g'] * $ratio;
                $totalP    += (float)$base['protein_per_100g'] * $ratio;
                $totalF    += (float)$base['fat_per_100g'] * $ratio;
                $totalC    += (float)$base['carbs_per_100g'] * $ratio;
            }
        }

        // 2. 🏆 取得預設份數並計算「單份」數值
        // 參考 JSON 中的 recipe_servings 欄位
        $servings = (float)($rawRecipe['recipe_servings'] ?? 1);
        if ($servings <= 0) $servings = 1; // 避免除以零

        // 3. 回傳單份營養價值
        return [
            'recipe_kcal_per_100g'    => round($totalKcal / $servings, 2), 
            'recipe_protein_per_100g' => round($totalP / $servings, 2),    // 這裡會存入 59.98
            'recipe_fat_per_100g'     => round($totalF / $servings, 2),    // 這裡會存入 28.53
            'recipe_carbs_per_100g'   => round($totalC / $servings, 2)     // 這裡會存入 127.14
        ];
    }

    private static function emptyResult() {
        return ['recipe_kcal_per_100g'=>0, 'recipe_protein_per_100g'=>0, 'recipe_fat_per_100g'=>0, 'recipe_carbs_per_100g'=>0];
    }
}