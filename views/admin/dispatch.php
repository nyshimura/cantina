<?php
// views/admin/dispatch.php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManagePreOrders');

// Fetch today's orders grouped by delivery_status
$today = date('Y-m-d');
$query = "
    SELECT 
        po.id,
        po.delivery_status,
        po.payment_status,
        po.payment_method,
        s.name as student_name,
        s.avatar_url,
        c.name as class_name,
        ms.name as schedule_name,
        GROUP_CONCAT(CONCAT(poi.qty, 'x ', p.name) SEPARATOR ', ') as items
    FROM pre_orders po
    JOIN students s ON po.student_id = s.id
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN meal_schedules ms ON c.meal_schedule_id = ms.id
    JOIN pre_order_items poi ON po.id = poi.pre_order_id
    JOIN products p ON poi.product_id = p.id
    WHERE po.order_date = :today AND po.payment_status != 'REFUNDED' AND po.delivery_status != 'CANCELLED'
    GROUP BY po.id
    ORDER BY ms.cutoff_time ASC, po.id ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute(['today' => $today]);
$orders = $stmt->fetchAll();

$pending = [];
$prepared = [];
$delivered = [];

foreach($orders as $o) {
    if ($o['delivery_status'] === 'PENDING') $pending[] = $o;
    elseif ($o['delivery_status'] === 'PREPARED') $prepared[] = $o;
    elseif ($o['delivery_status'] === 'DELIVERED') $delivered[] = $o;
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50">
    <?php include __DIR__ . '/../../includes/top_header.php'; ?>
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50 relative">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-white p-6 rounded-2xl border border-slate-200 shadow-sm gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-black text-slate-800 flex items-center gap-3">
                        <i data-lucide="monitor-speaker" class="text-indigo-500 w-8 h-8"></i> Painel de Entrega (TV)
                    </h1>
                    <p class="text-slate-500 mt-1 font-medium">Controle de pedidos e chamadas na TV para hoje (<?= date('d/m/Y') ?>)</p>
                    
                    <div class="mt-4 flex items-center gap-2 bg-indigo-50/50 p-3 rounded-xl border border-indigo-100 max-w-fit">
                        <i data-lucide="info" class="text-indigo-500 w-5 h-5 shrink-0"></i>
                        <div class="text-sm">
                            <span class="text-slate-600 font-medium">Digite este endereço no navegador da TV:</span>
                            <?php
                                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                $tvUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname(dirname($_SERVER['PHP_SELF']))) . "/tv.php";
                            ?>
                            <span class="font-bold text-indigo-700 ml-1 select-all"><?= $tvUrl ?></span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 w-full sm:w-auto self-start sm:self-center">
                    <a href="../../tv.php" target="_blank" class="flex-1 sm:flex-none bg-indigo-50 text-indigo-600 px-4 py-3 rounded-xl font-bold hover:bg-indigo-100 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="external-link" class="w-5 h-5"></i> Abrir TV no PC
                    </a>
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-full pb-20 lg:pb-0">
                
                <!-- Coluna 1: Na Fila (PENDING) -->
                <div class="flex flex-col bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden h-[80vh]">
                    <div class="bg-slate-100 p-4 border-b border-slate-200 flex justify-between items-center shrink-0">
                        <h2 class="font-black text-slate-700 flex items-center gap-2">
                            <i data-lucide="clock" class="text-slate-400"></i> Na Fila (<?= count($pending) ?>)
                        </h2>
                    </div>
                    <div class="p-4 flex-1 overflow-y-auto space-y-4">
                        <?php if(empty($pending)): ?>
                            <p class="text-center text-slate-400 font-bold py-10">Nenhum pedido na fila.</p>
                        <?php endif; ?>
                        <?php foreach($pending as $order): ?>
                            <div class="border-2 border-slate-100 rounded-2xl p-4 flex flex-col gap-3 bg-white" id="order-<?= $order['id'] ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex items-center gap-3">
                                        <?= $order['avatar_url'] ? '<img src="'.$order['avatar_url'].'" class="w-10 h-10 rounded-full object-cover">' : '<div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center"><i data-lucide="user" class="w-5 h-5 text-slate-400"></i></div>' ?>
                                        <div>
                                            <h3 class="font-bold text-slate-800 leading-tight"><?= htmlspecialchars($order['student_name']) ?></h3>
                                            <p class="text-xs font-bold text-emerald-600"><?= $order['class_name'] ?? 'Sem Turma' ?></p>
                                        </div>
                                    </div>
                                    <span class="text-xs font-black bg-slate-100 text-slate-500 px-2 py-1 rounded-md">#<?= $order['id'] ?></span>
                                </div>
                                <div class="text-sm font-medium text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <?= htmlspecialchars($order['items']) ?>
                                </div>
                                <button onclick="changeStatus(<?= $order['id'] ?>, 'PREPARED')" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-black py-3 rounded-xl transition-all shadow-sm flex justify-center items-center gap-2 uppercase tracking-wide text-sm">
                                    <i data-lucide="bell-ring" class="w-4 h-4"></i> Pronto / Chamar
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Coluna 2: Chamando (PREPARED) -->
                <div class="flex flex-col bg-amber-50 rounded-3xl border border-amber-200 shadow-sm overflow-hidden h-[80vh]">
                    <div class="bg-amber-400 p-4 border-b border-amber-500 flex justify-between items-center shrink-0">
                        <h2 class="font-black text-white flex items-center gap-2">
                            <i data-lucide="bell-ring" class="text-amber-100"></i> Chamando na TV (<?= count($prepared) ?>)
                        </h2>
                    </div>
                    <div class="p-4 flex-1 overflow-y-auto space-y-4">
                        <?php if(empty($prepared)): ?>
                            <p class="text-center text-amber-600/50 font-bold py-10">Ninguém sendo chamado.</p>
                        <?php endif; ?>
                        <?php foreach($prepared as $order): ?>
                            <div class="border-2 border-amber-200 rounded-2xl p-4 flex flex-col gap-3 bg-white shadow-sm" id="order-<?= $order['id'] ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex items-center gap-3">
                                        <?= $order['avatar_url'] ? '<img src="'.$order['avatar_url'].'" class="w-10 h-10 rounded-full object-cover">' : '<div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center"><i data-lucide="user" class="w-5 h-5 text-slate-400"></i></div>' ?>
                                        <div>
                                            <h3 class="font-bold text-slate-800 leading-tight"><?= htmlspecialchars($order['student_name']) ?></h3>
                                            <p class="text-xs font-bold text-emerald-600"><?= $order['class_name'] ?? 'Sem Turma' ?></p>
                                        </div>
                                    </div>
                                    <span class="text-xs font-black bg-amber-100 text-amber-700 px-2 py-1 rounded-md">#<?= $order['id'] ?></span>
                                </div>
                                <div class="text-sm font-medium text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <?= htmlspecialchars($order['items']) ?>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="changeStatus(<?= $order['id'] ?>, 'DELIVERED')" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white font-black py-3 rounded-xl transition-all shadow-sm flex justify-center items-center gap-2 uppercase tracking-wide text-sm shadow-emerald-500/30">
                                        <i data-lucide="check-circle" class="w-4 h-4"></i> Entregar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Coluna 3: Entregues (DELIVERED) -->
                <div class="flex flex-col bg-emerald-50 rounded-3xl border border-emerald-200 shadow-sm overflow-hidden h-[80vh]">
                    <div class="bg-emerald-500 p-4 border-b border-emerald-600 flex justify-between items-center shrink-0">
                        <h2 class="font-black text-white flex items-center gap-2">
                            <i data-lucide="check-circle-2" class="text-emerald-100"></i> Entregues (<?= count($delivered) ?>)
                        </h2>
                    </div>
                    <div class="p-4 flex-1 overflow-y-auto space-y-3 opacity-70 hover:opacity-100 transition-opacity">
                        <?php if(empty($delivered)): ?>
                            <p class="text-center text-emerald-600/50 font-bold py-10">Nenhuma entrega ainda.</p>
                        <?php endif; ?>
                        <?php foreach($delivered as $order): ?>
                            <div class="border border-emerald-200 rounded-xl p-3 flex justify-between items-center bg-white" id="order-<?= $order['id'] ?>">
                                <div>
                                    <h3 class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($order['student_name']) ?></h3>
                                    <p class="text-[10px] text-slate-400 font-medium truncate max-w-[200px]"><?= htmlspecialchars($order['items']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            </div>
        </main>
    </div>
</div>

<script>
lucide.createIcons();

function changeStatus(orderId, newStatus) {
    // Show a small loading state on the body cursor
    document.body.style.cursor = 'wait';
    
    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('status', newStatus);

    fetch('../../api/update_order_status.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            // Refresh to re-render the lists (in a more complex app we'd move DOM nodes, but reloading is safest to keep state sync)
            window.location.reload();
        } else {
            alert('Erro: ' + data.error);
            document.body.style.cursor = 'default';
        }
    })
    .catch(err => {
        alert('Erro de conexão.');
        document.body.style.cursor = 'default';
    });
}
</script>
</body>
</html>
