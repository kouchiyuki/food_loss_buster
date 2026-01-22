<?php
/**
 * Food Loss Buster - 食材登録画面 (insert_food.php)
 * おままごとデザイン版
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $food_name = filter_input(INPUT_POST, 'food_name');
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $unit = filter_input(INPUT_POST, 'unit'); 
    $expiry_date = filter_input(INPUT_POST, 'expiry_date');

    if ($food_name && $quantity > 0 && $expiry_date && $unit) {
        try {
            $pdo->beginTransaction();

            //食材名から master_id を取得
            $stmt_id = $pdo->prepare("SELECT master_id FROM food_master WHERE name = :name");
            $stmt_id->execute([':name' => $food_name]);
            $result = $stmt_id->fetch();
            
            if ($result) {
                $master_id = $result['master_id'];
            } else {
                // 新しくマスタに登録
                $stmt_new_master = $pdo->prepare("
                    INSERT INTO food_master (name, unit, category, price_per_unit) 
                    VALUES (:name, :unit, 'その他', 0)
                ");
                $stmt_new_master->execute([
                    ':name' => $food_name,
                    ':unit' => $unit
                ]);
                $master_id = $pdo->lastInsertId();
            }

            // 在庫登録
            $stmt = $pdo->prepare("INSERT INTO food_items (master_id, quantity, expiry_date) VALUES (:master_id, :quantity, :expiry_date)");
            $stmt->execute([
                ':master_id' => $master_id,
                ':quantity' => $quantity,
                ':expiry_date' => $expiry_date
            ]);
            
            $pdo->commit();

            $_SESSION['message'] = "「{$food_name}」が れいぞうこにはいったよ！";
            header("Location: top_refrigerator.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_message = "エラーだよ: " . $e->getMessage();
        }
    } else {
        $error_message = "ぜんぶ 入力してね！";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>たべものをいれる - Food Loss Buster</title>
    <link href="https://fonts.googleapis.com/css2?family=Kiwi+Maru:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #fff9e6; 
            font-family: 'Kiwi Maru', serif;
            padding: 20px;
        }

        .main-board {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 40px;
            border: 8px solid #ffcc80;
            box-shadow: 0 10px 0px #ffb74d;
            overflow: hidden;
            padding-bottom: 20px;
        }

        .header-banner {
            background-color: #ffcc80;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            text-shadow: 2px 2px 0px rgba(0,0,0,0.1);
        }

        .form-label {
            font-weight: bold;
            color: #d87c00;
            margin-top: 10px;
        }

        .form-control {
            border: 3px solid #ffe0b2;
            border-radius: 15px;
            padding: 12px;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #ffcc80;
            box-shadow: none;
        }

        /* 登録ボタン*/
        .btn-submit {
            background-color: #ffcc80;
            border: 3px solid #333;
            border-radius: 20px;
            padding: 15px;
            font-weight: bold;
            color: #333;
            box-shadow: 0 5px 0 #333;
            font-size: 1.2rem;
            margin-top: 20px;
        }
        .btn-submit:active {
            transform: translateY(4px);
            box-shadow: 0 1px 0 #333;
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
            🥘 たべものをいれる
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger m-3"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" class="p-4">
            <div class="mb-3">
                <label for="food_name" class="form-label">🍎 たべものの なまえ</label>
                <input class="form-control" list="food_master_list" id="food_name" name="food_name" placeholder="おにく、にんじん など" required autocomplete="off">
                <datalist id="food_master_list">
                    <?php foreach ($master_foods as $food): ?>
                        <option value="<?= htmlspecialchars($food['name']) ?>" data-unit="<?= htmlspecialchars($food['unit']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="mb-3">
                <label class="form-label">🔢 どれくらい？</label>
                <div class="row g-2">
                    <div class="col-7">
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" placeholder="かず" required>
                    </div>
                    <div class="col-5">
                        <input type="text" class="form-control" id="unit" name="unit" placeholder="たんい" required>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="expiry_date" class="form-label">📅 いつまで？</label>
                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-submit">れいぞうこに いれる！</button>
            </div>

            <div class="text-center">
                <a href="top_refrigerator.php" class="btn-back">やめる</a>
            </div>
        </form>
    </div>

    <script>
        // 単位の自動補完ロジック
        document.getElementById('food_name').addEventListener('input', function() {
            const foodNameInput = this.value;
            const datalist = document.getElementById('food_master_list');
            const unitInput = document.getElementById('unit');

            for (let option of datalist.options) {
                if (option.value === foodNameInput) {
                    unitInput.value = option.getAttribute('data-unit');
                    return;
                }
            }
        });

        // 今日より前の日付を選べないようにする
        document.getElementById('expiry_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>