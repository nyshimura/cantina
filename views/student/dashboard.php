<?php
// views/student/dashboard.php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('STUDENT');

$studentId = $_SESSION['user_id'];

try {
    // 1. Busca dados do aluno
    $stmt = $pdo->prepare("
        SELECT s.*, COALESCE(n.balance, 0) as balance, n.tag_id as nfc_id
        FROM students s
        LEFT JOIN nfc_tags n ON n.current_student_id = s.id
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    if (!$student) die("Erro: Aluno não encontrado.");

    // Fetch Classrooms
    $classrooms = $pdo->query("SELECT id, name FROM classrooms ORDER BY name ASC")->fetchAll();

    // Fetch School Name
    $schoolName = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'school_name'")->fetchColumn() ?: 'Cantina Escolar';

    // --- LÓGICA DE LIMITE DE RECARGA CORRIGIDA ---
    $rechargeConfig = json_decode($student['recharge_config'] ?? '[]', true);
    $limitVal = floatval($rechargeConfig['limit'] ?? 0);
    $limitPeriod = $rechargeConfig['period'] ?? 'Diário';
    $currentUsage = 0;
    $hasLimit = false;

    if ($limitVal > 0) {
        $hasLimit = true;
        
        // Filtro de Data (Diário ou Mensal)
        $dateFilter = "DATE(timestamp) = CURRENT_DATE()";
        if ($limitPeriod === 'Mensal') {
            $dateFilter = "MONTH(timestamp) = MONTH(CURRENT_DATE()) AND YEAR(timestamp) = YEAR(CURRENT_DATE())";
        }

        // CORREÇÃO: Soma apenas COMPLETED e PENDING RECENTES (últimos 30 min)
        $stmtLimit = $pdo->prepare("
            SELECT SUM(amount) 
            FROM transactions 
            WHERE student_id = ? 
            AND type = 'DEPOSIT' 
            AND $dateFilter
            AND (
                status = 'COMPLETED' 
                OR (status = 'PENDING' AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 MINUTE))
            )
        ");
        $stmtLimit->execute([$studentId]);
        $currentUsage = floatval($stmtLimit->fetchColumn() ?: 0);
    }
    
    $remainingLimit = max(0, $limitVal - $currentUsage);
    // ---------------------------------------------------

    // 2. Histórico
    $stmtH = $pdo->prepare("
        SELECT timestamp, items_summary as display_desc, type, amount, status 
        FROM transactions 
        WHERE student_id = ? AND status IN ('COMPLETED', 'REFUNDED', 'CANCELLED')
        ORDER BY timestamp DESC LIMIT 10
    ");
    $stmtH->execute([$studentId]);
    $txs = $stmtH->fetchAll();

    // 4. Pre-Order Data
    $stmtOrder = $pdo->prepare("
        SELECT po.id, po.payment_status, po.payment_method, p.name as product_name, p.image_url, po.delivery_status, po.created_at
        FROM pre_orders po
        JOIN pre_order_items poi ON po.id = poi.pre_order_id
        JOIN products p ON poi.product_id = p.id
        WHERE po.student_id = ? AND po.order_date = CURDATE() AND po.delivery_status != 'CANCELLED'
        LIMIT 1
    ");
    $stmtOrder->execute([$studentId]);
    $currentOrder = $stmtOrder->fetch();

    $products = $pdo->query("SELECT * FROM products WHERE active = 1 ORDER BY name")->fetchAll();
    
    $cutoff = null;
    if ($student['classroom_id']) {
        $stmtClass = $pdo->prepare("SELECT m.cutoff_time FROM classrooms c JOIN meal_schedules m ON c.meal_schedule_id = m.id WHERE c.id = ?");
        $stmtClass->execute([$student['classroom_id']]);
        $cutoff = $stmtClass->fetchColumn();
    }

} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

require __DIR__ . '/../../includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    html, body { 
        background-color: #f8fafc; 
        overflow-x: hidden;
        height: 100%;
    }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    /* Modal base styles */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 100;
        align-items: flex-start;
        justify-content: center;
        padding: 5vh 1rem;
        overflow-y: auto;
    }
    
    .modal-overlay.active {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 2rem;
        width: 100%;
        max-width: 28rem;
        padding: 2rem;
        position: relative;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        margin: auto;
        animation: modalIn 0.2s ease-out forwards;
    }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

    .credit-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.3);
    }
</style>

