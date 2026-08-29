<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN is_special_of_day TINYINT(1) DEFAULT 0");
    echo "Alterado com sucesso";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
