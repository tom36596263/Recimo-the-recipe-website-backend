<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

try {
	$pdo = $pdo ?? null;
	if (!$pdo) {
		global $pdo;
	}
	$method = $_SERVER['REQUEST_METHOD'];

	// 新增資料夾
	if ($method === 'POST') {
		$creator_id = $_POST['creator_id'] ?? '';
		$folder_name = $_POST['folder_name'] ?? '';
		if (!$creator_id || !$folder_name) {
			echo json_encode(['success' => false, 'message' => '缺少參數']);
			exit;
		}
		$stmt = $pdo->prepare('INSERT INTO favorites_folders (creator_id, folder_name, created_at) VALUES (?, ?, NOW())');
		$stmt->execute([$creator_id, $folder_name]);
		echo json_encode(['success' => true, 'message' => '資料夾已新增', 'favorites_folder_id' => $pdo->lastInsertId()]);
		exit;
	}

	// 取得資料夾列表
	if ($method === 'GET') {
		$creator_id = $_GET['creator_id'] ?? '';
		if (!$creator_id) {
			echo json_encode(['success' => false, 'message' => '缺少 creator_id']);
			exit;
		}
		$stmt = $pdo->prepare('SELECT * FROM favorites_folders WHERE creator_id = ? ORDER BY favorites_folder_id ASC');
		$stmt->execute([$creator_id]);
		$folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
		echo json_encode(['success' => true, 'folders' => $folders], JSON_UNESCAPED_UNICODE);
		exit;
	}

	// 修改資料夾名稱
	if ($method === 'PATCH') {
		parse_str(file_get_contents('php://input'), $_PATCH);
		$favorites_folder_id = $_PATCH['favorites_folder_id'] ?? '';
		$folder_name = $_PATCH['folder_name'] ?? '';
		if (!$favorites_folder_id || !$folder_name) {
			echo json_encode(['success' => false, 'message' => '缺少參數']);
			exit;
		}
		$stmt = $pdo->prepare('UPDATE favorites_folders SET folder_name = ? WHERE favorites_folder_id = ?');
		$stmt->execute([$folder_name, $favorites_folder_id]);
		echo json_encode(['success' => true, 'message' => '資料夾名稱已更新']);
		exit;
	}

	// 刪除資料夾
	if ($method === 'DELETE') {
		$input = file_get_contents('php://input');
		error_log("DELETE Input: " . $input);
		parse_str($input, $_DELETE);
		error_log("Parsed DELETE: " . print_r($_DELETE, true));
		
		$favorites_folder_id = $_DELETE['favorites_folder_id'] ?? '';
		if (!$favorites_folder_id) {
			echo json_encode(['success' => false, 'message' => '缺少 favorites_folder_id', 'received' => $_DELETE]);
			exit;
		}
		
		try {
			// 先刪除該資料夾內的所有收藏
			$stmt = $pdo->prepare('DELETE FROM favorites WHERE folder_id = ?');
			$stmt->execute([$favorites_folder_id]);
			$deletedCount = $stmt->rowCount();
			
			// 再刪除資料夾
			$stmt = $pdo->prepare('DELETE FROM favorites_folders WHERE favorites_folder_id = ?');
			$stmt->execute([$favorites_folder_id]);
			
			error_log("Delete successful for folder ID: " . $favorites_folder_id . ", deleted " . $deletedCount . " favorites");
			
			if ($deletedCount > 0) {
				echo json_encode(['success' => true, 'message' => "資料夾已刪除，同時移除了 {$deletedCount} 個收藏"]);
			} else {
				echo json_encode(['success' => true, 'message' => '資料夾已刪除']);
			}
		} catch (PDOException $e) {
			error_log("Delete error: " . $e->getMessage());
			echo json_encode(['success' => false, 'message' => '刪除失敗', 'error' => $e->getMessage()]);
		}
		exit;
	}

	echo json_encode(['success' => false, 'message' => '不支援的請求方式']);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => '資料庫錯誤', 'error' => $e->getMessage()]);
	exit;
}
