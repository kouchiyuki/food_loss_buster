<?php
session_start();
require_once 'db_config.php';
$pdo = connectDB();

$message = "";
$error_message = "";

// --- 送信処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    try {
        $parts = explode('_', $_POST['action_type']);
        $action = $parts[0];
        $id = (int)$parts[1];
        $used_qty = (int)$_POST['use_quantities'][$id];

        if ($used_qty > 0) {
            $pdo->beginTransaction();

            // 食材情報を取得
            $stmt = $pdo->prepare("SELECT m.name, m.unit FROM food_items i JOIN food_master m ON i.master_id = m.master_id WHERE i.id = ?");
            $stmt->execute([$id]);
            $food = $stmt->fetch();

            if ($food) {
                // 在庫を減らす
                $sql = "UPDATE food_items SET quantity = GREATEST(0, quantity - ?) WHERE id = ?";
                $pdo->prepare($sql)->execute([$used_qty, $id]);

                $db_status = ($action === 'eaten') ? 'Used' : 'Wasted';

                // 廃棄ログ(waste_log)に記録
                $logSql = "INSERT INTO waste_log (food_item_id, quantity, status, logged_at) VALUES (?, ?, ?, NOW())";
                $logStmt = $pdo->prepare($logSql);
                $logStmt->execute([$id, $used_qty, $db_status]);

                $pdo->commit();

                if ($db_status === 'Used') {
                    $message = "✨ すごい！ " . htmlspecialchars($food['name']) . " をたべたんだね！(^▽^)/";
                } else {
                    $message = "😢 " . htmlspecialchars($food['name']) . "、つぎは たべようね...(;_;)";
                }
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_message = "エラーになっちゃった: " . $e->getMessage();
    }
}

// --- 在庫一覧の取得
try {
    $sql = "SELECT i.id, m.name, i.quantity, m.unit, i.expiry_date
            FROM food_items i
            JOIN food_master m ON i.master_id = m.master_id
            WHERE i.quantity > 0 
            ORDER BY i.expiry_date ASC";
    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "データがよめなかったよ: " . $e->getMessage();
    $items = [];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>たべものをだす - Food Loss Buster</title>
    <link href="https://fonts.googleapis.com/css2?family=Kiwi+Maru:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #fff9e6; font-family: 'Kiwi Maru', serif; padding: 20px; }
        .main-board {
            max-width: 600px; 
            margin: 0 auto; 
            background: white;
            border-radius: 40px; 
            border: 8px solid #ffc1c1;
            box-shadow: 0 10px 0px #ffabab; 
            overflow: hidden;
        }
        .header-banner {
            background-color: #ffc1c1; 
            color: white; padding: 20px;
            text-align: center; 
            font-size: 1.5rem; 
            font-weight: bold;
        }
        .food-card { 
            border-bottom: 2px dashed #eee; 
            padding: 10px 15px;
            margin: 0 10px;
         }
        .food-name { 
            font-size: 0.95rem; 
            font-weight: bold; 
        }
        .food-info { 
            font-size: 0.8rem; 
            color: #666; 
        }
        /* 廃棄ボタン*/
        .btn-waste {
            background-color: #5D4037; 
            color: #D7CCC8; 
            border: 2px solid #3E2723;
            font-size: 0.75rem; 
            border-radius: 8px; 
            padding: 4px 8px;
            box-shadow: 0 3px 0 #3E2723;
        }
        .btn-waste:hover { background-color: #3E2723; color: white; }
        
        /* たべれたよ！ボタン */
        .btn-eat {
            background-color: #ffca28; 
            color: #5D4037; 
            border: 2px solid #ff8f00;
            font-size: 0.75rem;
            font-weight: bold; 
            border-radius: 8px; 
            padding: 4px 8px;
            box-shadow: 0 3px 0 #ff8f00;
        }
        .btn-eat:hover { background-color: #ffb300; }

        .qty-input { width: 60px; text-align: center; border-radius: 8px; border: 2px solid #ddd; }
        .btn-back { color: #888; text-decoration: none; display: block; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>

<div class="main-board">
    <div class="header-banner">🍴 たべものを だす</div>

    <?php if ($message): ?>
        <div class="alert alert-warning m-3 text-center"><strong><?= htmlspecialchars($message) ?></strong></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger m-3"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php if (empty($items)): ?>
            <div class="p-5 text-center">れいぞうこは 空っぽだよ。</div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="food-card d-flex align-items-center justify-content-between">
                    <div>
                        <span class="fs-5 fw-bold"><?= htmlspecialchars($item['name']) ?></span>
                        <span class="badge bg-info text-dark ms-2"><?= htmlspecialchars($item['quantity'] . $item['unit']) ?></span>
                        <div class="text-muted small">きげん: <?= htmlspecialchars($item['expiry_date']) ?></div>
                    </div>

                    <div class="text-end">
                        <div class="mb-2">
                            数: <input type="number" name="use_quantities[<?= $item['id'] ?>]" 
                                           class="qty-input" value="1" min="1" max="<?= $item['quantity'] ?>">
                        </div>
                        <div class="btn-group">
                            <button type="submit" name="action_type" value="eaten_<?= $item['id'] ?>" class="btn btn-eat me-2">
                                😋 たべれたよ！
                            </button>
                            <button type="submit" name="action_type" value="wasted_<?= $item['id'] ?>" class="btn btn-waste">
                                (;_;) たべれなかった
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </form>
</div>

<a href="top_refrigerator.php" class="btn-back">トップにもどる</a>

</body>
</html>