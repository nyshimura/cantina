<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManageTags');

// --- PROCESSAMENTO AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        if ($action === 'add') {
            $tagId = strtoupper(trim($input['tag_id']));
            $alias = trim($input['tag_alias']);
            
            if(empty($tagId) || empty($alias)) throw new Exception("UID e Apelido são obrigatórios.");

            $stmt = $pdo->prepare("INSERT INTO nfc_tags (tag_id, tag_alias, status, balance) VALUES (?, ?, 'SPARE', 0.00)");
            $stmt->execute([$tagId, $alias]);
        } 
        elseif ($action === 'edit_alias') {
            $stmt = $pdo->prepare("UPDATE nfc_tags SET tag_alias = ? WHERE tag_id = ?");
            $stmt->execute([trim($input['tag_alias']), $input['tag_id']]);
        }
        elseif ($action === 'delete') {
            $tagId = $input['tag_id'];
            
            // REGRA: Validação de saldo na TAG antes de deletar
            $stmt = $pdo->prepare("SELECT balance FROM nfc_tags WHERE tag_id = ?");
            $stmt->execute([$tagId]);
            $balance = $stmt->fetchColumn();

            if ($balance > 0) {
                throw new Exception("Não é possível remover: Esta tag ainda possui R$ " . number_format($balance, 2, ',', '.') . " de saldo.");
            }
            $pdo->prepare("DELETE FROM nfc_tags WHERE tag_id = ?")->execute([$tagId]);
        }
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// --- BUSCA DE DADOS ---
$search = $_GET['search'] ?? '';
$sql = "SELECT t.*, s.name as student_name, p.name as owner_name 
        FROM nfc_tags t 
        LEFT JOIN students s ON t.current_student_id = s.id 
        LEFT JOIN parents p ON s.parent_id = p.id 
        WHERE (t.tag_id LIKE ? OR t.tag_alias LIKE ? OR s.name LIKE ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute(["%$search%", "%$search%", "%$search%"]);
$tags = $stmt->fetchAll();

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

        <main class="flex-1 overflow-y-auto p-4 md:p-8 lg:p-12 pb-48 md:pb-12">
            <div class="max-w-7xl mx-auto">
                
                <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8 md:mb-10">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Gestão de Tags NFC</h1>
                        <p class="text-slate-500 mt-1 text-sm md:text-base">O saldo agora é vinculado diretamente ao cartão físico.</p>
                    </div>
                    <button onclick="document.getElementById('modalAddTag').classList.replace('hidden', 'flex')" class="w-full md:w-auto bg-emerald-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-emerald-700 shadow-lg flex items-center justify-center gap-2 transition-all active:scale-95">
                        <i data-lucide="plus-circle" class="w-5 h-5"></i> Cadastrar Tag
                    </button>
                </header>

                <div class="mb-6">
                    <form class="relative w-full max-w-2xl">
                        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por UID, apelido ou nome do aluno..." class="w-full pl-12 pr-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-emerald-500 transition-all shadow-sm font-medium">
                    </form>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[800px] md:min-w-0">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                <tr>
                                    <th class="px-6 md:px-8 py-4">Identificador (UID)</th>
                                    <th class="px-6 md:px-8 py-4">Apelido / Status</th>
                                    <th class="px-6 md:px-8 py-4">Saldo Atual</th>
                                    <th class="px-6 md:px-8 py-4">Vínculo Atual</th>
                                    <th class="px-6 md:px-8 py-4 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if(empty($tags)): ?>
                                    <tr>
                                        <td colspan="5" class="px-8 py-20 text-center text-slate-400">Nenhuma tag cadastrada ou encontrada.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($tags as $t): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 md:px-8 py-5 font-mono text-xs font-bold text-slate-500 uppercase whitespace-nowrap">
                                            <?= htmlspecialchars($t['tag_id']) ?>
                                        </td>
                                        <td class="px-6 md:px-8 py-5">
                                            <p class="font-bold text-slate-800 whitespace-nowrap"><?= htmlspecialchars($t['tag_alias']) ?></p>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase border whitespace-nowrap <?= $t['status'] == 'ACTIVE' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-slate-100 text-slate-500 border-slate-200' ?>">
                                                <?= $t['status'] == 'ACTIVE' ? 'Em Uso' : 'Livre' ?>
                                            </span>
                                        </td>
                                        <td class="px-6 md:px-8 py-5">
                                            <span class="text-sm font-black text-emerald-600 whitespace-nowrap">
                                                R$ <?= number_format($t['balance'], 2, ',', '.') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 md:px-8 py-5">
                                            <?php if($t['student_name']): ?>
                                                <p class="text-sm font-bold text-slate-700 whitespace-nowrap"><?= htmlspecialchars($t['student_name']) ?></p>
                                                <p class="text-[10px] text-slate-400 uppercase font-bold tracking-tight whitespace-nowrap"><?= htmlspecialchars($t['owner_name']) ?></p>
                                            <?php else: ?>
                                                <span class="text-slate-300 italic text-xs whitespace-nowrap">Sem portador</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 md:px-8 py-5 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button onclick='openEditAliasModal(<?= json_encode($t) ?>)' class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Editar Apelido">
                                                    <i data-lucide="edit-3" class="w-5 h-5"></i>
                                                </button>
                                                <button onclick='openDeleteModal(<?= json_encode($t) ?>)' class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Excluir Tag">
                                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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

<div id="modalAddTag" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] w-full max-w-sm shadow-2xl p-8 animate-in fade-in zoom-in duration-200">
        <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2"><i data-lucide="rss" class="text-emerald-600"></i> Nova Tag NFC</h3>
        <form onsubmit="event.preventDefault(); handleTagAction('add', {tag_id: document.getElementById('newTagId').value, tag_alias: document.getElementById('newTagAlias').value}, this.querySelector('button'))" class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">UID da Tag</label>
                <input type="text" id="newTagId" required placeholder="Ex: 04:A1:B2:C3" class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 font-mono uppercase transition-all">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Apelido de Identificação</label>
                <input type="text" id="newTagAlias" required placeholder="Ex: Cartão Reserva 01" class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all">
            </div>
            <button type="submit" class="w-full bg-emerald-600 text-white py-4 rounded-2xl font-bold hover:bg-emerald-700 shadow-xl shadow-emerald-100 active:scale-95 mt-4">Finalizar Cadastro</button>
            <button type="button" onclick="closeModals()" class="w-full py-2 text-slate-400 font-bold uppercase text-[10px] tracking-widest hover:text-slate-600 transition-colors">Cancelar</button>
        </form>
    </div>
