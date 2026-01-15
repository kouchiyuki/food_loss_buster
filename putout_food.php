<?php
session_start();
require_once 'db_config.php';
$pdo = connectDB();

// --- 1. 「だす（食べた）」処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['use_quantities'])) {
    try {
        $pdo->beginTransaction();
        
        $count = 0;
        foreach ($_POST['use_quantities'] as $id => $used_qty) {
            $used_qty = (int)$used_qty;
            if ($used_qty <= 0) continue; // 0以下の入力は無視

            // 在庫を減らす（マイナスにならないように GREATEST 関数を使用）
            $sql = "UPDATE food_items 
                    SET quantity = GREATEST(0, quantity - ?) 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$used_qty, $id]);
            $count++;
        }
        
        $pdo->commit();
        if ($count > 0) {
            $message = "ごちそうさまでした！れいぞうこが スッキリしたよ。";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "エラーになっちゃった: " . $e->getMessage();
    }
}

// --- 2. 在庫一覧の取得 ---
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
            max-width: 600px; margin: 0 auto; background: white;
            border-radius: 40px; border: 8px solid #ffc1c1;
            box-shadow: 0 10px 0px #ffabab; overflow: hidden; padding-bottom: 20px;
        }
        .header-banner {
            background-color: #ffc1c1; color: white; padding: 20px;
            text-align: center; font-size: 1.5rem; font-weight: bold;
        }
        /* 数字入力欄を可愛く */
        .qty-input {
            width: 70px; border: 3px solid #ffefef; border-radius: 10px;
            text-align: center; font-weight: bold; color: #ff8a8a;
        }
        .qty-input:focus { border-color: #ffc1c1; outline: none; background-color: #fffafa; }
        
        .btn-eat {
            background-color: #a0c4ff; border: 3px solid #333;
            border-radius: 20px; padding: 15px; font-weight: bold;
            box-shadow: 0 5px 0 #333; font-size: 1.2rem; transition: 0.2s;
        }
        .btn-eat:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 7px 0 #333; }
        .btn-eat:disabled { background-color: #eee; border-color: #ccc; box-shadow: none; color: #999; }
        .btn-back { color: #888; text-decoration: none; font-size: 0.9rem; margin-top: 20px; display: inline-block; }
    </style>
</head>
<body>

    <div class="main-board">
        <div class="header-banner">🍴 たべものをだす</div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success m-3">✨ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>たべもの</th>
                            <th class="text-center">のこり</th>
                            <th class="text-center">つかう数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="3" class="text-center p-5">れいぞうこは 空っぽだよ。</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($item['expiry_date']) ?> まで</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-light text-dark border">
                                        <?= htmlspecialchars($item['quantity'] . $item['unit']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <input type="number" 
                                               name="use_quantities[<?= $item['id'] ?>]" 
                                               class="form-control qty-input use-input" 
                                               min="0" 
                                               max="<?= $item['quantity'] ?>" 
                                               value="0">
                                        <span class="ms-1 small"><?= htmlspecialchars($item['unit']) ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-4">
                <button type="submit" class="btn btn-eat w-100" id="eat_button" disabled>
                    😋 たべたよ！
                </button>
                <div class="text-center">
                    <a href="top_refrigerator.php" class="btn-back">トップにもどる</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        const inputs = document.querySelectorAll('.use-input');
        const eatButton = document.getElementById('eat_button');

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                // いずれかの入力が0より大きければボタンを有効化
                const hasValue = Array.from(inputs).some(i => parseInt(i.value) > 0);
                eatButton.disabled = !hasValue;
                
                // 入力された合計数をボタンに表示（おまけ機能）
                const totalUsed = Array.from(inputs).reduce((sum, i) => sum + parseInt(i.value || 0), 0);
                if(totalUsed > 0) {
                    eatButton.textContent = `😋 ${totalUsed}つ たべたよ！`;
                } else {
                    eatButton.textContent = '😋 たべたよ！';
                }
            });
        });
    </script>
</body>
</html>