<?php
// データベース接続情報 (docker-compose.ymlで設定した値)
define('DB_HOST', 'db'); // Dockerサービス名
define('DB_USER', 'user_name'); 
define('DB_PASS', 'user_secret_password');
define('DB_NAME', 'refrigerator_db');

/**
 * データベースへの接続を行う関数
 * @return PDO 
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
 * @param PDO $pdo データベース接続オブジェクト
 * @return float 削減金額の合計
 */
function calculateMonthlyReduction(PDO $pdo): float {
    $yearMonth = date('Y-m');
    
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
