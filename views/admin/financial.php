<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManageFinancial');

// Filtro de Abas
$currentTab = $_GET['tab'] ?? 'PENDING'; // 'PENDING', 'HISTORY', 'REJECTED'

try {
    if ($currentTab === 'HISTORY') {
        // Aba Histórico: Mostra Concluídos e Estornados
        $sql = "SELECT t.*, s.name as student_name, s.avatar_url, n.tag_id as current_active_tag 
                FROM transactions t 
                JOIN students s ON t.student_id = s.id 
                LEFT JOIN nfc_tags n ON (n.current_student_id = s.id AND n.status = 'ACTIVE')
                WHERE t.type IN ('DEPOSIT', 'RECHARGE') 
                  AND t.status IN ('COMPLETED', 'REFUNDED')
                ORDER BY t.timestamp DESC LIMIT 50";
                
    } elseif ($currentTab === 'REJECTED') {
        // Aba Rejeitados: Mostra Cancelados
        $sql = "SELECT t.*, s.name as student_name, s.avatar_url, n.tag_id as current_active_tag 
                FROM transactions t 
                JOIN students s ON t.student_id = s.id 
                LEFT JOIN nfc_tags n ON (n.current_student_id = s.id AND n.status = 'ACTIVE')
                WHERE t.type IN ('DEPOSIT', 'RECHARGE') 
                  AND t.status = 'CANCELLED'
                ORDER BY t.timestamp DESC LIMIT 50";
                
    } else {
        // Aba Pendentes (Padrão)
        $sql = "SELECT t.*, s.name as student_name, s.avatar_url, n.tag_id as current_active_tag 
                FROM transactions t 
                JOIN students s ON t.student_id = s.id 
                LEFT JOIN nfc_tags n ON (n.current_student_id = s.id AND n.status = 'ACTIVE')
                WHERE t.type IN ('DEPOSIT', 'RECHARGE') 
                  AND t.status = 'PENDING'
                ORDER BY t.timestamp ASC";
    }
            
    $transactions = $pdo->query($sql)->fetchAll();

} catch (PDOException $e) {
    die("Erro ao carregar financeiro.");
}

// --- LÓGICA DE PERMISSÕES ---
$userLevel = $_SESSION['access_level'] ?? 'CASHIER';
$permsRaw  = $_SESSION['permissions'] ?? '{}';
$perms = json_decode($permsRaw, true);
if (!is_array($perms)) { $perms = []; }

function checkMobilePerm($key) {
    global $perms, $userLevel;
    if ($userLevel === 'ADMIN') return true; 
    return isset($perms[$key]) && $perms[$key] === true;
}

$currentPage = basename($_SERVER['PHP_SELF']);

require __DIR__ . '/../../includes/header.php';
?>

<a href="../../views/logout.php" class="md:hidden fixed top-3 right-4 z-[60] bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-md hover:bg-slate-800 transition-colors">
    Sair
</a>

