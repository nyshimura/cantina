<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$parentId = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$cpf = trim($_POST['cpf'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';

// Validação básica
if (empty($name) || empty($cpf) || empty($email) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Preencha os campos obrigatórios.']);
    exit;
}

try {
    // Monta a query dinamicamente baseada na presença da senha
    $sql = "UPDATE parents SET name = ?, cpf = ?, email = ?, phone = ?";
    $params = [$name, $cpf, $email, $phone];

    if (!empty($password)) {
        $sql .= ", password_hash = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE id = ?";
    $params[] = $parentId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Atualiza dados na sessão para refletir imediatamente
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;

    echo json_encode(['success' => true, 'message' => 'Perfil atualizado!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar.']);
}