<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$parentId = $_SESSION['user_id']; // Pai principal
$studentId = $_POST['student_id'] ?? null;
$coParentId = $_POST['coparent_id'] ?? null;
$action = $_POST['action'] ?? ''; // 'deactivate' ou 'reactivate'

if (!$studentId || !$coParentId) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

try {
    // 1. Verifica se o usuário logado é realmente pai do aluno (Segurança)
    $checkMain = $pdo->prepare("SELECT id FROM students WHERE id = ? AND parent_id = ?");
    $checkMain->execute([$studentId, $parentId]);
    if (!$checkMain->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    // 2. Define o novo status
    $newStatus = ($action === 'reactivate') ? 1 : 0;

    // 3. Atualiza o status na tabela pivot
    $stmt = $pdo->prepare("
        UPDATE student_co_parents 
        SET active = ? 
        WHERE student_id = ? AND parent_id = ?
    ");
    $stmt->execute([$newStatus, $studentId, $coParentId]);

    echo json_encode(['success' => true, 'message' => $newStatus ? 'Reativado com sucesso!' : 'Desativado com sucesso!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar.']);
}