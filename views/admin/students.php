<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManageStudents');

// --- PROCESSAMENTO AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    try {
        if ($action === 'edit') {
            $sql = "UPDATE students SET name = ?, email = ?, cpf = ?";
            $params = [$input['name'], $input['email'], $input['cpf']];

            if (!empty($input['avatar_seed'])) {
                $sql .= ", avatar_url = ?";
                $params[] = 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . urlencode($input['avatar_seed']);
            }

            if (!empty($input['pin'])) {
                $sql .= ", purchase_pin = ?";
                $params[] = password_hash($input['pin'], PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id = ?";
            $params[] = $input['id'];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

        } elseif ($action === 'nfc') {
            $studentId = $input['id'];
            $tagId = strtoupper(trim($input['tag_id']));
            $operatorId = $_SESSION['user_id'];
            $ip = $_SERVER['REMOTE_ADDR'];
            $now = date('Y-m-d H:i:s');
            
            $pdo->beginTransaction();

            if (!empty($tagId)) {
                // 1. Valida se a tag destino está SPARE (evitar "roubo")
                $stmtCheck = $pdo->prepare("SELECT status, balance FROM nfc_tags WHERE tag_id = ? FOR UPDATE");
                $stmtCheck->execute([$tagId]);
                $destTag = $stmtCheck->fetch();

                if (!$destTag) {
                    throw new Exception("Tag não encontrada no sistema.");
                }
                if ($destTag['status'] !== 'SPARE') {
                    throw new Exception("Esta tag já está em uso ou inativa.");
                }

                // 2. Busca a tag atual do aluno para transferir saldo
                $stmtOldTag = $pdo->prepare("SELECT tag_id, balance FROM nfc_tags WHERE current_student_id = ? AND status = 'ACTIVE' FOR UPDATE");
                $stmtOldTag->execute([$studentId]);
                $oldTag = $stmtOldTag->fetch();

                $transferAmount = 0;
                $oldTagId = null;

                if ($oldTag) {
                    $transferAmount = $oldTag['balance'];
                    $oldTagId = $oldTag['tag_id'];

                    // Zera e libera a tag antiga
                    $pdo->prepare("UPDATE nfc_tags SET current_student_id = NULL, status = 'SPARE', balance = 0 WHERE tag_id = ?")
                        ->execute([$oldTagId]);
                }

                // 3. Busca e zera o pending_balance
                $stmtPending = $pdo->prepare("SELECT pending_balance FROM students WHERE id = ? FOR UPDATE");
                $stmtPending->execute([$studentId]);
                $pendingBalance = $stmtPending->fetchColumn();

                if ($pendingBalance > 0) {
                    $transferAmount += $pendingBalance;
                    $pdo->prepare("UPDATE students SET pending_balance = 0 WHERE id = ?")->execute([$studentId]);
                }

                // 4. Vincula a nova tag e adiciona os saldos (da tag velha + pending)
                $pdo->prepare("UPDATE nfc_tags SET current_student_id = ?, status = 'ACTIVE', balance = balance + ? WHERE tag_id = ?")
                    ->execute([$studentId, $transferAmount, $tagId]);

                // 5. Gera Log se houve transferência de dinheiro
                if ($transferAmount > 0) {
                    $desc = "Transferência Automática de Saldo: R$ " . number_format($transferAmount, 2, ',', '.');
                    if ($oldTagId) $desc .= " da Tag antiga ($oldTagId)";
                    if ($pendingBalance > 0) $desc .= " (incluindo saldo Pix pendente)";
                    $desc .= " para a Tag nova ($tagId).";
                    
                    $impactJson = json_encode(['message' => "Transferência de R$ " . number_format($transferAmount, 2, ',', '.')]);
                    $pdo->prepare("INSERT INTO audit_logs (operator_id, action, description, ip_address, impact, timestamp) VALUES (?, 'BALANCE_TRANSFER', ?, ?, ?, ?)")
                        ->execute([$operatorId, $desc, $ip, $impactJson, $now]);
                }
            } else {
                // Remove a tag atual e envia saldo para pending_balance
                $stmtOldTag = $pdo->prepare("SELECT tag_id, balance FROM nfc_tags WHERE current_student_id = ? AND status = 'ACTIVE' FOR UPDATE");
                $stmtOldTag->execute([$studentId]);
                $oldTag = $stmtOldTag->fetch();

                if ($oldTag) {
                    $pdo->prepare("UPDATE students SET pending_balance = pending_balance + ? WHERE id = ?")->execute([$oldTag['balance'], $studentId]);
                    $pdo->prepare("UPDATE nfc_tags SET current_student_id = NULL, status = 'SPARE', balance = 0 WHERE tag_id = ?")->execute([$oldTag['tag_id']]);
                    
                    if ($oldTag['balance'] > 0) {
                        $desc = "Tag removida. Saldo de R$ " . number_format($oldTag['balance'], 2, ',', '.') . " guardado em Saldo Pendente.";
                        $impactJson = json_encode(['message' => "Guarda de Saldo"]);
                        $pdo->prepare("INSERT INTO audit_logs (operator_id, action, description, ip_address, impact, timestamp) VALUES (?, 'TAG_REMOVED', ?, ?, ?, ?)")
                            ->execute([$operatorId, $desc, $ip, $impactJson, $now]);
                    }
                }
            }
            $pdo->commit();
        } elseif ($action === 'deactivate') {
            $pdo->prepare("UPDATE students SET active = 0 WHERE id = ?")->execute([$input['id']]);
        } elseif ($action === 'activate') {
            $pdo->prepare("UPDATE students SET active = 1 WHERE id = ?")->execute([$input['id']]);
        } elseif ($action === 'link_parent') {
            $pdo->prepare("UPDATE students SET parent_id = ? WHERE id = ?")->execute([$input['parent_id'], $input['student_id']]);
        }
        echo json_encode(['success' => true]); exit;
    } catch (Exception $e) { 
        if($pdo->inTransaction()) $pdo->rollBack(); 
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit; 
    }
}

