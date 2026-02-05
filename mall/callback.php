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
        
        // 3. 必須回傳這個，綠界才會停止發送通知
        echo '1|OK';
    }
} catch (Exception $e) {
    file_put_contents('ecpay_error_log.txt', $e->getMessage() . "\n", FILE_APPEND);
}