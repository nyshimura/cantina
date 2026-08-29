<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');

$date = $_GET['date'] ?? null;
if (!$date) {
    die("Data não fornecida.");
}

// Fetch Totals (Summary)
$querySummary = "
    SELECT 
        p.name as product_name,
        SUM(poi.qty) as total_qty
    FROM pre_orders po
    JOIN pre_order_items poi ON po.id = poi.pre_order_id
    JOIN products p ON poi.product_id = p.id
    WHERE po.order_date = :target_date AND po.payment_status != 'REFUNDED' AND po.delivery_status != 'CANCELLED'
    GROUP BY p.id
    ORDER BY total_qty DESC
";
$stmt = $pdo->prepare($querySummary);
$stmt->execute(['target_date' => $date]);
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Details
$queryDetails = "
    SELECT 
        po.id as order_id,
        po.delivery_status,
        s.name as student_name,
        c.name as class_name,
        ms.name as schedule_name,
        poi.qty,
        p.name as product_name
    FROM pre_orders po
    JOIN pre_order_items poi ON po.id = poi.pre_order_id
    JOIN students s ON po.student_id = s.id
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN meal_schedules ms ON c.meal_schedule_id = ms.id
    JOIN products p ON poi.product_id = p.id
    WHERE po.order_date = :target_date AND po.payment_status != 'REFUNDED' AND po.delivery_status != 'CANCELLED'
    ORDER BY ms.cutoff_time ASC, c.name ASC, s.name ASC
";
$stmt = $pdo->prepare($queryDetails);
$stmt->execute(['target_date' => $date]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach($details as $row) {
    $schName = $row['schedule_name'] ?: 'Sem Horário';
    if(!isset($grouped[$schName])) {
        $grouped[$schName] = [];
    }
    $orderId = $row['order_id'];
    if(!isset($grouped[$schName][$orderId])) {
        $grouped[$schName][$orderId] = [
            'student' => $row['student_name'],
            'class' => $row['class_name'] ?: 'Sem Turma',
            'status' => $row['delivery_status'],
            'items' => []
        ];
    }
    $grouped[$schName][$orderId]['items'][] = $row['qty'] . 'x ' . $row['product_name'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Cozinha - <?= date('d/m/Y', strtotime($date)) ?></title>
    <style>
        body { font-family: sans-serif; color: #333; margin: 20px; font-size: 14px; }
        h1, h2, h3 { color: #000; margin-bottom: 5px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-pending { background: #ffebee; color: #c62828; }
        .badge-prepared { background: #fff3e0; color: #ef6c00; }
        .badge-delivered { background: #e8f5e9; color: #2e7d32; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #000; color: #fff; border: none; cursor: pointer; border-radius: 5px;">Imprimir / Salvar PDF</button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #ccc; border: none; cursor: pointer; border-radius: 5px; margin-left: 10px;">Fechar</button>
    </div>

    <div class="header">
        <h1>Relatório de Produção da Cozinha</h1>
        <h2>Data: <?= date('d/m/Y', strtotime($date)) ?></h2>
    </div>

    <?php if(empty($summary)): ?>
        <p style="text-align: center; padding: 50px;">Nenhum pedido registrado nesta data.</p>
    <?php else: ?>
        
        <h3>Resumo de Produção (Total a Produzir)</h3>
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th style="width: 100px; text-align: center;">Quantidade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($summary as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td style="text-align: center; font-weight: bold;"><?= $row['total_qty'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Detalhamento por Horário e Aluno</h3>
        <?php foreach($grouped as $schName => $orders): ?>
            <h4 style="background: #eee; padding: 5px 10px; margin-top: 20px; border-left: 4px solid #333;">Horário: <?= htmlspecialchars($schName) ?></h4>
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">Pedido #</th>
                        <th>Aluno</th>
                        <th>Turma</th>
                        <th>Itens (Qtd x Produto)</th>
                        <th style="width: 120px;">Status Final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($orders as $id => $order): 
                        $statusText = '';
                        $badgeClass = '';
                        if ($order['status'] === 'PENDING') { $statusText = 'Não Feito'; $badgeClass = 'badge-pending'; }
                        elseif ($order['status'] === 'PREPARED') { $statusText = 'Não Retirado'; $badgeClass = 'badge-prepared'; }
                        elseif ($order['status'] === 'DELIVERED') { $statusText = 'Entregue'; $badgeClass = 'badge-delivered'; }
                    ?>
                    <tr>
                        <td><?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></td>
                        <td><strong><?= htmlspecialchars($order['student']) ?></strong></td>
                        <td><?= htmlspecialchars($order['class']) ?></td>
                        <td><?= htmlspecialchars(implode(', ', $order['items'])) ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $statusText ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
        
    <?php endif; ?>
</body>
</html>
