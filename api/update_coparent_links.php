<?php
require_once '../includes/auth.php';
requireRole('PARENT');

$parentId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$coparentId = $data['coparent_id'] ?? 0;
$linkedStudentIds = $data['student_ids'] ?? [];

if (!$coparentId) {
    echo json_encode(['success' => false, 'message' => 'Co-responsável não informado']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Validar se os alunos selecionados pertencem ao pai principal logado
    $validStudents = [];
    $stmt = $pdo->prepare("SELECT id FROM students WHERE parent_id = ? AND active = 1");
    $stmt->execute([$parentId]);
    $myChildren = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($linkedStudentIds as $sId) {
        if (in_array($sId, $myChildren)) {
            $validStudents[] = $sId;
        }
    }

    // Remover todos os vínculos existentes PARA OS FILHOS DESTE PAI
    // Isso é importante porque o co-parent pode ser co-parent de filhos de outra pessoa, não queremos apagar tudo.
    // Apagamos apenas os vínculos com os filhos do $parentId
    if (count($myChildren) > 0) {
        $placeholders = implode(',', array_fill(0, count($myChildren), '?'));
        $params = array_merge([$coparentId], $myChildren);
        $stmtDelete = $pdo->prepare("DELETE FROM student_co_parents WHERE parent_id = ? AND student_id IN ($placeholders)");
        $stmtDelete->execute($params);
    }

    // Inserir os novos vínculos
    if (count($validStudents) > 0) {
        $stmtInsert = $pdo->prepare("INSERT INTO student_co_parents (student_id, parent_id, active) VALUES (?, ?, 1)");
        foreach ($validStudents as $sId) {
            $stmtInsert->execute([$sId, $coparentId]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
