<?php
// 1. 環境設定：隱藏所有警告，避免干擾 HTML 輸出
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Taipei');

require_once '../config/db_config.php';
require_once 'ECPay.Payment.Integration.php';

// 2. 取得訂單 (假設你網址還是傳 ?order_id=xxx)
$order_id = $_GET['order_id'] ?? null;
if (!$order_id) die("缺少訂單編號");

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) die("找不到該訂單");

try {
    $obj = new ECPay_AllInOne();

    // 3. 綠界固定參數 (請手動打一次，不要複製，確保沒空格)
    $obj->ServiceURL  = "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5";
    $obj->HashKey     = "5294y06JbISpM5x9";
    $obj->HashIV      = "v77hoKGq4kWxRRp9";
    $obj->MerchantID  = "2000132";
    $obj->EncryptType = '1'; // SHA256

    // 4. 訂單參數 (全部改用最簡單的字串測試)
    $obj->Send['MerchantTradeNo']   = "Recimo" . $order['order_id'] . substr(time(), -4);
    $obj->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
    $obj->Send['TotalAmount']       = (int)$order['total_amount'];
    $obj->Send['TradeDesc']         = "TestOrder";
    $obj->Send['ChoosePayment']     = ECPay_PaymentMethod::Credit;
    $obj->Send['ReturnURL']         = "https://ripe-cats-thank.loca.lt"; // 測試階段先隨便填
    $obj->Send['OrderResultURL']    = "http://localhost:5174/workspace/orders";

    // 🔥 這是最關鍵的一步：手動清除所有自動產生的 Items，只留一個最簡單的
    $obj->Send['Items'] = array(); 
    array_push($obj->Send['Items'], array(
        'Name' => "Food", 
        'Price' => (int)$order['total_amount'], 
        'Currency' => "元", 
        'Quantity' => (int)1, 
        'URL' => "deduce"
    ));

} catch (Exception $e) {
    echo "錯誤：" . $e->getMessage();
}