</div>

<div id="modalEditAlias" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] w-full max-w-sm p-8 shadow-2xl animate-in fade-in zoom-in duration-200">
        <h3 class="text-xl font-bold text-slate-800 mb-6">Editar Apelido</h3>
        <p class="text-[10px] text-slate-400 mb-4 uppercase font-bold tracking-widest">TAG UID: <span id="editAliasUid" class="text-slate-600 font-mono"></span></p>
        <input type="text" id="editAliasInput" class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 mb-6 font-bold transition-all">
        <button id="btnSaveAlias" class="w-full bg-emerald-600 text-white py-4 rounded-2xl font-bold hover:bg-emerald-700 shadow-xl shadow-emerald-100 active:scale-95">Salvar Alteração</button>
        <button onclick="closeModals()" class="w-full py-2 mt-4 text-slate-400 font-bold uppercase text-[10px] tracking-widest hover:text-slate-600 transition-colors">Cancelar</button>
    </div>
</div>

<div id="modalDelete" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] w-full max-w-sm shadow-2xl overflow-hidden p-10 text-center animate-in fade-in zoom-in duration-200">
        <div id="delIcon" class="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner"><i data-lucide="alert-circle" class="w-10 h-10"></i></div>
        <h3 class="text-2xl font-bold text-slate-800 mb-3 tracking-tight">Excluir Tag?</h3>
        <p class="text-slate-500 mb-10 text-sm leading-relaxed">Você está prestes a remover permanentemente a tag <span id="delTagName" class="font-bold text-slate-800"></span>.</p>
        
        <div id="delError" class="hidden bg-red-50 text-red-600 p-4 rounded-2xl text-xs font-bold mb-8 border border-red-100 text-left"></div>

        <div id="delConfirmSection">
            <div class="bg-slate-50 p-5 rounded-2xl text-left flex gap-4 mb-10 border border-slate-100 shadow-inner">
                <input type="checkbox" id="confirmDel" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-5 h-5 shrink-0">
                <label for="confirmDel" class="text-xs text-slate-600 leading-relaxed font-medium">Confirmo a exclusão e entendo que o saldo (se houver) deve ser zero.</label>
            </div>
            <button id="btnConfirmDel" disabled class="w-full py-5 rounded-2xl font-bold bg-red-100 text-white transition-all cursor-not-allowed shadow-xl">Confirmar Exclusão</button>
        </div>
        <button onclick="closeModals()" class="w-full py-2 mt-4 text-slate-400 font-bold uppercase text-[10px] tracking-widest hover:text-slate-700 transition-colors">Cancelar</button>
    </div>
