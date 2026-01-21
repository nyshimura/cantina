<?php
// api/search_parents.php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'OPERATOR') {
    http_response_code(403);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Busca responsáveis ativos por nome, email ou cpf
    $stmt = $pdo->prepare("SELECT id, name, email, cpf FROM parents WHERE active = 1 AND (name LIKE ? OR email LIKE ? OR cpf LIKE ?) LIMIT 10");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>