<?php
// views/admin/history.php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');

$search = $_GET['search'] ?? '';
$dateFilter = $_GET['date'] ?? date('Y-m-d'); 

try {
    // Configurações e Dados da Escola
    $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $schoolName = $settings['school_name'] ?? 'Escola Estadual Modelo';
    $schoolLogo = $settings['logo_url'] ?? '';

    // LEFT JOIN para permitir student_id NULL (Vendas em Dinheiro)
    $sql = "SELECT t.*, s.name as student_name, s.avatar_url 
            FROM transactions t 
            LEFT JOIN students s ON t.student_id = s.id 
            WHERE t.type = 'PURCHASE'";
    $params = [];

    if ($dateFilter) {
        $sql .= " AND DATE(t.timestamp) = ?";
        $params[] = $dateFilter;
    }

    if ($search) {
        $sql .= " AND (s.name LIKE ? OR t.id LIKE ? OR t.tag_id LIKE ? OR t.items_summary LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }

    $sql .= " ORDER BY t.timestamp DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erro ao carregar o histórico de transações.");
}

require __DIR__ . '/../../includes/header.php';
?>

<a href="../../views/logout.php" class="md:hidden fixed top-3 right-4 z-[60] bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-md hover:bg-slate-800 transition-colors">
    Sair
</a>

