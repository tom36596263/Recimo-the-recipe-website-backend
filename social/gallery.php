<?php
// 檔案路徑: C:\MAMP\htdocs\recimo_api\social\gallery.php

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
        // 🏆 修正：加入 g.status = 0，確保前台不顯示被管理員下架 (1) 的作品
        $sql = "SELECT g.*, u.user_name 
                FROM recipe_gallery g
                LEFT JOIN users u ON g.user_id = u.user_id
                WHERE g.recipe_id = :recipe_id AND g.status = 0
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
else if ($method === 'POST' && !isset($_GET['action'])) {
    $recipe_id = $_POST['recipe_id'] ?? null;
    $user_id   = $_POST['user_id']   ?? null;
    $gallery_text = $_POST['gallery_text'] ?? '';

    if (!$recipe_id || !$user_id || !isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => '缺少必要欄位或圖片']);
        exit;
    }

    try {
        $upload_dir = "../img/social/gallery/{$recipe_id}/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = time() . "_" . uniqid() . "." . $file_ext;
        $target_path = $upload_dir . $file_name;
        
        $db_url = "img/social/gallery/{$recipe_id}/" . $file_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            // 🏆 修正：明確寫入 status = 0 (正常顯示)
            $sql = "INSERT INTO recipe_gallery (recipe_id, user_id, gallery_text, upload_at, gallery_url, status) 
                    VALUES (:rid, :uid, :txt, NOW(), :url, 0)";
            
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
            throw new Exception("圖片移動失敗，請檢查資料夾寫入權限");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '上傳失敗：' . $e->getMessage()]);
    }
}

// --- [DELETE] 刪除本人作品照 (這是用戶手動徹底刪除，所以維持 DELETE) ---
else if ($method === 'DELETE' || ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete')) {
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    $gallery_id = $data['gallery_id'] ?? null;
    $user_id    = $data['user_id']    ?? null;

    if (!$gallery_id || !$user_id) {
        echo json_encode(['success' => false, 'message' => '缺少 ID 或用戶資訊']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT gallery_url FROM recipe_gallery WHERE gallery_id = ? AND user_id = ?");
        $stmt->execute([$gallery_id, $user_id]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => '無權限或找不到該筆資料']);
            exit;
        }

        $file_path = "../" . $row['gallery_url']; 
        if (file_exists($file_path)) {
            unlink($file_path);
        }

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