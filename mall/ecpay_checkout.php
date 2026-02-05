<?php
// 環境設定
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', '1'); // 開發階段建議開啟錯誤顯示，上線後改為 0
date_default_timezone_set('Asia/Taipei');

// 載入設定與 SDK
// 請確認路徑是否正確
require_once '../config/db_config.php';
require_once 'ECPay.Payment.Integration.php';

// 取得參數
$order_id = $_GET['order_id'] ?? null;
$debug_mode = isset($_GET['debug']) ? true : false; // 網址加上 &debug=1 可開啟除錯模式

if (!$order_id) die("錯誤：缺少訂單編號 (order_id)");

// 取得訂單資料
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) die("錯誤：找不到該訂單");
} catch (Exception $e) {
    die("資料庫錯誤：" . $e->getMessage());
}

/* * -----------------------------------------------------------
 * 綠界金流設定 (測試環境)
 * -----------------------------------------------------------
 * 測試特店編號: 3002607
 * HashKey: pwFHCqoQZGmho4w6
 * HashIV: EkRm7iFT261dpevs
 */

try {
    $obj = new ECPay_AllInOne();
    
    // 服務參數
    $obj->ServiceURL  = "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5"; // 測試環境
    $obj->HashKey     = "pwFHCqoQZGmho4w6"; // 測試用 Key
    $obj->HashIV      = "EkRm7iFT261dpevs"; // 測試用 IV
    $obj->MerchantID  = "3002607";          // 測試用特店編號
    $obj->EncryptType = '1';                // 固定為 1 (SHA256)

    // 訂單基本參數
    // MerchantTradeNo: 特店訂單編號 (不可重複)，建議使用 訂單ID + 時間戳記 或 亂數
    $obj->Send['MerchantTradeNo']   = $order['order_id'] . "T" . time(); 
    $obj->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
    $obj->Send['TotalAmount']       = (int)$order['total_amount'];
    $obj->Send['TradeDesc']         = "商城購物交易"; // 交易描述
    $obj->Send['ChoosePayment']     = ECPay_PaymentMethod::Credit; // 付款方式: ALL 或 Credit
    
    // 重要網址設定
    // ReturnURL: 綠界伺服器背景呼叫 (Server post to Server)，不可為 Localhost
    $obj->Send['ReturnURL']         = "https://tibamef2e.com/cjd102/g2/recimo/mall/callback.php"; 
    // OrderResultURL: 用戶付款完成後，綠界將用戶導回的頁面 (Client redirect)
    // $obj->Send['OrderResultURL']    = "https://tibamef2e.com/cjd102/g2/recimo/workspace/orders";
    $obj->Send['OrderResultURL']    = " https://squeakier-mona-inartistically.ngrok-free.dev/recimo_api/mall/ecpay_checkout_return_url.php";
    // NeedExtraPaidInfo: 是否需要額外的付款資訊 (如信用卡末四碼)
    $obj->Send['NeedExtraPaidInfo'] = 'Y';

    // 訂單商品項目 (Items 必填)
    // 注意：綠界會加總這裡的金額與 TotalAmount 比對，必須一致，否則會報錯
    // 這裡先放一個範例商品，若您的資料庫有商品明細，請用 foreach 迴圈加入
    array_push($obj->Send['Items'], array(
        'Name' => "商品一批 (" . $order['order_id'] . ")", // 商品名稱
        'Price' => (int)$order['total_amount'],            // 單價
        'Currency' => "NTD",                               // 幣別
        'Quantity' => (int)1,                              // 數量
        'URL' => "https://your-shop.com/product"           // 商品網址 (選填)
    ));

    // --- 除錯區塊 ---
    if ($debug_mode) {
        echo "<h3>Debug Mode: 即將送出的參數</h3>";
        echo "<pre>";
        print_r($obj->Send);
        echo "</pre>";
        echo "<hr>";
        echo "<p>若參數正確，請移除網址中的 <code>&debug=1</code> 進行正式串接。</p>";
        
        // 為了檢查 CheckMacValue，我們手動觸發一次 SDK 內部邏輯 (僅供檢視，非必要)
        // 注意：這只是為了印出來看，實際上 CheckOut() 會再算一次
        echo "<h3>預覽 CheckMacValue (SDK自動產生)</h3>";
        // 暫時模擬送出以取得 CheckMacValue (通常不需要這樣做，僅為了讓你安心)
        echo "系統將在提交時自動計算，無需手動生成。";
        exit;
    }

    // 產生表單並自動送出 (Auto Submit)
    $obj->CheckOut();

} catch (Exception $e) {
    echo '<div style="color:red; font-weight:bold;">發生錯誤：' . $e->getMessage() . '</div>';
}