<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('PARENT');

// Garante que o retorno seja sempre JSON
header('Content-Type: application/json');

$parentId = $_SESSION['user_id'];

try {
    // A query faz o seguinte caminho:
    // 1. Pega os alunos (s) onde VOCÊ é o pai principal (s.parent_id = ?)
    // 2. Olha na tabela de ligação (scp) quem está vinculado a esses alunos
    // 3. Pega os dados dessas pessoas na tabela parents (p)
    // 4. DISTINCT evita repetir o nome se a pessoa for responsável por 2 filhos seus
    
    $sql = "
        SELECT DISTINCT p.id, p.name, p.email, p.cpf
        FROM parents p
        INNER JOIN student_co_parents scp ON p.id = scp.parent_id
        INNER JOIN students s ON scp.student_id = s.id
        WHERE s.parent_id = ? 
        AND p.id != ? 
        AND scp.active = 1
        ORDER BY p.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$parentId, $parentId]);
    
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $list]);

} catch (Exception $e) {
    // Retorna erro 500 para o JS identificar que falhou, mas com mensagem JSON
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro SQL: ' . $e->getMessage()]);
}