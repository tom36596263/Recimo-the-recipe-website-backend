<?php
if( str_contains($_SERVER["HTTP_HOST"], "127.0.0.1") || str_contains($_SERVER["HTTP_HOST"], "localhost") ){
    // localhost
    $db_host = '127.0.0.1';
    $db_port = 8889;
    $db_dbname = 'recimo';

    $db_user = 'root';
    $db_password = 'root';
}else{
    // remote
    $db_host = '127.0.0.1';               // 資料庫主機(ip)
    $db_port = 3306;                      // 資料庫 port number
    $db_dbname = 'tibamefe_cjd102g2';                // 資料庫名稱 (需更改)
}


$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
} catch (PDOException $e) {
    exit();
}
?>