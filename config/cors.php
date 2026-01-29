<!-- 處理跨域 -->
<!-- 引入方法 -->
<!-- require_once("../config/cors.php"); -->
<?php
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



// CORS headers 全部開放
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PATCH, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

?>

