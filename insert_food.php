<?php
/**
 * Food Loss Buster - 食材登録画面 (insert_food.php)
 * * ユーザーが新しい食材を登録し、在庫に追加する。
 * * 食材名選択は予測変換（オートコンプリート）に対応。
 */
session_start();
require_once 'db_config.php';
$pdo = connectDB();

// food_masterから全食材を取得（予測変換リストの生成に使用）
try {
    $stmt = $pdo->query("SELECT master_id, name, unit FROM food_master ORDER BY name ASC");
    $master_foods = $stmt->fetchAll();
} catch (PDOException $e) {
    // マスターデータの取得に失敗した場合
    exit("マスターデータ取得エラー: " . $e->getMessage());
}

// POSTリクエストの処理（食材登録）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // フォームデータの受け取り
    $food_name = filter_input(INPUT_POST, 'food_name');
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $expiry_date = filter_input(INPUT_POST, 'expiry_date');

    // バリデーションチェック
    if ($food_name && $quantity > 0 && $expiry_date) {
        try {
            // トランザクション開始
            $pdo->beginTransaction();

            // 1. 食材名から master_id を取得する (オートコンプリート対応ロジック)
            $stmt_id = $pdo->prepare("SELECT master_id FROM food_master WHERE name = :name");
            $stmt_id->bindParam(':name', $food_name);
            $stmt_id->execute();
            $result = $stmt_id->fetch();
            
            if (!$result) {
                 // ユーザーがマスターにない名前を入力した場合
                throw new Exception("入力された食材名はリストにありません。正しい食材名を選択・入力してください。");
            }
            $master_id = $result['master_id'];

            // 2. food_itemsテーブルに在庫として登録
            $stmt = $pdo->prepare("INSERT INTO food_items (master_id, quantity, expiry_date) VALUES (:master_id, :quantity, :expiry_date)");
            $stmt->bindParam(':master_id', $master_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':expiry_date', $expiry_date);
            $stmt->execute();
            
            // コミット
            $pdo->commit();

            // 成功メッセージをセッションに格納し、トップページへリダイレクト
            $_SESSION['message'] = "「{$food_name}」がれいぞうこにはいったよ！";
            header("Location: top_refrigerator.php");
            exit;

        } catch (Exception $e) {
            // エラーが発生した場合、ロールバック
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "登録エラー: " . $e->getMessage();
        }
    } else {
        $error_message = "入力内容を確認してください。数量は1以上である必要があります。";
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
        <p class="text-center text-muted">冷蔵庫に新しい食材を登録しよう！</p>
        
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
                <label for="expiry_date" class="form-label">賞味期限:</label>
                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">とうろく</button>
                <a href="top_refrigerator.php" class="btn btn-secondary">トップにもどる</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /**
         * 食材名入力に応じて単位表示を自動で切り替えるロジック
         * datalistのdata-unit属性を使用して単位を取得する。
         */
        document.getElementById('food_name').addEventListener('input', function() {
            const foodNameInput = this.value;
            const datalist = document.getElementById('food_master_list');
            let unit = '';

            // datalistのオプションを検索し、入力値と完全に一致するものの data-unit を取得
            for (let option of datalist.options) {
                if (option.value === foodNameInput) {
                    unit = option.getAttribute('data-unit');
                    break;
                }
            }
            
            // 単位表示エリアを更新
            document.getElementById('unit_display').textContent = unit ? `（${unit}）` : '（単位）';
        });

        // 登録日の初期値を今日以降に設定（UX改善）
        document.getElementById('expiry_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>