<?php
// recimo_api/mealplans/get_favorites_folders.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([]);
    exit;
}

try {
    // 撈取該使用者的所有收藏夾
    $sql = "SELECT favorites_folder_id, folder_name 
            FROM favorites_folders 
            WHERE creator_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($folders);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
