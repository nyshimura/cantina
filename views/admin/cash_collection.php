<?php
// views/admin/cash_collection.php
require_once '../../includes/auth.php';
if (!isset($_SESSION['user_id']) || $_SESSION['access_level'] !== 'ADMIN') {
    header('Location: ../../index.php');
    exit;
}

// Fetch pending cash pre-orders for today
$stmt = $pdo->query("
    SELECT p.id as pre_order_id, p.payment_status, p.delivery_status, s.name as student_name, c.name as class_name, m.name as schedule_name,
           GROUP_CONCAT(CONCAT(pi.qty, 'x ', pi.product_name) SEPARATOR ', ') as items,
           SUM(pi.qty * pi.unit_price) as total_amount
    FROM pre_orders p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN meal_schedules m ON c.meal_schedule_id = m.id
    JOIN pre_order_items pi ON p.id = pi.pre_order_id
    WHERE p.order_date = CURRENT_DATE() AND p.payment_status = 'PENDING'
    GROUP BY p.id
    ORDER BY c.name, s.name
");
$pendingOrders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coleta de Dinheiro - Reservas - CantinaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 font-sans pb-20">
    
    <?php include '../../includes/navbar.php'; ?>

    <div class="max-w-4xl mx-auto p-4 lg:p-10">
        <header class="mb-10 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-black tracking-tight italic text-slate-800 flex items-center gap-3">
                    <i data-lucide="banknote" class="w-8 h-8 text-emerald-500"></i> Coleta de Dinheiro
                </h1>
                <p class="text-slate-500 font-medium italic">Confirme o recebimento das reservas feitas em dinheiro hoje.</p>
            </div>
        </header>

        <div class="bg-white rounded-3xl p-6 shadow-xl shadow-slate-200/50 border border-slate-100">
            <?php if(empty($pendingOrders)): ?>
                <div class="text-center p-10">
                    <i data-lucide="check-circle-2" class="w-16 h-16 text-slate-200 mx-auto mb-4"></i>
                    <p class="text-slate-400 font-bold italic">Nenhuma cobrança pendente para hoje.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-slate-100">
                                <th class="pb-4 font-black text-xs text-slate-400 uppercase tracking-widest px-4">Aluno</th>
                                <th class="pb-4 font-black text-xs text-slate-400 uppercase tracking-widest px-4">Turma / Intervalo</th>
                                <th class="pb-4 font-black text-xs text-slate-400 uppercase tracking-widest px-4">Itens</th>
                                <th class="pb-4 font-black text-xs text-slate-400 uppercase tracking-widest px-4">Valor</th>
                                <th class="pb-4 font-black text-xs text-slate-400 uppercase tracking-widest px-4 text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($pendingOrders as $order): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors" id="order-row-<?= $order['pre_order_id'] ?>">
                                    <td class="py-4 px-4 font-bold text-slate-700"><?= htmlspecialchars($order['student_name']) ?></td>
                                    <td class="py-4 px-4">
                                        <div class="text-sm font-bold text-slate-600"><?= htmlspecialchars($order['class_name'] ?: 'Sem Turma') ?></div>
                                        <div class="text-[10px] uppercase font-black tracking-widest text-slate-400"><?= htmlspecialchars($order['schedule_name'] ?: '-') ?></div>
                                    </td>
                                    <td class="py-4 px-4 text-sm text-slate-500 font-medium italic"><?= htmlspecialchars($order['items']) ?></td>
                                    <td class="py-4 px-4 font-black text-emerald-600">R$ <?= number_format($order['total_amount'], 2, ',', '.') ?></td>
                                    <td class="py-4 px-4 text-right">
                                        <button onclick="confirmPayment(<?= $order['pre_order_id'] ?>)" class="bg-emerald-50 text-emerald-600 px-4 py-2 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-emerald-500 hover:text-white transition-all transform hover:scale-105 shadow-sm">
                                            Recebido
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        async function confirmPayment(orderId) {
            if(!confirm("Confirmar o recebimento em dinheiro desta reserva?")) return;
            
            try {
                const res = await fetch('../../api/confirm_cash_preorder.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ pre_order_id: orderId })
                });
                const data = await res.json();
                
                if(data.success) {
                    const row = document.getElementById('order-row-' + orderId);
                    row.style.opacity = '0.5';
                    row.style.pointerEvents = 'none';
                    row.querySelector('button').innerText = 'PAGO';
                    row.querySelector('button').className = 'bg-slate-100 text-slate-400 px-4 py-2 rounded-xl font-black text-xs uppercase tracking-widest shadow-inner';
                } else {
                    alert(data.message);
                }
            } catch(e) {
                alert("Erro ao confirmar.");
            }
        }
    </script>
</body>
</html>
