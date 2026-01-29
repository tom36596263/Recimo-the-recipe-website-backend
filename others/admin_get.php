<?php
// 引入 CORS 設定與資料庫連線

require_once '../config/cors.php';
require_once '../config/db_config.php';

// Debug: 檢查資料庫連線
if (!isset($pdo) || !$pdo) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'message' => '資料庫連線失敗'
	], JSON_UNESCAPED_UNICODE);
	exit;
}

header('Content-Type: application/json; charset=utf-8');

// 僅允許 GET 方法
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	http_response_code(405);
	echo json_encode([
		'success' => false,
		'message' => 'Method Not Allowed'
	], JSON_UNESCAPED_UNICODE);
	exit;
}

try {
	// 取得所有管理員資料
	$sql = 'SELECT * FROM admins';
	$stmt = $pdo->query($sql);
	$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
	echo json_encode([
		'success' => true,
		'data' => $admins
	], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'message' => '資料庫錯誤: ' . $e->getMessage()
	], JSON_UNESCAPED_UNICODE);
	exit;
}
?>