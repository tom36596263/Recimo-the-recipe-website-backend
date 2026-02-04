<?php
// recipe_delete.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);
$recipe_id = $input['recipe_id'] ?? null;


function rmdir_recursive($dir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file !== "." && $file !== "..") {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {
                    rmdir_recursive($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($dir);
    }
}

if(is_dir("../img/recipes/$recipe_id")) {
    rmdir_recursive("../img/recipes/$recipe_id");
}


if (!$recipe_id) {
    echo json_encode(["status" => "error", "message" => "缺少食譜 ID"]);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM recipes WHERE recipe_id = ?");
    $stmt->execute([$recipe_id]);
//C:\MAMP\htdocs\recimo_api\img\recipes\163
    

    if ($stmt->rowCount() > 0) {
        echo json_encode(["status" => "success", "message" => "刪除成功"]);
    } else {
        echo json_encode(["status" => "error", "message" => "找不到該食譜，可能已被刪除"]);
    }
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫錯誤：" . $e->getMessage()]);
}
?>