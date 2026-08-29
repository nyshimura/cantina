<?php
// api/pickup_pre_order.php
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
$tagId = $input['tagId'] ?? null;

if (!$tagId) {
    echo json_encode(['success' => false, 'message' => 'Tag não informada']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Encontra o estudante da Tag
    $stmt = $pdo->prepare("SELECT current_student_id, status FROM nfc_tags WHERE tag_id = ? FOR UPDATE");
    $stmt->execute([$tagId]);
    $tag = $stmt->fetch();

    if (!$tag) throw new Exception("Cartão não reconhecido.");
    if ($tag['status'] !== 'ACTIVE') throw new Exception("Esta tag não está ativa.");
    
    $studentId = $tag['current_student_id'];

    // Encontra dados do estudante
    $stmtStu = $pdo->prepare("SELECT name FROM students WHERE id = ?");
    $stmtStu->execute([$studentId]);
    $student = $stmtStu->fetch();

    if (!$student) throw new Exception("Estudante inativo ou não encontrado.");

    // Busca pedido PAGO, mas ainda não entregue de HOJE
    $stmtOrder = $pdo->prepare("
        SELECT p.id,
               GROUP_CONCAT(CONCAT(pi.qty, 'x ', pi.product_name) SEPARATOR ', ') as items
        FROM pre_orders p
        JOIN pre_order_items pi ON p.id = pi.pre_order_id
        WHERE p.student_id = ? 
        AND p.order_date = CURRENT_DATE() 
        AND p.payment_status = 'PAID'
        AND p.delivery_status IN ('PENDING', 'PREPARED')
        GROUP BY p.id
    ");
    $stmtOrder->execute([$studentId]);
    $pendingOrders = $stmtOrder->fetchAll();

    if (empty($pendingOrders)) {
        // Retorna falso sem erro, apenas indicando que não há reservas para retirar
        $pdo->commit();
        echo json_encode(['success' => false, 'has_pre_order' => false]);
        exit;
    }

    $deliveredItems = [];
    foreach ($pendingOrders as $order) {
        // Marca como entregue
        $pdo->prepare("UPDATE pre_orders SET delivery_status = 'DELIVERED', delivered_at = NOW() WHERE id = ?")
            ->execute([$order['id']]);
        
        $deliveredItems[] = $order['items'];
    }

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'has_pre_order' => true,
        'student_name' => $student['name'],
        'items' => implode(' | ', $deliveredItems)
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