<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50">
    
    <?php include __DIR__ . '/../../includes/top_header.php'; ?>

    <div class="md:hidden bg-white border-b border-slate-200 w-full overflow-x-auto scrollbar-hide z-10 shrink-0">
        <div class="flex items-center gap-2 p-3 whitespace-nowrap">
            <?php 
            function renderMobileLink($perm, $url, $label, $icon, $current) {
                if (!checkMobilePerm($perm)) return;
                $activeClass = $current == $url ? 'bg-emerald-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 border border-slate-100';
                echo "<a href='$url' class='px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2 $activeClass'>";
                echo "<i data-lucide='$icon' class='w-4 h-4'></i> $label";
                echo "</a>";
            }

            renderMobileLink('canViewDashboard', 'dashboard.php', 'Dashboard', 'layout-grid', $currentPage);
            renderMobileLink('canManageSettings', 'settings.php', 'Configurações', 'settings', $currentPage);
            renderMobileLink('canManageFinancial', 'financial.php', 'Financeiro', 'dollar-sign', $currentPage);
            renderMobileLink('canManageStudents', 'students.php', 'Alunos', 'graduation-cap', $currentPage);
            renderMobileLink('canManageParents', 'parents.php', 'Responsáveis', 'users', $currentPage);
            renderMobileLink('canManageTags', 'tags.php', 'Tags NFC', 'rss', $currentPage);
            renderMobileLink('canManageTeam', 'team.php', 'Equipe', 'shield-check', $currentPage);
            renderMobileLink('canViewLogs', 'logs.php', 'Auditoria', 'file-text', $currentPage);
            ?>
        </div>
    </div>

    <div class="flex flex-1 overflow-hidden">
        
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 lg:p-12 pb-48 md:pb-12">
            <div class="max-w-7xl mx-auto">
                
                <header class="mb-8 md:mb-10">
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800 flex items-center gap-3">
                        <i data-lucide="check-square" class="text-emerald-500"></i> Gestão Financeira
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm md:text-base">Gerencie depósitos, recargas Pix e estornos.</p>
                </header>

                <div class="flex gap-1 mb-8 border-b border-slate-200 overflow-x-auto scrollbar-hide">
                    <a href="?tab=PENDING" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all border-b-2 whitespace-nowrap <?= $currentTab === 'PENDING' ? 'border-emerald-500 text-emerald-600 bg-emerald-50/50' : 'border-transparent text-slate-400 hover:text-slate-600 hover:bg-slate-100' ?>">
                        Pendentes
                    </a>
                    <a href="?tab=HISTORY" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all border-b-2 whitespace-nowrap <?= $currentTab === 'HISTORY' ? 'border-emerald-500 text-emerald-600 bg-emerald-50/50' : 'border-transparent text-slate-400 hover:text-slate-600 hover:bg-slate-100' ?>">
                        Histórico Aprovado
                    </a>
                    <a href="?tab=REJECTED" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all border-b-2 whitespace-nowrap <?= $currentTab === 'REJECTED' ? 'border-red-500 text-red-600 bg-red-50/50' : 'border-transparent text-slate-400 hover:text-slate-600 hover:bg-slate-100' ?>">
                        Rejeitados / Cancelados
                    </a>
                </div>

                <?php if (empty($transactions)): ?>
                    <div class="bg-white rounded-[2rem] border border-slate-200 p-12 md:p-20 text-center shadow-sm">
                        <div class="w-16 h-16 md:w-20 md:h-20 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i data-lucide="<?= $currentTab === 'PENDING' ? 'check-circle' : ($currentTab === 'REJECTED' ? 'x-circle' : 'history') ?>" class="w-8 h-8 md:w-10 md:h-10"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">Nenhum registro</h3>
                        <p class="text-slate-400 mt-2 text-sm md:text-base">
                            <?php 
                                if($currentTab === 'PENDING') echo 'Não há solicitações pendentes no momento.';
                                elseif($currentTab === 'REJECTED') echo 'Nenhuma transação rejeitada recentemente.';
                                else echo 'Nenhuma transação no histórico recente.';
                            ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-4">
                        <?php foreach ($transactions as $tx): 
                            $isPix = $tx['payment_method'] === 'PIX' || $tx['type'] === 'RECHARGE';
                            $label = $isPix ? 'Pix' : 'Depósito';
                            $icon = $isPix ? 'qr-code' : 'banknote';
                            $badgeColor = $isPix ? 'bg-purple-50 text-purple-600 border-purple-100' : 'bg-blue-50 text-blue-600 border-blue-100';
                            $isRefunded = $tx['status'] === 'REFUNDED';
                            $isCancelled = $tx['status'] === 'CANCELLED';
                        ?>
                        <div class="bg-white rounded-3xl p-5 md:p-6 shadow-sm border border-slate-200 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-5 hover:shadow-md transition-all">
                            
                            <div class="flex items-center gap-4 w-full lg:w-auto">
                                <img src="<?= $tx['avatar_url'] ?: 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $tx['student_id'] ?>" class="w-12 h-12 md:w-14 md:h-14 rounded-full border-2 border-slate-100 shadow-sm shrink-0">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span class="text-[10px] px-2 py-0.5 rounded font-bold border uppercase flex items-center gap-1 <?= $badgeColor ?>">
                                            <i data-lucide="<?= $icon ?>" class="w-3 h-3"></i> <?= $label ?>
                                        </span>
                                        <span class="text-[10px] font-mono text-slate-400 truncate max-w-[120px]">Ref: <?= $tx['external_reference'] ?: '#' . $tx['id'] ?></span>
                                    </div>
                                    <h4 class="font-bold text-slate-800 text-base md:text-lg truncate leading-tight"><?= htmlspecialchars($tx['student_name']) ?></h4>
                                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                                        <span class="text-[10px] text-slate-400"><?= date('d/m/Y H:i', strtotime($tx['timestamp'])) ?></span>
                                        
                                        <?php if ($currentTab === 'HISTORY'): ?>
                                            <?php if ($isRefunded): ?>
                                                <span class="text-[10px] bg-orange-50 text-orange-600 border border-orange-100 px-2 py-0.5 rounded font-bold uppercase flex items-center gap-1">
                                                    <i data-lucide="rotate-ccw" class="w-3 h-3"></i> Estornado
                                                </span>
                                            <?php else: ?>
                                                <span class="text-[10px] bg-emerald-50 text-emerald-600 border border-emerald-100 px-2 py-0.5 rounded font-bold uppercase flex items-center gap-1">
                                                    <i data-lucide="check-circle" class="w-3 h-3"></i> Aprovado
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif ($currentTab === 'REJECTED'): ?>
                                            <span class="text-[10px] bg-red-50 text-red-600 border border-red-100 px-2 py-0.5 rounded font-bold uppercase flex items-center gap-1">
                                                <i data-lucide="x-circle" class="w-3 h-3"></i> Rejeitado
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between w-full lg:w-auto gap-4 sm:gap-8 mt-2 lg:mt-0 border-t lg:border-t-0 border-slate-50 pt-4 lg:pt-0">
                                
                                <div class="flex justify-between w-full sm:w-auto sm:block">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0 sm:mb-1 self-center sm:self-auto">Valor</p>
                                    <span class="text-2xl font-black <?= ($isRefunded || $isCancelled) ? 'text-slate-400 line-through decoration-red-500' : 'text-emerald-600' ?>">
                                        R$ <?= number_format($tx['amount'], 2, ',', '.') ?>
                                    </span>
                                </div>

                                <div class="flex gap-2 w-full sm:w-auto">
                                    <?php if ($currentTab === 'PENDING'): ?>
                                        <?php if ($tx['current_active_tag']): ?>
                                            <button onclick="openConfirmModal(<?= $tx['id'] ?>, 'APPROVE', '<?= number_format($tx['amount'], 2, ',', '.') ?>', '<?= htmlspecialchars($tx['student_name']) ?>')" 
                                                    class="flex-1 sm:flex-none bg-emerald-600 text-white px-4 py-3 md:px-6 md:py-3 rounded-xl font-bold hover:bg-emerald-700 shadow-lg shadow-emerald-100 transition-all active:scale-95 flex items-center justify-center gap-2 text-xs md:text-sm whitespace-nowrap">
                                                <i data-lucide="check" class="w-4 h-4"></i> Aprovar
                                            </button>
                                        <?php else: ?>
                                            <button disabled class="flex-1 sm:flex-none bg-slate-100 text-slate-400 px-4 py-3 md:px-6 md:py-3 rounded-xl font-bold cursor-not-allowed flex items-center justify-center gap-2 text-xs md:text-sm whitespace-nowrap" title="Aluno sem tag ativa">
                                                <i data-lucide="alert-circle" class="w-4 h-4"></i> Sem Tag
                                            </button>
                                        <?php endif; ?>

                                        <button onclick="openConfirmModal(<?= $tx['id'] ?>, 'REJECT', '<?= number_format($tx['amount'], 2, ',', '.') ?>', '<?= htmlspecialchars($tx['student_name']) ?>')" 
                                                class="flex-1 sm:flex-none bg-white text-red-500 border border-red-100 px-4 py-3 md:px-6 md:py-3 rounded-xl font-bold hover:bg-red-50 transition-all active:scale-95 text-xs md:text-sm whitespace-nowrap">
                                            Rejeitar
                                        </button>

                                    <?php elseif ($currentTab === 'HISTORY'): ?>
                                        <?php if (!$isRefunded): ?>
                                            <button onclick="openConfirmModal(<?= $tx['id'] ?>, 'REFUND', '<?= number_format($tx['amount'], 2, ',', '.') ?>', '<?= htmlspecialchars($tx['student_name']) ?>')" 
                                                    class="w-full sm:w-auto bg-slate-50 text-slate-500 border border-slate-200 px-6 py-3 rounded-xl font-bold hover:bg-red-50 hover:text-red-500 hover:border-red-200 transition-all active:scale-95 flex items-center justify-center gap-2 text-xs md:text-sm whitespace-nowrap" title="Estornar e retirar saldo">
                                                <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Estornar
                                            </button>
                                        <?php else: ?>
                                            <div class="w-full sm:w-auto px-6 py-3 rounded-xl border border-slate-100 bg-slate-50 text-slate-400 font-bold text-xs uppercase cursor-not-allowed opacity-60 text-center whitespace-nowrap">
                                                Já Estornado
                                            </div>
                                        <?php endif; ?>
                                    
                                    <?php else: ?>
                                        <div class="w-full sm:w-auto px-6 py-3 rounded-xl border border-red-100 bg-red-50 text-red-400 font-bold text-xs uppercase cursor-not-allowed text-center whitespace-nowrap">
                                            Cancelado
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 h-[70px] flex items-center justify-around z-50 shadow-[0_-5px_20px_rgba(0,0,0,0.05)] px-2">
        <a href="pos.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="credit-card" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Venda</span>
        </a>
        <a href="history.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="list" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Histórico</span>
        </a>
        <a href="products.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="package" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Produtos</span>
        </a>
        <a href="dashboard.php" class="flex flex-col items-center gap-1 p-2 text-emerald-600">
            <i data-lucide="settings" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Gestão</span>
        </a>
    </div>

