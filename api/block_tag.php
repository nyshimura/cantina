<?php
// api/block_tag.php
require_once __DIR__ . '/../includes/auth.php';
requireRole('STUDENT');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$studentId = $_SESSION['user_id'];

try {
    // Pegar a tag ativa vinculada a este aluno
    $stmt = $pdo->prepare("SELECT tag_id FROM nfc_tags WHERE current_student_id = ? AND status = 'ACTIVE'");
    $stmt->execute([$studentId]);
    $tag = $stmt->fetch();

    if (!$tag) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma tag ativa encontrada para você neste momento.']);
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
