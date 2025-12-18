<?php
// データベース接続情報 (docker-compose.ymlで設定した値)
define('DB_HOST', 'db'); // Dockerサービス名
define('DB_USER', 'user_name'); 
define('DB_PASS', 'user_secret_password');
define('DB_NAME', 'refrigerator_db');

/**
 * データベースへの接続を行う関数
 * @return PDO 接続オブジェクト
 */
function connectDB(): PDO {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // 接続エラー時はログに出力して終了
        error_log("Database connection failed: " . $e->getMessage());
        die("データベース接続エラーが発生しました。詳細はログを確認してください。");
    }
}

/**
 * 今月の食品ロス削減実績（金額）を算出する関数
 * ※ ここでは Wasted ではなく Used が減らされた量を算出する想定
 * @param PDO $pdo データベース接続オブジェクト
 * @return float 削減金額の合計
 */
function calculateMonthlyReduction(PDO $pdo): float {
    $yearMonth = date('Y-m');
    
    // SQL: 今月 Used の量を food_master の単価と掛けて合計
    $sql = "SELECT 
                SUM(wl.quantity * fm.price_per_unit) AS total_reduction
            FROM waste_log wl
            JOIN food_items fi ON wl.food_item_id = fi.id
            JOIN food_master fm ON fi.master_id = fm.master_id
            WHERE 
                wl.status = 'Used'
                AND DATE_FORMAT(wl.logged_at, '%Y-%m') = :year_month";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':year_month' => $yearMonth]);
    $result = $stmt->fetch();

    return (float)($result['total_reduction'] ?? 0);
}

// 使用例
// $pdo = connectDB();
// $reduction = calculateMonthlyReduction($pdo);
// echo "今月の削減金額: " . number_format($reduction) . "円";