</div>

<div id="modalConfirmAction" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center p-4 z-[70] backdrop-blur-sm">
    <div class="bg-white rounded-[2.5rem] w-full max-w-md p-8 text-center shadow-2xl animate-in zoom-in duration-200 relative">
        <button onclick="closeConfirmModal()" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button>
        
        <div id="confirmIconContainer" class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
            <i id="confirmIcon" data-lucide="alert-triangle" class="w-10 h-10"></i>
        </div>

        <h3 id="confirmTitle" class="text-2xl font-black text-slate-800 mb-2 italic">Confirmar Ação?</h3>
        <p id="confirmMsg" class="text-slate-500 text-sm leading-relaxed mb-6 px-4">Tem certeza?</p>

        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 mb-6 flex items-start gap-3 text-left transition-colors">
            <input type="checkbox" id="safetyCheck" onchange="toggleConfirmButton()" class="mt-1 w-5 h-5 accent-emerald-500 rounded cursor-pointer shrink-0">
            <label for="safetyCheck" class="text-xs font-bold text-slate-600 cursor-pointer select-none">
                Estou ciente de que esta ação impacta o financeiro e conferi os dados.
            </label>
        </div>

        <div class="flex gap-3">
            <button onclick="closeConfirmModal()" class="flex-1 py-4 font-bold text-slate-400 hover:text-slate-600 transition-colors">Cancelar</button>
            <button id="btnProceed" onclick="proceedFinancial()" disabled class="flex-1 bg-slate-200 text-slate-400 py-4 rounded-2xl font-black transition-all cursor-not-allowed uppercase text-xs tracking-widest">
                Confirmar
            </button>
        </div>
    </div>
