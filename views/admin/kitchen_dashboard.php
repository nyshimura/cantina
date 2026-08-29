<?php
// views/admin/kitchen_dashboard.php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');

// Data Fetching
$today = date('Y-m-d');
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
    WHERE po.order_date = :today AND po.payment_status != 'REFUNDED' AND po.delivery_status != 'CANCELLED'
    ORDER BY ms.cutoff_time ASC, po.id ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute(['today' => $today]);
$items = $stmt->fetchAll();

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

require __DIR__ . '/../../includes/header.php';
?>

<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50">
    <?php include __DIR__ . '/../../includes/top_header.php'; ?>
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50 relative">
            <header class="flex flex-col sm:flex-row justify-between items-center bg-white p-6 rounded-2xl border border-slate-200 shadow-sm gap-4">
                <div>
                    <h1 class="text-3xl font-black text-slate-800 flex items-center gap-3">
                        <i data-lucide="chef-hat" class="text-indigo-500 w-8 h-8"></i> Cozinha - Painel de Comandas
                    </h1>
                    <p class="text-slate-500 mt-1 font-medium">Preparo dos lanches de hoje (<?= date('d/m/Y') ?>)</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto self-start sm:self-center">
                    <button onclick="document.getElementById('historyModal').classList.remove('hidden')" class="w-full sm:w-auto bg-indigo-50 text-indigo-600 px-4 py-3 rounded-xl font-bold hover:bg-indigo-100 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="calendar" class="w-5 h-5"></i> Histórico
                    </button>
                    <button onclick="location.reload()" class="w-full sm:w-auto bg-slate-100 text-slate-600 px-4 py-3 rounded-xl font-bold hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i> Atualizar
                    </button>
                </div>
            </header>

            <?php if(empty($grouped)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-12 flex flex-col items-center justify-center text-slate-400">
                    <i data-lucide="coffee" class="w-16 h-16 mb-4 opacity-50"></i>
                    <p class="text-lg font-bold">Nenhum pedido recebido ainda para hoje.</p>
                </div>
            <?php else: ?>
                <div class="space-y-8">
                    <?php foreach($grouped as $schId => $schData): 
                        // Check if cutoff has passed
                        $nowTime = date('H:i:s');
                        $isClosed = ($nowTime > $schData['cutoff_time']);
                    ?>
                        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                            <div class="bg-slate-800 p-6 flex justify-between items-center">
                                <h2 class="text-xl font-black text-white flex items-center gap-2">
                                    <i data-lucide="clock" class="text-slate-400"></i> <?= htmlspecialchars($schData['name']) ?>
                                </h2>
                                <div class="flex items-center gap-3">
                                    <span class="text-slate-300 text-sm font-bold bg-slate-700/50 px-3 py-1.5 rounded-lg">
                                        Limite: <?= substr($schData['cutoff_time'], 0, 5) ?>
                                    </span>
                                    <?php if($isClosed): ?>
                                        <span class="bg-emerald-500 text-white text-xs font-black px-3 py-1.5 rounded-lg uppercase tracking-widest flex items-center gap-1">
                                            <i data-lucide="lock" class="w-3 h-3"></i> Encerrado
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-amber-500 text-white text-xs font-black px-3 py-1.5 rounded-lg uppercase tracking-widest flex items-center gap-1">
                                            <i data-lucide="unlock" class="w-3 h-3"></i> Recebendo
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- COMANDAS GRID -->
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                                <?php foreach($schData['orders'] as $order): 
                                    // Status colors
                                    $bgClass = 'bg-amber-50 border-amber-200';
                                    $headerClass = 'bg-amber-100 text-amber-800 border-b border-amber-200';
                                    $btnLabel = 'Marcar como Pronto';
                                    $btnIcon = 'check-circle';
                                    $btnClass = 'bg-amber-500 hover:bg-amber-600 text-white';
                                    $onClickAction = 'PREPARED';
                                    
                                    if ($order['status'] === 'PREPARED') {
                                        $bgClass = 'bg-emerald-50 border-emerald-200';
                                        $headerClass = 'bg-emerald-500 text-white border-b border-emerald-600';
                                        $btnLabel = 'Pronto (Desfazer)';
                                        $btnIcon = 'rotate-ccw';
                                        $btnClass = 'bg-emerald-600 hover:bg-emerald-700 text-white';
                                        $onClickAction = 'PENDING';
                                    } elseif ($order['status'] === 'DELIVERED') {
                                        $bgClass = 'bg-slate-50 border-slate-200 opacity-80 grayscale-[50%]';
                                        $headerClass = 'bg-slate-200 text-slate-500 border-b border-slate-300';
                                        $btnLabel = 'Entregue (Refazer)';
                                        $btnIcon = 'rotate-ccw';
                                        $btnClass = 'bg-slate-300 hover:bg-red-500 hover:text-white text-slate-500 transition-colors';
                                        $onClickAction = 'PENDING';
                                    }
                                ?>
                                    <div class="rounded-2xl border-2 flex flex-col overflow-hidden shadow-sm <?= $bgClass ?> transition-all">
                                        <!-- Header Comanda -->
                                        <div class="p-3 <?= $headerClass ?> flex justify-between items-start">
                                            <div class="min-w-0">
                                                <h3 class="font-black text-sm uppercase tracking-wide truncate <?= $order['status'] === 'DELIVERED' ? 'line-through opacity-70' : '' ?>">
                                                    <?= htmlspecialchars(explode(' ', $order['student_name'])[0]) ?>
                                                </h3>
                                                <p class="text-[10px] font-bold opacity-80 truncate uppercase tracking-widest mt-0.5">
                                                    <?= htmlspecialchars($order['class_name']) ?>
                                                </p>
                                            </div>
                                            <div class="text-xs font-black opacity-60 ml-2 shrink-0">#<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                        </div>
                                        
                                        <!-- Itens -->
                                        <div class="p-4 flex-1">
                                            <ul class="space-y-2">
                                                <?php foreach($order['items'] as $item): ?>
                                                    <li class="flex items-start gap-2 text-slate-700 font-bold text-sm">
                                                        <span class="bg-white border border-slate-200 rounded px-1.5 py-0.5 text-xs text-slate-500 shrink-0 shadow-sm">
                                                            <?= $item['qty'] ?>x
                                                        </span>
                                                        <span class="leading-tight"><?= htmlspecialchars($item['product_name']) ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        
                                        <!-- Ação -->
                                        <div class="p-3 bg-white/50 border-t border-black/5 mt-auto">
                                            <button 
                                                <?= $onClickAction ? "onclick=\"changeStatus({$order['id']}, '{$onClickAction}')\"" : 'disabled' ?> 
                                                class="w-full <?= $btnClass ?> flex items-center justify-center gap-2 py-2.5 rounded-xl font-bold text-xs transition-colors shadow-sm">
                                                <i data-lucide="<?= $btnIcon ?>" class="w-4 h-4"></i> <?= $btnLabel ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Histórico Modal -->
<div id="historyModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeHistoryModal()"></div>
    <div class="bg-slate-50 rounded-3xl shadow-2xl w-full max-w-5xl overflow-hidden relative flex flex-col max-h-[90vh]">
        <div class="bg-white border-b border-slate-200 p-6 flex justify-between items-center">
            <h2 class="text-2xl font-black text-slate-800 flex items-center gap-3">
                <i data-lucide="calendar-search" class="text-indigo-500 w-8 h-8"></i> Histórico de Comandas
            </h2>
            <button onclick="closeHistoryModal()" class="text-slate-400 hover:text-slate-600 bg-slate-100 p-2 rounded-full transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        
        <div class="p-6 bg-white border-b border-slate-200 flex justify-between items-center flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <label class="font-bold text-slate-700">Selecione o Dia:</label>
                <input type="date" id="historyDate" max="<?= date('Y-m-d') ?>" class="border border-slate-300 rounded-xl px-4 py-2 font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="fetchHistory()">
                <button onclick="fetchHistory()" class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-bold hover:bg-indigo-700 transition-colors">
                    Buscar
                </button>
            </div>
            <button onclick="printReport()" class="bg-slate-800 text-white px-4 py-2 rounded-xl font-bold hover:bg-slate-900 transition-colors flex items-center gap-2">
                <i data-lucide="printer" class="w-4 h-4"></i> Imprimir Relatório
            </button>
        </div>
        
        <div id="historyContent" class="p-6 overflow-y-auto flex-1">
            <div class="text-center p-12 text-slate-400">
                <i data-lucide="calendar" class="w-16 h-16 mx-auto mb-4 opacity-50"></i>
                <p class="text-lg font-bold">Selecione uma data acima para carregar o histórico.</p>
            </div>
        </div>
    </div>
</div>
<!-- Custom Confirm Modal -->
<div id="confirmModal" class="hidden fixed inset-0 flex items-center justify-center p-4" style="z-index: 9999;">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden relative flex flex-col p-6 text-center">
        <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="alert-triangle" class="w-8 h-8"></i>
        </div>
        <h2 class="text-xl font-black text-slate-800 mb-2" id="confirmTitle">Confirmar Ação</h2>
        <p class="text-slate-500 font-medium mb-6" id="confirmMessage">Tem certeza que deseja fazer isso?</p>
        <div class="flex gap-3">
            <button onclick="closeConfirmModal()" class="flex-1 bg-slate-100 text-slate-700 py-3 rounded-xl font-bold hover:bg-slate-200 transition-colors">Cancelar</button>
            <button id="confirmBtn" class="flex-1 bg-amber-500 text-white py-3 rounded-xl font-bold hover:bg-amber-600 transition-colors">Sim, Refazer</button>
        </div>
    </div>
</div>

<!-- Custom Alert Modal -->
<div id="alertModal" class="hidden fixed inset-0 flex items-center justify-center p-4" style="z-index: 9999;">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeAlertModal()"></div>
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden relative flex flex-col p-6 text-center">
        <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="x-circle" class="w-8 h-8"></i>
        </div>
        <h2 class="text-xl font-black text-slate-800 mb-2">Aviso</h2>
        <p class="text-slate-500 font-medium mb-6" id="alertMessage">Ocorreu um erro.</p>
        <button onclick="closeAlertModal()" class="w-full bg-slate-800 text-white py-3 rounded-xl font-bold hover:bg-slate-900 transition-colors">Entendi</button>
    </div>
</div>

<script>
lucide.createIcons();

let pendingAction = null;

function showConfirm(title, message, onConfirm) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmModal').classList.remove('hidden');
    pendingAction = onConfirm;
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.add('hidden');
    pendingAction = null;
}

