<?php
// 「取消收藏」、「查詢是否已收藏」、「取得收藏清單」、「加入收藏」
require_once '../config/cors.php';
// 收藏功能整合 API
header('Content-Type: application/json');
require_once '../config/db_config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 加入收藏
if ($method === 'POST' && ($action === 'add' || $action === '')) {
	$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
	$recipe_id = isset($_POST['recipe_id']) ? intval($_POST['recipe_id']) : 0;
	$folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
	if ($user_id <= 0 || $recipe_id <= 0) {
		echo json_encode(['success' => false, 'message' => '缺少參數']);
		exit;
	}
	// 檢查是否已收藏
	$sql = "SELECT favorite_id FROM favorites WHERE user_id = ? AND recipe_id = ?";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$user_id, $recipe_id]);
	if ($stmt->fetch()) {
		echo json_encode(['success' => false, 'message' => '已收藏過']);
		exit;
	}
	// 新增收藏
	$now = date('Y-m-d H:i:s');
	if ($folder_id !== null) {
		$sql = "INSERT INTO favorites (user_id, recipe_id, folder_id, like_at) VALUES (?, ?, ?, ?)";
		$ok = $pdo->prepare($sql)->execute([$user_id, $recipe_id, $folder_id, $now]);
	} else {
		$sql = "INSERT INTO favorites (user_id, recipe_id, like_at) VALUES (?, ?, ?)";
		$ok = $pdo->prepare($sql)->execute([$user_id, $recipe_id, $now]);
	}
	if ($ok) {
		echo json_encode(['success' => true, 'message' => '收藏成功']);
	} else {
		echo json_encode(['success' => false, 'message' => '收藏失敗']);
	}
	exit;
}

// 取消收藏
if ($method === 'POST' && $action === 'remove') {
	$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
	$recipe_id = isset($_POST['recipe_id']) ? intval($_POST['recipe_id']) : 0;
	if ($user_id <= 0 || $recipe_id <= 0) {
		echo json_encode(['success' => false, 'message' => '缺少參數']);
		exit;
	}
	$sql = "DELETE FROM favorites WHERE user_id = ? AND recipe_id = ?";
	$ok = $pdo->prepare($sql)->execute([$user_id, $recipe_id]);
	if ($ok && $pdo->prepare("SELECT ROW_COUNT() as cnt")->execute() !== false) {
		echo json_encode(['success' => true, 'message' => '已取消收藏']);
	} else {
		echo json_encode(['success' => false, 'message' => '取消收藏失敗或未收藏']);
	}
	exit;
}

// 查詢是否已收藏
if ($method === 'GET' && isset($_GET['user_id']) && isset($_GET['recipe_id'])) {
	$user_id = intval($_GET['user_id']);
	$recipe_id = intval($_GET['recipe_id']);
	$sql = "SELECT favorite_id FROM favorites WHERE user_id = ? AND recipe_id = ?";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$user_id, $recipe_id]);
	if ($stmt->fetch()) {
		echo json_encode(['success' => true, 'favorited' => true]);
	} else {
		echo json_encode(['success' => true, 'favorited' => false]);
	}
	exit;
}


// 取得收藏清單（可依資料夾過濾，含食譜、作者、食材資訊）
if ($method === 'GET' && isset($_GET['user_id'])) {
	$user_id = intval($_GET['user_id']);
	$folder_id = isset($_GET['folder_id']) ? $_GET['folder_id'] : null;
	$params = [$user_id];
	$where = 'f.user_id = ?';
	if ($folder_id !== null) {
		if ($folder_id === '' || strtolower($folder_id) === 'null') {
			$where .= ' AND f.folder_id IS NULL';
		} else {
			$where .= ' AND f.folder_id = ?';
			$params[] = $folder_id;
		}
	}
	$sql = "SELECT f.favorite_id, f.recipe_id, f.folder_id, f.like_at,
				   r.*, u.user_name, u.user_url
			FROM favorites f
			JOIN recipes r ON f.recipe_id = r.recipe_id
			LEFT JOIN users u ON r.author_id = u.user_id
			WHERE $where
			ORDER BY f.like_at DESC";
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// 查詢每道食譜的食材
	foreach ($favorites as &$fav) {
		$recipe_id = $fav['recipe_id'];
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
		$fav['ingredients'] = $unique_ingredients;
	}
	unset($fav);
	echo json_encode(['success' => true, 'favorites' => $favorites]);
	exit;
}

// 移動收藏到其他資料夾
if ($method === 'PATCH') {
	parse_str(file_get_contents('php://input'), $_PATCH);
	$favorite_id = isset($_PATCH['favorite_id']) ? intval($_PATCH['favorite_id']) : 0;
	$folder_id = array_key_exists('folder_id', $_PATCH) ? $_PATCH['folder_id'] : null;
	if ($favorite_id <= 0) {
		echo json_encode(['success' => false, 'message' => '缺少 favorite_id']);
		exit;
	}
	if ($folder_id === '' || strtolower($folder_id) === 'null') {
		$sql = 'UPDATE favorites SET folder_id = NULL WHERE favorite_id = ?';
		$ok = $pdo->prepare($sql)->execute([$favorite_id]);
	} else {
		$sql = 'UPDATE favorites SET folder_id = ? WHERE favorite_id = ?';
		$ok = $pdo->prepare($sql)->execute([$folder_id, $favorite_id]);
	}
	if ($ok) {
		echo json_encode(['success' => true, 'message' => '已移動收藏資料夾']);
	} else {
		echo json_encode(['success' => false, 'message' => '移動失敗']);
	}
	exit;
}

// 其他錯誤
echo json_encode(['success' => false, 'message' => '請求參數錯誤']);
exit;
?>