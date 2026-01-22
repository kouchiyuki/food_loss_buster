<?php
session_start();
require_once 'db_config.php';
$pdo = connectDB(); 

// --- ロス削減実績の算出---
$total_saved = 0;
try {
    // 'Used'のものの数量を今月分だけ合計
    $sql_saved = "SELECT SUM(quantity) as total 
                  FROM waste_log 
                  WHERE status = 'Used' 
                  AND logged_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    $stmt_saved = $pdo->query($sql_saved);
    $saved_data = $stmt_saved->fetch();
    $total_saved = (int)($saved_data['total'] ?? 0);
} catch (PDOException $e) {
    $total_saved = 0;
}

// --- 優先消費提案 ---
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

// --- 期限まであと3日以内の食材の数を取得 ---
$alert_count = 0;
try {
    $sql = "SELECT COUNT(*) FROM food_items WHERE quantity > 0 AND expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY)";
    $stmt = $pdo->query($sql);
    $alert_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $alert_count = 0;
}

// --- 冷蔵庫のセリフを生成 ---
if ($alert_count > 0) {
    $fridge_talk = "⏰ <strong>あと{$alert_count}こ！</strong><br><small>" . htmlspecialchars($closest_food_name) . " を<br>はやくたべよう！</small>";
} elseif ($total_saved > 0) {
    $fridge_talk = "✨ <strong>すごーい！</strong><br><small>今月は {$total_saved}こも<br>たすけてくれたよ！</small>";
} elseif ($closest_food_name) {
    $fridge_talk = "😊 <strong>じゅんびOK</strong><br><small>次は " . htmlspecialchars($closest_food_name) . "<br>をたべようね！</small>";
} else {
    $fridge_talk = "✨ <strong>からっぽだよ</strong><br><small>なにかいれる？</small>";
}

if (empty($closest_food_name)) {
    $fridge_talk = "✨ <strong>ぴっかぴか！</strong><br><small>ぜんぶ たべたんだね！<br>はなまるだよ💮</small>";
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Loss Buster - TOP</title>
    <link href="https://fonts.googleapis.com/css2?family=Kiwi+Maru:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            background-color: #fff9e6;
            background-image: radial-gradient(#d1e3ff 15%, transparent 15%), 
                              radial-gradient(#d1e3ff 15%, transparent 15%);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
            font-family: 'Kiwi Maru', serif;
            margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; overflow: hidden;
        }
        
        .floor { position: absolute; bottom: 0; width: 100%; height: 20vh; background: repeating-linear-gradient(to bottom, #d2b48c 0px, #d2b48c 2px, #e3c9a1 2px, #e3c9a1 40px); border-top: 2px solid #c9a67a; z-index: 0; }

        .main-scene { position: relative; z-index: 10; display: flex; align-items: center; gap: 30px; }

        /* 冷蔵庫のデザイン */
        .fridge { 
            width: 280px; height: 480px; background-color: #d1e3ff; border: 4px solid #333; 
            border-radius: 50px 50px 30px 30px; position: relative; display: flex; flex-direction: column; 
            box-shadow: 10px 10px 0px rgba(0,0,0,0.05); 
        }
        .fridge::after { content: ""; position: absolute; top: 40%; left: 0; width: 100%; height: 4px; background-color: #333; }
        .handle { position: absolute; left: 15px; width: 50px; height: 12px; background-color: #a0c4ff; border: 3px solid #333; border-radius: 10px; }
        .handle-top { top: 30%; }
        .handle-bottom { top: 45%; }

        /* 実績表示パネル*/
        .stats-panel {
            position: absolute; top: -70px; left: 50%; transform: translateX(-50%);
            background: #ffca28; border: 3px solid #333; border-radius: 15px;
            padding: 5px 15px; white-space: nowrap; font-weight: bold; font-size: 0.9rem;
            box-shadow: 0 4px 0 #333;
        }

        .btn-custom { 
            background-color: #ffc1c1; border: 3px solid #333; border-radius: 15px; padding: 15px 25px; 
            font-weight: bold; color: #333; text-decoration: none; display: inline-block; 
            transition: all 0.2s; box-shadow: 0 4px 0 #333; text-align: center; min-width: 180px; 
        }
        .btn-custom:hover { transform: translateY(-2px); box-shadow: 0 6px 0 #333; background-color: #ffadad; }
        .btn-inside { position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%); width: 80%; }

        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

        .bubble {
            position: absolute; top: 60px; right: -80px; background: white; padding: 12px; 
            border-radius: 20px; border: 3px solid #333; font-size: 14px; box-shadow: 5px 5px 0 rgba(0,0,0,0.1);
            text-align: center; min-width: 150px; z-index: 20; animation: bounce 2s infinite;
        }
        .bubble::after {
            content: ""; position: absolute; left: -15px; top: 20px;
            border-width: 8px 15px 8px 0; border-style: solid; border-color: transparent white transparent transparent;
        }
    </style>
</head>
<body>

    <div class="floor"></div>

    <div class="main-scene">
        <div><a href="insert_food.php" class="btn-custom">たべものをいれる</a></div>

        <div class="fridge">
            <div class="stats-panel">🏆 今月救った数: <?= $total_saved ?>こ</div>

            <div class="handle handle-top"></div>
            <div class="handle handle-bottom"></div>
            
            <div class="bubble">
                <?= $fridge_talk ?>
            </div>

            <a href="look_inside_refrigerato.php" class="btn-custom btn-inside">なかをみる</a>
        </div>

        <div><a href="putout_food.php" class="btn-custom">たべものをだす</a></div>
    </div>

    <script>
        // 登録・削除完了時のメッセージ
        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                title: 'やったね！',
                text: '<?= htmlspecialchars($_SESSION['message']) ?>',
                icon: 'success',
                confirmButtonText: 'おっけー！',
                confirmButtonColor: '#ffcc80',
                background: '#fffdf0',
                borderRadius: '30px'
            });
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
    </script>
</body>
</html>