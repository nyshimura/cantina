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
$avatarSeed = $_POST['avatar_seed'] ?? ''; 
$classroomId = $_POST['classroom_id'] ?? '';

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

    // Inicia a construção da query
    $sql = "UPDATE students SET name = ?, cpf = ?, email = ?";
    $params = [$name, $cpf, $email];

    // Atualiza Avatar APENAS se o pai tiver interagido com o mapa
    if (!empty($avatarSeed)) {
        $sql .= ", avatar_url = ?";
        $params[] = "https://api.dicebear.com/9.x/adventurer/svg?seed=" . urlencode($avatarSeed);
    }

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

    if (!empty($classroomId)) {
        // Se mudou a turma, coloca em pendente
        // Vamos verificar a turma atual
        $stmtC = $pdo->prepare("SELECT classroom_id, pending_classroom_id FROM students WHERE id = ?");
        $stmtC->execute([$studentId]);
        $currentC = $stmtC->fetch();
        
        if ($currentC && $currentC['classroom_id'] != $classroomId) {
            $sql .= ", pending_classroom_id = ?";
            $params[] = $classroomId;
        }
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