</div>

<script>
    lucide.createIcons();
    function closeModals() { document.querySelectorAll('.fixed').forEach(m => m.classList.replace('flex', 'hidden')); }

    async function handleTagAction(action, data, btn, errorDiv = null) {
        const original = btn.innerText;
        btn.innerText = "Sincronizando..."; btn.disabled = true;
        if(errorDiv) errorDiv.classList.add('hidden');

        try {
            const res = await fetch('tags.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action, ...data }) 
            });
            const result = await res.json();
            
            if (result.success) { 
                btn.innerText = "Concluído!"; 
                setTimeout(() => location.reload(), 600); 
            } else {
                if(errorDiv) {
                    errorDiv.innerText = result.message;
                    errorDiv.classList.remove('hidden');
                } else { alert(result.message); }
                btn.innerText = original; btn.disabled = false;
            }
        } catch (e) { btn.innerText = original; btn.disabled = false; }
    }

    function openEditAliasModal(t) {
        document.getElementById('editAliasUid').innerText = t.tag_id;
        document.getElementById('editAliasInput').value = t.tag_alias;
        const btn = document.getElementById('btnSaveAlias');
        btn.onclick = () => handleTagAction('edit_alias', {tag_id: t.tag_id, tag_alias: document.getElementById('editAliasInput').value}, btn);
        document.getElementById('modalEditAlias').classList.replace('hidden', 'flex');
    }

    function openDeleteModal(t) {
        document.getElementById('delTagName').innerText = t.tag_alias || t.tag_id;
        document.getElementById('delError').classList.add('hidden');
        const btn = document.getElementById('btnConfirmDel');
        const check = document.getElementById('confirmDel');
        
        check.checked = false; 
        btn.disabled = true;
        btn.classList.add('bg-red-100', 'text-white', 'cursor-not-allowed');
        btn.classList.remove('bg-red-500', 'shadow-2xl');

        check.onchange = (e) => {
            btn.disabled = !e.target.checked;
            if(e.target.checked) {
                btn.classList.replace('bg-red-100', 'bg-red-500');
                btn.classList.remove('cursor-not-allowed');
                btn.classList.add('shadow-2xl');
            } else {
                btn.classList.replace('bg-red-500', 'bg-red-100');
                btn.classList.add('cursor-not-allowed');
                btn.classList.remove('shadow-2xl');
            }
        };
        
        btn.onclick = () => handleTagAction('delete', {tag_id: t.tag_id}, btn, document.getElementById('delError'));
        document.getElementById('modalDelete').classList.replace('hidden', 'flex');
    }
</script>