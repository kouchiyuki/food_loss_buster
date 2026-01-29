<?php
session_start();
require_once 'db_config.php';
$pdo = connectDB();

$today = new DateTime();

try {
    // 期限が近い順に取得
    $sql = "SELECT i.id, m.name, i.quantity, m.unit, m.category, i.expiry_date, i.registered_at
            FROM food_items i
            JOIN food_master m ON i.master_id = m.master_id
            WHERE i.quantity > 0 
            ORDER BY i.expiry_date ASC, i.registered_at ASC";
    
    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "エラーだよ: " . $e->getMessage();
    $items = [];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>なかをみる - Food Loss Buster</title>
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
            border: 8px solid #a0c4ff;
            box-shadow: 0 10px 0px #8eb9ff;
            overflow: hidden;
            padding-bottom: 20px;
        }

        .header-banner {
            background-color: #a0c4ff;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            text-shadow: 2px 2px 0px rgba(0,0,0,0.1);
        }

        .table { margin-bottom: 0; background: white; }
        .table thead th {
            background-color: #f0f7ff;
            border-bottom: 3px solid #a0c4ff;
            color: #555;
            font-size: 0.9rem;
        }
        .urgent-danger {
            background-color: #D7CCC8 !important;
        }
        .urgent-text-color {
            color: #3E2723 !important;
            font-weight: bold !important;
        }

        /* 期限切れ（赤） */
        .expired { background-color: #ffe5e5 !important; }
        .text-danger-strong { color: #ff5e5e !important; font-weight: bold !important; }

        .food-checkbox { width: 20px; height: 20px; cursor: pointer; }

        .btn-recipe {
            background-color: #ffc1c1; border: 3px solid #333; border-radius: 20px;
            padding: 15px; font-weight: bold; color: #333; transition: all 0.2s;
            box-shadow: 0 5px 0 #333; font-size: 1.1rem;
        }
        .btn-recipe:hover:not(:disabled) {
            transform: translateY(-2px); box-shadow: 0 7px 0 #333; background-color: #ffadad;
        }
        .btn-recipe:disabled {
            background-color: #eee; border-color: #ccc; box-shadow: none; color: #999;
        }

        .btn-back { color: #888; text-decoration: none; font-size: 0.9rem; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>

    <div class="main-board">
        <div class="header-banner">🍳 なかをみる</div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger m-3"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form id="recipe_search_form" method="GET" action="https://cookpad.com/search/" target="_blank">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th class="text-center">選ぶ</th>
                            <th>たべもの</th>
                            <th>かず</th>
                            <th>のこり</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="4" class="text-center p-5">冷蔵庫は空っぽだよ！<br>なにかいれよう🥕</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): 
                                $expiry_date = new DateTime($item['expiry_date']);
                                $interval = $today->diff($expiry_date);
                                // 日数の差を取得（過去ならマイナスになる）
                                $days_remaining = (int)$interval->format('%r%a');
                                
                                $row_class = '';
                                $text_class = '';
                                $expiry_text = '';

                                if ($days_remaining < 0) {
                                    $row_class = 'expired';
                                    $text_class = 'text-danger-strong';
                                    $expiry_text = '⚠️ きれてる！';
                                } elseif ($days_remaining <= 3) {
                                    $row_class = 'urgent-danger'; // 行の背景色
                                    $text_class = 'urgent-text-color'; // セルの文字色
                                    $expiry_text = '🔥 あと' . $days_remaining . '日！';
                                } else {
                                    $expiry_text = 'あと' . $days_remaining . '日';
                                }
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td class="text-center">
                                    <input type="checkbox" name="selected_foods[]" 
                                           value="<?= htmlspecialchars($item['name']) ?>" 
                                           class="form-check-input food-checkbox">
                                </td>
                                <td class="<?= $text_class ?>"><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                <td class="<?= $text_class ?>"><small><?= htmlspecialchars($item['quantity'] . $item['unit']) ?></small></td>
                                <td class="<?= $text_class ?>"><small><?= $expiry_text ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-4">
                <button type="button" class="btn btn-recipe" id="btn_cookpad" disabled>Cookpadでレシピを探す</button>
                <button type="button" class="btn btn-recipe btn-warning" id="btn_ai" disabled>AIにおすすめ料理を聞く</button>
                <div class="text-center">
                    <a href="top_refrigerator.php" class="btn-back">トップにもどる</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.food-checkbox');
            const btnCookpad = document.getElementById('btn_cookpad');
            const btnAI = document.getElementById('btn_ai');

            const updateButtonState = () => {
                const checkedFoods = Array.from(checkboxes).filter(cb => cb.checked);
                const count = checkedFoods.length;
                if (count > 0) {
                    btnCookpad.disabled = false;
                    btnAI.disabled = false;
                    btnCookpad.textContent = `✅ ${count}つの食材でCookpad検索`;
                    btnAI.textContent = `🤖 ${count}つの食材でAIに聞く`;
                } else {
                    btnCookpad.disabled = true;
                    btnAI.disabled = true;
                    btnCookpad.textContent = '食材をえらんでね';
                    btnAI.textContent = '食材をえらんでね';
                }
            };

            checkboxes.forEach(cb => { cb.addEventListener('change', updateButtonState); });
            updateButtonState();

            btnCookpad.addEventListener('click', () => {
            const checkedFoods = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
            const queryString = encodeURIComponent(checkedFoods.join(' '));
            window.open(`https://cookpad.com/search/${queryString}`, '_blank');
            });

            btnAI.addEventListener('click', () => {
                const checkedFoods = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
                // 選択した食材をPOSTでAI処理用PHPに送る
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'ai_recipe.php'; // 新規作成するAI用PHP
                checkedFoods.forEach(food => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_foods[]';
                    input.value = food;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            });
        });
    </script>
</body>
</html>