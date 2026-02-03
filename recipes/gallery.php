<?php
// 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\gallery.php

require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// --- [GET] 取得該食譜的所有成品照 ---
if ($method === 'GET') {
    $recipe_id = isset($_GET['recipe_id']) ? intval($_GET['recipe_id']) : 0;

    if ($recipe_id <= 0) {
        echo json_encode(['success' => false, 'message' => '無效的食譜 ID']);
        exit;
    }

    try {
        $sql = "SELECT g.*, u.user_name 
                FROM recipe_gallery g
                LEFT JOIN users u ON g.user_id = u.user_id
                WHERE g.recipe_id = :recipe_id
                ORDER BY g.upload_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':recipe_id' => $recipe_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => '讀取失敗: ' . $e->getMessage()]);
    }
}

// --- [POST] 上傳新的成品照 ---
else if ($method === 'POST') {
    $recipe_id = $_POST['recipe_id'] ?? null;
    $user_id   = $_POST['user_id']   ?? null;
    $gallery_text = $_POST['gallery_text'] ?? '';

    if (!$recipe_id || !$user_id || !isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => '缺少必要欄位或圖片']);
        exit;
    }

    try {
        $upload_dir = "../img/social/32/gallery/{$recipe_id}/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = time() . "_" . uniqid() . "." . $file_ext;
        $target_path = $upload_dir . $file_name;
        
        $db_url = "img/social/32/gallery/{$recipe_id}/" . $file_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $sql = "INSERT INTO recipe_gallery (recipe_id, user_id, gallery_text, upload_at, gallery_url) 
                    VALUES (:rid, :uid, :txt, NOW(), :url)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':rid' => $recipe_id,
                ':uid' => $user_id,
                ':txt' => $gallery_text,
                ':url' => $db_url
            ]);

            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => '成品照發布成功！',
                    'new_id'  => $pdo->lastInsertId()
                ]);
            }
        } else {
            throw new Exception("圖片移動失敗");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '上傳失敗：' . $e->getMessage()]);
    }
}

// --- 🏆 [DELETE] 刪除本人作品照 ---
else if ($method === 'DELETE' || ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete')) {
    // 取得 JSON 資料
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    $gallery_id = $data['gallery_id'] ?? null;
    $user_id    = $data['user_id']    ?? null;

    if (!$gallery_id || !$user_id) {
        echo json_encode(['success' => false, 'message' => '缺少 ID 或用戶資訊']);
        exit;
    }

    try {
        // 1. 先查出圖片 URL 並確認擁有者
        $stmt = $pdo->prepare("SELECT gallery_url FROM recipe_gallery WHERE gallery_id = ? AND user_id = ?");
        $stmt->execute([$gallery_id, $user_id]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => '無權限或找不到該筆資料']);
            exit;
        }

        // 2. 刪除硬碟實體檔案 ( unlink )
        $file_path = "../" . $row['gallery_url']; // 加上目錄前綴
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // 3. 刪除資料庫紀錄
        $del_stmt = $pdo->prepare("DELETE FROM recipe_gallery WHERE gallery_id = ?");
        $del_result = $del_stmt->execute([$gallery_id]);

        if ($del_result) {
            echo json_encode(['success' => true, 'message' => '作品已成功移除']);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => '刪除出錯: ' . $e->getMessage()]);
    }
}
?>