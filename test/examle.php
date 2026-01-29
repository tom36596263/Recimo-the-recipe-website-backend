<?php
// 1. 引用資料庫設定檔 (注意路徑：從 recipes 資料夾往上一層到 config)
require_once '../config/db_config.php';

try {
    // 2. 撰寫 SQL 語句：抓取食譜名稱、分類與圖片路徑
    $sql = "SELECT recipe_id, recipe_name, recipe_description, recipe_image_url 
            FROM recipes 
            ORDER BY recipe_id DESC";

    // 3. 執行查詢並取得所有資料
    $stmt = $pdo->query($sql);
    $recipes = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "抓取資料失敗：" . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <title>我的食譜清單</title>
    <style>
        .recipe-card {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px;
            display: inline-block;
            width: 250px;
            vertical-align: top;
        }

        .recipe-card img {
            width: 100%;
            height: auto;
            border-radius: 5px;
        }

        .recipe-name {
            font-weight: bold;
            color: #d35400;
        }
    </style>
</head>

<body>

    <h1>🍳 所有的美味食譜</h1>

    <?php if (count($recipes) > 0): ?>
        <div class="recipe-container">
            <?php foreach ($recipes as $row): ?>
                <div class="recipe-card">
                    <img src="../<?= htmlspecialchars($row['recipe_image_url']) ?>" alt="食譜圖片">

                    <div class="recipe-name">
                        <?= htmlspecialchars($row['recipe_name']) ?>
                    </div>

                    <p><?= htmlspecialchars(mb_substr($row['recipe_description'], 0, 30)) ?>...</p>

                    <a href="detail.php?id=<?= $row['recipe_id'] ?>">查看作法</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>目前還沒有任何食譜喔！快去後台新增吧。</p>
    <?php endif; ?>

</body>

</html>