<?php
// C:\MAMP\htdocs\recimo_api\recipes\recipe_tags_get.php

require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

function getRecipeTags($pdo, $recipe_id) {
    $sql = "SELECT rt.tag_id, t.tag_name, t.tag_type
            FROM recipe_tag rt
            JOIN tags t ON rt.tag_id = t.tag_id
            WHERE rt.recipe_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$recipe_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 既然是獨立 API，就要執行並回傳
$recipe_id = $_GET['recipe_id'] ?? null;
if ($recipe_id) {
    echo json_encode([
        'success' => true,
        'tags' => getRecipeTags($pdo, $recipe_id)
    ]);
}