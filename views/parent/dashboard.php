<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('PARENT');

$parentId = $_SESSION['user_id'];

try {
    // 1. Fetch linked children
    $stmtChildren = $pdo->prepare("SELECT id, name FROM students WHERE parent_id = ? AND active = 1");
    $stmtChildren->execute([$parentId]);
    $children = $stmtChildren->fetchAll();

    $selectedId = $_GET['child_id'] ?? ($children[0]['id'] ?? null);

    $childData = ['name' => '', 'balance' => 0, 'daily_limit' => 0, 'avatar_url' => '', 'cpf' => '', 'email' => '', 'can_self_charge' => 0, 'recharge_config' => '{"limit":0,"period":"Mensal"}'];
    $history = [];
    $coParents = [];
    $parentData = [];
    $graphData = [];
    $weekMap = [0 => 'seg', 1 => 'ter', 2 => 'qua', 3 => 'qui', 4 => 'sex', 5 => 'sáb', 6 => 'dom'];
    $maxSpendInWeek = 0;

    // 2. Fetch Parent Data
    $stmtParent = $pdo->prepare("SELECT name, cpf, email, phone FROM parents WHERE id = ?");
    $stmtParent->execute([$parentId]);
    $parentData = $stmtParent->fetch();

    if ($selectedId) {
        // 3. Fetch Student Data (including balance from nfc_tags)
        $stmt = $pdo->prepare("
            SELECT s.*, COALESCE(n.balance, 0) as balance 
            FROM students s
            LEFT JOIN nfc_tags n ON n.current_student_id = s.id
            WHERE s.id = ? AND s.parent_id = ?
        ");
        $stmt->execute([$selectedId, $parentId]);
        $data = $stmt->fetch();
        if ($data) {
            $childData = $data;
            if (!$childData['recharge_config']) $childData['recharge_config'] = '{"limit":0,"period":"Mensal"}';
        }

        // 4. Fetch History (CORRECTED: Includes REFUNDED and CANCELLED status)
        $stmtH = $pdo->prepare("
            SELECT timestamp, items_summary as display_desc, type, amount, status 
            FROM transactions 
            WHERE student_id = ? AND status IN ('COMPLETED', 'REFUNDED', 'CANCELLED')
            ORDER BY timestamp DESC LIMIT 20
        ");
        $stmtH->execute([$selectedId]);
        $history = $stmtH->fetchAll();

        // 5. Weekly Graph Data
        $stmtGraph = $pdo->prepare("
            SELECT WEEKDAY(timestamp) as wday, SUM(amount) as total 
            FROM transactions 
            WHERE student_id = ? 
              AND status = 'COMPLETED' 
              AND type NOT IN ('DEPOSIT', 'RECHARGE') 
              AND YEARWEEK(timestamp, 1) = YEARWEEK(NOW(), 1)
            GROUP BY wday
            ORDER BY wday ASC
        ");
        $stmtGraph->execute([$selectedId]);

        while ($row = $stmtGraph->fetch()) {
            $total = abs((float)$row['total']);
            $graphData[] = ['day' => $weekMap[$row['wday']], 'value' => $total];
            if ($total > $maxSpendInWeek) $maxSpendInWeek = $total;
        }

        // 6. Fetch Co-Parents
        $stmtCo = $pdo->prepare("
            SELECT p.id, p.name, p.email, scp.active
            FROM student_co_parents scp
            JOIN parents p ON scp.parent_id = p.id
            WHERE scp.student_id = ?
            ORDER BY scp.active DESC, p.name ASC
        ");
        $stmtCo->execute([$selectedId]);
        $coParents = $stmtCo->fetchAll();
    }

    $dailyLimit = (float)$childData['daily_limit'];
    $chartScale = $dailyLimit > 0 ? $dailyLimit : ($maxSpendInWeek > 0 ? $maxSpendInWeek : 100);

} catch (Exception $e) {
    die("Erro ao carregar dados.");
}

require __DIR__ . '/../../includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    /* Global Styles */
    html, body { overflow-y: auto !important; height: auto !important; background-color: #f8fafc; }
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #10b981; border-radius: 10px; border: 2px solid #f1f5f9; }
    ::-webkit-scrollbar-thumb:hover { background: #059669; }

    /* Modal Overlay */
    .modal-overlay {
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        display: none;
        position: fixed;
        inset: 0;
        z-index: 100;
        align-items: center;
        justify-content: center;
        padding: 2vh;
        overflow-y: auto;
    }
    .modal-overlay.active { display: flex; }
    
    #modalAddCoParent { z-index: 110; }
    #modalDeleteCoParent { z-index: 120; }

    /* Modal Content */
    .modal-content { 
        height: auto; max-height: 98vh; width: 100%; max-width: 450px; 
        overflow: hidden; display: flex; flex-direction: column;
        padding: clamp(1rem, 3vh, 2.5rem) !important;
        animation: modalFadeIn 0.2s ease-out; 
    }

    .modal-content h2 { font-size: clamp(1.1rem, 2.8vh, 1.5rem) !important; margin-bottom: 2vh !important; }
    .modal-content .flex-col.items-center, .modal-content .flex.items-center.gap-3 { margin-bottom: 2vh !important; }
    .modal-content img { max-height: clamp(60px, 25vh, 240px) !important; width: auto; margin-bottom: 1.5vh !important; }
    .modal-content form { gap: clamp(0.5rem, 1.5vh, 1.25rem) !important; display: flex; flex-direction: column; }
    .modal-content label { font-size: clamp(0.65rem, 1.8vh, 0.75rem) !important; margin-bottom: 0.1vh !important; }
    
    .modal-content input:not(.credit-input), .modal-content select { 
        height: clamp(35px, 5.5vh, 50px) !important; padding: 0 1rem !important; font-size: clamp(0.8rem, 2vh, 0.95rem) !important;
    }

    /* Credit Input Style */
    .credit-input {
        height: clamp(50px, 8vh, 70px) !important; padding-left: 5rem !important; padding-right: 1rem !important;
        font-size: clamp(1.5rem, 4vh, 2rem) !important; font-weight: 900; color: #334155;
    }

    .modal-content .submit-btn { padding: clamp(0.8rem, 2vh, 1.25rem) 0 !important; font-size: 0.85rem !important; }
    .coparent-list { display: flex; flex-direction: column; gap: 1vh; overflow-y: auto; max-height: 35vh; margin-bottom: 2vh; padding-right: 0.5rem; }
    .coparent-list::-webkit-scrollbar { width: 4px; }
    .coparent-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .coparent-item.inactive { opacity: 0.6; background-color: #f1f5f9; border-style: dashed; }

    /* --- NOVO ESTILO: Lista de Busca Rápida --- */
    #existingCoparentsList { border-top: 1px solid #f1f5f9; padding-top: 1.5vh; margin-top: 1vh; }

    @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>

<div class="p-4 lg:p-10 font-sans pb-20">
    <div class="max-w-6xl mx-auto">
        
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
            <div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Área do Responsável</h1>
                <p class="text-slate-500 font-medium italic">Bem-vindo, <?= explode(' ', $_SESSION['name'])[0] ?></p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 bg-white p-1.5 rounded-xl shadow-sm border border-slate-100">
                    <span class="text-[9px] font-black text-slate-400 uppercase px-2 tracking-widest">Filho:</span>
                    <form action="" method="GET">
                        <select name="child_id" onchange="this.form.submit()" class="font-bold text-slate-700 outline-none bg-transparent py-1 pr-4">
                            <?php foreach ($children as $child): ?>
                                <option value="<?= $child['id'] ?>" <?= $selectedId == $child['id'] ? 'selected' : '' ?>><?= $child['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <button onclick="openModal('modalAddChild')" class="bg-emerald-50 text-emerald-600 p-1.5 rounded-lg hover:bg-emerald-100 transition-colors"><i data-lucide="plus" class="w-4 h-4"></i></button>
                </div>
                <button onclick="openModal('modalEditStudent')" class="bg-white border border-slate-200 p-2.5 rounded-xl text-slate-400 hover:text-emerald-500 shadow-sm transition-all"><i data-lucide="pencil" class="w-5 h-5"></i></button>
                <button onclick="openModal('modalCoParents')" class="bg-white border border-slate-200 px-4 py-2.5 rounded-xl text-slate-400 hover:text-emerald-500 shadow-sm flex items-center gap-2 text-sm font-bold transition-all"><i data-lucide="users" class="w-5 h-5"></i> Responsáveis</button>
                <button onclick="openModal('modalSettings')" class="bg-white border border-slate-200 px-4 py-2.5 rounded-xl text-slate-400 hover:text-emerald-500 shadow-sm flex items-center gap-2 text-sm font-bold transition-all"><i data-lucide="user-cog" class="w-5 h-5"></i> Perfil</button>
                <a href="../../views/logout.php" class="bg-red-50 border border-red-100 px-4 py-2.5 rounded-xl text-red-500 hover:bg-red-500 hover:text-white shadow-sm flex items-center gap-2 text-sm font-bold transition-all"><i data-lucide="log-out" class="w-5 h-5"></i> Sair</a>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
            
            <div class="bg-[#10b981] rounded-[2.5rem] p-10 text-white shadow-xl shadow-emerald-100 flex flex-col justify-between relative overflow-hidden group">
                <div>
                    <p class="text-emerald-100 text-[10px] font-bold uppercase tracking-widest mb-2">Saldo Disponível</p>
                    <h2 class="text-6xl font-black italic tracking-tighter"><?= formatBRL($childData['balance']) ?></h2>
                </div>
                <button onclick="openModal('modalInsertCredit')" class="relative z-10 mt-12 bg-white text-[#10b981] font-black py-4 rounded-2xl w-full shadow-lg hover:scale-[1.03] flex items-center justify-center gap-2">+ Inserir Créditos</button>
                <i data-lucide="dollar-sign" class="absolute -right-4 -top-4 w-32 h-32 text-white/10 rotate-12"></i>
            </div>

            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm space-y-8">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2"><i data-lucide="shield-check" class="w-4 h-4 text-emerald-500"></i> Controles</h3>
                <div onclick="openModal('modalLimit')" class="p-6 bg-slate-50 rounded-2xl border border-slate-100 cursor-pointer hover:border-emerald-300 transition-all">
                    <div class="flex justify-between items-center mb-1">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Limite de Gastos</p>
                        <i data-lucide="pencil-line" class="w-4 h-4 text-slate-300"></i>
                    </div>
                    <p class="text-2xl font-black text-slate-700 italic"><?= formatBRL($childData['daily_limit']) ?> <span class="text-xs font-medium text-slate-300">/ dia</span></p>
                </div>
                <div onclick="openModal('modalWallet')" class="flex justify-between items-center px-2 group cursor-pointer italic text-sm font-bold text-slate-300 hover:text-emerald-500 transition-all">
                    <span>Autorrecarga</span>
                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm flex flex-col">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6">Consumo Semanal</h3>
                <?php if (empty($graphData)): ?>
                    <div class="flex-1 flex items-center justify-center min-h-[120px] bg-slate-50/50 rounded-3xl"><p class="text-slate-400 text-xs italic">Sem gastos registrados.</p></div>
                <?php else: ?>
                    <div class="flex-1 bg-slate-50/50 rounded-3xl flex items-end justify-center p-6 gap-6 min-h-[120px]">
                        <?php foreach ($graphData as $data):
                            $height = ($data['value'] / $chartScale) * 100;
                            $displayHeight = min(max($height, 5), 100);
                            $isHigh = ($data['value'] >= $chartScale * 0.9);
                            $bgClass = $isHigh ? 'bg-[#10b981]' : 'bg-emerald-200';
                        ?>
                            <div class="flex flex-col items-center gap-2 h-full justify-end w-12 group">
                                <div class="relative w-full <?= $bgClass ?> rounded-xl shadow-sm cursor-pointer transition-all hover:bg-emerald-600 hover:scale-105" style="height: <?= $displayHeight ?>%">
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block bg-slate-800 text-white text-[10px] font-bold py-1 px-2 rounded-lg whitespace-nowrap z-10 shadow-lg pointer-events-none">R$ <?= formatBRL($data['value']) ?></div>
                                </div>
                                <span class="text-[10px] font-black text-slate-300 uppercase"><?= $data['day'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="text-center text-[10px] font-black text-slate-300 mt-4 uppercase"><?= $childData['daily_limit'] > 0 ? 'Baseado no Limite Diário' : 'Semana Atual' ?></p>
            </div>
        </div>

        <div class="bg-white rounded-[3rem] border border-slate-100 shadow-sm overflow-hidden mb-10">
            <div class="px-10 py-8 border-b border-slate-50 flex items-center gap-3"><div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="history" class="w-5 h-5"></i></div><h3 class="text-xl font-black text-slate-800 tracking-tight italic">Histórico de Movimentações</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-widest sticky top-0">
                        <tr>
                            <th class="px-10 py-6">Data</th>
                            <th class="px-10 py-6">Descrição</th>
                            <th class="px-10 py-6 text-center">Tipo</th>
                            <th class="px-10 py-6 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($history as $item):
                            $isEntry = $item['type'] === 'DEPOSIT' || $item['type'] === 'RECHARGE';
                            $isRefund = $item['status'] === 'REFUNDED';
                            $isCancelled = $item['status'] === 'CANCELLED';
                        ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-10 py-6 text-xs font-bold text-slate-400"><?= date('d/m/Y H:i', strtotime($item['timestamp'])) ?></td>
                                <td class="px-10 py-6 text-sm font-bold text-slate-700 italic flex items-center gap-2">
                                    <?= htmlspecialchars($item['display_desc']) ?>
                                    <?php if ($isRefund): ?>
                                        <span class="text-[9px] bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full uppercase font-black border border-orange-200">Estornado</span>
                                    <?php elseif ($isCancelled): ?>
                                        <span class="text-[9px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full uppercase font-black border border-red-200">Cancelado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-10 py-6 text-center">
                                    <span class="mx-auto flex items-center justify-center gap-1.5 w-fit px-4 py-1.5 rounded-full text-[9px] font-black uppercase border <?= ($isRefund || $isCancelled) ? 'bg-slate-100 text-slate-400 border-slate-200 line-through' : ($isEntry ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-orange-50 text-orange-600 border-orange-100') ?>">
                                        <i data-lucide="<?= ($isRefund || $isCancelled) ? 'slash' : ($isEntry ? 'arrow-up-circle' : 'arrow-down-circle') ?>" class="w-3.5 h-3.5"></i> 
                                        <?= $isRefund ? 'Estornado' : ($isCancelled ? 'Cancelado' : ($isEntry ? 'Entrada' : 'Saída')) ?>
                                    </span>
                                </td>
                                <td class="px-10 py-6 text-right font-black text-sm italic <?= ($isRefund || $isCancelled) ? 'text-slate-300 line-through' : 'text-slate-800' ?>">
                                    <?= ($isEntry && !$isRefund && !$isCancelled ? '+ ' : '') . formatBRL($item['amount']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="modalInsertCredit" class="modal-overlay">
    <div class="modal-content bg-white rounded-[2.5rem] w-full max-w-md p-10 relative">
        <button onclick="closeModal('modalInsertCredit')" class="absolute right-8 top-8 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button>
        <h2 class="text-2xl font-black text-slate-800 mb-8 flex items-center gap-3 italic"><i data-lucide="credit-card" class="text-emerald-500"></i> Recarga</h2>
        <div class="bg-slate-50 p-5 rounded-3xl flex items-center gap-4 mb-8">
            <img src="<?= $childData['avatar_url'] ?>" class="w-14 h-14 rounded-full border-4 border-white shadow-sm">
            <div><p class="text-[9px] font-bold text-slate-400 uppercase">Para o aluno(a)</p><p class="text-lg font-black text-slate-800 italic"><?= $childData['name'] ?></p></div>
        </div>
        
        <div id="stepAmount">
            <div class="mb-10">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-2 block tracking-widest">Valor da Recarga</label>
                <div class="relative w-full">
                    <span class="absolute left-6 top-1/2 -translate-y-1/2 font-black text-slate-300 text-3xl italic z-10">R$</span>
                    <input type="number" id="pixAmount" step="0.05" class="credit-input w-full bg-slate-50 border-2 border-slate-100 rounded-3xl outline-none focus:border-emerald-500 transition-all" placeholder="0,00">
                </div>
            </div>
            <button onclick="generatePix()" class="w-full bg-[#10b981] text-white font-black py-6 rounded-3xl shadow-xl hover:scale-[1.02] transition-all">Pagar e Gerar QR Code PIX</button>
        </div>

        <div id="stepPix" class="hidden flex flex-col items-center">
            <div id="qrCodeContainer" class="bg-white border-2 border-slate-100 rounded-2xl p-4 inline-block mb-6 shadow-sm"></div>
            
            <div class="relative w-full mb-4">
                <input type="text" id="copyPaste" readonly class="w-full text-[10px] text-center text-slate-400 bg-slate-50 p-3 rounded-xl font-mono truncate cursor-pointer pr-10 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" onclick="copyPixCode()">
                <button onclick="copyPixCode()" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-500 transition-colors">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                </button>
            </div>
            <div id="copyFeedback" class="hidden w-full text-center mb-4">
                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">Código PIX Copiado!</span>
            </div>

            <button onclick="location.reload()" class="w-full bg-[#10b981] text-white font-black py-6 rounded-3xl shadow-xl hover:scale-[1.02] transition-all">Já Paguei</button>
        </div>
    </div>
</div>

<div id="modalSettings" class="modal-overlay"><div class="modal-content bg-white rounded-[2.5rem] relative shadow-2xl"><button onclick="closeModal('modalSettings')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button><div class="flex items-center gap-3 shrink-0 mb-6"><div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="user-cog" class="w-5 h-5"></i></div><h2 class="font-black text-slate-800 italic">Meu Perfil</h2></div><form onsubmit="handleFormSubmit(event, 'update_profile.php', 'modalSettings')"><div><label class="text-slate-400 uppercase ml-2 font-black">Nome Completo</label><input type="text" name="name" value="<?= $parentData['name'] ?>" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">CPF</label><input type="text" name="cpf" value="<?= $parentData['cpf'] ?>" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">E-mail</label><input type="email" name="email" value="<?= $parentData['email'] ?>" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">Telefone</label><input type="text" name="phone" value="<?= $parentData['phone'] ?>" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">Nova Senha (Opcional)</label><input type="password" name="password" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700" placeholder="••••••••"></div><button type="submit" class="submit-btn w-full bg-[#10b981] text-white font-black rounded-2xl shadow-xl hover:scale-[1.02] transition-all italic uppercase tracking-widest text-xs mt-2">Salvar Alterações</button></form></div></div>

<div id="modalCoParents" class="modal-overlay">
    <div class="modal-content bg-white rounded-[2.5rem] relative shadow-2xl">
        <button onclick="closeModal('modalCoParents')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button>
        <div class="flex items-center gap-3 shrink-0 mb-[2vh]">
            <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="users" class="w-5 h-5"></i></div>
            <h2 class="font-black text-slate-800 italic">Meus Co-Responsáveis</h2>
        </div>
        <div class="coparent-list">
            <div class="p-4 rounded-2xl border border-emerald-100 bg-emerald-50/30 flex items-center justify-between shrink-0">
                <div><p class="text-sm font-black text-slate-800 italic"><?= $_SESSION['name'] ?> <span class="text-[9px] bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded-full uppercase ml-1">Você</span></p><p class="text-xs text-slate-400 font-medium"><?= $_SESSION['email'] ?></p></div>
            </div>
            <?php foreach ($coParents as $cp): $isActive = $cp['active'] == 1; ?>
                <div class="coparent-item p-4 rounded-2xl border border-slate-100 bg-white hover:border-emerald-200 transition-colors flex items-center justify-between group shrink-0 <?= !$isActive ? 'inactive' : '' ?>">
                    <div>
                        <p class="text-sm font-black text-slate-700 italic flex items-center gap-2"><?= $cp['name'] ?> <?php if (!$isActive): ?><span class="text-[9px] bg-slate-200 text-slate-500 px-1.5 py-0.5 rounded font-bold uppercase">Inativo</span><?php endif; ?></p>
                        <p class="text-xs text-slate-400 font-medium"><?= $cp['email'] ?></p>
                    </div>
                    <?php if ($isActive): ?>
                        <button onclick="prepareDelete('<?= $cp['id'] ?>', '<?= $cp['name'] ?>')" class="text-slate-300 hover:text-red-500 p-2"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    <?php else: ?>
                        <button onclick="reactivateParent('<?= $cp['id'] ?>')" class="text-slate-300 hover:text-emerald-500 p-2"><i data-lucide="refresh-cw" class="w-4 h-4"></i></button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="existingCoparentsList" class="hidden">
            <p class="text-[10px] font-black text-slate-400 uppercase mb-3 tracking-widest">Vincular Responsável Existente</p>
            <div id="existingItems" class="space-y-2 mb-4 max-h-32 overflow-y-auto"></div>
        </div>

        <div class="flex flex-col gap-2 mt-auto">
            <button onclick="loadExistingCoparents()" class="w-full bg-slate-50 text-slate-600 font-bold py-3 rounded-2xl hover:bg-slate-100 transition-all flex items-center justify-center gap-2 shrink-0 border border-slate-200"><i data-lucide="search" class="w-4 h-4"></i> Usar Responsável já Cadastrado</button>
            <button onclick="openModal('modalAddCoParent', true)" class="w-full border-2 border-dashed border-emerald-300 text-emerald-600 font-bold py-3 rounded-2xl hover:bg-emerald-50 transition-all flex items-center justify-center gap-2 mt-auto shrink-0"><i data-lucide="user-plus" class="w-4 h-4"></i> Adicionar Novo Responsável</button>
        </div>
    </div>
</div>

<div id="modalAddCoParent" class="modal-overlay"><div class="modal-content bg-white rounded-[2.5rem] relative shadow-2xl max-w-sm"><button onclick="closeModal('modalAddCoParent')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button><div class="flex items-center gap-3 shrink-0 mb-[2vh]"><div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="user-plus" class="w-5 h-5"></i></div><h2 class="font-black text-slate-800 italic">Adicionar Responsável</h2></div><div class="bg-blue-50/50 p-4 rounded-2xl border border-blue-100 mb-[2vh]"><p class="text-[10px] text-blue-600 font-medium leading-relaxed">O novo responsável terá acesso total.</p></div><form onsubmit="handleFormSubmit(event, 'add_coparent.php', 'modalAddCoParent')"><input type="hidden" name="student_id" value="<?= $selectedId ?>"><div><label class="text-slate-400 uppercase ml-2 font-black">Nome Completo</label><input type="text" name="name" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">CPF</label><input type="text" name="cpf" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">E-mail</label><input type="email" name="email" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">Telefone</label><input type="text" name="phone" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">Senha</label><input type="password" name="password" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700" placeholder="••••••••"></div><button type="submit" class="submit-btn w-full bg-[#10b981] text-white font-black rounded-2xl shadow-xl hover:scale-[1.02] transition-all italic uppercase tracking-widest text-xs mt-[1vh]">Adicionar</button></form></div></div>
<div id="modalDeleteCoParent" class="modal-overlay"><div class="modal-content bg-white rounded-[2.5rem] relative shadow-2xl max-w-sm text-center"><button onclick="closeModal('modalDeleteCoParent')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button><div class="w-14 h-14 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4"><i data-lucide="alert-triangle" class="w-6 h-6"></i></div><h2 class="font-black text-slate-800 italic text-xl mb-2">Remover Responsável?</h2><p class="text-slate-400 text-sm mb-6">Tem certeza que deseja remover o acesso de <span id="deleteName" class="font-bold text-slate-600"></span>?</p><form onsubmit="handleFormSubmit(event, 'toggle_coparent.php', 'modalDeleteCoParent')"><input type="hidden" name="coparent_id" id="deleteId"><input type="hidden" name="student_id" value="<?= $selectedId ?>"><input type="hidden" name="action" value="deactivate"><div class="flex items-center justify-center gap-2 mb-6 cursor-pointer" onclick="document.getElementById('checkDelete').click()"><input type="checkbox" id="checkDelete" required class="w-4 h-4 accent-red-500 rounded cursor-pointer"><span class="text-xs font-bold text-slate-500">Confirmar a desativação</span></div><div class="flex gap-3"><button type="button" onclick="closeModal('modalDeleteCoParent')" class="flex-1 py-3 font-black text-slate-400 hover:text-slate-600 italic">Cancelar</button><button type="submit" class="submit-btn flex-1 bg-red-500 text-white font-black rounded-2xl shadow-lg hover:bg-red-600 transition-all italic uppercase text-xs">Desativar</button></div></form></div></div>
<div id="modalEditStudent" class="modal-overlay"><div class="modal-content bg-white rounded-[2.5rem] relative shadow-2xl"><button onclick="closeModal('modalEditStudent')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button><div class="flex items-center gap-3 shrink-0"><div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="pencil" class="w-4 h-4"></i></div><h2 class="font-black text-slate-800 italic">Editar Dados do Aluno</h2></div><div class="flex justify-center shrink-0"><img src="<?= $childData['avatar_url'] ?>" class="rounded-full border-4 border-slate-50 shadow-sm"></div><form onsubmit="handleFormSubmit(event, 'update_child.php', 'modalEditStudent')"><input type="hidden" name="student_id" value="<?= $selectedId ?>"><div><label class="text-slate-400 uppercase ml-2 font-black">Nome</label><input type="text" name="name" value="<?= $childData['name'] ?>" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none focus:border-emerald-500 font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">CPF</label><input type="text" name="cpf" value="<?= $childData['cpf'] ?>" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none focus:border-emerald-500 font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">E-mail</label><input type="email" name="email" value="<?= $childData['email'] ?>" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none focus:border-emerald-500 font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 font-black">Nova Senha</label><input type="password" name="password" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none focus:border-emerald-500 font-bold text-slate-700" placeholder="•••"></div><div><label class="text-slate-400 uppercase ml-2 font-black">URL Avatar</label><input type="text" name="avatar_url" value="<?= $childData['avatar_url'] ?>" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none focus:border-emerald-500 font-bold text-slate-700 text-xs"></div><button type="submit" class="submit-btn w-full bg-[#10b981] text-white font-black rounded-2xl shadow-xl hover:scale-[1.02] transition-all italic uppercase tracking-widest text-xs">Salvar</button></form></div></div>
<div id="modalWallet" class="modal-overlay"><div class="modal-content bg-white rounded-[2.5rem] relative max-w-sm shadow-2xl"><button onclick="closeModal('modalWallet')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button><div class="flex items-center gap-3 shrink-0"><div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="wallet" class="w-5 h-5"></i></div><h2 class="font-black text-slate-800 italic">Carteira</h2></div><?php $config = json_decode($childData['recharge_config'], true); ?><form onsubmit="handleFormSubmit(event, 'update_recharge.php', 'modalWallet')"><input type="hidden" name="student_id" value="<?= $selectedId ?>"><div class="bg-slate-50/50 p-4 rounded-2xl flex items-center justify-between border border-slate-100 mb-[1vh]"><span class="font-black text-slate-700 text-sm italic">Autorrecarga</span><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" name="can_self_charge" value="1" <?= $childData['can_self_charge'] ? 'checked' : '' ?> class="sr-only peer"><div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-emerald-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div></label></div><div class="grid grid-cols-2 gap-4"><div><label class="text-slate-400 uppercase ml-2 block tracking-widest">Limite</label><input type="number" name="recharge_limit" value="<?= $config['limit'] ?? 100 ?>" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700"></div><div><label class="text-slate-400 uppercase ml-2 block tracking-widest">Período</label><select name="recharge_period" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold text-slate-700"><option value="Diário" <?= ($config['period'] ?? '') == 'Diário' ? 'selected' : '' ?>>Diário</option><option value="Mensal" <?= ($config['period'] ?? '') == 'Mensal' ? 'selected' : '' ?>>Mensal</option></select></div></div><button type="submit" class="submit-btn w-full bg-[#10b981] text-white font-black px-8 py-3 rounded-xl shadow-lg hover:scale-[1.02] transition-all italic uppercase mt-2">Salvar</button></form></div></div>
<div id="modalAddChild" class="modal-overlay"><div class="modal-content bg-white rounded-[2.5rem] relative shadow-2xl"><button onclick="closeModal('modalAddChild')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button><div class="flex items-center gap-3 shrink-0"><div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="user-plus" class="w-4 h-4"></i></div><h2 class="text-xl font-black text-slate-800 italic">Novo Dependente</h2></div><form onsubmit="handleFormSubmit(event, 'add_child.php', 'modalAddChild')"><div><label class="text-slate-400 uppercase ml-2 block tracking-widest">Nome Completo</label><input type="text" name="name" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold"></div><div><label class="text-slate-400 uppercase ml-2 block tracking-widest">CPF</label><input type="text" name="cpf" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold"></div><div><label class="text-slate-400 uppercase ml-2 block tracking-widest">E-mail</label><input type="email" name="email" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold"></div><div><label class="text-slate-400 uppercase ml-2 block tracking-widest">Senha</label><input type="password" name="password" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl font-bold"></div><button type="submit" class="submit-btn w-full bg-[#10b981] text-white font-black py-5 rounded-3xl italic uppercase">Cadastrar</button></form></div></div>
<div id="modalLimit" class="modal-overlay"><div class="modal-content bg-white rounded-[2.5rem] relative max-w-sm shadow-2xl text-center"><button onclick="closeModal('modalLimit')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button><h2 class="font-black text-slate-800 italic">Limite Diário</h2><form onsubmit="handleFormSubmit(event, 'update_limit.php', 'modalLimit')"><input type="hidden" name="student_id" value="<?= $selectedId ?>"><div class="relative my-6 shrink-0"><span class="absolute left-6 top-1/2 -translate-y-1/2 font-black text-slate-300 text-2xl italic">R$</span><input type="number" name="daily_limit" step="0.05" value="<?= $childData['daily_limit'] ?>" class="w-full pl-16 pr-6 bg-slate-50 border-2 border-slate-100 rounded-3xl font-black text-4xl text-center"></div><button type="submit" class="submit-btn w-full bg-[#10b981] text-white font-black py-4 rounded-2xl shadow-lg italic">Salvar Limite</button></form></div></div>

<div id="modalSuccessPayment" class="modal-overlay">
    <div class="modal-content bg-white rounded-[2.5rem] w-full max-w-sm p-10 text-center shadow-2xl animate-in zoom-in duration-300">
        <div class="w-20 h-20 bg-emerald-100 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="check-circle" class="w-10 h-10"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-800 mb-2">Pagamento Confirmado!</h3>
        <p class="text-slate-500 font-medium mb-8">O saldo foi creditado na conta do aluno.</p>
        <button onclick="location.reload()" class="w-full bg-slate-900 text-white font-bold py-4 rounded-2xl hover:bg-slate-800 transition-all">Entendido</button>
    </div>
</div>

<script>
    let statusInterval;

    function openModal(id, keepOthers = false) {
        if (!keepOthers && document.querySelectorAll('.modal-overlay')) {
            document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
        }
        const modal = document.getElementById(id);
        if (modal) modal.classList.add('active');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove('active');
        if (statusInterval) clearInterval(statusInterval);
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

    function prepareDelete(id, name) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteName').textContent = name;
        document.getElementById('checkDelete').checked = false;
        openModal('modalDeleteCoParent', true);
    }

    async function reactivateParent(id) {
        const fd = new FormData();
        fd.append('coparent_id', id);
        fd.append('student_id', '<?= $selectedId ?>');
        fd.append('action', 'reactivate');

        try {
            const res = await fetch('../../api/toggle_coparent.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) location.reload();
        } catch (e) {}
    }

    // --- NOVA FUNÇÃO 1: Carrega Co-Parents existentes ---
    async function loadExistingCoparents() {
        const container = document.getElementById('existingCoparentsList');
        const itemsDiv = document.getElementById('existingItems');
        itemsDiv.innerHTML = '<p class="text-xs text-slate-400 italic py-2">Carregando...</p>';
        container.classList.remove('hidden');

        try {
            const res = await fetch('../../api/get_my_coparents.php');
            const result = await res.json();
            
            if (result.success && result.data.length > 0) {
                itemsDiv.innerHTML = '';
                // Filtra para não mostrar os que já estão vinculados a ESTE aluno específico
                const currentEmails = Array.from(document.querySelectorAll('.coparent-item p.text-xs')).map(p => p.textContent.trim());
                
                const available = result.data.filter(p => !currentEmails.includes(p.email));

                if (available.length === 0) {
                    itemsDiv.innerHTML = '<p class="text-xs text-slate-400 italic py-2">Todos já vinculados.</p>';
                    return;
                }

                available.forEach(p => {
                    const item = document.createElement('div');
                    item.className = "flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100 hover:border-emerald-300 cursor-pointer transition-all group";
                    item.innerHTML = `
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-black text-slate-700 truncate italic">${p.name}</p>
                            <p class="text-[10px] text-slate-400 font-medium">${p.email}</p>
                        </div>
                        <button class="quick-link-btn text-emerald-500 p-1 hover:bg-emerald-50 rounded-lg"><i data-lucide="link" class="w-4 h-4"></i></button>
                    `;
                    item.onclick = () => quickLinkParent(p.id, item.querySelector('.quick-link-btn'));
                    itemsDiv.appendChild(item);
                });
                lucide.createIcons();
            } else {
                itemsDiv.innerHTML = '<p class="text-xs text-slate-400 italic py-2">Nenhum outro responsável encontrado.</p>';
            }
        } catch (e) {
            itemsDiv.innerHTML = '<p class="text-xs text-red-400 italic py-2">Erro ao carregar lista.</p>';
        }
    }

    // --- NOVA FUNÇÃO 2: Vínculo Rápido ---
    async function quickLinkParent(pid, btn) {
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<span class="animate-spin inline-block w-3 h-3 border-2 border-emerald-500 border-t-transparent rounded-full"></span>';
        
        const fd = new FormData();
        fd.append('student_id', '<?= $selectedId ?>');
        fd.append('parent_id', pid);
        fd.append('action', 'link_existing'); // Certifique-se que o toggle_coparent.php suporta essa action ou adapte a lógica

        try {
            // Nota: Se sua API toggle_coparent.php espera "add" ou outra action, ajuste aqui.
            // Vou assumir que você adicionará 'link_existing' ou usará a lógica de adição padrão.
            const res = await fetch('../../api/toggle_coparent.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>';
                lucide.createIcons();
                setTimeout(() => location.reload(), 800);
            } else {
                btn.innerHTML = oldHtml;
                alert(data.message); // Fallback simples para erro específico de link
            }
        } catch (e) { 
            btn.innerHTML = oldHtml; 
        }
    }

    async function handleFormSubmit(event, apiPath, modalId) {
        event.preventDefault();
        const form = event.target;
        const btn = form.querySelector('.submit-btn');
        const originalText = btn.textContent;
        const originalBg = btn.classList.contains('bg-red-500') ? 'bg-red-500' : 'bg-[#10b981]';
        
        btn.disabled = true;
        btn.textContent = 'Salvando...';
        
        try {
            const formData = new FormData(form);
            const response = await fetch(`../../api/${apiPath}`, { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                btn.classList.replace(originalBg, 'bg-emerald-700');
                btn.textContent = 'Sucesso!';
                setTimeout(() => { location.reload(); }, 1000);
            } else {
                btn.classList.replace(originalBg, 'bg-red-500');
                btn.textContent = result.message;
                setTimeout(() => {
                    btn.classList.replace('bg-red-500', originalBg);
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 4000);
            }
        } catch (e) {
            btn.classList.replace(originalBg, 'bg-red-500');
            btn.textContent = 'Erro de Conexão';
            setTimeout(() => {
                btn.classList.replace('bg-red-500', originalBg);
                btn.textContent = originalText;
                btn.disabled = false;
            }, 3000);
        }
    }

    async function generatePix() {
        const val = document.getElementById('pixAmount').value;
        const btn = document.querySelector('#stepAmount button');
        const oldText = btn.textContent;
        
        if (!val || val <= 0) {
            btn.classList.replace('bg-[#10b981]', 'bg-red-500');
            btn.textContent = 'Digite um valor válido';
            setTimeout(() => {
                btn.classList.replace('bg-red-500', 'bg-[#10b981]');
                btn.textContent = oldText;
            }, 2000);
            return;
        }

        btn.textContent = 'Gerando...';
        btn.disabled = true;

        try {
            const res = await fetch('../../api/recharge.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount: val, student_id: '<?= $selectedId ?>' })
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
                    new QRCode(container, {
                        text: data.copy_paste,
                        width: 190, height: 190,
                        colorDark: "#0f172a", colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.M
                    });
                }

                document.getElementById('copyPaste').value = data.copy_paste;
                startStatusPolling(data.external_reference);
            } else {
                btn.classList.replace('bg-[#10b981]', 'bg-red-500');
                btn.textContent = data.message;
                setTimeout(() => {
                    btn.classList.replace('bg-red-500', 'bg-[#10b981]');
                    btn.textContent = oldText;
                    btn.disabled = false;
                }, 3000);
            }
        } catch (e) {
            btn.textContent = 'Erro de conexão';
            setTimeout(() => { btn.textContent = oldText; btn.disabled = false; }, 3000);
        }
    }

    function startStatusPolling(ref) {
        if (!ref) return;
        statusInterval = setInterval(async () => {
            try {
                const res = await fetch('../../api/check_status.php?ref=' + ref);
                const data = await res.json();
                if (data.status === 'COMPLETED') {
                    clearInterval(statusInterval);
                    closeModal('modalInsertCredit'); 
                    openModal('modalSuccessPayment'); 
                }
            } catch (e) {}
        }, 3000);
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
        }
    }
    lucide.createIcons();
</script>
</body>
</html>