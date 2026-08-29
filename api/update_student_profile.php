<?php
// api/update_student_profile.php
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
$pin = $_POST['pin'] ?? ''; // <--- NOVO: Captura o PIN
$avatarSeed = $_POST['avatar_seed'] ?? '';
$classroomId = $_POST['classroom_id'] ?? '';

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

    // 3. Monta a query de atualização
    $sql = "UPDATE students SET email = ?";
    $params = [$email];

    if (!empty($avatarSeed)) {
        $sql .= ", avatar_url = ?";
        $params[] = "https://api.dicebear.com/9.x/adventurer/svg?seed=" . urlencode($avatarSeed);
    }

    // --- LÓGICA DE SENHA (LOGIN) ---
    if (!empty($password)) {
        $sql .= ", password_hash = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    // --- LÓGICA DE PIN (COMPRA) - NOVO ---
    if (!empty($pin)) {
        // Validação: Deve conter apenas números
        if (!ctype_digit($pin)) {
            echo json_encode(['success' => false, 'message' => 'O PIN deve conter apenas números.']);
            exit;
        }
        // Validação: Tamanho (4 a 6 dígitos)
        if (strlen($pin) < 4 || strlen($pin) > 6) {
            echo json_encode(['success' => false, 'message' => 'O PIN deve ter entre 4 e 6 dígitos.']);
            exit;
        }
        
        $sql .= ", purchase_pin = ?";
        $params[] = password_hash($pin, PASSWORD_DEFAULT); // Salva como Hash seguro
    }

    if (!empty($classroomId)) {
        $stmtC = $pdo->prepare("SELECT classroom_id, pending_classroom_id FROM students WHERE id = ?");
        $stmtC->execute([$studentId]);
        $currentC = $stmtC->fetch();
        
        if ($currentC && $currentC['classroom_id'] != $classroomId) {
            $sql .= ", pending_classroom_id = ?";
            $params[] = $classroomId;
        }
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
?>