<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50 font-sans">
    
    <div class="bg-white border-b border-slate-100 px-4 md:px-8 py-3 flex items-center justify-between shadow-sm z-20 shrink-0 h-16 md:h-20">
        
        <div class="flex items-center gap-3">
            <?php if (!empty($schoolLogo)): ?>
                <img src="<?= htmlspecialchars($schoolLogo) ?>" alt="Logo" class="w-10 h-10 rounded-xl object-contain bg-white shrink-0 border border-slate-100 shadow-sm">
            <?php else: ?>
                <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center text-orange-600 shrink-0">
                    <i data-lucide="history" class="w-6 h-6"></i>
                </div>
            <?php endif; ?>

            <div>
                <h1 class="font-black text-slate-800 leading-tight text-sm md:text-base"><?= htmlspecialchars($schoolName) ?></h1>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Histórico</p>
            </div>
        </div>

        <div class="hidden md:flex bg-slate-100 p-1 rounded-xl items-center gap-1">
            <a href="pos.php" class="px-4 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-white/50 transition-all">PDV</a>
            <a href="history.php" class="px-4 py-2 rounded-lg text-sm font-bold bg-white text-emerald-600 shadow-sm transition-all">Histórico</a>
            <a href="products.php" class="px-4 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-white/50 transition-all">Catálogo</a>
            <a href="dashboard.php" class="px-4 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-white/50 transition-all">Gestão</a>
        </div>

        <div class="hidden md:flex items-center gap-4">
            <div class="text-right hidden lg:block">
                <p class="text-xs font-bold text-slate-800"><?= explode(' ', $_SESSION['name'] ?? 'Operador')[0] ?></p>
                <p class="text-[10px] text-emerald-500 font-bold uppercase">Online</p>
            </div>
            <a href="../../views/logout.php" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-800 transition-all shadow-lg shadow-slate-200">
                Sair
            </a>
        </div>
    </div>

    <main class="flex-1 overflow-y-auto p-4 md:p-8 lg:p-12 pb-[80px] md:pb-8">
        <div class="max-w-7xl mx-auto">
            
            <header class="hidden md:flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-10">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800 flex items-center gap-3">
                        <i data-lucide="history" class="text-emerald-500"></i> Histórico de Vendas
                    </h1>
                    <p class="text-slate-500 mt-1">Registros de consumo atualizados em tempo real conforme fuso horário local.</p>
                </div>
                <form class="flex bg-white border border-slate-200 rounded-2xl p-2 shadow-sm items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-slate-400 ml-2"></i>
                    <input type="date" name="date" value="<?= $dateFilter ?>" onchange="this.form.submit()" class="text-sm font-bold text-slate-700 outline-none bg-transparent cursor-pointer">
                </form>
            </header>

            <div class="md:hidden mb-6 space-y-4">
                <form class="flex bg-white border border-slate-200 rounded-xl p-3 shadow-sm items-center gap-3">
                    <i data-lucide="calendar" class="w-5 h-5 text-slate-400"></i>
                    <input type="date" name="date" value="<?= $dateFilter ?>" onchange="this.form.submit()" class="w-full text-sm font-bold text-slate-700 outline-none bg-transparent">
                </form>
                
                <form class="flex bg-white border border-slate-200 rounded-xl p-3 shadow-sm items-center gap-3">
                    <i data-lucide="search" class="w-5 h-5 text-slate-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar aluno, ID ou tag..." class="w-full text-sm font-bold text-slate-700 outline-none bg-transparent">
                </form>
            </div>

            <div class="bg-white rounded-2xl md:rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[800px] md:min-w-0">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                            <tr>
                                <th class="px-6 md:px-8 py-4 md:py-6 whitespace-nowrap">Horário</th>
                                <th class="px-6 md:px-8 py-4 md:py-6">Identificação</th>
                                <th class="px-6 md:px-8 py-4 md:py-6">Método</th>
                                <th class="px-6 md:px-8 py-4 md:py-6 text-right">Valor</th>
                                <th class="px-6 md:px-8 py-4 md:py-6 text-center">Status</th>
                                <th class="px-6 md:px-8 py-4 md:py-6 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($history as $venda): 
                                $isCancelled = ($venda['status'] === 'CANCELLED');
                                $isCash = ($venda['payment_method'] === 'CASH');
                                $rowStyle = $isCancelled ? 'opacity-30 grayscale bg-slate-50/50' : 'hover:bg-slate-50/80';
                                $textStyle = $isCancelled ? 'line-through text-slate-400' : 'text-slate-700';
                            ?>
                            <tr id="row-<?= $venda['id'] ?>" class="transition-all <?= $rowStyle ?>">
                                <td class="px-6 md:px-8 py-4 md:py-5 font-mono text-xs font-bold text-slate-400 whitespace-nowrap">
                                    <?= date('H:i', strtotime($venda['timestamp'])) ?>
                                </td>
                                <td class="px-6 md:px-8 py-4 md:py-5">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($venda['avatar_url'])): ?>
                                            <img src="<?= $venda['avatar_url'] ?>" class="w-8 h-8 md:w-10 md:h-10 rounded-full border border-slate-200 shadow-sm object-cover">
                                        <?php else: ?>
                                            <div class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 shadow-inner">
                                                <i data-lucide="user" class="w-4 h-4 md:w-5 md:h-5"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <p class="text-sm font-bold <?= $textStyle ?> leading-none mb-1 whitespace-nowrap">
                                                <?= htmlspecialchars($venda['student_name'] ?: 'Aluno') ?>
                                            </p>
                                            <span class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-tighter">
                                                Ref: <?= $venda['tag_id'] ?: 'DINHEIRO' ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 md:px-8 py-4 md:py-5">
                                    <?php if($isCash): ?>
                                        <div class="flex items-center gap-2 text-amber-600">
                                            <div class="p-1.5 bg-amber-50 rounded-lg border border-amber-100"><i data-lucide="banknote" class="w-4 h-4"></i></div>
                                            <span class="text-[10px] font-black uppercase whitespace-nowrap">Dinheiro</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2 text-blue-600">
                                            <div class="p-1.5 bg-blue-50 rounded-lg border border-blue-100"><i data-lucide="rss" class="w-4 h-4"></i></div>
                                            <span class="text-[10px] font-black uppercase whitespace-nowrap">NFC</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 md:px-8 py-4 md:py-5 text-right whitespace-nowrap">
                                    <span class="text-sm font-black <?= $textStyle ?>">
                                        R$ <?= number_format(abs($venda['amount']), 2, ',', '.') ?>
                                    </span>
                                </td>
                                <td class="px-6 md:px-8 py-4 md:py-5 text-center whitespace-nowrap">
                                    <?php if($isCancelled): ?>
                                        <span class="px-3 py-1 rounded-full text-[9px] font-black bg-slate-200 text-slate-500 border border-slate-300">ANULADA</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-[9px] font-black bg-emerald-50 text-emerald-600 border border-emerald-100">EFETIVADA</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 md:px-8 py-4 md:py-5 text-right">
                                    <button onclick="viewDetails(<?= $venda['id'] ?>, '<?= $venda['status'] ?>')" class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-xl transition-all">
                                        <i data-lucide="eye" class="w-5 h-5"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 h-[70px] flex items-center justify-around z-50 shadow-[0_-5px_20px_rgba(0,0,0,0.05)] px-2">
        <a href="pos.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="credit-card" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Venda</span>
        </a>
        <a href="history.php" class="flex flex-col items-center gap-1 p-2 text-emerald-600">
            <i data-lucide="list" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Histórico</span>
        </a>
        <a href="products.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="package" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Produtos</span>
        </a>
        <a href="dashboard.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="settings" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Gestão</span>
        </a>
    </div>

</div>