document.getElementById('confirmBtn').addEventListener('click', () => {
    if (pendingAction) pendingAction();
    closeConfirmModal();
});

function showAlert(message) {
    document.getElementById('alertMessage').textContent = message;
    document.getElementById('alertModal').classList.remove('hidden');
}

function closeAlertModal() {
    document.getElementById('alertModal').classList.add('hidden');
}

function changeStatus(orderId, newStatus) {
    console.log("changeStatus clicked:", orderId, newStatus);
    if (newStatus === 'PENDING') {
        showConfirm(
            'Refazer Pedido', 
            'Deseja realmente voltar este pedido para o preparo?', 
            () => executeStatusChange(orderId, newStatus)
        );
        return;
    }
    executeStatusChange(orderId, newStatus);
}

function executeStatusChange(orderId, newStatus) {
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
            location.reload();
        } else {
            showAlert(data.message);
            document.body.style.cursor = 'default';
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('Erro de conexão ao atualizar status.');
        document.body.style.cursor = 'default';
    });
}

// Modal de Histórico
function openHistoryModal() {
    document.getElementById('historyModal').classList.remove('hidden');
    // Pre-fill yesterday as default if empty
    const dateInput = document.getElementById('historyDate');
    if (!dateInput.value) {
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        dateInput.value = yesterday.toISOString().split('T')[0];
        fetchHistory();
    }
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.add('hidden');
}

