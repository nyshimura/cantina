<?php
require_once __DIR__ . '/config/db.php';

try {
    $classes = [
        // Fundamental 1
        '1º Ano A', '1º Ano B',
        '2º Ano A', '2º Ano B',
        '3º Ano A', '3º Ano B',
        '4º Ano A', '4º Ano B',
        '5º Ano A', '5º Ano B',
        // Fundamental 2
        '6º Ano A', '6º Ano B',
        '7º Ano A', '7º Ano B',
        '8º Ano A', '8º Ano B',
        '9º Ano A', '9º Ano B',
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO classrooms (name) VALUES (?)");
    foreach ($classes as $c) {
        $stmt->execute([$c]);
    }
    echo "Salas criadas com sucesso!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
