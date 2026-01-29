<?php
// 設定你要檢查的檔案名稱 (請確認這裡跟你的檔名一樣)
$filename = 'get_remove_bg_ingredients.php';

echo "<h2>🕵️‍♀️ JSON 檔案偵錯模式</h2>";

// 1. 檢查檔案存不存在
if (!file_exists($filename)) {
    echo "❌ <b>找不到檔案！</b><br>";
    echo "PHP 正在尋找的路徑是：" . realpath('.') . DIRECTORY_SEPARATOR . $filename . "<br>";
    echo "請確認檔名是否正確？是否有多餘的 .txt 副檔名？";
    exit();
}

echo "✅ 檔案存在。<br>";

// 2. 讀取內容
$content = file_get_contents($filename);
if ($content === false) {
    echo "❌ <b>無法讀取檔案內容！</b> (可能是權限問題)";
    exit();
}

echo "✅ 成功讀取檔案內容，長度：" . strlen($content) . " 字元。<br>";

// 3. 嘗試解碼 JSON
$data = json_decode($content, true);

if ($data === null) {
    echo "❌ <b>JSON 解碼失敗！</b><br>";
    echo "錯誤原因：<span style='color:red; font-weight:bold;'>" . json_last_error_msg() . "</span><br>";
    echo "<hr>";
    echo "<b>常見原因分析：</b><br>";
    echo "1. Syntax error: JSON 格式有錯 (例如多了一個逗號)。<br>";
    echo "2. Malformed UTF-8 characters: 檔案編碼不是 UTF-8 (請用 VS Code 重新存成 UTF-8)。<br>";
    echo "3. Control character error: 檔案裡有看不見的隱藏字元。<br>";
} else {
    echo "🎉 <b>恭喜！JSON 格式正確，成功讀取到 " . count($data) . " 筆資料。</b>";
}
?>