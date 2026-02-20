<?php
header('Bypass-Tunnel-Reminder: any-value');
require_once '../config/db_config.php';
require_once '../ECPay.Payment.Integration.php';

try {
    $obj = new ECPay_AllInOne();
    $obj->HashKey = '5294y06JbISpM5x9';
    $obj->HashIV  = 'v77hoKGq4kWxRRp9';

    // 1. 自動驗證 CheckMacValue (安全性檢查)
    $feedback = $obj->CheckOutFeedback();

    if (count($feedback) > 0) {
        $merchantTradeNo = $feedback['MerchantTradeNo']; // 例如：Recimo123789
        $rtnCode         = $feedback['RtnCode'];         // 1 代表付款成功

        if ($rtnCode == '1') {
            // 🌟 精準解析 order_id
            // 先去掉前綴 "Recimo"，再去掉尾部 3 位數
            $temp = str_replace("Recimo", "", $merchantTradeNo);
            $order_id = substr($temp, 0, -3);

            // 2. 更新資料庫
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = 1, order_status = 1 WHERE order_id = ?");
            $stmt->execute([$order_id]);
            
            // 寫個 Log 方便除錯
            file_put_contents('ecpay_db_log.txt', "訂單 {$order_id} 付款成功並更新成功\n", FILE_APPEND);
        }
       if ($rtnCode == '1') {
    // 1. 先查詢該訂單目前的狀態
    $checkStmt = $pdo->prepare("SELECT payment_status FROM orders WHERE order_id = ?");
    $checkStmt->execute([$order_id]);
    $currentStatus = $checkStmt->fetchColumn();

    // 2. 只有在狀態還沒變成「已付款」時才更新，避免重複觸發邏輯（如發送 Email）
    if ($currentStatus == 0) { 
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 1, order_status = 1, pay_time = NOW() WHERE order_id = ?");
        $stmt->execute([$order_id]);
    }

    echo '1|OK'; // 務必確保輸出只有這四個字，不要有額外的 HTML 標籤
}
        
    }
} catch (Exception $e) {
    file_put_contents('ecpay_error_log.txt', $e->getMessage() . "\n", FILE_APPEND);
}