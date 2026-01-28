<?php
$db_host = '';               // 資料庫主機(ip)
$db_port = 3306;                      // 資料庫 port number
$db_dbname = 'tibamefe_cjd102g2'; // 資料庫名稱 (需更改)

$db_user = '';      // 可連線資料庫的帳號
$db_password = '';        // 該帳號的密碼

$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    echo '<p style="color: green;">資料庫連線成功。</p>';
} catch (PDOException $e) {
    echo '<p style="color: red;">資料庫連線錯誤：' . $e->getMessage() . '。</p>';
    exit();
}
