<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManageTeam');

// --- LÓGICA DE PROCESSAMENTO BACKEND ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO operators (name, email, password_hash, access_level, permissions) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$input['name'], $input['email'], password_hash('123', PASSWORD_DEFAULT), $input['role'], '{}']);
        } 
        elseif ($action === 'update_perms') {
            // Salva as permissões como JSON
            $stmt = $pdo->prepare("UPDATE operators SET permissions = ? WHERE id = ?");
            $stmt->execute([json_encode($input['perms']), $input['id']]);
        }
        elseif ($action === 'deactivate') {
            if ($input['id'] == $_SESSION['user_id']) throw new Exception("Não pode desativar a própria conta.");
            $pdo->prepare("UPDATE operators SET active = 0 WHERE id = ?")->execute([$input['id']]);
        }
        elseif ($action === 'activate') {
            $pdo->prepare("UPDATE operators SET active = 1 WHERE id = ?")->execute([$input['id']]);
        }

        echo json_encode(['success' => true]); exit;
    } catch (Exception $e) { 
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit; 
    }
}

$statusFilter = $_GET['status'] ?? 'ativos';
$sql = "SELECT * FROM operators WHERE 1=1";
if ($statusFilter === 'ativos') $sql .= " AND active = 1";
elseif ($statusFilter === 'inativos') $sql .= " AND active = 0";
$operators = $pdo->query($sql . " ORDER BY name ASC")->fetchAll();

// --- LÓGICA DE PERMISSÕES (Para o menu mobile) ---
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

        <main class="flex-1 overflow-y-auto bg-slate-50 p-4 md:p-8 lg:p-12 pb-48 md:pb-12">
            <div class="max-w-7xl mx-auto">
                
                <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8 md:mb-10">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Equipe de Operadores</h1>
                        <p class="text-slate-500 mt-1 text-sm md:text-base">Gerencie quem tem acesso ao painel e defina permissões específicas.</p>
                    </div>
                    <button onclick="document.getElementById('modalAdd').classList.replace('hidden', 'flex')" class="w-full md:w-auto bg-emerald-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-emerald-700 shadow-lg flex items-center justify-center gap-2 transition-all active:scale-95">
                        <i data-lucide="plus-circle" class="w-5 h-5"></i> Novo Operador
                    </button>
                </header>

                <div class="flex bg-white border border-slate-200 rounded-xl p-1 shadow-sm h-fit w-fit mb-8 overflow-x-auto shrink-0">
                    <a href="?status=ativos" class="px-6 py-2 text-sm <?= $statusFilter === 'ativos' ? 'font-bold bg-emerald-50 text-emerald-700' : 'font-medium text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-all whitespace-nowrap">Ativos</a>
                    <a href="?status=inativos" class="px-6 py-2 text-sm <?= $statusFilter === 'inativos' ? 'font-bold bg-emerald-50 text-emerald-700' : 'font-medium text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-all whitespace-nowrap">Inativos</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($operators as $op): ?>
                    <div onclick='openPermsModal(<?= json_encode($op) ?>)' class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 border-l-4 <?= $op['access_level'] === 'ADMIN' ? 'border-l-amber-500' : 'border-l-slate-400' ?> flex flex-col justify-between cursor-pointer hover:shadow-xl hover:-translate-y-1 transition-all group">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg group-hover:text-emerald-600 transition-colors leading-tight mb-1"><?= htmlspecialchars($op['name']) ?></h3>
                                <p class="text-sm text-slate-400 font-medium"><?= htmlspecialchars($op['email']) ?></p>
                                <p class="text-[10px] text-emerald-600 font-black mt-4 uppercase tracking-[0.15em] flex items-center gap-1">
                                    <i data-lucide="shield-check" class="w-3 h-3"></i> Configurar Permissões
                                </p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-tighter <?= $op['access_level'] === 'ADMIN' ? 'bg-amber-50 text-amber-600 border border-amber-100' : 'bg-slate-50 text-slate-500 border border-slate-200' ?>">
                                <?= $op['access_level'] ?>
                            </span>
                        </div>

                        <?php if($op['id'] != $_SESSION['user_id']): ?>
                        <div class="mt-6 pt-4 border-t border-slate-50 flex justify-end" onclick="event.stopPropagation()">
                            <button onclick='openSafetyModal("<?= $op['active'] ? 'deactivate' : 'activate' ?>", <?= json_encode($op) ?>)' class="<?= $op['active'] ? 'text-red-400 hover:text-red-600' : 'text-emerald-500 hover:text-emerald-700' ?> text-[10px] font-black uppercase tracking-widest transition-colors py-2 px-3 hover:bg-slate-50 rounded-lg">
                                <?= $op['active'] ? 'Desativar Conta' : 'Reativar Conta' ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
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

