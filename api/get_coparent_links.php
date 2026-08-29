<?php
require_once '../includes/auth.php';
requireRole('PARENT');

$parentId = $_SESSION['user_id'];
$coparentId = $_GET['coparent_id'] ?? 0;

if (!$coparentId) {
    echo json_encode(['success' => false, 'message' => 'Co-responsável não informado']);
    exit;
}

// Buscar todos os filhos do pai principal
$stmt = $pdo->prepare("SELECT id, name, avatar_url FROM students WHERE parent_id = ? AND active = 1");
$stmt->execute([$parentId]);
$children = $stmt->fetchAll();

// Buscar quais filhos estão vinculados a este co-responsável
$stmt = $pdo->prepare("SELECT student_id FROM student_co_parents WHERE parent_id = ? AND active = 1");
$stmt->execute([$coparentId]);
$linkedRows = $stmt->fetchAll();
$linkedIds = array_map(function($row) { return $row['student_id']; }, $linkedRows);

foreach ($children as &$child) {
    $child['is_linked'] = in_array($child['id'], $linkedIds);
}

echo json_encode(['success' => true, 'data' => $children]);