<div class="min-h-screen w-full flex flex-col items-center p-4 md:p-8 overflow-y-auto">
    
    <div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <div class="lg:col-span-4 flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="<?= $student['avatar_url'] ?>" class="w-12 h-12 rounded-full border-2 border-white shadow-sm bg-white object-cover">
                    <div class="min-w-0"> <h1 class="text-lg font-black text-slate-800 leading-tight truncate">Olá, <?= explode(' ', $student['name'])[0] ?></h1>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide truncate"><?= htmlspecialchars($schoolName) ?></p>
                    </div>
                </div>
                <button onclick="openModal('modalProfile')" class="p-2 text-slate-400 hover:text-emerald-500 transition-colors bg-white rounded-xl shadow-sm border border-slate-100 flex-shrink-0"><i data-lucide="settings" class="w-5 h-5"></i></button>
            </div>

            <div class="credit-card relative w-full aspect-[1.586] rounded-3xl p-6 text-white flex flex-col justify-between overflow-hidden group shadow-2xl transform transition-transform hover:scale-[1.02]">
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-white/5 rounded-full blur-3xl group-hover:bg-white/10 transition-all"></div>
                
                <div class="flex justify-between items-start z-10">
                    <div class="w-11 h-8 rounded bg-amber-200/90 flex items-center justify-center shadow-sm">
                        <div class="w-8 h-5 border border-amber-500/30 rounded-[2px] flex items-center justify-center"><div class="w-full h-[1px] bg-amber-500/30"></div></div>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Saldo Atual</p>
                        <p class="text-2xl font-black tracking-tight">R$ <?= number_format($student['balance'], 2, ',', '.') ?></p>
                    </div>
                </div>
                
                <div class="z-10 mt-auto">
                    <div class="flex items-center gap-3 mb-4 opacity-50"><i data-lucide="wifi" class="w-5 h-5 rotate-90"></i></div>
                    <p class="font-mono text-lg tracking-widest mb-1 opacity-90 truncate">
                        •••• •••• •••• <?= !empty($student['nfc_id']) ? strtoupper(substr($student['nfc_id'], -4)) : '0000' ?>
                    </p>
                    <div class="flex justify-between items-end">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-300 truncate max-w-[70%]"><?= $student['name'] ?></p>
                        <div class="flex -space-x-3 opacity-90 flex-shrink-0"><div class="w-8 h-8 rounded-full bg-red-500/80"></div><div class="w-8 h-8 rounded-full bg-amber-500/80"></div></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <?php if ($student['can_self_charge']): ?>
                    <button onclick="openModal('modalRecharge')" class="bg-emerald-50 text-emerald-600 border border-emerald-100 py-4 rounded-2xl font-black text-xs uppercase tracking-widest flex flex-col items-center gap-2 hover:bg-emerald-100 transition-all shadow-sm active:scale-95"><i data-lucide="plus-circle" class="w-5 h-5"></i> Recarregar</button>
                <?php else: ?>
                    <button disabled class="bg-slate-50 text-slate-300 border border-slate-100 py-4 rounded-2xl font-black text-xs uppercase tracking-widest flex flex-col items-center gap-2 cursor-not-allowed opacity-75"><i data-lucide="lock" class="w-5 h-5"></i> Recarregar</button>
                <?php endif; ?>
                
                <div class="bg-white border border-slate-100 py-4 rounded-2xl flex flex-col items-center justify-center gap-1 shadow-sm">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Limite <?= ucfirst($limitPeriod) ?></span>
                    <span class="text-lg font-black text-slate-700">R$ <?= number_format($limitVal, 2, ',', '.') ?></span>
                </div>
            </div>
            
            <a href="../logout.php" class="text-center text-xs font-bold text-red-400 hover:text-red-500 mt-2 flex items-center justify-center gap-2 py-2"><i data-lucide="log-out" class="w-3 h-3"></i> Sair da conta</a>
        </div>

        <div class="lg:col-span-8 flex flex-col gap-8">
            <!-- Nova Seção: Cardápio do Dia -->
            <div class="bg-white rounded-[2.5rem] p-6 md:p-8 border border-slate-100 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-indigo-50 text-indigo-600 rounded-xl flex-shrink-0"><i data-lucide="utensils" class="w-5 h-5"></i></div>
                        <div>
                            <h2 class="text-xl font-black text-slate-800 italic">Reserva de Lanche (Hoje)</h2>
                            <?php if($cutoff): ?>
                                <p class="text-xs text-slate-500 font-bold uppercase">Encerra às <?= substr($cutoff, 0, 5) ?></p>
                            <?php else: ?>
                                <p class="text-xs text-amber-500 font-bold uppercase"><i data-lucide="alert-triangle" class="w-3 h-3 inline"></i> Sem turma configurada</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php 
                $now = date('H:i:s');
                $canOrder = $cutoff && ($now <= $cutoff);
                ?>

                <?php if ($currentOrder): ?>
                    <!-- Já tem pedido hoje -->
                    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <?php if($currentOrder['image_url']): ?>
                                <img src="<?= $currentOrder['image_url'] ?>" class="w-16 h-16 object-contain drop-shadow-sm">
                            <?php else: ?>
                                <div class="w-16 h-16 bg-white rounded-xl shadow-sm flex items-center justify-center"><i data-lucide="sandwich" class="w-6 h-6 text-emerald-500"></i></div>
                            <?php endif; ?>
                            <div>
                                <h3 class="font-black text-emerald-800 text-lg"><?= htmlspecialchars($currentOrder['product_name']) ?></h3>
                                <p class="text-xs font-bold uppercase text-emerald-600 tracking-wider">
                                    Status: <?= $currentOrder['delivery_status'] == 'DELIVERED' ? 'Entregue' : ($currentOrder['delivery_status'] == 'PREPARED' ? 'Pronto para Retirar' : 'Aguardando Preparo') ?>
                                </p>
                            </div>
                        </div>
                        <?php 
                            // Regra de Cancelamento: 15 minutos do pedido, DESDE QUE antes do cutoff
                            $orderTime = strtotime($currentOrder['created_at']);
                            $timePassed = time() - $orderTime;
                            $canCancel = ($timePassed <= 900) && $canOrder && ($currentOrder['delivery_status'] == 'PENDING');
                        ?>
                        <?php if($canCancel): ?>
                            <button onclick="cancelOrder(<?= $currentOrder['id'] ?>)" class="bg-red-100 text-red-600 px-4 py-2 rounded-xl font-bold hover:bg-red-200 transition-colors text-sm whitespace-nowrap">Cancelar (<?= floor((900 - $timePassed)/60) ?>m restantes)</button>
                        <?php endif; ?>
                    </div>
                <?php elseif (!$cutoff): ?>
                    <!-- Sem turma -->
                    <div class="bg-amber-50 p-6 rounded-2xl border border-amber-200 text-center">
                        <p class="text-amber-700 font-bold text-sm">Vá no seu Perfil e selecione sua turma para poder fazer reservas.</p>
                    </div>
                <?php elseif (!$canOrder): ?>
                    <!-- Passou do horário -->
                    <div class="bg-slate-100 p-6 rounded-2xl border border-slate-200 text-center text-slate-500">
                        <i data-lucide="clock" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                        <p class="font-bold">O horário para reservas já encerrou.</p>
                    </div>
                <?php else: ?>
                    <!-- Fazer Pedido -->
                    <form onsubmit="placeOrder(event)" class="space-y-6 mt-4">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-48 overflow-y-auto pr-2">
                            <?php foreach($products as $p): ?>
                                <label class="relative border-2 border-slate-100 rounded-xl p-3 cursor-pointer hover:border-indigo-200 flex flex-col justify-between">
                                    <input type="radio" name="product_id" value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" onchange="checkBalance()" class="peer sr-only" required>
                                    <div class="peer-checked:border-indigo-500 absolute inset-0 border-2 border-transparent rounded-xl pointer-events-none transition-all"></div>
                                    <div class="peer-checked:text-indigo-600 font-bold text-slate-700 text-sm leading-tight relative z-10 flex items-center gap-1">
                                        <?= htmlspecialchars($p['name']) ?>
                                        <?php if($p['is_special_of_day']): ?><i data-lucide="star" class="w-3 h-3 text-amber-500"></i><?php endif; ?>
                                    </div>
                                    <div class="text-xs font-black text-slate-400 mt-2 relative z-10">R$ <?= number_format($p['price'], 2, ',', '.') ?></div>
                                    <div class="peer-checked:block hidden absolute top-2 right-2 text-indigo-500 z-10">
                                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Forma de Pagamento</label>
                            <div class="flex gap-3">
                                <label id="labelPaymentWallet" class="flex-1 relative border-2 border-slate-100 rounded-xl p-3 cursor-pointer hover:border-emerald-200 text-center transition-colors">
                                    <input type="radio" name="payment_method" value="WALLET" id="radioWallet" class="peer sr-only" checked>
                                    <div class="peer-checked:border-emerald-500 peer-checked:bg-emerald-50 absolute inset-0 border-2 border-transparent rounded-xl pointer-events-none transition-all"></div>
                                    <div id="walletIconContainer"><i data-lucide="wallet" class="relative z-10 w-5 h-5 mx-auto mb-1 peer-checked:text-emerald-600 text-slate-400 transition-colors"></i></div>
                                    <div id="walletLabel" class="relative z-10 peer-checked:text-emerald-700 font-bold text-slate-600 text-xs uppercase tracking-wide">Saldo Tag</div>
                                </label>
                                <label class="flex-1 relative border-2 border-slate-100 rounded-xl p-3 cursor-pointer hover:border-amber-200 text-center">
                                    <input type="radio" name="payment_method" value="CASH" id="radioCash" class="peer sr-only">
                                    <div class="peer-checked:border-amber-500 peer-checked:bg-amber-50 absolute inset-0 border-2 border-transparent rounded-xl pointer-events-none transition-all"></div>
                                    <i data-lucide="coins" class="relative z-10 w-5 h-5 mx-auto mb-1 peer-checked:text-amber-600 text-slate-400"></i>
                                    <div class="relative z-10 peer-checked:text-amber-700 font-bold text-slate-600 text-xs uppercase tracking-wide">Dinheiro</div>
                                </label>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100">
                            <button type="submit" id="btnSubmitOrder" class="w-full bg-indigo-600 text-white font-black py-4 rounded-xl shadow-lg shadow-indigo-500/30 hover:bg-indigo-700 transition-all uppercase tracking-widest text-sm flex items-center justify-center gap-2">
                                <i data-lucide="check" class="w-5 h-5"></i> Confirmar Reserva
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Movimentações (antigo bg-white rounded-[2.5rem]...) -->
            <div class="bg-white rounded-[2.5rem] p-6 md:p-8 border border-slate-100 shadow-sm flex flex-col min-h-[400px]">
            <div class="flex items-center gap-3 mb-8 sticky top-0 bg-white z-10 pb-2">
                <div class="p-2 bg-slate-50 text-slate-600 rounded-xl flex-shrink-0"><i data-lucide="coffee" class="w-5 h-5"></i></div>
                <h2 class="text-xl font-black text-slate-800 italic">Últimas Movimentações</h2>
            </div>
            
            <div class="flex-1 overflow-y-auto pr-1"> 
                <?php if(empty($txs)): ?>
                    <div class="flex flex-col items-center justify-center h-64 text-slate-300">
                        <i data-lucide="inbox" class="w-12 h-12 mb-2"></i>
                        <p class="font-bold text-sm">Nenhuma movimentação ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                    <?php foreach($txs as $t): 
                        $isEntry = $t['type'] === 'DEPOSIT' || $t['type'] === 'RECHARGE'; 
                        $isRefund = $t['status'] === 'REFUNDED';
                        $isCancelled = $t['status'] === 'CANCELLED';
                        
                        // Visual Logic
                        if ($isCancelled) {
                            $icon = 'x-circle';
                            $color = 'bg-red-50 text-red-500';
                            $amountClass = 'text-red-300 line-through decoration-red-500';
                        } elseif ($isRefund) {
                            $icon = 'rotate-ccw'; 
                            $color = 'bg-orange-50 text-orange-500';
                            $amountClass = 'text-slate-400 line-through decoration-orange-500';
                        } else {
                            $icon = $isEntry ? 'arrow-up-circle' : 'coffee'; 
                            $color = $isEntry ? 'bg-emerald-50 text-emerald-600' : 'bg-orange-50 text-orange-600'; 
                            $amountClass = $isEntry ? 'text-emerald-600' : 'text-slate-800';
                        }
                        
                        $sign = ($isEntry && !$isRefund && !$isCancelled) ? '+' : '-'; 
                    ?>
                    <div class="flex items-center justify-between p-4 hover:bg-slate-50 rounded-2xl transition-colors group cursor-default">
                        <div class="flex items-center gap-4 overflow-hidden">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center <?= $color ?> flex-shrink-0">
                                <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-700 text-sm mb-0.5 group-hover:text-slate-900 transition-colors truncate">
                                    <?= htmlspecialchars($t['display_desc'] ?: 'Compra na Cantina') ?>
                                    <?php if($isRefund): ?>
                                        <span class="text-[9px] bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full border border-orange-200 uppercase ml-1 align-middle">Estornado</span>
                                    <?php elseif($isCancelled): ?>
                                        <span class="text-[9px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full border border-red-200 uppercase ml-1 align-middle">Cancelado</span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide truncate"><?= date('d/m/Y • H:i', strtotime($t['timestamp'])) ?></p>
                            </div>
                        </div>
                        <span class="text-sm font-black <?= $amountClass ?> whitespace-nowrap ml-2"><?= $sign ?> R$ <?= number_format(abs($t['amount']), 2, ',', '.') ?></span>
                    </div>
                    <div class="h-px bg-slate-50 w-full last:hidden"></div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="modalProfile" class="modal-overlay">
    <div class="modal-content relative">
        <button onclick="closeModal('modalProfile')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button>
        <div class="flex items-center gap-3 mb-6">
            <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="user-cog" class="w-5 h-5"></i></div>
            <h2 class="font-black text-slate-800 italic text-xl">Editar Perfil</h2>
        </div>
        <div class="flex justify-center mb-6">
            <img id="editAvatarPreview" src="<?= $student['avatar_url'] ?>" class="w-20 h-20 rounded-full border-4 border-slate-50 shadow-sm">
        </div>
        <form onsubmit="handleFormSubmit(event, 'update_student_profile.php')">
            <div class="space-y-4">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Mudar Avatar (Deslize no mapa)</label>
                    <div id="editAvatarMap" class="relative w-full h-32 rounded-xl cursor-crosshair overflow-hidden shadow-inner touch-none" style="background: conic-gradient(from 180deg at 50% 50%, #ff0000, #ff8000, #ffff00, #00ff00, #00ffff, #0000ff, #8000ff, #ff00ff, #ff0000);">
                        <div class="absolute inset-0" style="background: linear-gradient(to bottom, rgba(255,255,255,1), rgba(255,255,255,0) 50%, rgba(0,0,0,0) 50%, rgba(0,0,0,1));"></div>
                        <div id="editAvatarPointer" class="absolute w-4 h-4 bg-white border-2 border-slate-800 rounded-full shadow-md pointer-events-none -translate-x-1/2 -translate-y-1/2" style="top: 50%; left: 50%;"></div>
                    </div>
                    <input type="hidden" name="avatar_seed" id="editAvatarSeed" value="">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Nome (Visualização)</label>
                    <input type="text" value="<?= $student['name'] ?>" disabled class="w-full p-3 bg-slate-100 border border-slate-200 rounded-xl font-bold text-slate-500 cursor-not-allowed">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">E-mail</label>
                    <input type="email" name="email" value="<?= $student['email'] ?>" required class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Turma (Aprovação da Escola)</label>
                    <select name="classroom_id" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-emerald-500">
                        <option value="">Selecione uma Turma...</option>
                        <?php foreach($classrooms as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($student['classroom_id'] == $c['id'] || $student['pending_classroom_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Nova Senha (Login)</label>
                    <input type="password" name="password" placeholder="••••••••" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-emerald-500">
                </div>
                
                <div class="bg-red-50/50 p-4 rounded-2xl border border-red-100/50">
                    <label class="text-[10px] font-black text-red-400 uppercase ml-2 mb-1 block flex items-center gap-1">
                        <i data-lucide="lock" class="w-3 h-3"></i> Senha de Compra (PIN)
                    </label>
                    <div class="relative">
                        <input type="password" name="pin" maxlength="6" inputmode="numeric" placeholder="4 a 6 números" class="w-full p-3 bg-white border border-red-100 rounded-xl font-bold text-slate-700 outline-none focus:border-red-400 focus:ring-4 focus:ring-red-500/10 text-center tracking-[0.5em] placeholder:tracking-normal placeholder:text-slate-300 placeholder:font-medium">
                    </div>
                    <p class="text-[9px] text-red-300 font-bold mt-2 ml-2 text-center uppercase tracking-wide">Deixe vazio para não alterar</p>
                </div>

            </div>
            <button type="submit" class="submit-btn w-full bg-emerald-500 text-white font-black py-4 rounded-xl shadow-lg hover:bg-emerald-600 transition-all mt-6 uppercase text-xs tracking-widest">Salvar Alterações</button>
        </form>
    </div>
</div>

<?php if($student['can_self_charge']): ?>
<div id="modalRecharge" class="modal-overlay">
    <div class="modal-content relative text-center">
        <button onclick="closeModal('modalRecharge')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button>
        <div class="w-14 h-14 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4"><i data-lucide="qr-code" class="w-7 h-7"></i></div>
        <h2 class="font-black text-slate-800 italic text-xl mb-2">Recarga via Pix</h2>
        <p class="text-slate-400 text-xs font-medium mb-8 max-w-[200px] mx-auto">O valor será creditado após a confirmação.</p>
        
        <div id="stepAmount">
            <div class="relative mb-6">
                <span class="absolute left-1/2 -translate-x-[60px] top-1/2 -translate-y-1/2 font-black text-slate-300 text-2xl">R$</span>
                <input type="number" id="pixAmount" class="w-full text-center text-4xl font-black text-slate-700 bg-transparent outline-none placeholder-slate-200" placeholder="0,00" step="0.05">
            </div>
            
            <?php if($hasLimit): ?>
                <div id="limitInfo" class="mb-4">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                        Limite Restante: R$ <span class="text-emerald-500"><?= number_format($remainingLimit, 2, ',', '.') ?></span> (<?= $limitPeriod ?>)
                    </p>
                </div>
            <?php endif; ?>
            
            <div id="rechargeError" class="hidden mb-4 p-3 bg-red-50 text-red-500 text-xs font-bold rounded-xl border border-red-100"></div>

            <button onclick="generatePix()" class="w-full bg-slate-800 text-white font-bold py-4 rounded-xl hover:bg-slate-900 transition-all shadow-lg">Gerar QR Code</button>
        </div>

        <div id="stepPix" class="hidden flex flex-col items-center">
            <div id="qrCodeContainer" class="bg-white border-2 border-slate-100 rounded-2xl p-4 inline-block mb-4 shadow-sm"></div>
            
            <div class="relative w-full mb-4">
                <input type="text" id="copyPaste" readonly class="w-full text-[10px] text-center text-slate-400 bg-slate-50 p-3 rounded-xl font-mono truncate cursor-pointer pr-10 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" onclick="copyPixCode()">
                <button onclick="copyPixCode()" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-500 transition-colors">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                </button>
            </div>
            
            <div id="copyFeedback" class="hidden w-full text-center mb-4">
                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">Código PIX Copiado!</span>
            </div>

            <button onclick="location.reload()" class="w-full bg-emerald-500 text-white font-bold py-3 rounded-xl hover:bg-emerald-600 transition-all shadow-lg">Já Paguei</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="modalSuccessPayment" class="modal-overlay">
    <div class="modal-content relative text-center">
        <div class="w-20 h-20 bg-emerald-100 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="check-circle" class="w-10 h-10"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-800 mb-2">Pagamento Confirmado!</h3>
        <p class="text-slate-500 font-medium mb-8">O saldo foi creditado na sua conta.</p>
        <button onclick="location.reload()" class="w-full bg-slate-900 text-white font-bold py-4 rounded-2xl hover:bg-slate-800 transition-all">Entendido</button>
    </div>
</div>

<div id="modalAlert" class="modal-overlay"><div class="modal-content bg-white rounded-[2.5rem] relative max-w-sm shadow-2xl text-center"><button onclick="closeModal('modalAlert')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button><div class="w-16 h-16 rounded-full bg-slate-50 text-slate-500 flex items-center justify-center mx-auto mb-4" id="modalAlertIconContainer"><i data-lucide="info" class="w-8 h-8" id="modalAlertIcon"></i></div><h2 class="font-black text-slate-800 italic mb-2" id="modalAlertTitle">Aviso</h2><p class="text-sm font-medium text-slate-500 mb-6" id="modalAlertMessage"></p><button onclick="closeModal('modalAlert')" class="w-full bg-slate-800 text-white font-black py-4 rounded-2xl shadow-lg hover:bg-slate-900 transition-all uppercase tracking-widest text-xs">Entendi</button></div></div>
<div id="modalConfirm" class="modal-overlay"><div class="modal-content bg-white rounded-[2.5rem] relative max-w-sm shadow-2xl text-center"><button onclick="closeModal('modalConfirm')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button><div class="w-16 h-16 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center mx-auto mb-4"><i data-lucide="help-circle" class="w-8 h-8"></i></div><h2 class="font-black text-slate-800 italic mb-2" id="modalConfirmTitle">Confirmação</h2><p class="text-sm font-medium text-slate-500 mb-6" id="modalConfirmMessage"></p><div class="flex gap-3"><button onclick="closeModal('modalConfirm')" class="flex-1 py-4 font-black text-slate-400 hover:text-slate-600 italic">Cancelar</button><button id="btnConfirmAction" class="flex-1 bg-amber-500 text-white font-black rounded-2xl shadow-lg hover:bg-amber-600 transition-all uppercase tracking-widest text-xs">Confirmar</button></div></div></div>

<script>
    const currentBalance = <?= $student['balance'] ?>;
    const availableBalance = currentBalance; // Aluno não usa cheque especial no app

    function showAlert(message, type = 'info') {
        document.getElementById('modalAlertMessage').textContent = message;
        const iconContainer = document.getElementById('modalAlertIconContainer');
        const title = document.getElementById('modalAlertTitle');
        
        iconContainer.className = 'w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 ' + 
            (type === 'error' ? 'bg-red-50 text-red-500' : (type === 'success' ? 'bg-emerald-50 text-emerald-500' : 'bg-slate-50 text-slate-500'));
        title.textContent = type === 'error' ? 'Erro' : (type === 'success' ? 'Sucesso' : 'Aviso');
        
        if(typeof lucide !== 'undefined') lucide.createIcons();
        openModal('modalAlert');
    }

    function showConfirm(message, onConfirm) {
        document.getElementById('modalConfirmMessage').textContent = message;
        const btn = document.getElementById('btnConfirmAction');
        btn.onclick = () => { closeModal('modalConfirm'); onConfirm(); };
        openModal('modalConfirm');
    }

    function checkBalance() {
        const selected = document.querySelector('input[name="product_id"]:checked');
        if(!selected) return;
        const price = parseFloat(selected.dataset.price);
        const labelWallet = document.getElementById('labelPaymentWallet');
        const radioWallet = document.getElementById('radioWallet');
        const radioCash = document.getElementById('radioCash');
        const walletLabel = document.getElementById('walletLabel');
        const walletIconContainer = document.getElementById('walletIconContainer');
        
        const hasTag = <?= !empty($student['nfc_id']) ? 'true' : 'false' ?>;
        const canSelfCharge = <?= $student['can_self_charge'] ? 'true' : 'false' ?>;
        
        if(price > availableBalance) {
            if(radioWallet.checked) radioCash.checked = true;
            radioWallet.disabled = true;
            
            if(hasTag && canSelfCharge) {
                // Tem permissão: vira um botão de recarga vermelho
                labelWallet.classList.remove('hidden');
                labelWallet.classList.replace('hover:border-emerald-200', 'hover:border-red-200');
                walletIconContainer.innerHTML = '<i data-lucide="alert-circle" class="relative z-10 w-5 h-5 mx-auto mb-1 text-red-400 transition-colors"></i>';
                walletLabel.classList.add('text-[10px]');
                walletLabel.textContent = 'Sem Saldo (Recarregar)';
                labelWallet.onclick = (e) => {
                    e.preventDefault();
                    openModal('modalRecharge');
                };
            } else {
                // Não tem permissão: esconde a opção totalmente e muda pro dinheiro automaticamente
                labelWallet.classList.add('hidden');
            }
            if(typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            radioWallet.disabled = false;
            labelWallet.classList.remove('hidden');
            labelWallet.classList.replace('hover:border-red-200', 'hover:border-emerald-200');
            walletIconContainer.innerHTML = '<i data-lucide="wallet" class="relative z-10 w-5 h-5 mx-auto mb-1 peer-checked:text-emerald-600 text-slate-400 transition-colors"></i>';
            walletLabel.classList.remove('text-[10px]');
            walletLabel.textContent = 'Saldo Tag';
            labelWallet.onclick = null;
            if(typeof lucide !== 'undefined') lucide.createIcons();
        }
    }
    let statusInterval;
    function openModal(id) { 
        document.getElementById(id).classList.add('active'); 
        if(id === 'modalRecharge') {
            document.getElementById('rechargeError').classList.add('hidden'); // Limpa erros anteriores
            document.getElementById('pixAmount').value = '';
        }
    }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); if(statusInterval) clearInterval(statusInterval); }

    // VARIÁVEIS INJETADAS PELO PHP PARA O JS
    const hasLimit = <?= $hasLimit ? 'true' : 'false' ?>;
    const remainingLimit = <?= $remainingLimit ?>;
    const limitPeriod = "<?= $limitPeriod ?>";

    async function generatePix() {
        const amountInput = document.getElementById('pixAmount');
        const errorBox = document.getElementById('rechargeError');
        const val = parseFloat(amountInput.value);
        
        errorBox.classList.add('hidden'); // Reseta erro

        if(!val || val <= 0) {
            errorBox.textContent = 'Digite um valor válido.';
            errorBox.classList.remove('hidden');
            return;
        }
        
        // VERIFICAÇÃO DE LIMITE VISUAL
        if (hasLimit && val > remainingLimit) {
            errorBox.textContent = `Limite excedido! Máximo permitido: R$ ${remainingLimit.toFixed(2).replace('.', ',')}`;
            errorBox.classList.remove('hidden');
            return;
        }
        
        const btn = document.querySelector('#stepAmount button');
        const oldText = btn.textContent;
        btn.textContent = 'Gerando...'; btn.disabled = true;

        try {
            const res = await fetch('../../api/recharge.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ amount: val })
            });
            const data = await res.json();

            if (data.success) {
                document.getElementById('stepAmount').classList.add('hidden');
                document.getElementById('stepPix').classList.remove('hidden');
                document.getElementById('stepPix').classList.add('flex');
                
                const container = document.getElementById('qrCodeContainer');
                container.innerHTML = ''; 
                
                if (data.qr_code_base64 && data.method === 'MERCADO_PAGO') {
                    const img = document.createElement('img');
                    img.src = 'data:image/png;base64,' + data.qr_code_base64;
                    img.className = 'w-48 h-48 object-contain';
                    container.appendChild(img);
                } else {
                    new QRCode(container, { text: data.copy_paste, width: 190, height: 190, colorDark : "#0f172a", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.M });
                }
                document.getElementById('copyPaste').value = data.copy_paste;
                startStatusPolling(data.external_reference);
            } else {
                errorBox.textContent = 'Erro: ' + data.message;
                errorBox.classList.remove('hidden');
                btn.textContent = oldText; btn.disabled = false;
            }
        } catch(e) {
            errorBox.textContent = 'Erro de conexão com o servidor.';
            errorBox.classList.remove('hidden');
            btn.textContent = oldText; btn.disabled = false;
        }
    }

    async function handleFormSubmit(e, api) {
        e.preventDefault();
        const btn = e.target.querySelector('.submit-btn');
        const oldText = btn.textContent;
        btn.textContent = 'Salvando...'; btn.disabled = true;
        const fd = new FormData(e.target);
        try {
            const res = await fetch('../../api/' + api, { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) { btn.textContent = 'Sucesso!'; btn.classList.replace('bg-emerald-500', 'bg-emerald-700'); setTimeout(() => location.reload(), 1000); } 
            else { showAlert(data.message, 'error'); btn.textContent = oldText; btn.disabled = false; }
        } catch(err) { showAlert('Erro de conexão', 'error'); btn.disabled = false; }
    }

    function copyPixCode() {
        const copyText = document.getElementById("copyPaste");
        copyText.select();
        copyText.setSelectionRange(0, 99999); 
        document.execCommand("copy");
        const feedback = document.getElementById('copyFeedback');
        feedback.classList.remove('hidden');
        setTimeout(() => feedback.classList.add('hidden'), 3000);
    }

    function startStatusPolling(ref) {
        if(!ref) return;
        statusInterval = setInterval(async () => {
            try {
                const res = await fetch('../../api/check_status.php?ref=' + ref);
                const data = await res.json();
                if(data.status === 'COMPLETED') { 
                    clearInterval(statusInterval); 
                    closeModal('modalRecharge');
                    openModal('modalSuccessPayment');
                }
            } catch(e) {}
        }, 3000);
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
        }
    }
    
    // --- Lógica do Avatar Cartesiano ---
    const editMap = document.getElementById('editAvatarMap');
    const editPointer = document.getElementById('editAvatarPointer');
    const editPreview = document.getElementById('editAvatarPreview');
    const editSeedInput = document.getElementById('editAvatarSeed');
    
    let isDraggingAvatar = false;
    let avatarDebounceTimer;
    
    function updateEditAvatar(e) {
        if(!editMap) return;
        const rect = editMap.getBoundingClientRect();
        let clientX = e.touches ? e.touches[0].clientX : e.clientX;
        let clientY = e.touches ? e.touches[0].clientY : e.clientY;
    
        let x = clientX - rect.left;
        let y = clientY - rect.top;
        
        x = Math.max(0, Math.min(x, rect.width));
        y = Math.max(0, Math.min(y, rect.height));
        
        editPointer.style.left = x + 'px';
        editPointer.style.top = y + 'px';
        
        const r = Math.round((x / rect.width) * 255);
        const b = Math.round((y / rect.height) * 255);
        const g = Math.round(((rect.width - x) / rect.width) * 255);
        
        const hex = ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        editSeedInput.value = hex;
    
        clearTimeout(avatarDebounceTimer);
        avatarDebounceTimer = setTimeout(() => {
            editPreview.src = `https://api.dicebear.com/9.x/adventurer/svg?seed=${hex}`;
        }, 100);
    }
    
    if(editMap) {
        editMap.addEventListener('mousedown', (e) => { isDraggingAvatar = true; updateEditAvatar(e); });
        window.addEventListener('mousemove', (e) => { if(isDraggingAvatar) updateEditAvatar(e); });
        window.addEventListener('mouseup', () => { isDraggingAvatar = false; });
        
        editMap.addEventListener('touchstart', (e) => { isDraggingAvatar = true; updateEditAvatar(e); }, {passive: false});
        window.addEventListener('touchmove', (e) => { if(isDraggingAvatar) { updateEditAvatar(e); e.preventDefault(); } }, {passive: false});
        window.addEventListener('touchend', () => { isDraggingAvatar = false; });
    }

    async function placeOrder(e) {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        const oldText = btn.innerHTML;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Processando...';
        btn.disabled = true;

        const formData = new FormData(e.target);
        const data = {
            student_id: <?= $studentId ?>,
            product_id: formData.get('product_id'),
            payment_method: formData.get('payment_method')
        };

        try {
            const res = await fetch('../../api/create_pre_order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (json.success) {
                location.reload();
            } else {
                showAlert(json.message, 'error');
                btn.innerHTML = oldText;
                btn.disabled = false;
            }
        } catch(err) {
            showAlert("Erro de conexão.", 'error');
            btn.innerHTML = oldText;
            btn.disabled = false;
        }
    }

    function cancelOrder(orderId) {
        showConfirm("Tem certeza que deseja cancelar esta reserva? (O saldo será estornado caso tenha sido cobrado)", async () => {
            try {
                const res = await fetch('../../api/cancel_pre_order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ order_id: orderId, student_id: <?= $studentId ?> })
                });
                
                const json = await res.json();
                if (json.success) {
                    location.reload();
                } else {
                    showAlert("Erro: " + json.message, 'error');
                }
            } catch(e) {
                showAlert("Erro de conexão.", 'error');
            }
        });
    }

    lucide.createIcons();
</script>