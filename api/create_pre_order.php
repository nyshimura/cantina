<?php
// api/create_pre_order.php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$student_id = $input['student_id'] ?? null;
$product_id = $input['product_id'] ?? null;
$payment_method = $input['payment_method'] ?? 'WALLET'; // WALLET ou CASH
$is_operator = isset($_SESSION['access_level']) && $_SESSION['access_level'] === 'OPERATOR';

if (!$student_id || !$product_id) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Validar Produto
    $stmtProd = $pdo->prepare("SELECT price, name FROM products WHERE id = ? AND active = 1");
    $stmtProd->execute([$product_id]);
    $product = $stmtProd->fetch();
    if (!$product) throw new Exception("Produto inválido ou inativo.");
    
    $price = $product['price'];

    // Validar Estudante e Horário
    $stmtStu = $pdo->prepare("
        SELECT s.id, COALESCE(n.balance, 0) as balance, s.allow_overdraft, s.custom_overdraft_limit, c.meal_schedule_id, m.cutoff_time
        FROM students s
        LEFT JOIN nfc_tags n ON s.id = n.current_student_id AND n.status = 'ACTIVE'
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        LEFT JOIN meal_schedules m ON c.meal_schedule_id = m.id
        WHERE s.id = ?
    ");
    $stmtStu->execute([$student_id]);
    $student = $stmtStu->fetch();

    if (!$student) throw new Exception("Estudante não encontrado.");

    // Se o estudante não tiver turma com horário, usamos um cutoff genérico (ou ignoramos para operadores)
    if ($student['cutoff_time'] && !$is_operator) {
        $now = date('H:i:s');
        if ($now > $student['cutoff_time']) {
            throw new Exception("O horário limite para pedidos desta turma já encerrou (".$student['cutoff_time'].").");
        }
    }

    // Verificar se já tem pedido hoje (Regra de Negócio: pode ter mais de um? Geralmente 1 lanche por dia, mas e se quiser 2? Vamos permitir, mas avisar no front).

    // Lógica de Pagamento
    $payment_status = 'PENDING';
    if ($payment_method === 'WALLET') {
        // Verificar saldo
        $globalLimit = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'global_overdraft_limit'")->fetchColumn() ?: 15.00;
        $overdraftLimit = ($student['allow_overdraft'] == 1) 
            ? ($student['custom_overdraft_limit'] !== null ? $student['custom_overdraft_limit'] : $globalLimit) 
            : 0;
            
        $available = $student['balance'] + $overdraftLimit;
        if ($price > $available) {
            throw new Exception("Saldo insuficiente. Saldo atual: R$ " . number_format($student['balance'], 2, ',', '.'));
        }
        
        // Descontar do saldo imediatamente e gerar transação de PRE_ORDER
        $stmtUpdate = $pdo->prepare("UPDATE nfc_tags SET balance = balance - ? WHERE current_student_id = ? AND status = 'ACTIVE'");
        $stmtUpdate->execute([$price, $student_id]);
        
        $stmtTx = $pdo->prepare("INSERT INTO transactions (student_id, operator_id, type, amount, status) VALUES (?, ?, 'PURCHASE', ?, 'COMPLETED')");
        $operator_id = $is_operator ? $_SESSION['user_id'] : null;
        $stmtTx->execute([$student_id, $operator_id, $price]);
        $txId = $pdo->lastInsertId();
        
        $stmtItem = $pdo->prepare("INSERT INTO transaction_items (transaction_id, product_id, product_name, qty, price) VALUES (?, ?, ?, 1, ?)");
        $stmtItem->execute([$txId, $product_id, $product['name'], $price]);
        
        $payment_status = 'PAID';
    }

    // Criar o Pre-Order
    $stmtOrder = $pdo->prepare("
        INSERT INTO pre_orders (student_id, operator_id, order_date, payment_method, payment_status, total_amount) 
        VALUES (?, ?, CURDATE(), ?, ?, ?)
    ");
    $operator_id_order = $is_operator ? $_SESSION['user_id'] : null;
    $stmtOrder->execute([$student_id, $operator_id_order, $payment_method, $payment_status, $price]);
    $orderId = $pdo->lastInsertId();

    $stmtOrderItem = $pdo->prepare("
        INSERT INTO pre_order_items (pre_order_id, product_id, qty, price) 
        VALUES (?, ?, 1, ?)
    ");
    $stmtOrderItem->execute([$orderId, $product_id, $price]);

    $pdo->commit();
    echo json_encode(['success' => true, 'order_id' => $orderId, 'payment_status' => $payment_status]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
