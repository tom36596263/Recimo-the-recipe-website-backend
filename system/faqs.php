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

	// CREATE 新增 FAQ
	if ($method === 'POST') {
		$faq_type = $_POST['faq_type'] ?? '';
		$faq_title = $_POST['faq_title'] ?? '';
		$faq_answer = $_POST['faq_answer'] ?? '';
		if (!$faq_type || !$faq_title || !$faq_answer) {
			echo json_encode(['success' => false, 'message' => '缺少參數']);
			exit;
		}
		$stmt = $pdo->prepare('INSERT INTO faqs (faq_type, faq_title, faq_answer) VALUES (?, ?, ?)');
		$stmt->execute([$faq_type, $faq_title, $faq_answer]);
		echo json_encode(['success' => true, 'message' => 'FAQ 已新增', 'faq_id' => $pdo->lastInsertId()]);
		exit;
	}

	// READ 讀取 FAQ（全部或依類型）
	if ($method === 'GET') {
		$faq_type = $_GET['faq_type'] ?? '';
		$faq_id = $_GET['faq_id'] ?? '';
		if ($faq_id) {
			$stmt = $pdo->prepare('SELECT * FROM faqs WHERE faq_id = ?');
			$stmt->execute([$faq_id]);
			$faq = $stmt->fetch(PDO::FETCH_ASSOC);
			echo json_encode(['success' => true, 'faq' => $faq], JSON_UNESCAPED_UNICODE);
			exit;
		} elseif ($faq_type) {
			   $stmt = $pdo->prepare('SELECT * FROM faqs WHERE faq_type = ? ORDER BY faq_id ASC');
			$stmt->execute([$faq_type]);
			$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
			echo json_encode(['success' => true, 'faqs' => $faqs], JSON_UNESCAPED_UNICODE);
			exit;
		} else {
			   $stmt = $pdo->query('SELECT * FROM faqs ORDER BY faq_id ASC');
			$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
			echo json_encode(['success' => true, 'faqs' => $faqs], JSON_UNESCAPED_UNICODE);
			exit;
		}
	}

	// UPDATE 更新 FAQ
	if ($method === 'PATCH') {
		// PATCH 需用 application/x-www-form-urlencoded 或 JSON
		parse_str(file_get_contents('php://input'), $_PATCH);
		$faq_id = $_PATCH['faq_id'] ?? '';
		$faq_type = $_PATCH['faq_type'] ?? '';
		$faq_title = $_PATCH['faq_title'] ?? '';
		$faq_answer = $_PATCH['faq_answer'] ?? '';
		if (!$faq_id || !$faq_type || !$faq_title || !$faq_answer) {
			echo json_encode(['success' => false, 'message' => '缺少參數']);
			exit;
		}
		$stmt = $pdo->prepare('UPDATE faqs SET faq_type = ?, faq_title = ?, faq_answer = ? WHERE faq_id = ?');
		$stmt->execute([$faq_type, $faq_title, $faq_answer, $faq_id]);
		echo json_encode(['success' => true, 'message' => 'FAQ 已更新']);
		exit;
	}

	// DELETE 刪除 FAQ
	if ($method === 'DELETE') {
		parse_str(file_get_contents('php://input'), $_DELETE);
		$faq_id = $_DELETE['faq_id'] ?? '';
		if (!$faq_id) {
			echo json_encode(['success' => false, 'message' => '缺少 faq_id']);
			exit;
		}
		$stmt = $pdo->prepare('DELETE FROM faqs WHERE faq_id = ?');
		$stmt->execute([$faq_id]);
		echo json_encode(['success' => true, 'message' => 'FAQ 已刪除']);
		exit;
	}

	echo json_encode(['success' => false, 'message' => '不支援的請求方式']);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => '資料庫錯誤', 'error' => $e->getMessage()]);
	exit;
}