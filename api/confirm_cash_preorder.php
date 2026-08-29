<?php
// api/confirm_cash_preorder.php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['access_level'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pre_order_id = $input['pre_order_id'] ?? null;

if (!$pre_order_id) {
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT payment_status FROM pre_orders WHERE id = ? FOR UPDATE");
    $stmt->execute([$pre_order_id]);
    $order = $stmt->fetch();

    if (!$order) throw new Exception("Reserva não encontrada.");
    if ($order['payment_status'] === 'PAID') throw new Exception("Já está pago.");

    $pdo->prepare("UPDATE pre_orders SET payment_status = 'PAID' WHERE id = ?")
        ->execute([$pre_order_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
