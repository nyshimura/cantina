<?php
// api/cancel_pre_order.php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? null;
$student_id = $input['student_id'] ?? null;

if (!$order_id || !$student_id) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

// Security: If not admin/operator, verify if it's the student themselves
$is_student = isset($_SESSION['access_level']) && $_SESSION['access_level'] === 'STUDENT';
if ($is_student && $_SESSION['user_id'] != $student_id) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT po.*, c.meal_schedule_id, m.cutoff_time
        FROM pre_orders po
        JOIN students s ON po.student_id = s.id
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        LEFT JOIN meal_schedules m ON c.meal_schedule_id = m.id
        WHERE po.id = ? AND po.student_id = ?
    ");
    $stmt->execute([$order_id, $student_id]);
    $order = $stmt->fetch();

    if (!$order) throw new Exception("Pedido não encontrado.");
    if ($order['delivery_status'] !== 'PENDING') throw new Exception("O pedido não pode ser cancelado neste estágio.");

    // Check 15-minute rule and cutoff for students
    if ($is_student) {
        $orderTime = strtotime($order['created_at']);
        $timePassed = time() - $orderTime;
        
        if ($timePassed > 900) {
            throw new Exception("O prazo de 15 minutos para cancelamento já expirou.");
        }
        
        if ($order['cutoff_time']) {
            $now = date('H:i:s');
            if ($now > $order['cutoff_time']) {
                throw new Exception("O horário limite para cancelamento desta turma já encerrou.");
            }
        }
    }

    // Cancel order
    $stmtUpdate = $pdo->prepare("UPDATE pre_orders SET delivery_status = 'CANCELLED' WHERE id = ?");
    $stmtUpdate->execute([$order_id]);

    // Refund if PAID
    if ($order['payment_status'] === 'PAID') {
        $amount = $order['total_amount'];
        
        // Update balance
        $stmtBalance = $pdo->prepare("UPDATE students SET balance = balance + ? WHERE id = ?");
        $stmtBalance->execute([$amount, $student_id]);
        
        // Mark the purchase transaction as REFUNDED if it exists (for simplicity, we create a REFUND transaction)
        $stmtRefund = $pdo->prepare("INSERT INTO transactions (student_id, type, amount, status, display_desc) VALUES (?, 'REFUND', ?, 'COMPLETED', 'Estorno de Lanche')");
        $stmtRefund->execute([$student_id, $amount]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
