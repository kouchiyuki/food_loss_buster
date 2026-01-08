<?php
/**
 * Food Loss Buster - 食材登録画面 (insert_food.php)
 * 修正版：単位も自由に登録できるように変更
 */
session_start();
require_once 'db_config.php';
$pdo = connectDB();

// food_masterから全食材を取得（予測変換リスト用）
try {
    $stmt = $pdo->query("SELECT master_id, name, unit FROM food_master ORDER BY name ASC");
    $master_foods = $stmt->fetchAll();
} catch (PDOException $e) {
    exit("マスターデータ取得エラー: " . $e->getMessage());
}

// POSTリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $food_name = filter_input(INPUT_POST, 'food_name');
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $unit = filter_input(INPUT_POST, 'unit'); // ★追加：単位を受け取る
    $expiry_date = filter_input(INPUT_POST, 'expiry_date');

    if ($food_name && $quantity > 0 && $expiry_date && $unit) {
        try {
            $pdo->beginTransaction();

            // 1. 食材名から master_id を取得
            $stmt_id = $pdo->prepare("SELECT master_id FROM food_master WHERE name = :name");
            $stmt_id->execute([':name' => $food_name]);
            $result = $stmt_id->fetch();
            
            if ($result) {
                $master_id = $result['master_id'];
                // すでにマスタにある場合、必要なら単位を更新する処理も書けますが、
                // 今回はそのまま（既存の単位優先）で進めます。
            } else {
                // ★【ここを修正】入力された単位を使って新しく登録する
                $stmt_new_master = $pdo->prepare("
                    INSERT INTO food_master (name, unit, category, price_per_unit) 
                    VALUES (:name, :unit, 'その他', 0)
                ");
                $stmt_new_master->execute([
                    ':name' => $food_name,
                    ':unit' => $unit // ★入力された単位を保存
                ]);
                $master_id = $pdo->lastInsertId();
            }

            // 2. 在庫登録
            $stmt = $pdo->prepare("INSERT INTO food_items (master_id, quantity, expiry_date) VALUES (:master_id, :quantity, :expiry_date)");
            $stmt->execute([
                ':master_id' => $master_id,
                ':quantity' => $quantity,
                ':expiry_date' => $expiry_date
            ]);
            
            $pdo->commit();

            $_SESSION['message'] = "「{$food_name}」が {$quantity} {$unit} れいぞうこにはいったよ！";
            header("Location: top_refrigerator.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_message = "登録エラー: " . $e->getMessage();
        }
    } else {
        $error_message = "入力内容を確認してください。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>たべものをいれる - Food Loss Buster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 600px; margin-top: 50px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4 text-center">たべものをいれる</h1>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="food_name" class="form-label">たべものなまえ:</label>
                <input class="form-control" list="food_master_list" id="food_name" name="food_name" placeholder="例: おにく、にんじん" required autocomplete="off">
                <datalist id="food_master_list">
                    <?php foreach ($master_foods as $food): ?>
                        <option value="<?= htmlspecialchars($food['name']) ?>" data-unit="<?= htmlspecialchars($food['unit']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="mb-3">
                <label for="quantity" class="form-label">かず と たんい:</label>
                <div class="row">
                    <div class="col-8">
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" placeholder="かず" required>
                    </div>
                    <div class="col-4">
                        <input type="text" class="form-control" id="unit" name="unit" placeholder="たんい" required>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="expiry_date" class="form-label">賞味期限:</label>
                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">とうろく</button>
                <a href="top_refrigerator.php" class="btn btn-secondary">トップにもどる</a>
            </div>
        </form>
    </div>

    <script>
        // 名前を入力したときに単位を自動補完する
        document.getElementById('food_name').addEventListener('input', function() {
            const foodNameInput = this.value;
            const datalist = document.getElementById('food_master_list');
            const unitInput = document.getElementById('unit'); // 単位の入力欄

            for (let option of datalist.options) {
                if (option.value === foodNameInput) {
                    unitInput.value = option.getAttribute('data-unit'); // マスタにあれば自動入力
                    return;
                }
            }
            // 新しい名前の場合はあえて空にするか、自由に入力してもらう
        });

        document.getElementById('expiry_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>