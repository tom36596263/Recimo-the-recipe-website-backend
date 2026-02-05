<?php
/*
 * 檔案名稱：payment_return.php
 * 功能：接收綠界 POST 回傳的付款結果，並轉導 (Redirect) 回前端頁面 (GET)
 */

// 1. 設定前端頁面的網址
// 請將此處修改為您前端開發伺服器的接收頁面
// 當付款完成點擊「返回商店」時，瀏覽器會跳轉到這裡
$frontend_url = "http://localhost:5173/workspace/orders";

// 進階設定：如果您有正式站與測試站，可以用 PHP 判斷切換
// if ($_SERVER['HTTP_HOST'] !== 'localhost') {
//     $frontend_url = "https://www.your-production-site.com/workspace/orders";
// }

// 2. 接收綠界回傳的參數 (POST)
// 這些是綠界回傳的關鍵欄位
$rtnCode = $_POST['RtnCode'] ?? '0';           // 1=成功，其他=失敗
$rtnMsg  = $_POST['RtnMsg'] ?? '';             // 交易訊息 (例如: 交易成功)
$tradeNo = $_POST['MerchantTradeNo'] ?? '';    // 特店訂單編號 (您原本送出的 id)
$amount  = $_POST['TradeAmt'] ?? '0';          // 交易金額
$paymentDate = $_POST['PaymentDate'] ?? '';    // 付款時間

// 3. 組合轉址參數
// 將收到的 POST 資料轉為 GET 參數，拼接到前端網址後面
$redirect_url = sprintf(
    "%s?status=%s&msg=%s&order_id=%s&amount=%s&date=%s",
    $frontend_url,
    urlencode($rtnCode),
    urlencode($rtnMsg),
    urlencode($tradeNo),
    urlencode($amount),
    urlencode($paymentDate)
);

// 4. 執行轉址 (Redirect)
// 這裡將使用 HTTP 302 轉址，將使用者瀏覽器導向前端頁面
// 這樣前端就會收到 GET 請求，避開 404 POST Error
header("Location: $redirect_url");
exit;
?>