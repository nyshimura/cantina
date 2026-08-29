<?php
// api/get_kitchen_history.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('OPERATOR');

$date = $_GET['date'] ?? null;
if (!$date) {
    echo '<div class="text-center p-10 text-red-500 font-bold">Data não fornecida.</div>';
    exit;
}

try {
    $query = "
        SELECT 
            po.id as order_id,
            po.delivery_status,
            s.name as student_name,
            c.name as class_name,
            ms.id as schedule_id,
            ms.name as schedule_name,
            ms.cutoff_time,
            poi.qty,
            p.name as product_name
        FROM pre_orders po
        JOIN pre_order_items poi ON po.id = poi.pre_order_id
        JOIN students s ON po.student_id = s.id
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        LEFT JOIN meal_schedules ms ON c.meal_schedule_id = ms.id
        JOIN products p ON poi.product_id = p.id
        WHERE po.order_date = :target_date AND po.payment_status != 'REFUNDED' AND po.delivery_status != 'CANCELLED'
        ORDER BY ms.cutoff_time ASC, po.id ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['target_date' => $date]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by Schedule, then by Order
    $grouped = [];
    foreach($items as $row) {
        $schId = $row['schedule_id'] ?: 0;
        if(!isset($grouped[$schId])) {
            $grouped[$schId] = [
                'name' => $row['schedule_name'] ?: 'Sem Horário',
                'cutoff_time' => $row['cutoff_time'] ?: '23:59:00',
                'orders' => []
            ];
        }
        
        $orderId = $row['order_id'];
        if(!isset($grouped[$schId]['orders'][$orderId])) {
            $grouped[$schId]['orders'][$orderId] = [
                'id' => $row['order_id'],
                'student_name' => $row['student_name'],
                'class_name' => $row['class_name'] ?: 'Sem Turma',
                'status' => $row['delivery_status'],
                'items' => []
            ];
        }
        
        $grouped[$schId]['orders'][$orderId]['items'][] = [
            'qty' => $row['qty'],
            'product_name' => $row['product_name']
        ];
    }
    
    if (empty($grouped)) {
        echo '<div class="text-center p-10 text-slate-500 font-bold"><i data-lucide="coffee" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>Nenhum pedido para este dia.</div>';
        exit;
    }

    echo '<div class="space-y-6">';
    foreach($grouped as $schId => $schData) {
        echo '<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">';
        echo '<div class="bg-slate-700 p-4 flex justify-between items-center">';
        echo '<h2 class="text-lg font-black text-white flex items-center gap-2"><i data-lucide="clock" class="w-5 h-5 text-slate-400"></i> ' . htmlspecialchars($schData['name']) . '</h2>';
        echo '</div>';
        
        echo '<div class="p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">';
        foreach($schData['orders'] as $order) {
            $bgClass = 'bg-slate-50 border-slate-200 opacity-60 grayscale';
            $headerClass = 'bg-slate-200 text-slate-500 border-b border-slate-300';
            $statusLabel = 'Desconhecido';
            
            if ($order['status'] === 'PREPARED') {
                $statusLabel = 'Não Retirado';
                $bgClass = 'bg-amber-50 border-amber-200 opacity-80';
                $headerClass = 'bg-amber-200 text-amber-700 border-b border-amber-300';
            } elseif ($order['status'] === 'DELIVERED') {
                $statusLabel = 'Entregue';
            } elseif ($order['status'] === 'PENDING') {
                $statusLabel = 'Pendente / Não Feito';
                $bgClass = 'bg-red-50 border-red-200 opacity-80';
                $headerClass = 'bg-red-200 text-red-700 border-b border-red-300';
            }

            echo '<div class="rounded-xl border-2 flex flex-col overflow-hidden shadow-sm ' . $bgClass . '">';
            echo '<div class="p-3 ' . $headerClass . ' flex justify-between items-start">';
            echo '<div class="min-w-0">';
            echo '<h3 class="font-black text-sm uppercase tracking-wide truncate">' . htmlspecialchars(explode(' ', $order['student_name'])[0]) . '</h3>';
            echo '<p class="text-[10px] font-bold opacity-80 truncate uppercase tracking-widest mt-0.5">' . htmlspecialchars($order['class_name']) . '</p>';
            echo '</div>';
            echo '<div class="text-xs font-black opacity-60 ml-2 shrink-0">#' . str_pad($order['id'], 4, '0', STR_PAD_LEFT) . '</div>';
            echo '</div>';
            
            echo '<div class="p-3 flex-1">';
            echo '<ul class="space-y-1">';
            foreach($order['items'] as $item) {
                echo '<li class="flex items-start gap-1 text-slate-700 font-bold text-xs">';
                echo '<span class="bg-white border border-slate-200 rounded px-1 text-slate-500 shrink-0 shadow-sm">' . $item['qty'] . 'x</span>';
                echo '<span class="leading-tight">' . htmlspecialchars($item['product_name']) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="px-3 py-2 bg-white/50 border-t border-black/5 mt-auto text-center font-bold text-xs uppercase tracking-widest text-slate-500">';
            echo $statusLabel;
            echo '</div>';
            
            echo '</div>'; // End Ticket
        }
        echo '</div>'; // End Grid
        echo '</div>'; // End Schedule Block
    }
    echo '</div>';
} catch (Exception $e) {
    echo '<div class="text-red-500 p-5">Erro: ' . $e->getMessage() . '</div>';
}
