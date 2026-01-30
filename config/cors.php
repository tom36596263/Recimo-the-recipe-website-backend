<?php
// CORS headers 全部開放
header("Access-Control-Allow-Origin: *"); // 允許誰可以抓php，「*」代表「任何人/任何網域」
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PATCH, PUT, DELETE"); // 允許可以執行那些動作
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // 允許可以帶走哪些情報Content-Type 是為了傳 JSON，Authorization 是為了傳登入用的 Token

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  http_response_code(200);
  exit;
}
// 以上的if判斷是在處理 「預檢請求」(Preflight Request)。
// 瀏覽器為了安全，會自動先發送一個隱形的測試請求，這個測試請求的動作 (Method) 就叫做 OPTIONS。
// 若不寫此if判斷式，瀏覽器發送OPTIONS 預檢請求時，它期待得到一個 200 OK 的回應，
// PHP 會把 OPTIONS 當作一般的請求，繼續往下執行 example.php 裡面的 SQL 語句。
// 但因為 OPTIONS 請求通常不會帶著 SQL 需要的參數（例如 recipe_id），你的 PHP 可能會報錯（500 錯誤），或者噴出資料庫連線錯誤。




// 允許的域名列表(白名單)
// $allowed_origins = [
//   "http://127.0.0.1:5500",
//   "http://localhost:5500",
//   "http://localhost:5173"
// ];

// 抓到請求的來源網域
//   $origin = $_SERVER['HTTP_ORIGIN'] ?? ''; // http://localhost:5500 或 http://127.0.0.1:5500
//   // 檢查來源是否在允許列表中
//   if (in_array($origin, $allowed_origins)) {
//     header("Access-Control-Allow-Origin: " . $origin);
//   }
//   header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
