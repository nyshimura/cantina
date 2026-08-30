<?php
// api/block_tag.php
require_once __DIR__ . '/../includes/auth.php';

// Permite STUDENT ou PARENT
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['STUDENT', 'PARENT'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($_SESSION['role'] === 'PARENT') {
    if (empty($input['student_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID do aluno não fornecido.']);
        exit;
    }
    $studentId = intval($input['student_id']);
    
    // Verifica se o pai é dono do aluno
    $stmtCheck = $pdo->prepare("SELECT 1 FROM student_parents WHERE student_id = ? AND parent_id = ?");
    $stmtCheck->execute([$studentId, $_SESSION['user_id']]);
    if (!$stmtCheck->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para bloquear a tag deste aluno.']);
        exit;
    }
} else {
    // Se for estudante, ele só pode bloquear a própria tag
    $studentId = $_SESSION['user_id'];
}

try {
    // Pegar a tag ativa vinculada a este aluno
    $stmt = $pdo->prepare("SELECT tag_id FROM nfc_tags WHERE current_student_id = ? AND status = 'ACTIVE'");
    $stmt->execute([$studentId]);
    $tag = $stmt->fetch();

    if (!$tag) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma tag ativa encontrada para este aluno neste momento.']);
        exit;
    }

    $tagId = $tag['tag_id'];

    // Mudar o status para SPARE, mantendo o current_student_id para rastro até que a secretaria zere.
    $stmtUpdate = $pdo->prepare("UPDATE nfc_tags SET status = 'SPARE' WHERE tag_id = ?");
    $stmtUpdate->execute([$tagId]);

    echo json_encode(['success' => true, 'message' => 'Pulseira bloqueada com sucesso! Nenhuma compra poderá ser feita com ela até que a cantina emita uma nova.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
