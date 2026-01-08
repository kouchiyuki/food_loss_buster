<?php
session_start();
require_once 'db_config.php';
$pdo = connectDB();

// --- 1. 「だす（食べた）」処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['selected_items'] as $id) {
            // 在庫を0にする（または1減らすなど、運用に合わせて調整可能）
            // 今回は「使い切った」として数量を0に更新します
            $sql = "UPDATE food_items SET quantity = 0 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
        }
        
        $pdo->commit();
        $message = "ごちそうさまでした！れいぞうこが スッキリしたよ。";
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
        body { 
            background-color: #fff9e6; 
            font-family: 'Kiwi Maru', serif;
            padding: 20px;
        }

        .main-board {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 40px;
            border: 8px solid #ffc1c1; /* 「だす」はピンクの枠 */
            box-shadow: 0 10px 0px #ffabab;
            overflow: hidden;
            padding-bottom: 20px;
        }

        .header-banner {
            background-color: #ffc1c1;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            text-shadow: 2px 2px 0px rgba(0,0,0,0.1);
        }

        .table thead th {
            background-color: #fff0f0;
            border-bottom: 3px solid #ffc1c1;
            color: #555;
        }

        /* 食べ終わった時のボタン（おままごと風） */
        .btn-eat {
            background-color: #a0c4ff; /* 爽やかな水色 */
            border: 3px solid #333;
            border-radius: 20px;
            padding: 15px;
            font-weight: bold;
            color: #333;
            transition: all 0.2s;
            box-shadow: 0 5px 0 #333;
            font-size: 1.2rem;
        }
        .btn-eat:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 7px 0 #333;
            background-color: #8eb9ff;
        }
        .btn-eat:disabled {
            background-color: #eee;
            border-color: #ccc;
            box-shadow: none;
            color: #999;
        }

        .food-checkbox {
            width: 25px;
            height: 25px;
            cursor: pointer;
        }

        .btn-back {
            color: #888;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <div class="main-board">
        <div class="header-banner">
            🍴 たべものをだす
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success m-3">✨ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger m-3">⚠️ <?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th class="text-center">だす</th>
                            <th>たべもの</th>
                            <th>のこり</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="3" class="text-center p-5">
                                    れいぞうこは 空っぽだよ。
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="selected_items[]" 
                                           value="<?= $item['id'] ?>" 
                                           class="form-check-input food-checkbox">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($item['expiry_date']) ?> まで</small>
                                </td>
                                <td><?= htmlspecialchars($item['quantity'] . $item['unit']) ?></td>
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
        // チェックが入っている時だけボタンを押せるようにする
        const checkboxes = document.querySelectorAll('.food-checkbox');
        const eatButton = document.getElementById('eat_button');

        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                const checkedCount = Array.from(checkboxes).filter(c => c.checked).length;
                eatButton.disabled = checkedCount === 0;
                if(checkedCount > 0) {
                    eatButton.textContent = `😋 ${checkedCount}つ をたべたよ！`;
                } else {
                    eatButton.textContent = '😋 たべたよ！';
                }
            });
        });
    </script>
</body>
</html>