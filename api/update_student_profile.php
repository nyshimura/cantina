<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('STUDENT');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$studentId = $_SESSION['user_id'];
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'O e-mail é obrigatório.']);
    exit;
}

try {
    // 1. Busca o nome atual do aluno para garantir o Avatar correto
    $stmtName = $pdo->prepare("SELECT name FROM students WHERE id = ?");
    $stmtName->execute([$studentId]);
    $currentName = $stmtName->fetchColumn();

    if (!$currentName) {
        echo json_encode(['success' => false, 'message' => 'Aluno não encontrado.']);
        exit;
    }

    // 2. Gera a URL do Avatar (Padrão DiceBear Adventurer)
    $avatarUrl = 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . urlencode($currentName);

    // 3. Monta a query de atualização
    $sql = "UPDATE students SET email = ?, avatar_url = ?";
    $params = [$email, $avatarUrl];

    if (!empty($password)) {
        $sql .= ", password_hash = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE id = ?";
    $params[] = $studentId;

    // 4. Executa
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Perfil atualizado com sucesso!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar: ' . $e->getMessage()]);
}