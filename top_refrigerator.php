<?php
// DB接続と関数定義の読み込み
session_start();
require_once 'db_config.php';
$pdo = connectDB(); 

$message = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // 一度表示したら削除
}

// 1. ロス削減実績の算出
$reduction_amount = calculateMonthlyReduction($pdo); 
$reduction_text = number_format($reduction_amount); 

// 2. 優先消費提案の食材を取得 (期限が一番近い、かつ在庫が0より大きいもの)
$closest_food_name = '冷蔵庫は平和だよ！';

try {
    $sql = "SELECT m.name 
            FROM food_items i
            JOIN food_master m ON i.master_id = m.master_id
            WHERE i.quantity > 0 
            ORDER BY i.expiry_date ASC
            LIMIT 1";

    $stmt = $pdo->query($sql);
    $closest_food = $stmt->fetch();
    
    if ($closest_food) {
        $closest_food_name = $closest_food['name'] . '、はやくたべようね！';
    } else {
        $closest_food_name = '冷蔵庫は空っぽだよ！';
    }

} catch (PDOException $e) {
    // エラー時のメッセージ
    $closest_food_name = 'エラーが発生したよ。';
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Fridge Fun! - Food Loss Buster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 600px; margin-top: 50px; }
        .btn-custom { margin-bottom: 15px; padding: 15px; font-size: 1.25rem; }
    </style>
</head>
<body>
    <?php if ($message): ?>
        <script>
            alert("<?= htmlspecialchars($message) ?>"); 
        </script>
    <?php endif; ?>
    
    <div class="container text-center">
        <h1 class="mb-4">
            <img src="https://via.placeholder.com/60" alt="Avocado Icon" class="me-3"> 
            My Fridge Fun!
        </h1>
        
        <div class="alert alert-success mt-4">
            今月、**¥<?= $reduction_text ?>円分** の食品ロスを削減できました！🥳
        </div>

        <p class="h4 text-danger mt-3">
            <?= htmlspecialchars($closest_food_name) ?>
        </p>

        <hr class="my-4">

        <a href="insert_food.php" class="btn btn-primary w-100 btn-custom">たべものをいれる</a>
        <a href="look_inside_refrigerato.php" class="btn btn-warning w-100 btn-custom">なかをみる</a>
        <a href="putout_food.php" class="btn btn-danger w-100 btn-custom">たべものをだす</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>