<div id="modalDetails" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center p-4 z-[60] backdrop-blur-sm animate-in fade-in duration-200">
    <div class="bg-white rounded-[2.5rem] w-full max-w-md shadow-2xl overflow-hidden scale-in-center">
        <div class="p-8 pb-4 flex justify-between items-center bg-white border-b border-slate-50">
            <div>
                <h3 class="text-2xl font-bold text-slate-800 tracking-tight">Cupom Digital</h3>
                <p id="detailSaleId" class="text-[10px] font-bold text-slate-400 uppercase mt-1"></p>
            </div>
            <button onclick="closeDetails()" class="w-10 h-10 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div id="modalMainContent" class="p-8 pt-6">
            <div id="modalAlert" class="hidden mb-6 p-4 rounded-2xl flex items-start gap-3 border shadow-sm animate-in slide-in-from-top-2">
                <i id="alertIcon" data-lucide="info" class="w-5 h-5 shrink-0"></i>
                <p id="alertMsg" class="text-xs font-bold leading-relaxed"></p>
            </div>

            <div id="itemsDisplayContainer">
                <div id="detailsList" class="space-y-3 mb-8 min-h-[120px]"></div>
                <div id="refundSection" class="pt-6 border-t border-slate-100">
                    <button id="btnStartRefund" onclick="toggleConfirmRefund(true)" class="w-full bg-red-50 text-red-600 py-5 rounded-[1.5rem] font-bold hover:bg-red-100 transition-all flex items-center justify-center gap-3">
                        <i data-lucide="rotate-ccw" class="w-5 h-5"></i> Cancelar esta Venda
                    </button>
                </div>
            </div>

            <div id="confirmRefundUI" class="hidden py-4 text-center">
                <div class="w-20 h-20 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="alert-triangle" class="w-10 h-10"></i>
                </div>
                <h4 class="text-xl font-bold text-slate-800 mb-2">Confirmar Cancelamento?</h4>
                <p class="text-slate-500 text-sm mb-8">Se for cartão, o dinheiro volta na hora. Se for dinheiro, o valor deve ser devolvido fisicamente.</p>
                <div class="flex gap-3">
                    <button onclick="toggleConfirmRefund(false)" class="flex-1 bg-slate-100 text-slate-500 py-5 rounded-2xl font-bold text-xs uppercase tracking-widest">Voltar</button>
                    <button id="btnConfirmRefund" onclick="processRefund()" class="flex-1 bg-red-600 text-white py-5 rounded-2xl font-bold text-xs uppercase shadow-lg shadow-red-200 hover:bg-red-700 transition-all">Sim, Anular</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    let currentViewingId = null;

    function showAlert(msg, type = 'error') {
        const box = document.getElementById('modalAlert');
        const alertMsg = document.getElementById('alertMsg');
        const icon = document.getElementById('alertIcon');
        const styles = {
            error: { bg: 'bg-red-50', border: 'border-red-100', text: 'text-red-700', icon: 'alert-circle' },
            success: { bg: 'bg-emerald-50', border: 'border-emerald-100', text: 'text-emerald-700', icon: 'check-circle' }
        };
        box.className = `mb-6 p-4 rounded-2xl flex items-start gap-3 border shadow-sm ${styles[type].bg} ${styles[type].border} ${styles[type].text}`;
        alertMsg.innerText = msg;
        icon.setAttribute('data-lucide', styles[type].icon);
        box.classList.remove('hidden');
        lucide.createIcons();
    }

    async function viewDetails(id, status) {
        currentViewingId = id;
        document.getElementById('modalAlert').classList.add('hidden');
        toggleConfirmRefund(false);
        document.getElementById('detailSaleId').innerText = "Transação #" + id;
        const list = document.getElementById('detailsList');
        list.innerHTML = '<div class="flex flex-col items-center justify-center py-10 opacity-30"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500 mb-3"></div><p class="text-[10px] font-black uppercase tracking-widest">Buscando Cupom...</p></div>';
        document.getElementById('modalDetails').classList.replace('hidden', 'flex');
        try {
            const res = await fetch(`../../api/get_sale_items.php?id=${id}`);
            const items = await res.json();
            if(!items || items.length === 0) {
                list.innerHTML = '<div class="text-center py-8 px-4 bg-slate-50 rounded-3xl border border-dashed border-slate-200"><p class="text-slate-400 text-sm italic font-medium">Venda registrada sem detalhamento.</p></div>';
            } else {
                list.innerHTML = items.map(item => `
                    <div class="flex justify-between items-center p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <div><p class="text-sm font-bold text-slate-800">${item.product_name}</p><p class="text-[10px] text-slate-400 font-black uppercase mt-0.5">${item.qty}x R$ ${parseFloat(item.unit_price).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p></div>
                        <span class="font-black text-slate-700 text-sm">R$ ${(item.qty * item.unit_price).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                    </div>`).join('');
            }
            document.getElementById('refundSection').classList.toggle('hidden', status === 'CANCELLED');
            lucide.createIcons();
        } catch (e) { showAlert("Ops! Não conseguimos carregar os detalhes."); }
    }

    function toggleConfirmRefund(show) {
        document.getElementById('itemsDisplayContainer').classList.toggle('hidden', show);
        document.getElementById('confirmRefundUI').classList.toggle('hidden', !show);
        document.getElementById('modalAlert').classList.add('hidden');
    }

    async function processRefund() {
        const btn = document.getElementById('btnConfirmRefund');
        btn.disabled = true; btn.innerText = "Cancelando...";
        try {
            const res = await fetch('../../api/refund_sale.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id: currentViewingId }) });
            const result = await res.json();
            if(result.success) { showAlert("Venda anulada! O saldo (se NFC) foi devolvido.", 'success'); setTimeout(() => location.reload(), 1500); }
            else { showAlert(result.message); btn.disabled = false; btn.innerText = "Sim, Anular"; }
        } catch(e) { showAlert("Problema de conexão."); btn.disabled = false; }
    }

    function closeDetails() { document.getElementById('modalDetails').classList.replace('flex', 'hidden'); }
</script>
</body>