<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$parentId = $_SESSION['user_id'];
$studentId = $_POST['student_id'] ?? null;
$canSelfCharge = isset($_POST['can_self_charge']) ? 1 : 0;
$limit = $_POST['recharge_limit'] ?? 0;
$period = $_POST['recharge_period'] ?? 'Mensal';

if (!$studentId) {
    echo json_encode(['success' => false, 'message' => 'Aluno inválido.']);
    exit;
}

try {
    $check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND parent_id = ?");
    $check->execute([$studentId, $parentId]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    // JSON_UNESCAPED_UNICODE garante que salve "Diário" e não "Di\u00e1rio"
    $rechargeConfig = json_encode(
        ['limit' => (float)$limit, 'period' => $period], 
        JSON_UNESCAPED_UNICODE 
    );

    $stmt = $pdo->prepare("UPDATE students SET can_self_charge = ?, recharge_config = ? WHERE id = ?");
    $stmt->execute([$canSelfCharge, $rechargeConfig, $studentId]);

    echo json_encode(['success' => true, 'message' => 'Configuração salva!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar.']);
}