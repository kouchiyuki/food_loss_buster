-- =========================
-- Food Loss Buster 初期化SQL
-- =========================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================
-- 1. 食材マスタ
-- =========================
CREATE TABLE IF NOT EXISTS food_master (
  master_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  unit VARCHAR(50) NOT NULL,
  category VARCHAR(100) NOT NULL,
  price_per_unit INT NOT NULL COMMENT '1単位あたりの概算価格'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- 2. 冷蔵庫の在庫
-- =========================
CREATE TABLE IF NOT EXISTS food_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  master_id INT NOT NULL,
  quantity INT NOT NULL,
  expiry_date DATE NOT NULL,
  registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_food_items_master
    FOREIGN KEY (master_id)
    REFERENCES food_master(master_id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- 3. 使用・廃棄ログ
-- =========================
CREATE TABLE IF NOT EXISTS waste_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  food_item_id INT NOT NULL,
  quantity INT NOT NULL,
  status ENUM('Used', 'Wasted') NOT NULL,
  logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_waste_log_item
    FOREIGN KEY (food_item_id)
    REFERENCES food_items(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================
-- 初期マスタデータ（最低限）
-- =========================
INSERT INTO food_master (name, unit, category, price_per_unit) VALUES
('にんじん', '本', '野菜', 50),
('たまねぎ', '個', '野菜', 40),
('じゃがいも', '個', '野菜', 60),
('鶏むね肉', 'g', '肉', 1),
('豚こま肉', 'g', '肉', 1),
('卵', '個', '卵', 30),
('牛乳', 'ml', '乳製品', 0.2)
('キャベツ', '玉', '野菜', 200),
('白菜', '玉', '野菜', 300),
('ブロッコリー', 'こ', '野菜', 150),
('豚バラ肉', 'g', '肉', 2),
('牛肉こま切れ', 'g', '肉', 3),
('鮭の切り身', '切れ', '魚', 150),
('納豆', 'パック', '大豆製品', 40),
('豆腐', '丁', '大豆製品', 80),
('マヨネーズ', '本', '調味料', 300),
('ケチャップ', '本', '調味料', 300),
('食パン', '枚', 'パン', 30);
ON DUPLICATE KEY UPDATE name = name;