<div id="modalAdd" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-[2.5rem] w-full max-w-sm p-8 shadow-2xl animate-in fade-in zoom-in duration-200">
        <h3 class="text-2xl font-bold text-slate-800 mb-6 tracking-tight">Novo Membro</h3>
        <form onsubmit="event.preventDefault(); handleTeamAction('add', {name: document.getElementById('addName').value, email: document.getElementById('addEmail').value, role: document.getElementById('addRole').value}, this.querySelector('button'))" class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Nome Completo</label>
                <input type="text" id="addName" required class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all font-bold text-slate-700">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">E-mail de Acesso</label>
                <input type="email" id="addEmail" required class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all font-bold text-slate-700">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Nível Hierárquico</label>
                <select id="addRole" class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all bg-white font-bold text-slate-700">
                    <option value="CASHIER">CASHIER (Operador de Caixa)</option>
                    <option value="ADMIN">ADMIN (Acesso Total)</option>
                </select>
            </div>
            <p class="text-[10px] text-slate-400 italic px-1">Nota: A senha padrão inicial será '123'.</p>
            <button type="submit" class="w-full bg-emerald-600 text-white py-4 rounded-2xl font-bold hover:bg-emerald-700 shadow-xl shadow-emerald-100 mt-4 active:scale-95 transition-all">Cadastrar Operador</button>
            <button type="button" onclick="closeModals()" class="w-full py-2 text-slate-400 font-bold uppercase text-[10px] tracking-widest hover:text-slate-600 transition-colors">Cancelar</button>
        </form>
    </div>
</div>

<div id="modalPerms" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-[2.5rem] w-full max-w-md shadow-2xl p-8 animate-in fade-in zoom-in duration-200 max-h-[90vh] overflow-y-auto">
        <h3 class="text-2xl font-bold text-slate-800 mb-1 tracking-tight">Permissões de Acesso</h3>
        <p id="permOpName" class="text-sm text-emerald-600 mb-8 font-black uppercase tracking-widest"></p>
        
        <form id="formPerms" class="space-y-2">
            <?php 
            $labels = [
                'canViewDashboard' => 'Visualizar Dashboard',
                'canManageSettings' => 'Configurações do Sistema',
                'canManageFinancial' => 'Aprovações Financeiras',
                'canManageStudents' => 'Gestão de Alunos',
                'canManageParents' => 'Gestão de Responsáveis',
                'canManageTags' => 'Gestão de Tags NFC',
                'canManageTeam' => 'Gestão da Equipe',
                'canViewLogs' => 'Logs de Auditoria'
            ];
            foreach($labels as $key => $label): ?>
            <label class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 hover:bg-slate-50 cursor-pointer transition-all group">
                <span class="text-sm font-bold text-slate-600 group-hover:text-slate-800"><?= $label ?></span>
                <input type="checkbox" name="<?= $key ?>" class="w-5 h-5 rounded-md border-slate-300 text-emerald-600 focus:ring-emerald-500 transition-all">
            </label>
            <?php endforeach; ?>

            <div class="pt-6 flex flex-col gap-2">
                <button type="submit" id="btnSavePerms" class="w-full bg-emerald-600 text-white py-4 rounded-2xl font-bold hover:bg-emerald-700 shadow-xl shadow-emerald-100 transition-all active:scale-95">Atualizar Acessos</button>
                <button type="button" onclick="closeModals()" class="w-full py-2 text-slate-400 font-bold uppercase text-[10px] tracking-widest hover:text-slate-600 transition-colors">Fechar</button>
            </div>
        </form>
    </div>
</div>

