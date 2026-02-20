<?php
// 1. 強制顯示所有錯誤，方便調試
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'PDO連線遺失']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $reporter_id = $input['reporter_id'] ?? null;
    $target_type = $input['target_type'] ?? null; 
    $target_id   = $input['target_id']   ?? null;
    
    $reason_raw  = isset($input['reason']) ? trim($input['reason']) : ''; 
    $note        = trim($input['note']   ?? ''); 

    try {
        $table = "";
        $id_column = "";
        $type_map = [];

        /**
         * 🏆 統一全站類型定義 (後台顯示會根據此數字翻譯)
         * 1:垃圾訊息, 2:仇恨言論, 3:色情內容, 4:不實資訊, 5:內容侵權, 6:其他
         */
        switch ($target_type) {
            case 'gallery':
                $table = "reported_galleries";
                $id_column = "gallery_id";
                $type_map = [
                    '內容侵權 (盜圖或盜文)' => 5,
                    '垃圾訊息 / 廣告'      => 1,
                    '色情或不當內容'      => 3,
                    '仇恨或攻擊言論'      => 2,
                    '其他原因'           => 6
                ];
                break;

            case 'recipe':
                $table = "reported_recipes";
                $id_column = "recipe_id";
                $type_map = [
                    '內容侵權 (盜圖或盜文)'      => 5,
                    '垃圾訊息 / 廣告'           => 1,
                    '不實資訊 / 錯誤的食譜步驟'  => 4,
                    '仇恨或不當言論'           => 2,
                    '其他原因'                => 6
                ];
                break;

            case 'comment':
                $table = "reported_comments";
                $id_column = "comment_id";
                $type_map = [
                    '垃圾訊息 / 廣告' => 1,
                    '仇恨或攻擊言論' => 2,
                    '色情或不當內容' => 3,
                    '不實資訊'      => 4,
                    '其他原因'      => 6
                ];
                break;

            default:
                throw new Exception('不支援的類型: ' . $target_type);
        }

        // 判定類型 ID，若找不到文字則歸類為 6 (其他)
        $report_type_int = $type_map[$reason_raw] ?? 6;

        // 處理文字內容：優先存 note (補充說明)，沒填則存標籤文字
        $final_reason = !empty($note) ? $note : $reason_raw;

        // 執行 SQL 插入
        $sql = "INSERT INTO $table ($id_column, reporter_id, report_type, report_reason, status, handler_id, reported_at, update_at) 
                VALUES (:target_id, :reporter_id, :report_type, :reason, 0, NULL, NOW(), NULL)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':target_id'   => $target_id,
            ':reporter_id' => $reporter_id,
            ':report_type' => $report_type_int,
            ':reason'      => $final_reason
        ]);

        if ($result) {
            echo json_encode(['status' => 'success', 'message' => '檢舉成功']);
        }

    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error', 
            'message' => '資料庫錯誤',
            'sql_error' => $e->getMessage()
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error', 
            'message' => $e->getMessage()
        ]);
    }
}
?>