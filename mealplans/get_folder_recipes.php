<?php
// recimo_api/mealplans/get_folder_recipes.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$folder_id = $_GET['folder_id'] ?? null;

if (!$folder_id) {
    echo json_encode([]);
    exit;
}

try {
    // 從 favorites 資料表中，找出該 folder_id 對應的所有 recipe_id
    $sql = "SELECT recipe_id FROM favorites WHERE folder_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$folder_id]);

    // 只取 recipe_id 欄位，回傳一維陣列 [1, 5, 10, ...]
    $recipeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($recipeIds);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
