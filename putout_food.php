<?php
require_once 'db_config.php';
$pdo = connectDB();

$message = '';
$error_message = '';

// POSTリクエスト（削除処理）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status'); // 'Used' or 'Wasted'
    $quantity_to_remove = filter_input(INPUT_POST, 'quantity_to_remove', FILTER_VALIDATE_INT);

    if ($item_id && $status && ($status === 'Used' || $status === 'Wasted') && $quantity_to_remove > 0) {
        $pdo->beginTransaction();
        try {
            // 1. 削除対象の在庫情報を取得
            $stmt = $pdo->prepare("SELECT quantity, master_id FROM food_items WHERE id = :id");
            $stmt->bindParam(':id', $item_id);
            $stmt->execute();
            $item = $stmt->fetch();

            if (!$item) {
                throw new Exception("食材が見つかりません。");
            }
            if ($quantity_to_remove > $item['quantity']) {
                 throw new Exception("削除数が在庫数を超えています。");
            }

            // 2. waste_logに記録（食品ロス削減実績の算出に必要）
            $stmt = $pdo->prepare("INSERT INTO waste_log (food_item_id, quantity, status) VALUES (:item_id, :quantity, :status)");
            $stmt->bindParam(':item_id', $item_id);
            $stmt->bindParam(':quantity', $quantity_to_remove);
            $stmt->bindParam(':status', $status);
            $stmt->execute();

            // 3. food_itemsから数量を減らす
            $new_quantity = $item['quantity'] - $quantity_to_remove;
            // ★★★ ここから修正 ★★★
            if ($new_quantity <= 0) {
                // 在庫が0以下になる場合、レコードを削除する代わりに数量を0に更新する
                // DBエラー1451を回避するため、物理削除はしない
                $stmt = $pdo->prepare("UPDATE food_items SET quantity = 0 WHERE id = :id");
                $stmt->bindParam(':id', $item_id);
                $stmt->execute();
                
            } else {
                // 在庫が残る場合、数量を更新
                $stmt = $pdo->prepare("UPDATE food_items SET quantity = :quantity WHERE id = :id");
                $stmt->bindParam(':quantity', $new_quantity);
                $stmt->bindParam(':id', $item_id);
                $stmt->execute();
            }
            // ★★★ ここまで修正 ★★★

            $pdo->commit();
            $message = $item['quantity'] > 1 && $new_quantity > 0 ? "一部をだしたよ！のこりは {$new_quantity} です。" : "たべものをだしたよ！";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "処理エラー: " . $e->getMessage();
        }
    } else {
        $error_message = "入力が不正です。";
    }
}

// 在庫一覧の取得（look_inside_refrigerato.phpと同じロジックで期限順に取得）
try {
    $sql = "SELECT i.*, m.name, m.unit, m.category 
            FROM food_items i
            JOIN food_master m ON i.master_id = m.master_id
            -- ★★★ ここを追加 ★★★
            WHERE i.quantity > 0 
            -- ★★★ ここまで追加 ★★★
            ORDER BY i.expiry_date ASC, i.registered_at ASC";
    
    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message .= " | データ取得エラー: " . $e->getMessage();
    $items = [];
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
            <form method="POST" class="modal-content">
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
                        <input type="hidden" id="max-quantity-limit">
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
        // モーダルが表示される際に、アイテムの情報をフォームにセットするJavaScript
        document.addEventListener('DOMContentLoaded', function() {
            var removeModal = document.getElementById('removeModal');
            removeModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var itemId = button.getAttribute('data-item-id');
                var itemName = button.getAttribute('data-item-name');
                var itemUnit = button.getAttribute('data-item-unit');
                var maxQuantity = button.getAttribute('data-max-quantity');

                var modalItemName = removeModal.querySelector('#modal-item-name');
                var modalItemId = removeModal.querySelector('#modal-item-id');
                var modalUnitDisplay = removeModal.querySelector('#modal-unit-display');
                var maxQuantityDisplay = removeModal.querySelector('#max-quantity-display');
                var quantityInput = removeModal.querySelector('#quantity_to_remove');
                
                // フォームへの値の設定
                modalItemName.textContent = itemName + ' をいくつだしますか？';
                modalItemId.value = itemId;
                modalUnitDisplay.textContent = itemUnit;
                maxQuantityDisplay.textContent = maxQuantity + ' ' + itemUnit;

                // 数量入力の最大値を設定
                quantityInput.max = maxQuantity;
                quantityInput.value = maxQuantity; // デフォルトで全量にする

                // quantity_to_removeのmax属性を直接設定
                quantityInput.setAttribute('max', maxQuantity);
            });
        });
    </script>
</body>
</html>