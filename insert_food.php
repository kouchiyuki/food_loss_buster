<?php
require_once 'db_config.php';
$pdo = connectDB();

// フォームの送信処理 (POSTリクエストの場合)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // フォームからデータを受け取る
    // ★★★ 修正箇所：食材名を受け取るように変更 ★★★
    $food_name = filter_input(INPUT_POST, 'food_name');
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $expiry_date = filter_input(INPUT_POST, 'expiry_date');

    // 必須項目のチェック
    if ($master_id && $quantity > 0 && $expiry_date) {
        try {
            // ★★★ 追加処理：食材名から master_id を取得する ★★★
            $stmt_id = $pdo->prepare("SELECT master_id FROM food_master WHERE name = :name");
            $stmt_id->bindParam(':name', $food_name);
            $stmt_id->execute();
            $result = $stmt_id->fetch();
            
            if (!$result) {
                 // ユーザーがマスターにない名前を入力した場合
                throw new Exception("入力された食材名はリストにありません。");
            }
            $master_id = $result['master_id'];
            // ★★★ ここまで追加 ★★★

            // DBに在庫として登録
            $stmt = $pdo->prepare("INSERT INTO food_items (master_id, quantity, expiry_date) VALUES (:master_id, :quantity, :expiry_date)");
            $stmt->bindParam(':master_id', $master_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':expiry_date', $expiry_date);
            $stmt->execute();

            // 登録成功メッセージをセッションに保存（ポップアップ表示用）
            session_start();
            $_SESSION['message'] = "れいぞうこにはいったよ！";
            
            // トップ画面へリダイレクト
            header('Location: top_refrigerator.php');
            exit;

        } catch (PDOException $e) {
            $error_message = "登録エラー: " . $e->getMessage();
        }
    } else {
        $error_message = "すべての項目を正しく入力してください。";
    }
}

// 食材マスターデータを取得 (プルダウンメニュー作成用)
try {
    $stmt = $pdo->query("SELECT master_id, name, unit FROM food_master ORDER BY category, name");
    $master_foods = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "食材リストの取得エラー: " . $e->getMessage();
    $master_foods = [];
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
        <p class="text-center text-muted">冷蔵庫に入れた食材を登録しよう！</p>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="food_name" class="form-label">たべものなまえ:</label>
                <input class="form-control" list="food_master_list" id="food_name" name="food_name" placeholder="例: おにく、にんじん" required>
                
                <datalist id="food_master_list">
                    <?php foreach ($master_foods as $food): ?>
                        <option value="<?= htmlspecialchars($food['name']) ?>" 
                                data-unit="<?= htmlspecialchars($food['unit']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="mb-3">
                <label for="quantity" class="form-label">かず:</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                    <span class="input-group-text" id="unit_display">（単位）</span>
                </div>
            </div>

            <div class="mb-3">
                <label for="expiry_date" class="form-label">いつまでにもぐもぐする？（賞味期限/消費期限）:</label>
                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required min="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-lg">とうろく</button>
                <a href="top_refrigerator.php" class="btn btn-secondary">もどる</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 食材名が入力または選択されたら単位を表示するJavaScript
        document.getElementById('food_name').addEventListener('input', function() {
            const foodNameInput = this.value;
            const datalist = document.getElementById('food_master_list');
            let unit = '';

            // datalistのオプションを検索し、入力値と一致するものの data-unit を取得
            for (let option of datalist.options) {
                if (option.value === foodNameInput) {
                    unit = option.getAttribute('data-unit');
                    break;
                }
            }
            
            document.getElementById('unit_display').textContent = unit ? `（${unit}）` : '（単位）';
        });
    </script>
</body>
</html>