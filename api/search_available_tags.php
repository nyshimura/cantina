<?php
// api/search_available_tags.php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'OPERATOR') {
    http_response_code(403);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$q = $_GET['q'] ?? '';

try {
    // Busca apenas tags LIVRES que batem com o UID ou Apelido
    $sql = "SELECT tag_id, tag_alias FROM nfc_tags WHERE status = 'SPARE'";
    $params = [];
    
    if (!empty($q)) {
        $sql .= " AND (tag_id LIKE ? OR tag_alias LIKE ?)";
        $params = ["%$q%", "%$q%"];
    }
    
    $sql .= " ORDER BY tag_alias ASC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>