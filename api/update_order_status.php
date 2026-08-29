<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManagePreOrders');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$orderId = $_POST['order_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$orderId || !in_array($status, ['PENDING', 'PREPARED', 'DELIVERED', 'CANCELLED'])) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE pre_orders SET delivery_status = :status WHERE id = :id");
    $stmt->execute(['status' => $status, 'id' => $orderId]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
