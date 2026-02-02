<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $reporter_id = $input['reporter_id'] ?? null;
    $target_type = $input['target_type'] ?? null; 
    $target_id   = $input['target_id']   ?? null;
    $reason      = trim($input['reason'] ?? ''); 
    $note        = trim($input['note']   ?? '');

    if (!$reporter_id || !$target_type || !$target_id || !$reason) {
        echo json_encode(['status' => 'error', 'message' => '欄位不足']);
        exit;
    }

    try {
        $table = "";
        $id_column = "";
        $type_map = [];

        if ($target_type === 'comment') {
            $table = "reported_comments";
            $id_column = "comment_id";
            $type_map = [
                '仇恨或攻擊言論' => 1,
                '色情或不當內容' => 2,
                '垃圾訊息 / 廣告' => 3,
                '不實資訊' => 4,
                '其他原因' => 5
            ];
        } elseif ($target_type === 'recipe') {
            $table = "reported_recipes";
            $id_column = "recipe_id";
            $type_map = [
                '內容侵權 (盜圖或盜文)' => 1,
                '垃圾訊息 / 廣告' => 2,
                '不實資訊 / 錯誤的食譜步驟' => 3,
                '仇恨或不當言論' => 4,
                '其他原因' => 5
            ];
        } else {
            throw new Exception('不支援的檢舉類型');
        }

        // 🏆 根據文字映射出 ID
        $report_type = isset($type_map[$reason]) ? $type_map[$reason] : 5;

        $sql = "INSERT INTO $table ($id_column, reporter_id, report_type, report_reason, status, reported_at) 
                VALUES (?, ?, ?, ?, 0, NOW())";
        
        $stmt = $pdo->prepare($sql);
        
        // 🏆 核心修正：
        // 1. report_type 存入映射後的數字 ID
        // 2. report_reason 只存入 $note (純補充內容)，如果沒填就存空字串
        $result = $stmt->execute([
            $target_id, 
            $reporter_id, 
            $report_type,
            $note 
        ]);

        if ($result) {
            echo json_encode(['status' => 'success', 'message' => '檢舉成功']);
        } else {
            throw new Exception('資料庫寫入失敗');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}