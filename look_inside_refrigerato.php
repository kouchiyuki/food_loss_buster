<?php
require_once 'db_config.php';
$pdo = connectDB();

// 現在の日付を取得
$today = new DateTime();

// 期限が近い食材を優先表示するため、expiry_dateが近い順にデータを取得
try {
    $sql = "SELECT i.*, m.name, m.unit, m.category, i.id 
            FROM food_items i
            JOIN food_master m ON i.master_id = m.master_id
            -- 在庫が0より大きいもののみ表示
            WHERE i.quantity > 0 
            ORDER BY i.expiry_date ASC, i.registered_at ASC"; // 期限が早い順、登録が古い順
    
    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "データ取得エラー: " . $e->getMessage();
    $items = [];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>なかをみる - Food Loss Buster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 800px; margin-top: 50px; }
        .expired { background-color: #f8d7da; border-color: #f5c6cb; } /* 期限切れ背景色 */
        .alert-near { background-color: #fff3cd; border-color: #ffeeba; } /* 期限近い背景色 */
        .text-danger-strong { color: #dc3545 !important; font-weight: bold; } /* 赤文字強調 */
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4 text-center">なかをみる</h1>
        <p class="text-center text-muted">冷蔵庫の在庫と期限をチェック！</p>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form id="recipe_search_form" method="GET" action="https://cookpad.com/search/" target="_blank">

            <table class="table table-striped table-hover mt-4">
                <thead>
                    <tr>
                        <th></th> <th>たべもの</th>
                        <th>かず</th>
                        <th>カテゴリー</th>
                        <th>のこり期限</th>
                        <th>登録日</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" class="text-center">冷蔵庫の中は空っぽです。たべものをいれましょう！</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): 
                            $expiry_date = new DateTime($item['expiry_date']);
                            $interval = $today->diff($expiry_date);
                            $days_remaining = (int)$interval->format('%R%a'); // 残り日数を取得 (Rは符号)
                            
                            $row_class = '';
                            $expiry_text = $item['expiry_date'];
                            $expiry_style = '';

                            if ($days_remaining <= 0) {
                                // 期限切れ
                                $row_class = 'expired';
                                $expiry_text = '⚠️ 期限切れ！';
                                $expiry_style = 'class="text-danger-strong"';
                            } elseif ($days_remaining <= 7) {
                                // 残り7日以内
                                $row_class = 'alert-near';
                                $expiry_text = 'あと' . $days_remaining . '日！';
                                $expiry_style = 'class="text-danger-strong"';
                            } else {
                                $expiry_text = 'あと' . $days_remaining . '日';
                            }
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td>
                                <input type="checkbox" name="selected_foods[]" 
                                       value="<?= htmlspecialchars($item['name']) ?>" 
                                       class="form-check-input food-checkbox">
                            </td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['quantity'] . ' ' . $item['unit']) ?></td>
                            <td><?= htmlspecialchars($item['category']) ?></td>
                            <td <?= $expiry_style ?>><?= $expiry_text ?></td>
                            <td><?= htmlspecialchars(date('Y/m/d', strtotime($item['registered_at']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="d-grid gap-2 mt-4 mb-4">
                <button type="submit" class="btn btn-success w-100" id="search_button" disabled>
                    ✅ 選んだ食材でレシピ提案！
                </button>
            </div>
        
        </form>
        <div class="d-grid gap-2 mt-4">
            <a href="top_refrigerator.php" class="btn btn-secondary">トップにもどる</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.food-checkbox');
            const searchButton = document.getElementById('search_button');
            const form = document.getElementById('recipe_search_form');

            const updateButtonState = () => {
                // チェックされた食材の数をカウント
                const checkedFoods = Array.from(checkboxes).filter(cb => cb.checked);
                const count = checkedFoods.length;
                
                if (count > 0) {
                    searchButton.disabled = false;
                    searchButton.textContent = `✅ ${count}つの食材でレシピ提案！`;
                } else {
                    searchButton.disabled = true;
                    searchButton.textContent = '✅ 選んだ食材でレシピ提案！';
                }
            };

            // チェックボックスの状態が変更されたらボタンを更新
            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateButtonState);
            });

            // フォーム送信時、選択された食材名を結合して検索クエリを生成
            form.addEventListener('submit', function(event) {
                const checkedFoods = Array.from(checkboxes)
                                         .filter(cb => cb.checked)
                                         .map(cb => cb.value); // 食材名を取得
                
                if (checkedFoods.length === 0) {
                    // 基本的にはボタンがdisabledなので不要だが念のため
                    event.preventDefault(); 
                    return;
                }

                // 選択された食材名をスペース区切り（検索サイトに優しい形式）で結合
                const queryString = checkedFoods.join(' ');
                
                // Cookpadの検索URLに動的に設定（URLエンコードはブラウザが自動で行う）
                this.action = `https://cookpad.com/search/${queryString}`;
            });
            
            // ページロード時の初期状態を設定
            updateButtonState();
        });
    </script>
</body>
</html>