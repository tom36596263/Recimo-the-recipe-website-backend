<?php
// 1. 取得請求來源的網址 (例如 http://localhost:5174)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// 2. 設定允許存取的白名單 (以後部署就把正式網址加進這個陣列)
$allowed_origins = [
    'http://localhost:5173',   // Vite 預設網址 1
    'http://localhost:5174',   // Vite 預設網址 2
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174',
    'https://tibamef2e.com'
];

// 3. 檢查來源是否在白名單內
if (in_array($origin, $allowed_origins)) {
    // 這裡不能寫 *，必須精確回傳該來源網址
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    // 如果包含 localhost 但沒在陣列精準匹配到，也直接允許該來源
    header("Access-Control-Allow-Origin: " . $origin);
}

// 4. 允許攜帶 Cookie / Session
header("Access-Control-Allow-Credentials: true");

// 5. 允許的請求動作 (Methods)
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");

// 6. 允許前端發送的 Header 標頭
// 包含 Content-Type 是為了讓 PHP 讀得到 JSON，X-Requested-With 常用於判斷 AJAX
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// 7. 處理瀏覽器的「預檢請求 (OPTIONS)」
// 瀏覽器在發送正式 POST 之前會先發 OPTIONS 詢問權限，這時直接回傳 200 並結束
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// CORS headers 全部開放
// header("Access-Control-Allow-Origin: *"); // 允許誰可以抓php，「*」代表「任何人/任何網域」
// header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PATCH, PUT, DELETE"); // 允許可以執行那些動作
// header("Access-Control-Allow-Headers: Content-Type, Authorization"); // 允許可以帶走哪些情報Content-Type 是為了傳 JSON，Authorization 是為了傳登入用的 Token

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//   http_response_code(200);
//   exit;
// }
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
