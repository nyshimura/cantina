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
// AQUI: Removemos a captura do POST e geramos automaticamente abaixo
// $avatarUrl = trim($_POST['avatar_url'] ?? ''); 
$password = $_POST['password'] ?? '';

if (!$studentId || empty($name) || empty($cpf) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Preencha os campos obrigatórios.']);
    exit;
}

try {
    $check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND parent_id = ?");
    $check->execute([$studentId, $parentId]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    // GERA O AVATAR AUTOMATICAMENTE BASEADO NO NOVO NOME
    $avatarUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed=" . urlencode($name);

    $sql = "UPDATE students SET name = ?, cpf = ?, email = ?, avatar_url = ? " . (!empty($password) ? ", password_hash = ?" : "") . " WHERE id = ?";
    $params = [$name, $cpf, $email, $avatarUrl];
    if (!empty($password)) $params[] = password_hash($password, PASSWORD_DEFAULT);
    $params[] = $studentId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Perfil salvo!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar perfil.']);
}