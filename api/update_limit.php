<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$parentId = $_SESSION['user_id'];
$studentId = $_POST['student_id'] ?? null;
$limit = $_POST['daily_limit'] ?? 0;

if (!$studentId) {
    echo json_encode(['success' => false, 'message' => 'Aluno não identificado.']);
    exit;
}

try {
    // Verifica se o aluno pertence ao pai
    $check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND parent_id = ?");
    $check->execute([$studentId, $parentId]);
    
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    // Atualiza o limite na tabela students
    $stmt = $pdo->prepare("UPDATE students SET daily_limit = ? WHERE id = ?");
    $stmt->execute([$limit, $studentId]);

    logAction('UPDATE_LIMIT', "Limite diário do aluno ID $studentId atualizado para R$ $limit");

    echo json_encode(['success' => true, 'message' => 'Limite salvo!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
}