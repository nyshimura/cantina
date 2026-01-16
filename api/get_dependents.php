<?php
// api/get_dependents.php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

// Apenas operadores podem consultar dependentes de outros
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'OPERATOR') {
    http_response_code(403);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$parentId = $_GET['parent_id'] ?? 0;

if (!$parentId) {
    echo json_encode([]);
    exit;
}

try {
    // Busca alunos onde o pai é o principal OU onde ele é co-responsável
    $sql = "SELECT id, name, avatar_url, 'PRIMARY' as link_type 
            FROM students 
            WHERE parent_id = ? AND active = 1
            UNION
            SELECT s.id, s.name, s.avatar_url, 'CO_PARENT' as link_type 
            FROM students s 
            JOIN student_co_parents scp ON s.id = scp.student_id 
            WHERE scp.parent_id = ? AND s.active = 1
            ORDER BY name ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$parentId, $parentId]);
    $students = $stmt->fetchAll();

    echo json_encode($students);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>