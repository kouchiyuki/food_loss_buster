<?php
session_start();
require_once 'db_config.php';
$pdo = connectDB(); 

$message = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// 1. ロス削減実績の算出（db_config.phpに関数が定義されている前提です）
// もしエラーが出る場合は、一旦 0 を代入するように書き換えてください
$reduction_amount = 0; 
if (function_exists('calculateMonthlyReduction')) {
    $reduction_amount = calculateMonthlyReduction($pdo); 
}
$reduction_text = number_format($reduction_amount); 

// 2. 優先消費提案（一番期限が近いもの1つ）
$closest_food_name = '';
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
        $closest_food_name = $closest_food['name'];
    }
} catch (PDOException $e) {
    $closest_food_name = 'エラー';
}

// 3. 期限まであと3日以内の食材の数を取得
$alert_count = 0;
try {
    $sql = "SELECT COUNT(*) FROM food_items WHERE quantity > 0 AND expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY)";
    $stmt = $pdo->query($sql);
    $alert_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $alert_count = 0;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Loss Buster - TOP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #fff9e6; height: 100vh; margin: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; font-family: "Hiragino Sans", "Meiryo", sans-serif; position: relative; }
        
        /* 背景装飾 */
        .decorations { position: absolute; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
        .dot { position: absolute; border-radius: 50%; opacity: 0.5; }
        .floor { position: absolute; bottom: 0; width: 100%; height: 20vh; background: repeating-linear-gradient(to bottom, #d2b48c 0px, #d2b48c 2px, #e3c9a1 2px, #e3c9a1 40px); border-top: 2px solid #c9a67a; z-index: 0; }

        .main-scene { position: relative; z-index: 10; display: flex; align-items: center; gap: 30px; }

        /* 冷蔵庫 */
        .fridge { width: 280px; height: 480px; background-color: #d1e3ff; border: 4px solid #333; border-radius: 50px 50px 30px 30px; position: relative; display: flex; flex-direction: column; box-shadow: 10px 10px 0px rgba(0,0,0,0.05); }
        .fridge::after { content: ""; position: absolute; top: 40%; left: 0; width: 100%; height: 4px; background-color: #333; }
        .handle { position: absolute; left: 15px; width: 50px; height: 12px; background-color: #a0c4ff; border: 3px solid #333; border-radius: 10px; }
        .handle-top { top: 30%; }
        .handle-bottom { top: 45%; }

        /* ボタン */
        .btn-custom { background-color: #ffc1c1; border: 3px solid #333; border-radius: 15px; padding: 15px 25px; font-weight: bold; color: #333; text-decoration: none; display: inline-block; transition: all 0.2s; box-shadow: 0 4px 0 #333; text-align: center; min-width: 180px; }
        .btn-custom:hover { transform: translateY(-2px); box-shadow: 0 6px 0 #333; background-color: #ffadad; color: #333; }
        .btn-custom:active { transform: translateY(2px); box-shadow: 0 0px 0 #333; }
        .btn-inside { position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%); width: 80%; }

        /* 吹き出しのアニメーション */
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .bubble {
            position: absolute;
            top: 60px;
            right: -60px;
            background: white;
            padding: 12px;
            border-radius: 20px;
            border: 3px solid #333;
            font-size: 14px;
            box-shadow: 5px 5px 0 rgba(0,0,0,0.1);
            text-align: center;
            min-width: 130px;
            z-index: 20;
            animation: bounce 2s infinite;
        }
        /* 吹き出しのしっぽ */
        .bubble::after {
            content: "";
            position: absolute;
            left: -15px;
            top: 20px;
            border-width: 8px 15px 8px 0;
            border-style: solid;
            border-color: transparent white transparent transparent;
        }
    </style>
</head>
<body>

    <div class="decorations">
        <div class="dot" style="width:20px; height:20px; background:#ffcfcf; left:10%; top:20%;"></div>
        <div class="dot" style="width:15px; height:15px; background:#d4f1f9; left:80%; top:15%;"></div>
        <div class="dot" style="width:25px; height:25px; background:#fdf9c4; left:20%; top:70%;"></div>
        <div class="dot" style="width:18px; height:18px; background:#e0f9d4; left:85%; top:60%;"></div>
    </div>

    <div class="floor"></div>

    <div class="main-scene">
        <div><a href="insert_food.php" class="btn-custom">たべものをいれる</a></div>

        <div class="fridge">
            <div class="handle handle-top"></div>
            <div class="handle handle-bottom"></div>
            
            <?php if ($alert_count > 0): ?>
                <div class="bubble">
                    ⏰ <strong>あと<?= $alert_count ?>こ！</strong><br>
                    <small><?= htmlspecialchars($closest_food_name) ?> を<br>はやくたべよう！</small>
                </div>
            <?php elseif ($closest_food_name): ?>
                <div class="bubble" style="animation: none;">
                    😊 <strong>いまのところOK</strong><br>
                    <small>次は <?= htmlspecialchars($closest_food_name) ?><br>だね！</small>
                </div>
            <?php else: ?>
                <div class="bubble" style="animation: none;">
                    ✨ <strong>からっぽだよ</strong><br>
                    <small>なにかいれる？</small>
                </div>
            <?php endif; ?>

            <a href="look_inside_refrigerato.php" class="btn-custom btn-inside">なかをみる</a>
        </div>

        <div><a href="putout_food.php" class="btn-custom">たべものをだす</a></div>
    </div>

</body>
</html>