<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManageParents');

// --- PROCESSAMENTO AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        if ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE parents SET name = ?, email = ?, phone = ?, cpf = ? WHERE id = ?");
            $stmt->execute([$input['name'], $input['email'], $input['phone'], $input['cpf'], $input['id']]);
        } elseif ($action === 'deactivate') {
            $stmt = $pdo->prepare("UPDATE parents SET active = 0 WHERE id = ?");
            $stmt->execute([$input['id']]);
        } elseif ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE parents SET active = 1 WHERE id = ?");
            $stmt->execute([$input['id']]);
        } elseif ($action === 'unbind') {
            $studentId = $input['student_id'];
            $parentId = $input['parent_id'];
            $stmt = $pdo->prepare("SELECT parent_id FROM students WHERE id = ?");
            $stmt->execute([$studentId]);
            $mainParent = $stmt->fetchColumn();
            if ($mainParent == $parentId) {
                $pdo->prepare("UPDATE students SET parent_id = NULL WHERE id = ?")->execute([$studentId]);
            } else {
                $pdo->prepare("DELETE FROM student_co_parents WHERE student_id = ? AND parent_id = ?")->execute([$studentId, $parentId]);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$statusFilter = $_GET['status'] ?? 'ativos';
$search = $_GET['search'] ?? '';

$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM students s WHERE s.parent_id = p.id AND s.active = 1) + 
        (SELECT COUNT(*) FROM student_co_parents scp JOIN students s2 ON scp.student_id = s2.id WHERE scp.parent_id = p.id AND s2.active = 1) as children_count
        FROM parents p WHERE 1=1";
$params = [];

if ($statusFilter === 'ativos') $sql .= " AND p.active = 1";
elseif ($statusFilter === 'inativos') $sql .= " AND p.active = 0";

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.email LIKE ? OR p.cpf LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$sql .= " ORDER BY p.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parents = $stmt->fetchAll();

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
                
                <header class="mb-8 md:mb-10">
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Responsáveis Cadastrados</h1>
                    <p class="text-slate-500 mt-1 text-sm md:text-base">Gerencie os dados de contato e os vínculos familiares dos alunos.</p>
                </header>

                <div class="flex flex-col md:flex-row justify-between gap-4 mb-6">
                    <form class="relative flex-1 max-w-2xl">
                        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por nome, CPF ou e-mail..." class="w-full pl-12 pr-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-emerald-500 transition-all shadow-sm font-medium">
                    </form>
                    <div class="flex bg-white border border-slate-200 rounded-xl p-1 shadow-sm h-fit overflow-x-auto shrink-0">
                        <a href="?status=ativos" class="flex-1 text-center px-4 md:px-6 py-2 text-xs md:text-sm <?= $statusFilter === 'ativos' ? 'font-bold bg-emerald-50 text-emerald-700' : 'font-medium text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-all whitespace-nowrap">Ativos</a>
                        <a href="?status=inativos" class="flex-1 text-center px-4 md:px-6 py-2 text-xs md:text-sm <?= $statusFilter === 'inativos' ? 'font-bold bg-emerald-50 text-emerald-700' : 'font-medium text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-all whitespace-nowrap">Inativos</a>
                        <a href="?status=todos" class="flex-1 text-center px-4 md:px-6 py-2 text-xs md:text-sm <?= $statusFilter === 'todos' ? 'font-bold bg-emerald-50 text-emerald-700' : 'font-medium text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-all whitespace-nowrap">Todos</a>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[900px] md:min-w-0">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                <tr>
                                    <th class="px-6 md:px-8 py-4">Nome / Identificação</th>
                                    <th class="px-6 md:px-8 py-4">Contato</th>
                                    <th class="px-6 md:px-8 py-4 text-center">Dependentes</th>
                                    <th class="px-6 md:px-8 py-4 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($parents as $p): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 md:px-8 py-5">
                                        <p class="font-bold text-slate-800 text-sm whitespace-nowrap"><?= htmlspecialchars($p['name']) ?></p>
                                        <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider mt-0.5 whitespace-nowrap">CPF: <?= htmlspecialchars($p['cpf']) ?></p>
                                    </td>
                                    <td class="px-6 md:px-8 py-5">
                                        <p class="text-sm text-slate-600 whitespace-nowrap"><?= htmlspecialchars($p['email']) ?></p>
                                        <p class="text-xs text-slate-400 whitespace-nowrap"><?= htmlspecialchars($p['phone']) ?></p>
                                    </td>
                                    <td class="px-6 md:px-8 py-5 text-center">
                                        <span class="bg-blue-50 text-blue-600 text-[10px] px-2.5 py-1 rounded-full font-bold uppercase border border-blue-100 whitespace-nowrap">
                                            <?= $p['children_count'] ?> dependentes
                                        </span>
                                    </td>
                                    <td class="px-6 md:px-8 py-5 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button onclick='openEditModal(<?= json_encode($p) ?>)' class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-all" title="Editar Dados">
                                                <i data-lucide="edit-3" class="w-5 h-5"></i>
                                            </button>
                                            <?php if($p['active']): ?>
                                                <button onclick='openSafetyModal("deactivate", <?= json_encode($p) ?>)' class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Desativar">
                                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                                </button>
                                            <?php else: ?>
                                                <button onclick='openSafetyModal("activate", <?= json_encode($p) ?>)' class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all" title="Reativar">
                                                    <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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

<div id="modalEdit" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-xl font-bold text-slate-800">Editar Responsável</h3>
            <button onclick="closeModals()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <form id="formEdit" class="p-8 space-y-5">
            <input type="hidden" id="editId">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nome Completo</label>
                <input type="text" id="editName" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-emerald-500 transition-all font-bold">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">E-mail</label>
                <input type="email" id="editEmail" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-emerald-500 transition-all font-bold">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Telefone</label>
                    <input type="text" id="editPhone" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-emerald-500 transition-all font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CPF</label>
                    <input type="text" id="editCpf" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-emerald-500 transition-all font-bold">
                </div>
            </div>
            <div class="pt-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Dependentes Vinculados</label>
                <div id="childrenList" class="space-y-2 max-h-48 overflow-y-auto"></div>
            </div>
            <div class="flex justify-between items-center pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModals()" class="text-slate-500 font-bold px-6 hover:text-slate-700 uppercase text-xs tracking-widest">Cancelar</button>
                <button type="submit" id="btnSaveEdit" class="bg-emerald-600 text-white px-10 py-3 rounded-xl font-bold hover:bg-emerald-700 shadow-xl transition-all active:scale-95">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<div id="modalSafety" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-3xl w-full max-w-sm shadow-2xl overflow-hidden p-8 text-center animate-in fade-in zoom-in duration-200">
        <div id="safetyIconContainer" class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner"><i id="safetyIcon" data-lucide="alert-circle" class="w-8 h-8"></i></div>
        <h3 id="safetyTitle" class="text-2xl font-bold text-slate-800 mb-2"></h3>
        <p class="text-slate-500 mb-8 text-sm leading-relaxed">Você está prestes a <span id="safetyActionText" class="font-bold"></span> <span id="safetyName" class="font-bold text-slate-800"></span>.</p>
        <div class="bg-slate-50 p-4 rounded-xl text-left flex gap-3 mb-8 border border-slate-100 shadow-inner">
            <input type="checkbox" id="safetyCheck" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-5 h-5 shrink-0">
            <label for="safetyCheck" id="safetyCheckLabel" class="text-xs text-slate-600 leading-relaxed font-medium cursor-pointer"></label>
        </div>
        <div class="flex flex-col gap-2">
            <button id="btnConfirmSafety" disabled class="w-full py-4 rounded-xl font-bold transition-all cursor-not-allowed text-white shadow-xl">Confirmar</button>
            <button onclick="closeModals()" class="w-full py-2 text-slate-500 font-bold uppercase text-[10px] tracking-widest hover:text-slate-700 transition-colors">Cancelar</button>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    let currentId = null;

    function closeModals() {
        document.querySelectorAll('.fixed').forEach(m => { 
            // Ignora o menu mobile
            if (m.id && m.id.startsWith('modal')) {
                m.classList.add('hidden'); m.classList.remove('flex'); 
            }
        });
    }

    async function handleAction(action, data, btn) {
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = "Salvando...";
        try {
            const res = await fetch('parents.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action, ...data })
            });
            const result = await res.json();
            if (result.success) {
                btn.innerText = "Salvo!";
                setTimeout(() => location.reload(), 800);
            } else {
                alert("Erro: " + result.message);
                btn.innerText = originalText;
                btn.disabled = false;
            }
        } catch (e) {
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }

    async function openEditModal(p) {
        currentId = p.id;
        document.getElementById('editId').value = p.id;
        document.getElementById('editName').value = p.name;
        document.getElementById('editEmail').value = p.email;
        document.getElementById('editPhone').value = p.phone;
        document.getElementById('editCpf').value = p.cpf;
        const list = document.getElementById('childrenList');
        list.innerHTML = '<p class="text-xs text-slate-400">Carregando dependentes...</p>';
        try {
            const res = await fetch(`../../api/get_dependents.php?parent_id=${p.id}`);
            const students = await res.json();
            list.innerHTML = students.length === 0 ? '<p class="text-xs text-slate-400 italic">Nenhum dependente vinculado.</p>' : '';
            students.forEach(s => {
                list.innerHTML += `
                    <div class="flex items-center justify-between p-3 border rounded-xl bg-white shadow-sm border-slate-100 group">
                        <div class="flex items-center gap-3">
                            <img src="${s.avatar_url}" class="w-8 h-8 rounded-full border border-slate-100 shadow-sm">
                            <span class="text-sm font-bold text-slate-700">${s.name}</span>
                        </div>
                        <button type="button" onclick='openSafetyModal("unbind", {student_id: ${s.id}, student_name: "${s.name}", parent_id: ${p.id}})' 
                                class="p-2 text-slate-300 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all">
                            <i data-lucide="user-minus" class="w-5 h-5"></i>
                        </button>
                    </div>`;
            });
            lucide.createIcons();
        } catch (e) { list.innerHTML = '<p class="text-xs text-red-500">Erro ao carregar dependentes.</p>'; }
        document.getElementById('modalEdit').classList.replace('hidden', 'flex');
    }

    document.getElementById('formEdit').onsubmit = (e) => {
        e.preventDefault();
        handleAction('edit', {
            id: document.getElementById('editId').value,
            name: document.getElementById('editName').value,
            email: document.getElementById('editEmail').value,
            phone: document.getElementById('editPhone').value,
            cpf: document.getElementById('editCpf').value
        }, document.getElementById('btnSaveEdit'));
    };

    function openSafetyModal(type, data) {
        const modal = document.getElementById('modalSafety');
        const iconContainer = document.getElementById('safetyIconContainer');
        const title = document.getElementById('safetyTitle');
        const actionText = document.getElementById('safetyActionText');
        const nameText = document.getElementById('safetyName');
        const checkLabel = document.getElementById('safetyCheckLabel');
        const btn = document.getElementById('btnConfirmSafety');
        const check = document.getElementById('safetyCheck');

        check.checked = false;
        btn.disabled = true;

        if (type === 'unbind') {
            title.innerText = "Desvincular Dependente?";
            actionText.innerText = "desvincular";
            nameText.innerText = data.student_name;
            checkLabel.innerText = "Estou ciente que este responsável não terá mais acesso aos dados e saldo deste dependente.";
            btn.innerText = "Confirmar Desvínculo";
            iconContainer.className = "w-16 h-16 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-6";
            btn.className = "w-full bg-amber-200 text-white py-4 rounded-xl font-bold transition-all cursor-not-allowed";
            btn.onclick = () => handleAction('unbind', {student_id: data.student_id, parent_id: data.parent_id}, btn);
        } else {
            title.innerText = type === 'deactivate' ? "Desativar Responsável?" : "Reativar Responsável?";
            actionText.innerText = type === 'deactivate' ? "desativar" : "reativar";
            nameText.innerText = data.name;
            checkLabel.innerText = type === 'deactivate' ? "O responsável perderá o acesso imediato ao portal e aplicativos." : "O responsável voltará a ter acesso total ao sistema.";
            btn.innerText = type === 'deactivate' ? "Confirmar Desativação" : "Confirmar Reativação";
            const color = type === 'deactivate' ? 'red' : 'emerald';
            iconContainer.className = `w-16 h-16 bg-${color}-50 text-${color}-500 rounded-full flex items-center justify-center mx-auto mb-6`;
            btn.className = `w-full bg-${color}-200 text-white py-4 rounded-xl font-bold transition-all cursor-not-allowed text-white`;
            btn.onclick = () => handleAction(type, {id: data.id}, btn);
        }

        check.onchange = (e) => {
            btn.disabled = !e.target.checked;
            if (e.target.checked) {
                btn.classList.remove('cursor-not-allowed', 'bg-red-200', 'bg-emerald-200', 'bg-amber-200');
                if (type === 'unbind') btn.classList.add('bg-amber-600', 'shadow-xl');
                else btn.classList.add(type === 'deactivate' ? 'bg-red-500' : 'bg-emerald-600', 'shadow-xl');
            } else {
                btn.classList.add('cursor-not-allowed', type === 'unbind' ? 'bg-amber-200' : (type === 'deactivate' ? 'bg-red-200' : 'bg-emerald-200'));
                btn.classList.remove('bg-red-500', 'bg-emerald-600', 'bg-amber-600', 'shadow-xl');
            }
        };

        modal.classList.replace('hidden', 'flex');
        lucide.createIcons();
    }
</script>