function fetchHistory() {
    const date = document.getElementById('historyDate').value;
    const content = document.getElementById('historyContent');
    
    if (!date) return;
    
    content.innerHTML = '<div class="text-center p-12 text-indigo-500"><i data-lucide="loader-2" class="w-12 h-12 animate-spin mx-auto mb-4"></i><p class="font-bold">Buscando histórico...</p></div>';
    lucide.createIcons();
    
    fetch('../../api/get_kitchen_history.php?date=' + date)
    .then(res => res.text())
    .then(html => {
        content.innerHTML = html;
        lucide.createIcons();
    })
    .catch(err => {
        content.innerHTML = '<div class="text-center p-12 text-red-500 font-bold">Erro ao buscar histórico. Tente novamente.</div>';
    });
}

function printReport() {
    const date = document.getElementById('historyDate').value;
    if (!date) return alert('Selecione uma data primeiro.');
    window.open('kitchen_report.php?date=' + date, '_blank');
}

// Auto-refresh the kitchen board every 30 seconds to fetch new orders
setInterval(() => {
    // Only reload if no modals are open
    const isHistoryHidden = document.getElementById('historyModal').classList.contains('hidden');
    const isConfirmHidden = document.getElementById('confirmModal').classList.contains('hidden');
    const isAlertHidden = document.getElementById('alertModal').classList.contains('hidden');
    
    if (isHistoryHidden && isConfirmHidden && isAlertHidden) {
        location.reload();
    }
}, 30000);
</script>
</body>
</html>
