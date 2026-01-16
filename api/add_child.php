<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$parentId = $_SESSION['user_id'];
$fullName = trim($_POST['name'] ?? '');
$cpf = trim($_POST['cpf'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($fullName) || empty($cpf) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
    exit;
}

try {
    // 1. Gera URL do Avatar AUTOMATICAMENTE (DiceBear Adventurer v9.x)
    // Usa o nome completo para gerar o seed
    $avatarUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed=" . urlencode($fullName);

    // 2. Verifica se o e-mail já existe
    $check = $pdo->prepare("SELECT id FROM students WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'E-mail já cadastrado.']);
        exit;
    }

    // 3. Insere o aluno com o avatar automático
    $stmt = $pdo->prepare("
        INSERT INTO students (parent_id, name, cpf, email, password_hash, avatar_url, daily_limit, active) 
        VALUES (?, ?, ?, ?, ?, ?, 0.00, 1)
    ");
    
    $stmt->execute([
        $parentId,
        $fullName,
        $cpf,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $avatarUrl
    ]);

    logAction('ADD_STUDENT', "Dependente cadastrado: $fullName");

    echo json_encode(['success' => true, 'message' => 'Dependente cadastrado!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}