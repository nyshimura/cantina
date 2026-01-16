<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

// Aceita ALUNO ou PAI
if (!isset($_SESSION['user_id'])) exit;

$externalRef = $_GET['ref'] ?? '';

if (!$externalRef) {
    echo json_encode(['status' => 'ERROR']);
    exit;
}

$stmt = $pdo->prepare("SELECT status FROM transactions WHERE external_reference = ?");
$stmt->execute([$externalRef]);
$status = $stmt->fetchColumn();

echo json_encode(['status' => $status ?: 'NOT_FOUND']);