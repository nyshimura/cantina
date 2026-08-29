<?php
require_once __DIR__ . '/config/db.php';

function getMigrations() {
    return [
        ['type'=>'col', 't'=>'students', 'c'=>'purchase_pin', 'sql'=>"ALTER TABLE students ADD COLUMN purchase_pin VARCHAR(255) DEFAULT NULL"],
        ['type'=>'col', 't'=>'students', 'c'=>'allow_overdraft', 'sql'=>"ALTER TABLE students ADD COLUMN allow_overdraft TINYINT(1) DEFAULT 1"],
        ['type'=>'col', 't'=>'students', 'c'=>'custom_overdraft_limit', 'sql'=>"ALTER TABLE students ADD COLUMN custom_overdraft_limit DECIMAL(10,2) DEFAULT NULL"],
        ['type'=>'col', 't'=>'students', 'c'=>'pending_balance', 'sql'=>"ALTER TABLE students ADD COLUMN pending_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER daily_limit"],
        ['type'=>'col', 't'=>'system_settings', 'c'=>'security_enable_pin', 'sql'=>"INSERT INTO system_settings (setting_key, setting_value) VALUES ('security_enable_pin', '0') ON DUPLICATE KEY UPDATE setting_key=setting_key"],
        ['type'=>'col', 't'=>'system_settings', 'c'=>'security_pin_min_amount', 'sql'=>"INSERT INTO system_settings (setting_key, setting_value) VALUES ('security_pin_min_amount', '0.00') ON DUPLICATE KEY UPDATE setting_key=setting_key"],
        ['type'=>'col', 't'=>'system_settings', 'c'=>'global_overdraft_limit', 'sql'=>"INSERT INTO system_settings (setting_key, setting_value) VALUES ('global_overdraft_limit', '15.00') ON DUPLICATE KEY UPDATE setting_key=setting_key"],
        
        ['type'=>'col', 't'=>'students', 'c'=>'classroom_id', 'sql'=>"ALTER TABLE students ADD COLUMN classroom_id INT DEFAULT NULL"],
        ['type'=>'col', 't'=>'students', 'c'=>'pending_classroom_id', 'sql'=>"ALTER TABLE students ADD COLUMN pending_classroom_id INT DEFAULT NULL"],
        ['type'=>'tbl', 't'=>'meal_schedules', 'sql'=>"CREATE TABLE meal_schedules (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, cutoff_time TIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['type'=>'tbl', 't'=>'classrooms', 'sql'=>"CREATE TABLE classrooms (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, meal_schedule_id INT DEFAULT NULL, FOREIGN KEY (meal_schedule_id) REFERENCES meal_schedules(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['type'=>'tbl', 't'=>'pre_orders', 'sql'=>"CREATE TABLE pre_orders (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, operator_id INT DEFAULT NULL, order_date DATE NOT NULL, payment_method ENUM('WALLET', 'CASH') NOT NULL, payment_status ENUM('PENDING', 'PAID', 'REFUNDED') NOT NULL DEFAULT 'PENDING', delivery_status ENUM('PENDING', 'PREPARED', 'DELIVERED', 'CANCELLED') NOT NULL DEFAULT 'PENDING', total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['type'=>'tbl', 't'=>'pre_order_items', 'sql'=>"CREATE TABLE pre_order_items (id INT AUTO_INCREMENT PRIMARY KEY, pre_order_id INT NOT NULL, product_id INT NOT NULL, qty INT NOT NULL DEFAULT 1, price DECIMAL(10,2) NOT NULL, FOREIGN KEY (pre_order_id) REFERENCES pre_orders(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"]
    ];
}

function runMigrations($conn) {
    $migrations = getMigrations();
    foreach ($migrations as $mig) {
        $table = $mig['t'];
        $sql   = $mig['sql'];
        $type  = $mig['type'];

        try {
            if ($type === 'col') {
                $col = $mig['c'];
                $stmtT = $conn->query("SHOW TABLES LIKE '$table'");
                if ($stmtT->rowCount() == 0) continue; 
                $stmtC = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
                if ($stmtC->rowCount() == 0) {
                    $conn->exec($sql);
                    echo "DB: Coluna '$col' criada.\n";
                }
            } elseif ($type === 'tbl') {
                $stmtT = $conn->query("SHOW TABLES LIKE '$table'");
                if ($stmtT->rowCount() == 0) {
                    $conn->exec($sql);
                    echo "DB: Tabela '$table' criada.\n";
                }
            } else {
                $conn->exec($sql);
            }
        } catch (Exception $e) { 
            echo "Error on $sql : " . $e->getMessage() . "\n";
        }
    }
}

runMigrations($pdo);
echo "Migrations completed.\n";

