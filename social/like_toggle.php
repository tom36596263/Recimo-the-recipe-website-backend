<?php
// 檔案位置：C:\MAMP\htdocs\recimo_api\social\like_toggle.php

require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method !== 'POST') {
        throw new Exception('Method Not Allowed', 405);
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $recipe_id = $data['recipe_id'] ?? null;
    // 🏆 新增：接收前端傳來的 action，預設為 plus
    $action    = $data['action'] ?? 'plus'; 

    if (!$recipe_id) {
        throw new Exception('缺少必要參數 recipe_id', 400);
    }

    // 🏆 核心邏輯：根據 action 決定加或減
    if ($action === 'minus') {
        // 取消點讚：-1，且用 GREATEST 確保不會變成負數
        $updateSql = "UPDATE recipes SET recipe_like_count = GREATEST(0, recipe_like_count - 1) WHERE recipe_id = ?";
    } else {
        // 點讚：+1
        $updateSql = "UPDATE recipes SET recipe_like_count = recipe_like_count + 1 WHERE recipe_id = ?";
    }

    $stmt = $pdo->prepare($updateSql);
    $stmt->execute([$recipe_id]);

    // 取得資料庫最新的總讚數
    $countSql = "SELECT recipe_like_count FROM recipes WHERE recipe_id = ?";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute([$recipe_id]);
    $newCount = $stmtCount->fetchColumn();

    echo json_encode([
        'success'   => true,
        'status'    => ($action === 'plus' ? 'liked' : 'unliked'),
        'is_liked'  => ($action === 'plus'), // 回傳布林值給前端決定燈號
        'new_count' => (int)$newCount
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // 修正 http_response_code 噴 Fatal Error 的問題
    $code = $e->getCode();
    $httpCode = (is_int($code) && $code >= 100 && $code <= 599) ? $code : 500;
    
    http_response_code($httpCode);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}