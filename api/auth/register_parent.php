<?php
// api/auth/register_parent.php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $name     = trim($input['name'] ?? '');
    $email    = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $cpf      = preg_replace('/\D/', '', $input['cpf'] ?? ''); // Limpa CPF
    $phone    = trim($input['phone'] ?? '');

    // 1. Validações
    if (empty($name) || empty($email) || empty($password) || empty($cpf)) {
        throw new Exception("Preencha todos os campos.");
    }

    if (strlen($cpf) !== 11) {
        throw new Exception("CPF inválido.");
    }

    // 2. Verifica se já existe
    $stmt = $pdo->prepare("SELECT id FROM parents WHERE email = ? OR cpf = ?");
    $stmt->execute([$email, $cpf]);
    if ($stmt->fetch()) {
        throw new Exception("E-mail ou CPF já cadastrado.");
    }

    // 3. Insere no banco
    // ATENÇÃO: Coluna ajustada para 'password_hash' conforme imagem do seu banco
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO parents (name, email, password_hash, cpf, phone, active, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $email, $hash, $cpf, $phone]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}