</div>

<div id="modalFeedback" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center p-4 z-[80] backdrop-blur-sm">
    <div class="bg-white rounded-[2.5rem] w-full max-w-sm p-10 text-center shadow-2xl animate-in zoom-in duration-200">
        <div id="feedbackIconContainer" class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-8">
            <i id="feedbackIcon" data-lucide="check" class="w-10 h-10"></i>
        </div>
        <h3 id="feedbackTitle" class="text-2xl font-bold text-slate-800 mb-2"></h3>
        <p id="feedbackMsg" class="text-slate-500 text-sm leading-relaxed mb-10"></p>
        <button onclick="location.reload()" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-bold hover:bg-slate-800 transition-all">Entendido</button>
    </div>
</div>

<script>
    lucide.createIcons();
    let currentId = null;
    let currentAction = null;

    function openConfirmModal(id, action, amount, name) {
        currentId = id;
        currentAction = action;
        const modal = document.getElementById('modalConfirmAction');
        const iconContainer = document.getElementById('confirmIconContainer');
        const icon = document.getElementById('confirmIcon');
        const title = document.getElementById('confirmTitle');
        const msg = document.getElementById('confirmMsg');
        const btn = document.getElementById('btnProceed');
        const check = document.getElementById('safetyCheck');

        check.checked = false;
        btn.disabled = true;
        btn.className = "flex-1 bg-slate-200 text-slate-400 py-4 rounded-2xl font-black transition-all cursor-not-allowed uppercase text-xs tracking-widest";

        if (action === 'APPROVE') {
            title.innerText = "Aprovar Crédito?";
            msg.innerHTML = `Liberar <strong>R$ ${amount}</strong> para <strong>${name}</strong>?`;
            iconContainer.className = "w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6";
            icon.setAttribute('data-lucide', 'check-circle');
            btn.innerText = "Liberar Saldo";
            btn.dataset.activeClass = "bg-emerald-500 text-white hover:bg-emerald-600 shadow-lg shadow-emerald-200";
        } else if (action === 'REJECT') {
            title.innerText = "Rejeitar Solicitação?";
            msg.innerHTML = `Rejeitar a solicitação de R$ ${amount} de ${name}?`;
            iconContainer.className = "w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6";
            icon.setAttribute('data-lucide', 'x-circle');
            btn.innerText = "Rejeitar";
            btn.dataset.activeClass = "bg-red-500 text-white hover:bg-red-600 shadow-lg shadow-red-200";
        } else if (action === 'REFUND') {
            title.innerText = "Realizar Estorno?";
            msg.innerHTML = `Deseja <strong>RETIRAR R$ ${amount}</strong> do saldo de ${name}? <br><br><span class='text-red-500 font-bold'>Atenção: O saldo será descontado do cartão.</span>`;
            iconContainer.className = "w-20 h-20 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center mx-auto mb-6";
            icon.setAttribute('data-lucide', 'rotate-ccw');
            btn.innerText = "Confirmar Estorno";
            btn.dataset.activeClass = "bg-orange-500 text-white hover:bg-orange-600 shadow-lg shadow-orange-200";
        }

        lucide.createIcons();
        modal.classList.replace('hidden', 'flex');
    }

    function closeConfirmModal() { document.getElementById('modalConfirmAction').classList.replace('flex', 'hidden'); }

    function toggleConfirmButton() {
        const check = document.getElementById('safetyCheck');
        const btn = document.getElementById('btnProceed');
        if (check.checked) {
            btn.disabled = false;
            btn.className = "flex-1 py-4 rounded-2xl font-black transition-all uppercase text-xs tracking-widest cursor-pointer " + btn.dataset.activeClass;
        } else {
            btn.disabled = true;
            btn.className = "flex-1 bg-slate-200 text-slate-400 py-4 rounded-2xl font-black transition-all cursor-not-allowed uppercase text-xs tracking-widest";
        }
    }

    async function proceedFinancial() {
        if (!currentId || !currentAction) return;
        closeConfirmModal();
        try {
            const res = await fetch('../../api/financial_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: currentId, action: currentAction })
            });
            const result = await res.json();
            
            const modal = document.getElementById('modalFeedback');
            const iconContainer = document.getElementById('feedbackIconContainer');
            const title = document.getElementById('feedbackTitle');
            const msg = document.getElementById('feedbackMsg');

            if (result.success) {
                if (currentAction === 'APPROVE') {
                    title.innerText = "Sucesso!"; msg.innerText = "Saldo creditado.";
                    iconContainer.className = "w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-8";
                    document.getElementById('feedbackIcon').setAttribute('data-lucide', 'check');
                } else if (currentAction === 'REFUND') {
                    title.innerText = "Estorno Realizado"; msg.innerText = "Valor descontado do saldo.";
                    iconContainer.className = "w-20 h-20 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center mx-auto mb-8";
                    document.getElementById('feedbackIcon').setAttribute('data-lucide', 'rotate-ccw');
                } else {
                    title.innerText = "Rejeitado"; msg.innerText = "Solicitação cancelada.";
                    iconContainer.className = "w-20 h-20 bg-slate-50 text-slate-500 rounded-full flex items-center justify-center mx-auto mb-8";
                    document.getElementById('feedbackIcon').setAttribute('data-lucide', 'x');
                }
            } else {
                title.innerText = "Erro"; msg.innerText = result.message;
                iconContainer.className = "w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-8";
                document.getElementById('feedbackIcon').setAttribute('data-lucide', 'alert-circle');
            }
            modal.classList.replace('hidden', 'flex');
            lucide.createIcons();
        } catch (e) { alert("Erro de conexão."); }
    }
</script>