<?php
// config/db.php

// DETECTAR BASE URL AUTOMATICAMENTE
$scriptName = $_SERVER['SCRIPT_NAME'];
$dirName = dirname($scriptName);
$baseUrl = str_replace('\\', '/', $dirName);
$baseUrl = preg_replace('#/(views|api|includes|config)(/.*)?$#', '', $baseUrl);
$baseUrl = rtrim($baseUrl, '/'); 
define('BASE_URL', $baseUrl);

// --- CRIPTOGRAFIA REMOVIDA DAQUI ---
// As funções encryptData e decryptData foram movidas para includes/auth.php
// para evitar o erro "Cannot redeclare function".

// CONEXÃO COM O BANCO
$host = 'localhost';
$db   = 'nomedb';
$user = 'userdb';
$pass = 'senhadb';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Em produção, evite mostrar detalhes da conexão para o usuário final
    // die("Erro de conexão com o banco de dados."); 
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Função auxiliar de verificação de parentesco
// Mantida aqui pois não conflitua com auth.php
function isParentOf($studentId, $parentId) {
    global $pdo;
    $sql = "SELECT 1 FROM students WHERE id = ? AND parent_id = ?
            UNION
            SELECT 1 FROM student_co_parents WHERE student_id = ? AND parent_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId, $parentId, $studentId, $parentId]);
    return $stmt->fetchColumn();
}
?>