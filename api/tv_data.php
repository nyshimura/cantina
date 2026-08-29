<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

try {
    $today = date('Y-m-d');
    // We don't have updated_at in pre_orders yet maybe, so we sort by ID
    $query = "
        SELECT 
            po.id as order_id,
            po.delivery_status,
            s.name as student_name,
            s.avatar_url,
            c.name as class_name
        FROM pre_orders po
        JOIN students s ON po.student_id = s.id
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        WHERE po.order_date = :today AND po.delivery_status IN ('PENDING', 'PREPARED')
        ORDER BY po.id ASC
        LIMIT 40
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['today' => $today]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format names
    foreach($orders as &$order) {
        $parts = explode(' ', trim($order['student_name']));
        if(count($parts) > 1) {
            $order['display_name'] = $parts[0] . ' ' . mb_substr(end($parts), 0, 1) . '.';
        } else {
            $order['display_name'] = $parts[0];
        }
    }

    echo json_encode(['success' => true, 'data' => $orders]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
