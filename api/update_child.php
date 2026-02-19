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
$password = $_POST['password'] ?? '';
$pin = $_POST['pin'] ?? ''; 

if (!$studentId || empty($name) || empty($cpf) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Preencha os campos obrigatórios.']);
    exit;
}

try {
    // --- NOVA VALIDAÇÃO: Verifica se o Pai Logado tem permissão (Principal ou Co-Responsável) ---
    if (!isParentAuthorizedForStudent($pdo, $parentId, $studentId)) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Você não tem permissão para editar os dados deste aluno.']);
        exit;
    }
    // -------------------------------------------------------------------------------------------

    // GERA O AVATAR AUTOMATICAMENTE BASEADO NO NOVO NOME
    $avatarUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed=" . urlencode($name);

    // Inicia a construção da query
    $sql = "UPDATE students SET name = ?, cpf = ?, email = ?, avatar_url = ?";
    $params = [$name, $cpf, $email, $avatarUrl];

    // Lógica da Senha de Login (Site)
    if (!empty($password)) {
        $sql .= ", password_hash = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    // Lógica da Senha de Compra (PIN)
    if (!empty($pin)) {
        // Validação básica de segurança
        if (!ctype_digit($pin)) {
            echo json_encode(['success' => false, 'message' => 'A Senha de Compra deve conter apenas números.']);
            exit;
        }
        if (strlen($pin) < 4 || strlen($pin) > 6) {
            echo json_encode(['success' => false, 'message' => 'A Senha de Compra deve ter entre 4 e 6 dígitos.']);
            exit;
        }

        $sql .= ", purchase_pin = ?";
        $params[] = password_hash($pin, PASSWORD_DEFAULT);
    }

    // Finaliza a query
    $sql .= " WHERE id = ?";
    $params[] = $studentId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Perfil salvo com sucesso!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar perfil: ' . $e->getMessage()]);
}
?>