<div id="modalSafety" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] w-full max-w-sm shadow-2xl p-10 text-center animate-in fade-in zoom-in duration-200">
        <div id="safetyIconContainer" class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner"><i id="safetyIcon" data-lucide="alert-circle" class="w-10 h-10"></i></div>
        <h3 id="safetyTitle" class="text-2xl font-bold text-slate-800 mb-3 tracking-tight"></h3>
        <p class="text-slate-500 mb-10 text-sm leading-relaxed">Você está prestes a <span id="safetyActionText" class="font-bold"></span> o acesso de <span id="safetyName" class="font-bold text-slate-800"></span>.</p>
        <div class="bg-slate-50 p-5 rounded-2xl text-left flex gap-4 mb-10 border border-slate-100">
            <input type="checkbox" id="safetyCheck" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-5 h-5 shrink-0">
            <label for="safetyCheck" id="safetyCheckLabel" class="text-xs text-slate-600 leading-relaxed font-medium">Confirmo que entendo as consequências desta alteração de status.</label>
        </div>
        <div class="flex flex-col gap-3">
            <button id="btnConfirmSafety" disabled class="w-full py-5 rounded-2xl font-bold transition-all cursor-not-allowed text-white shadow-xl">Confirmar Ação</button>
            <button onclick="closeModals()" class="w-full py-2 text-slate-400 font-bold uppercase text-[10px] tracking-widest hover:text-slate-700 transition-colors">Cancelar</button>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    let currentOpId = null;

    function closeModals() { 
        document.querySelectorAll('.fixed').forEach(m => {
            // Ignora o menu mobile inferior
            if (m.id && m.id.startsWith('modal')) {
                m.classList.replace('flex', 'hidden');
            }
        }); 
    }

    async function handleTeamAction(action, data, btn) {
        const original = btn.innerText; 
        btn.innerText = "Processando..."; 
        btn.disabled = true;
        try {
            const res = await fetch('team.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action, ...data }) 
            });
            const result = await res.json();
            if (result.success) { 
                btn.innerText = "Concluído!"; 
                setTimeout(() => location.reload(), 600); 
            } else { 
                alert(result.message); 
                btn.innerText = original; 
                btn.disabled = false; 
            }
        } catch (e) { 
            btn.innerText = original; 
            btn.disabled = false; 
        }
    }

    function openPermsModal(op) {
        currentOpId = op.id;
        document.getElementById('permOpName').innerText = op.name;
        const perms = JSON.parse(op.permissions || '{}');
        const form = document.getElementById('formPerms');
        
        form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.checked = perms[cb.name] === true;
        });

        document.getElementById('modalPerms').classList.replace('hidden', 'flex');
    }

    document.getElementById('formPerms').onsubmit = (e) => {
        e.preventDefault();
        const perms = {};
        new FormData(e.target).forEach((val, key) => perms[key] = true);
        handleTeamAction('update_perms', {id: currentOpId, perms}, document.getElementById('btnSavePerms'));
    };

    function openSafetyModal(type, op) {
        currentOpId = op.id;
        const modal = document.getElementById('modalSafety');
        const iconContainer = document.getElementById('safetyIconContainer');
        const title = document.getElementById('safetyTitle');
        const actionText = document.getElementById('safetyActionText');
        const nameText = document.getElementById('safetyName');
        const btn = document.getElementById('btnConfirmSafety');
        const check = document.getElementById('safetyCheck');

        check.checked = false;
        btn.disabled = true;
        nameText.innerText = op.name;

        if (type === 'deactivate') {
            title.innerText = "Suspender Acesso?";
            actionText.innerText = "desativar";
            iconContainer.className = "w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner";
            btn.className = "w-full py-5 rounded-2xl font-bold transition-all cursor-not-allowed text-white bg-red-200";
        } else {
            title.innerText = "Reativar Acesso?";
            actionText.innerText = "reativar";
            iconContainer.className = "w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner";
            btn.className = "w-full py-5 rounded-2xl font-bold transition-all cursor-not-allowed text-white bg-emerald-200";
        }

        check.onchange = (e) => {
            btn.disabled = !e.target.checked;
            if (e.target.checked) {
                btn.classList.remove('cursor-not-allowed', type === 'deactivate' ? 'bg-red-200' : 'bg-emerald-200');
                btn.classList.add(type === 'deactivate' ? 'bg-red-500' : 'bg-emerald-600', 'shadow-2xl');
            } else {
                btn.classList.add('cursor-not-allowed', type === 'deactivate' ? 'bg-red-200' : 'bg-emerald-200');
                btn.classList.remove(type === 'deactivate' ? 'bg-red-500' : 'bg-emerald-600', 'shadow-2xl');
            }
        };

        btn.onclick = () => handleTeamAction(type, {id: op.id}, btn);
        modal.classList.replace('hidden', 'flex');
        lucide.createIcons();
    }
</script>