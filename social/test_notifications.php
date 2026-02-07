<?php
/**
 * 通知功能測試檔案
 * 用於檢查資料表和基本功能
 */

require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

$response = [
    'database_connection' => false,
    'table_exists' => false,
    'table_structure' => null,
    'sample_data' => null,
    'errors' => []
];

try {
    // 1. 測試資料庫連接
    if (isset($pdo)) {
        $response['database_connection'] = true;
    } else {
        $response['errors'][] = '資料庫連接物件不存在';
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 2. 檢查 notifications 表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        $response['table_exists'] = true;
        
        // 3. 獲取表結構
        $stmt = $pdo->query("DESCRIBE notifications");
        $response['table_structure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. 獲取資料總數
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['total_records'] = $count['total'];
        
        // 5. 獲取一筆範例資料（如果有）
        $stmt = $pdo->query("SELECT * FROM notifications LIMIT 1");
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sample) {
            $response['sample_data'] = $sample;
        }
        
        // 6. 測試查詢功能
        $testUserId = 1;
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE receiver_id = ?");
        $stmt->execute([$testUserId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['test_query'] = [
            'user_id' => $testUserId,
            'count' => $result['count']
        ];
        
    } else {
        $response['errors'][] = 'notifications 資料表不存在';
        
        // 顯示所有現有的表
        $stmt = $pdo->query("SHOW TABLES");
        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $response['existing_tables'] = $allTables;
    }
    
} catch (PDOException $e) {
    $response['errors'][] = 'PDO 錯誤: ' . $e->getMessage();
} catch (Exception $e) {
    $response['errors'][] = '錯誤: ' . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
