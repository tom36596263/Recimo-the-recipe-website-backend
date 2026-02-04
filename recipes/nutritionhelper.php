<?php
/**
 * 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\nutritionhelper.php
 * 功能：計算整份食譜的「總營養成分」
 */

class nutritionhelper {
    /**
     * @param array $rawRecipe 包含食譜資訊
     * @param array $ingredients 該食譜的食材清單
     * @param PDO $pdo 資料庫連線
     */
    public static function calculate($rawRecipe, $ingredients, $pdo) {
        $totalKcal = 0; $totalP = 0; $totalF = 0; $totalC = 0;

        if (empty($ingredients)) return self::emptyResult();

        // 1. 計算所有食材加總的總量
        foreach ($ingredients as $ing) {
            $ingId = $ing['ingredient_id'] ?? null;
            if (!$ingId) continue;

            $stmt = $pdo->prepare("SELECT kcal_per_100g, protein_per_100g, fat_per_100g, carbs_per_100g, gram_conversion FROM ingredients WHERE ingredient_id = ?");
            $stmt->execute([$ingId]);
            $base = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($base) {
                $amt = (float)($ing['amount'] ?? 0);
                
                // 處理單位轉換
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

        // 2. 🏆 修改重點：直接回傳總量 (不再除以 $servings)
        // 雖然 Key 名稱暫時維持 recipe_kcal_per_100g 以相容資料庫，但數值已是整份總和
        return [
            'recipe_kcal_per_100g'    => round($totalKcal, 2), 
            'recipe_protein_per_100g' => round($totalP, 2),
            'recipe_fat_per_100g'     => round($totalF, 2),
            'recipe_carbs_per_100g'   => round($totalC, 2)
        ];
    }

    private static function emptyResult() {
        return ['recipe_kcal_per_100g'=>0, 'recipe_protein_per_100g'=>0, 'recipe_fat_per_100g'=>0, 'recipe_carbs_per_100g'=>0];
    }
}