// --- BUSCA DE DADOS ---
$statusFilter = $_GET['status'] ?? 'ativos';
$search = $_GET['search'] ?? '';

// SQL ATUALIZADO: Busca has_pin para mostrar o cadeado na tela, e o pending_balance
$sql = "SELECT s.*, p.name as parent_name, n.tag_id, n.balance as tag_balance,
        (s.purchase_pin IS NOT NULL AND s.purchase_pin != '') as has_pin 
        FROM students s 
        LEFT JOIN parents p ON s.parent_id = p.id 
        LEFT JOIN nfc_tags n ON n.current_student_id = s.id 
        WHERE 1=1";
$params = [];

if ($statusFilter === 'ativos') $sql .= " AND s.active = 1";
elseif ($statusFilter === 'inativos') $sql .= " AND s.active = 0";

if ($search) { 
    $sql .= " AND (s.name LIKE ? OR s.email LIKE ? OR s.cpf LIKE ?)"; 
    $params = ["%$search%", "%$search%", "%$search%"]; 
}

$sql .= " ORDER BY s.name ASC";
$stmt = $pdo->prepare($sql); 
$stmt->execute($params); 
$students = $stmt->fetchAll();

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



<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50">
    
    <?php include __DIR__ . '/../../includes/top_header.php'; ?>

    <div class="flex flex-1 overflow-hidden">
        
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="flex-1 overflow-y-auto bg-slate-50 p-4 md:p-8 lg:p-12 pb-48 md:pb-12">
            <div class="max-w-7xl mx-auto">
                
                <header class="mb-8 md:mb-10">
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Gestão de Alunos</h1>
                    <p class="text-slate-500 mt-1 text-sm md:text-base">Administre cadastros e vínculos de cartões NFC (Saldos centralizados na Tag).</p>
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
                                    <th class="px-6 md:px-8 py-4">Estudante</th>
                                    <th class="px-6 md:px-8 py-4 text-center">Cartão / Saldo</th>
                                    <th class="px-6 md:px-8 py-4 text-center">Responsável</th>
                                    <th class="px-6 md:px-8 py-4 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($students as $s): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 md:px-8 py-4">
                                        <div class="flex items-center gap-4">
                                            <img src="<?= $s['avatar_url'] ?>" class="w-10 h-10 rounded-full bg-slate-100 border-2 border-white shadow-sm shrink-0">
                                            <div>
                                                <p class="font-bold text-slate-800 text-sm whitespace-nowrap flex items-center gap-2">
                                                    <?= htmlspecialchars($s['name']) ?>
                                                    <?php if($s['has_pin']): ?>
                                                        <i data-lucide="lock" class="w-3 h-3 text-emerald-500" title="Senha de Compra Ativa"></i>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-xs text-slate-400"><?= htmlspecialchars($s['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 md:px-8 py-4 text-center">
                                        <?php if($s['tag_id']): ?>
                                            <div class="flex flex-col items-center gap-1">
                                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 text-blue-600 border border-blue-100 uppercase flex items-center gap-1.5 w-fit whitespace-nowrap">
                                                    <i data-lucide="rss" class="w-3 h-3"></i> <?= $s['tag_id'] ?>
                                                </span>
                                                <span class="text-sm font-black text-emerald-600 whitespace-nowrap">
                                                    R$ <?= number_format($s['tag_balance'] ?? 0, 2, ',', '.') ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center gap-1">
                                                <button onclick='openNfcModal(<?= json_encode($s) ?>)' class="mx-auto px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 uppercase flex items-center gap-1.5 hover:bg-slate-200 transition-all border border-slate-200 w-fit whitespace-nowrap">
                                                    <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i> Sem Tag
                                                </button>
                                                <?php if($s['pending_balance'] > 0): ?>
                                                    <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 rounded-md border border-amber-100 whitespace-nowrap">
                                                        Pendente: R$ <?= number_format($s['pending_balance'], 2, ',', '.') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 md:px-8 py-4 text-center">
                                        <?php if($s['parent_id']): ?>
                                            <div class="flex flex-col items-center">
                                                <span class="text-sm font-bold text-slate-700 whitespace-nowrap"><?= htmlspecialchars($s['parent_name']) ?></span>
                                                <button onclick='openLinkModal(<?= json_encode($s) ?>)' class="text-[10px] text-emerald-600 font-bold hover:underline uppercase tracking-tighter whitespace-nowrap">Alterar Vínculo</button>
                                            </div>
                                        <?php else: ?>
                                            <button onclick='openLinkModal(<?= json_encode($s) ?>)' class="mx-auto flex items-center gap-2 px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-100 rounded-lg text-[10px] font-bold uppercase hover:bg-amber-100 transition-all whitespace-nowrap">
                                                <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Vincular Pai
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 md:px-8 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button onclick='openEditModal(<?= json_encode($s) ?>)' class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-all" title="Editar Dados">
                                                <i data-lucide="edit-3" class="w-5 h-5"></i>
                                            </button>
                                            <button onclick='openNfcModal(<?= json_encode($s) ?>)' class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Gerenciar NFC">
                                                <i data-lucide="scan" class="w-5 h-5"></i>
                                            </button>
                                            <?php if($s['active']): ?>
                                                <button onclick='openSafetyModal("deactivate", <?= json_encode($s) ?>)' class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Desativar Aluno">
                                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                                </button>
                                            <?php else: ?>
                                                <button onclick='openSafetyModal("activate", <?= json_encode($s) ?>)' class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all" title="Reativar Aluno">
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
    <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-xl font-bold text-slate-800 flex items-center gap-2"><i data-lucide="user" class="text-emerald-600"></i> Detalhes do Aluno</h3>
            <button onclick="closeModals()" class="text-slate-400 hover:text-slate-600 transition-colors"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <form onsubmit="event.preventDefault(); handleAction('edit', {id: currentStudentId, name: document.getElementById('editName').value, email: document.getElementById('editEmail').value, cpf: document.getElementById('editCpf').value, pin: document.getElementById('editPin').value, avatar_seed: document.getElementById('editAvatarSeed').value}, this.querySelector('button[type=submit]'))" class="p-8 space-y-6">
            <div class="bg-slate-50 p-5 rounded-2xl flex items-center gap-5 border border-slate-100">
                <img id="editAvatar" src="" class="w-16 h-16 rounded-full border-4 border-white shadow-md bg-white shrink-0">
                <div class="flex-1">
                    <p id="editNameHeader" class="font-bold text-slate-800 text-lg leading-tight"></p>
                    <p id="editEmailHeader" class="text-sm text-slate-500 font-medium mb-3"></p>
                    
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Mudar Avatar (Deslize no mapa)</label>
                    <div id="editAvatarMap" class="relative w-full h-10 rounded-xl cursor-crosshair overflow-hidden shadow-inner touch-none" style="background: conic-gradient(from 180deg at 50% 50%, #ff0000, #ff8000, #ffff00, #00ff00, #00ffff, #0000ff, #8000ff, #ff00ff, #ff0000);">
                        <div class="absolute inset-0" style="background: linear-gradient(to bottom, rgba(255,255,255,1), rgba(255,255,255,0) 50%, rgba(0,0,0,0) 50%, rgba(0,0,0,1));"></div>
                        <div id="editAvatarPointer" class="absolute w-4 h-4 bg-white border-2 border-slate-800 rounded-full shadow-md pointer-events-none -translate-x-1/2 -translate-y-1/2" style="top: 50%; left: 50%;"></div>
                    </div>
                    <input type="hidden" id="editAvatarSeed" value="">
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Nome Completo</label>
                    <input type="text" id="editName" class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all font-bold">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">E-mail Institucional</label>
                    <input type="email" id="editEmail" class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all font-bold">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Número do CPF</label>
                        <input type="text" id="editCpf" class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-red-400 uppercase tracking-widest mb-2 ml-1 flex items-center gap-1"><i data-lucide="lock" class="w-3 h-3"></i> Senha de Compra (PIN)</label>
                        <input type="password" id="editPin" maxlength="6" inputmode="numeric" placeholder="Manter Atual" class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-red-500/10 focus:border-red-500 transition-all font-bold text-center tracking-widest placeholder:tracking-normal placeholder:font-medium placeholder:text-slate-300">
                    </div>
                </div>
            </div>
            <div class="flex justify-between items-center pt-4">
                <button type="button" onclick="closeModals()" class="text-slate-500 font-bold hover:text-slate-700 px-6 uppercase text-xs tracking-widest transition-colors">Descartar</button>
                <button type="submit" class="bg-emerald-600 text-white px-10 py-4 rounded-2xl font-bold hover:bg-emerald-700 shadow-xl shadow-emerald-100 transition-all active:scale-95">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<div id="modalNfc" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] w-full max-w-sm shadow-2xl p-8 text-center animate-in fade-in zoom-in duration-200">
        <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center justify-center gap-2"><i data-lucide="scan" class="text-emerald-600"></i> Atribuir NFC</h3>
        <div class="bg-slate-50 p-5 rounded-2xl flex items-center gap-4 mb-8 text-left border border-slate-100">
            <img id="nfcAvatar" src="" class="w-12 h-12 rounded-full border-2 border-white shadow-sm">
            <div>
                <p id="nfcNameHeader" class="font-bold text-slate-800 text-sm leading-none mb-1"></p>
                <p id="nfcEmailHeader" class="text-[10px] text-slate-400 font-medium"></p>
            </div>
        </div>
        <div class="relative mb-6 text-left">
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Buscar Tag Disponível</label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input type="text" id="nfcSearch" placeholder="UID ou Apelido da Tag..." onkeyup="searchTags(this.value)" class="w-full pl-10 pr-4 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 font-bold transition-all">
            </div>
            <div id="nfcResults" class="max-h-40 overflow-y-auto divide-y border rounded-2xl hidden bg-white shadow-2xl mt-2 absolute w-full z-10"></div>
        </div>
        <p id="selectedTagDisplay" class="text-center font-bold text-emerald-600 hidden bg-emerald-50 p-4 rounded-2xl mb-6 border border-emerald-100"></p>
        <button id="btnSaveNfc" disabled class="w-full bg-emerald-600 text-white py-4 rounded-2xl font-bold transition-all shadow-xl shadow-emerald-100 cursor-not-allowed opacity-50 active:scale-95">Vincular Cartão</button>
        <button onclick="closeModals()" class="w-full py-2 mt-4 text-slate-400 font-bold uppercase text-[10px] tracking-widest hover:text-slate-600 transition-colors">Cancelar</button>
    </div>
