<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo->exec("ALTER TABLE classrooms ADD COLUMN academic_year YEAR DEFAULT 2026");
    echo "Alterado com sucesso";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
