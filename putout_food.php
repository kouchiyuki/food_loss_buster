<?php
// putout_food.php
require 'db_config.php'; // DB接続設定

$message = '';
$error_message = '';

try {
    // DB接続
    $pdo = connectDB();

    // まず在庫一覧を取得
    $sql = "
        SELECT fi.id, fm.name AS name, fi.quantity, fm.unit, fi.expiry_date
        FROM food_items fi
        JOIN food_master fm ON fi.master_id = fm.master_id
        ORDER BY fi.expiry_date ASC
    ";
    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll();

} catch (Exception $e) {
    $items = [];
    $error_message = "在庫情報の取得に失敗しました: " . htmlspecialchars($e->getMessage());
}

// POSTで受け取った場合
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $food_item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $quantity = isset($_POST['quantity_to_remove']) ? (int)$_POST['quantity_to_remove'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : ''; // 'Used' or 'Wasted'

    if ($food_item_id <= 0 || $quantity <= 0 || !in_array($status, ['Used','Wasted'])) {
        $error_message = "不正なリクエストです。";
    } else {
        try {
            $pdo->beginTransaction();

            // ① 在庫を減らす（数量チェックも同時に）
            $updateSql = "
                UPDATE food_items
                SET quantity = quantity - :quantity
                WHERE id = :food_item_id AND quantity >= :quantity
            ";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':quantity' => $quantity,
                ':food_item_id' => $food_item_id
            ]);

            // ② 廃棄の場合のみ waste_log に記録
            if ($status === 'Wasted') {
                $logSql = "
                    INSERT INTO waste_log (food_item_id, quantity, status, logged_at)
                    VALUES (:food_item_id, :quantity, 'Wasted', NOW())
                ";
                $logStmt = $pdo->prepare($logSql);
                $logStmt->execute([
                    ':food_item_id' => $food_item_id,
                    ':quantity' => $quantity
                ]);
            }

            $pdo->commit();
            $message = "在庫を更新しました。";

            // 更新後に在庫一覧を再取得
            $stmt = $pdo->query($sql);
            $items = $stmt->fetchAll();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "エラーが発生しました：" . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>たべものをだす - Food Loss Buster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 800px; margin-top: 50px; }
        .alert-near { background-color: #fff3cd; border-color: #ffeeba; } 
        .text-danger-strong { color: #dc3545 !important; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4 text-center">たべものをだす</h1>
        <p class="text-center text-muted">使った分、捨てた分を記録しよう！</p>
        
        <?php if ($message): ?>
            <div class="alert alert-success" role="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="alert alert-info">
            「たべものなまえ」の横の「だす」ボタンを押して、使用量と状態を選んでね。
        </div>

        <?php if (empty($items)): ?>
            <p class="text-center">冷蔵庫の中は空っぽです。</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped mt-4">
                    <thead>
                        <tr>
                            <th>たべもの</th>
                            <th>のこりかず</th>
                            <th>のこり期限</th>
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): 
                            $expiry_date = new DateTime($item['expiry_date']);
                            $today = new DateTime();
                            $interval = $today->diff($expiry_date);
                            $days_remaining = (int)$interval->format('%R%a');
                            $row_class = $days_remaining <= 7 ? 'alert-near' : '';
                            $expiry_text = $days_remaining <= 0 ? '⚠️ 期限切れ' : 'あと' . $days_remaining . '日';
                            $expiry_style = $days_remaining <= 7 ? 'class="text-danger-strong"' : '';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['quantity'] . ' ' . $item['unit']) ?></td>
                            <td <?= $expiry_style ?>><?= $expiry_text ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                        data-bs-target="#removeModal" 
                                        data-item-id="<?= $item['id'] ?>" 
                                        data-item-name="<?= htmlspecialchars($item['name']) ?>"
                                        data-item-unit="<?= htmlspecialchars($item['unit']) ?>"
                                        data-max-quantity="<?= $item['quantity'] ?>">
                                    だす
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="d-grid gap-2 mt-4">
            <a href="top_refrigerator.php" class="btn btn-secondary">トップにもどる</a>
        </div>
    </div>

    <div class="modal fade" id="removeModal" tabindex="-1" aria-labelledby="removeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="putout_food.php" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeModalLabel">たべものをだす</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="modal-item-name"></p>

                    <div class="mb-3">
                        <label for="quantity_to_remove" class="form-label">いくつだす？ (のこり: <span id="max-quantity-display"></span>)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="quantity_to_remove" name="quantity_to_remove" min="1" required>
                            <span class="input-group-text" id="modal-unit-display"></span>
                        </div>
                        <input type="hidden" name="item_id" id="modal-item-id">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">どうしたの？</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="statusUsed" value="Used" required checked>
                            <label class="form-check-label" for="statusUsed">
                                🍳 使いました（削減実績に貢献！）
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="statusWasted" value="Wasted">
                            <label class="form-check-label" for="statusWasted">
                                🗑️ 捨てました（食品ロスとして記録）
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-danger">だす！</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var removeModal = document.getElementById('removeModal');
            removeModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var itemId = button.getAttribute('data-item-id');
                var itemName = button.getAttribute('data-item-name');
                var itemUnit = button.getAttribute('data-item-unit');
                var maxQuantity = button.getAttribute('data-max-quantity');

                removeModal.querySelector('#modal-item-name').textContent = itemName + ' をいくつだしますか？';
                removeModal.querySelector('#modal-item-id').value = itemId;
                removeModal.querySelector('#modal-unit-display').textContent = itemUnit;
                removeModal.querySelector('#max-quantity-display').textContent = maxQuantity + ' ' + itemUnit;
                var quantityInput = removeModal.querySelector('#quantity_to_remove');
                quantityInput.max = maxQuantity;
                quantityInput.value = maxQuantity;
            });
        });
    </script>
</body>
</html>