</div>

<div id="modalLinkParent" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-xl font-bold text-slate-800">Vincular Responsável</h3>
            <button onclick="closeModals()" class="text-slate-400 hover:text-slate-600 transition-colors"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <div class="p-8 space-y-8">
            <div class="bg-slate-50 p-5 rounded-2xl flex items-center gap-4 border border-slate-100">
                <img id="linkStudentAvatar" src="" class="w-12 h-12 rounded-full border-2 border-white shadow-md">
                <div>
                    <p id="linkStudentName" class="font-bold text-slate-800 text-sm"></p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Selecione o responsável financeiro</p>
                </div>
            </div>
            <div class="relative">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Procurar por Nome ou CPF</label>
                <div class="relative">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="parentSearch" placeholder="Ex: José da Silva..." onkeyup="searchParents(this.value)" class="w-full pl-12 pr-4 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all font-bold">
                </div>
                <div id="parentResults" class="max-h-48 overflow-y-auto divide-y border rounded-2xl hidden bg-white shadow-2xl mt-2 absolute w-full z-10"></div>
            </div>
            <p id="selectedParentName" class="text-center font-bold text-emerald-600 hidden bg-emerald-50 p-4 rounded-2xl border border-emerald-100"></p>
            <button id="btnConfirmLink" disabled class="w-full bg-slate-100 text-slate-400 py-5 rounded-2xl font-bold transition-all cursor-not-allowed uppercase text-xs tracking-widest">Confirmar Vínculo</button>
        </div>
    </div>
