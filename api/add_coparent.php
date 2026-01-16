<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$parentId = $_SESSION['user_id'];
$studentId = $_POST['student_id'] ?? null;
$name = trim($_POST['name'] ?? '');
$cpf = trim($_POST['cpf'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';

if (!$studentId || empty($name) || empty($cpf) || empty($email) || empty($phone) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
    exit;
}

try {
    // 1. Verifica se já existe um usuário com este e-mail
    // Alterado para cumprir a regra de individualidade: se o e-mail existe, bloqueamos a criação.
    $stmt = $pdo->prepare("SELECT id FROM parents WHERE email = ?");
    $stmt->execute([$email]);
    $existingParent = $stmt->fetch();

    if ($existingParent) {
        // Se o e-mail já existe, não vinculamos automaticamente para evitar misturar famílias
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado no sistema por outro usuário.']);
        exit;
    }

    // 2. Cria novo pai (Só chega aqui se o e-mail for inédito)
    $stmtInsert = $pdo->prepare("
        INSERT INTO parents (name, cpf, email, phone, password_hash, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmtInsert->execute([
        $name, 
        $cpf, 
        $email, 
        $phone, 
        password_hash($password, PASSWORD_DEFAULT)
    ]);
    $newParentId = $pdo->lastInsertId();

    // 3. Vincula na tabela student_co_parents
    // Verifica se o vínculo já existe (prevenção de duplicidade na tabela de ligação)
    $checkLink = $pdo->prepare("SELECT student_id FROM student_co_parents WHERE student_id = ? AND parent_id = ?");
    $checkLink->execute([$studentId, $newParentId]);
    
    if (!$checkLink->fetch()) {
        $stmtLink = $pdo->prepare("INSERT INTO student_co_parents (student_id, parent_id) VALUES (?, ?)");
        $stmtLink->execute([$studentId, $newParentId]);
    }

    echo json_encode(['success' => true, 'message' => 'Responsável adicionado com sucesso!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}