<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

try {
	$pdo = $pdo ?? null;
	if (!$pdo) {
		// 舊版 db_config 可能沒回傳 $pdo
		global $pdo;
	}
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		// 新增瀏覽紀錄（有唯一索引則更新 viewed_at）
		$user_id = $_POST['user_id'] ?? null;
		$recipe_id = $_POST['recipe_id'] ?? null;
		if (!$user_id || !$recipe_id) {
			echo json_encode(['success' => false, 'message' => '缺少參數']);
			exit;
		}
		// 嘗試插入，若重複則更新 viewed_at
		$stmt = $pdo->prepare("INSERT INTO browsing_history (user_id, recipe_id, viewed_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE viewed_at = NOW()");
		$stmt->execute([$user_id, $recipe_id]);
		echo json_encode(['success' => true, 'message' => '瀏覽紀錄已新增/更新']);
		exit;
	}

	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		// 查詢瀏覽紀錄，並回傳食譜詳細
		$user_id = $_GET['user_id'] ?? null;
		$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
		if (!$user_id) {
			echo json_encode(['success' => false, 'message' => '缺少 user_id']);
			exit;
		}
		// 查詢瀏覽紀錄（最新在前）
		$stmt = $pdo->prepare("SELECT recipe_id, viewed_at FROM browsing_history WHERE user_id = ? ORDER BY viewed_at DESC LIMIT ?");
		$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
		$stmt->bindValue(2, $limit, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// 取得所有 recipe_id
		$recipe_ids = array_column($rows, 'recipe_id');
		$recipes = [];
		$recipes_map = [];
		if (count($recipe_ids) > 0) {
			// 查詢食譜詳細（含作者資訊）
			$in = str_repeat('?,', count($recipe_ids) - 1) . '?';
			$sql = "SELECT r.*, u.user_name, u.user_url FROM recipes r LEFT JOIN users u ON r.author_id = u.user_id WHERE r.recipe_id IN ($in)";
			$stmt2 = $pdo->prepare($sql);
			foreach ($recipe_ids as $k => $id) {
				$stmt2->bindValue($k + 1, $id, PDO::PARAM_INT);
			}
			$stmt2->execute();
			$all_recipes = $stmt2->fetchAll(PDO::FETCH_ASSOC);
			// 查詢每道食譜的食材（參考 myrecipe_get.php）
			foreach ($all_recipes as &$recipe) {
				$recipe_id = $recipe['recipe_id'];
				$sql_ing = 'SELECT DISTINCT
					ri.ingredient_id,
					i.ingredient_name,
					i.main_category,
					i.sub_category,
					i.ingredient_image_url,
					ri.amount,
					ri.unit_name,
					ri.remark
				FROM recipe_ingredients ri
				JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
				WHERE ri.recipe_id = ?';
				$stmt_ing = $pdo->prepare($sql_ing);
				$stmt_ing->execute([$recipe_id]);
				$all_ingredients = $stmt_ing->fetchAll(PDO::FETCH_ASSOC);
				// 過濾重複 ingredient_id，只保留第一筆
				$unique_ingredients = [];
				$seen = [];
				foreach ($all_ingredients as $ing) {
					if (!in_array($ing['ingredient_id'], $seen)) {
						$unique_ingredients[] = $ing;
						$seen[] = $ing['ingredient_id'];
					}
				}
				$recipe['ingredients'] = $unique_ingredients;
				$recipes_map[$recipe_id] = $recipe;
			}
		}
		// 組合結果
		$result = [];
		foreach ($rows as $row) {
			$rid = $row['recipe_id'];
			$detail = $recipes_map[$rid] ?? null;
			if ($detail) {
				$result[] = [
					'recipe_id' => $rid,
					'viewed_at' => $row['viewed_at'],
					'recipe_detail' => $detail
				];
			}
		}
		echo json_encode(['success' => true, 'history' => $result], JSON_UNESCAPED_UNICODE);
		exit;
	}
	echo json_encode(['success' => false, 'message' => '不支援的請求方式']);
} catch (PDOException $e) {
	echo json_encode(['success' => false, 'message' => '資料庫錯誤', 'error' => $e->getMessage()]);
}