</div>

<div id="modalSafety" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] w-full max-w-sm shadow-2xl overflow-hidden p-10 text-center animate-in fade-in zoom-in duration-200">
        <div id="safetyIconContainer" class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner"><i id="safetyIcon" data-lucide="alert-circle" class="w-10 h-10"></i></div>
        <h3 id="safetyTitle" class="text-2xl font-bold text-slate-800 mb-3 tracking-tight"></h3>
        <p class="text-slate-500 mb-10 text-sm leading-relaxed">Você está prestes a <span id="safetyActionText" class="font-bold"></span> o cadastro de <span id="safetyName" class="font-bold text-slate-800"></span>.</p>
        <div class="bg-slate-50 p-5 rounded-2xl text-left flex gap-4 mb-10 border border-slate-100 shadow-inner">
            <input type="checkbox" id="safetyCheck" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-5 h-5 shrink-0">
            <label for="safetyCheck" id="safetyCheckLabel" class="text-xs text-slate-600 leading-relaxed font-medium cursor-pointer"></label>
        </div>
        <div class="flex flex-col gap-3">
            <button id="btnConfirmSafety" disabled class="w-full py-5 rounded-2xl font-bold transition-all cursor-not-allowed text-white shadow-xl">Confirmar Ação</button>
            <button onclick="closeModals()" class="w-full py-2 text-slate-400 font-bold uppercase text-[10px] tracking-widest hover:text-slate-700 transition-colors">Cancelar</button>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    let currentStudentId = null;
    let selectedParentId = null;
    let selectedTagId = null;

    function closeModals() {
        document.querySelectorAll('.fixed').forEach(m => { 
            // Ignora o menu mobile inferior (que não é modal)
            if(m.id && m.id.startsWith('modal')) {
                m.classList.add('hidden'); m.classList.remove('flex'); 
            }
        });
    }

    async function handleAction(action, data, btn) {
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = "Sincronizando...";
        try {
            const res = await fetch('students.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action, ...data })
            });
            const result = await res.json();
            if (result.success) {
                btn.innerText = "Concluído!";
                setTimeout(() => location.reload(), 600);
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

    function openEditModal(s) {
        currentStudentId = s.id;
        document.getElementById('editAvatar').src = s.avatar_url;
        document.getElementById('editNameHeader').innerText = s.name;
        document.getElementById('editEmailHeader').innerText = s.email;
        document.getElementById('editName').value = s.name;
        document.getElementById('editEmail').value = s.email;
        document.getElementById('editCpf').value = s.cpf || '';
        document.getElementById('editAvatarSeed').value = ''; // Reseta seeder de edição
        // Limpa o campo de senha para não mostrar hash
        document.getElementById('editPin').value = '';
        document.getElementById('modalEdit').classList.replace('hidden', 'flex');
    }

    async function searchTags(q) {
        const resDiv = document.getElementById('nfcResults');
        if (q.length < 1) { resDiv.classList.add('hidden'); return; }
        try {
            const res = await fetch(`../../api/search_available_tags.php?q=${q}`);
            const tags = await res.json();
            resDiv.innerHTML = '';
            if (tags.length > 0) {
                resDiv.classList.remove('hidden');
                tags.forEach(t => {
                    resDiv.innerHTML += `<div onclick="selectTag('${t.tag_id}', '${t.tag_alias}')" class="p-4 hover:bg-emerald-50 cursor-pointer flex flex-col text-left transition-colors border-b last:border-0"><span class="text-sm font-bold text-slate-800">${t.tag_alias}</span><span class="text-[10px] font-mono text-slate-500 uppercase tracking-widest">${t.tag_id}</span></div>`;
                });
            } else { resDiv.innerHTML = '<p class="p-4 text-xs text-slate-400">Nenhuma tag disponível encontrada.</p>'; resDiv.classList.remove('hidden'); }
        } catch (e) { console.error("Falha na busca de tags"); }
    }

    function selectTag(id, alias) {
        selectedTagId = id;
        document.getElementById('nfcResults').classList.add('hidden');
        document.getElementById('nfcSearch').value = '';
        const display = document.getElementById('selectedTagDisplay');
        display.innerText = `Tag Selecionada: ${alias}`;
        display.classList.remove('hidden');
        const btn = document.getElementById('btnSaveNfc');
        btn.disabled = false; btn.classList.remove('opacity-50', 'cursor-not-allowed');
        btn.onclick = () => handleAction('nfc', {id: currentStudentId, tag_id: selectedTagId}, btn);
    }

    function openNfcModal(s) {
        currentStudentId = s.id;
        selectedTagId = null;
        document.getElementById('nfcAvatar').src = s.avatar_url;
        document.getElementById('nfcNameHeader').innerText = s.name;
        document.getElementById('nfcEmailHeader').innerText = s.email;
        document.getElementById('nfcSearch').value = '';
        document.getElementById('nfcResults').classList.add('hidden');
        document.getElementById('selectedTagDisplay').classList.add('hidden');
        const btn = document.getElementById('btnSaveNfc');
        btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed');
        document.getElementById('modalNfc').classList.replace('hidden', 'flex');
    }

    async function searchParents(q) {
        const resDiv = document.getElementById('parentResults');
        if (q.length < 2) { resDiv.classList.add('hidden'); return; }
        try {
            const res = await fetch(`../../api/search_parents.php?q=${q}`);
            const parents = await res.json();
            resDiv.innerHTML = '';
            if (parents.length > 0) {
                resDiv.classList.remove('hidden');
                parents.forEach(p => {
                    resDiv.innerHTML += `<div onclick="selectParent(${p.id}, '${p.name}')" class="p-4 hover:bg-slate-50 cursor-pointer flex flex-col text-left transition-colors border-b last:border-0"><span class="text-sm font-bold text-slate-800">${p.name}</span><span class="text-[10px] text-slate-500 uppercase tracking-tighter font-medium">${p.cpf} | ${p.email}</span></div>`;
                });
            } else { resDiv.innerHTML = '<p class="p-4 text-xs text-slate-400">Nenhum responsável encontrado.</p>'; resDiv.classList.remove('hidden'); }
        } catch (e) { console.error("Falha na busca de responsáveis"); }
    }

    function selectParent(id, name) {
        selectedParentId = id;
        document.getElementById('parentResults').classList.add('hidden');
        const nameP = document.getElementById('selectedParentName');
        nameP.innerText = `Selecionado: ${name}`;
        nameP.classList.remove('hidden');
        const btn = document.getElementById('btnConfirmLink');
        btn.disabled = false;
        btn.classList.replace('bg-slate-100', 'bg-emerald-600');
        btn.classList.replace('text-slate-400', 'text-white');
        btn.classList.remove('cursor-not-allowed');
        btn.classList.add('shadow-xl', 'shadow-emerald-100');
        btn.onclick = () => handleAction('link_parent', {student_id: currentStudentId, parent_id: selectedParentId}, btn);
    }

    function openSafetyModal(type, s) {
        currentStudentId = s.id;
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
        nameText.innerText = s.name;

        if (type === 'deactivate') {
            title.innerText = "Desativar Aluno?";
            actionText.innerText = "desativar";
            
            // Aviso de devolução de saldo
            const totalBalance = (parseFloat(s.tag_balance) || 0) + (parseFloat(s.pending_balance) || 0);
            if (totalBalance > 0) {
                checkLabel.innerHTML = `<span class="text-red-600 font-bold block mb-1">ATENÇÃO! Aluno possui saldo ativo: R$ ${totalBalance.toFixed(2).replace('.', ',')}</span> O aluno não poderá realizar compras. A responsabilidade por devolver o saldo manualmente aos pais é da instituição. Confirmo que estou ciente.`;
                iconContainer.className = "w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner ring-4 ring-red-50";
            } else {
                checkLabel.innerText = "O aluno não poderá mais realizar compras. O cadastro ficará inativo e a tag será preservada.";
                iconContainer.className = "w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner";
            }
            
            btn.innerText = "Confirmar Desativação";
            btn.className = "w-full py-5 rounded-2xl font-bold transition-all cursor-not-allowed text-white bg-red-200";
        } else {
            title.innerText = "Reativar Aluno?";
            actionText.innerText = "reativar";
            checkLabel.innerText = "O aluno voltará a ter acesso total ao sistema, poderá realizar novas compras e usar o saldo de sua tag.";
            btn.innerText = "Confirmar Reativação";
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

        btn.onclick = () => handleAction(type, {id: s.id}, btn);
        modal.classList.replace('hidden', 'flex');
        lucide.createIcons();
    }

    function openLinkModal(s) {
        currentStudentId = s.id;
        document.getElementById('linkStudentAvatar').src = s.avatar_url;
        document.getElementById('linkStudentName').innerText = s.name;
        document.getElementById('parentSearch').value = '';
        document.getElementById('parentResults').classList.add('hidden');
        document.getElementById('selectedParentName').classList.add('hidden');
        const btn = document.getElementById('btnConfirmLink');
        btn.disabled = true;
        btn.classList.replace('bg-emerald-600', 'bg-slate-100');
        btn.classList.replace('text-white', 'text-slate-400');
        btn.classList.add('cursor-not-allowed');
        document.getElementById('modalLinkParent').classList.replace('hidden', 'flex');
    }
    // Lógica do Mapa Cartesiano do Avatar
    const editMap = document.getElementById('editAvatarMap');
    const editPointer = document.getElementById('editAvatarPointer');
    const editPreview = document.getElementById('editAvatar');
    const editSeedInput = document.getElementById('editAvatarSeed');
    
    let isDraggingAvatar = false;
    let avatarDebounceTimer;

    function updateEditAvatar(e) {
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

    editMap.addEventListener('mousedown', (e) => { isDraggingAvatar = true; updateEditAvatar(e); });
    window.addEventListener('mousemove', (e) => { if(isDraggingAvatar) updateEditAvatar(e); });
    window.addEventListener('mouseup', () => { isDraggingAvatar = false; });
    
    editMap.addEventListener('touchstart', (e) => { isDraggingAvatar = true; updateEditAvatar(e); }, {passive: false});
    window.addEventListener('touchmove', (e) => { if(isDraggingAvatar) { updateEditAvatar(e); e.preventDefault(); } }, {passive: false});
    window.addEventListener('touchend', () => { isDraggingAvatar = false; });
</script>
</body>
</html>