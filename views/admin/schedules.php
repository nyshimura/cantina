<?php
// views/admin/schedules.php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManageSettings'); // Using this permission for now, or maybe canManagePreOrders later

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        if ($action === 'save_schedule') {
            if (!empty($input['id'])) {
                $pdo->prepare("UPDATE meal_schedules SET name=?, cutoff_time=? WHERE id=?")->execute([$input['name'], $input['cutoff_time'], $input['id']]);
            } else {
                $pdo->prepare("INSERT INTO meal_schedules (name, cutoff_time) VALUES (?, ?)")->execute([$input['name'], $input['cutoff_time']]);
            }
        } elseif ($action === 'delete_schedule') {
            $pdo->prepare("DELETE FROM meal_schedules WHERE id=?")->execute([$input['id']]);
        } elseif ($action === 'save_classroom') {
            $year = !empty($input['academic_year']) ? $input['academic_year'] : null;
            if (!empty($input['id'])) {
                $pdo->prepare("UPDATE classrooms SET name=?, meal_schedule_id=?, academic_year=? WHERE id=?")->execute([$input['name'], $input['meal_schedule_id'], $year, $input['id']]);
            } else {
                $pdo->prepare("INSERT INTO classrooms (name, meal_schedule_id, academic_year) VALUES (?, ?, ?)")->execute([$input['name'], $input['meal_schedule_id'], $year]);
            }
        } elseif ($action === 'delete_classroom') {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM students WHERE classroom_id = ? OR pending_classroom_id = ?");
            $stmtCheck->execute([$input['id'], $input['id']]);
            if ($stmtCheck->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Não é possível excluir esta turma, pois existem alunos nela.']);
                exit;
            }
            $pdo->prepare("DELETE FROM classrooms WHERE id=?")->execute([$input['id']]);
        } elseif ($action === 'approve_transfer') {
            $pdo->prepare("UPDATE students SET classroom_id = pending_classroom_id, pending_classroom_id = NULL WHERE id=?")->execute([$input['student_id']]);
        } elseif ($action === 'reject_transfer') {
            $pdo->prepare("UPDATE students SET pending_classroom_id = NULL WHERE id=?")->execute([$input['student_id']]);
        } elseif ($action === 'mass_delete_classrooms') {
            $ids = $input['ids'] ?? [];
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                // Verifica se há alunos nessas turmas
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM students WHERE classroom_id IN ($placeholders) OR pending_classroom_id IN ($placeholders)");
                $paramsCheck = array_merge($ids, $ids);
                $stmtCheck->execute($paramsCheck);
                
                if ($stmtCheck->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Não é possível excluir em massa, pois algumas das turmas selecionadas possuem alunos vinculados.']);
                    exit;
                }
                
                $pdo->prepare("DELETE FROM classrooms WHERE id IN ($placeholders)")->execute($ids);
            }
        } elseif ($action === 'mass_assign_schedule') {
            $ids = $input['ids'] ?? [];
            $scheduleId = $input['schedule_id'] ?? null;
            if (!empty($ids) && $scheduleId) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge([$scheduleId], $ids);
                $pdo->prepare("UPDATE classrooms SET meal_schedule_id = ? WHERE id IN ($placeholders)")->execute($params);
            }
        }
        echo json_encode(['success' => true]); exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit;
    }
}

// Data fetching
$schedules = $pdo->query("SELECT * FROM meal_schedules ORDER BY cutoff_time")->fetchAll();
$classrooms = $pdo->query("SELECT c.*, m.name as schedule_name, m.cutoff_time FROM classrooms c LEFT JOIN meal_schedules m ON c.meal_schedule_id = m.id ORDER BY c.name")->fetchAll();

$pendingTransfers = $pdo->query("
    SELECT s.id, s.name as student_name, c_old.name as old_class, c_new.name as new_class 
    FROM students s 
    LEFT JOIN classrooms c_old ON s.classroom_id = c_old.id 
    JOIN classrooms c_new ON s.pending_classroom_id = c_new.id 
    WHERE s.pending_classroom_id IS NOT NULL
")->fetchAll();

$stmtConfig = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('school_name', 'logo_url')");
$settings = $stmtConfig->fetchAll(PDO::FETCH_KEY_PAIR);
require __DIR__ . '/../../includes/header.php';
?>

<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50">
    
    <?php include __DIR__ . '/../../includes/top_header.php'; ?>
    
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../includes/app_sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shrink-0">
            <h1 class="text-2xl font-black text-slate-800 italic tracking-tight">Turmas & Horários</h1>
            <div class="flex items-center gap-4">
                <button onclick="openModal('modalClassroom')" class="bg-indigo-500 text-white px-4 py-2 rounded-xl font-bold hover:bg-indigo-600 transition-all shadow-md">+ Nova Turma</button>
                <button onclick="openModal('modalSchedule')" class="bg-emerald-500 text-white px-4 py-2 rounded-xl font-bold hover:bg-emerald-600 transition-all shadow-md">+ Novo Horário</button>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            <?php if(count($pendingTransfers) > 0): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6">
                <h3 class="text-amber-800 font-bold mb-4 flex items-center gap-2"><i data-lucide="alert-circle" class="w-5 h-5"></i> Pedidos de Mudança de Turma Pendentes</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach($pendingTransfers as $pt): ?>
                    <div class="bg-white p-4 rounded-xl border border-amber-100 shadow-sm flex flex-col">
                        <span class="font-black text-slate-800"><?= htmlspecialchars($pt['student_name']) ?></span>
                        <div class="flex items-center gap-2 text-sm mt-2 text-slate-500">
                            <span class="line-through"><?= htmlspecialchars($pt['old_class'] ?? 'Sem Turma') ?></span>
                            <i data-lucide="arrow-right" class="w-3 h-3 text-slate-400"></i>
                            <span class="font-bold text-indigo-600"><?= htmlspecialchars($pt['new_class']) ?></span>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <button onclick="handleTransfer(<?= $pt['id'] ?>, 'approve_transfer')" class="flex-1 bg-emerald-100 text-emerald-700 py-2 rounded-lg font-bold text-xs hover:bg-emerald-200">Aprovar</button>
                            <button onclick="handleTransfer(<?= $pt['id'] ?>, 'reject_transfer')" class="flex-1 bg-red-100 text-red-700 py-2 rounded-lg font-bold text-xs hover:bg-red-200">Recusar</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Horários de Intervalo -->
                <div class="lg:col-span-1 bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                    <div class="p-4 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="font-black text-slate-700">Horários de Intervalo</h2>
                    </div>
                    <div class="p-4 space-y-3">
                        <?php foreach($schedules as $sch): ?>
                        <div class="flex items-center justify-between p-3 bg-slate-50 border border-slate-100 rounded-xl">
                            <div>
                                <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($sch['name']) ?></div>
                                <div class="text-xs text-slate-500 flex items-center gap-1 mt-1"><i data-lucide="clock" class="w-3 h-3"></i> Limite: <?= substr($sch['cutoff_time'], 0, 5) ?></div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick='editSchedule(<?= json_encode($sch) ?>)' class="text-slate-400 hover:text-indigo-500"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                                <button onclick="deleteSchedule(<?= $sch['id'] ?>)" class="text-slate-400 hover:text-red-500"><i data-lucide="trash" class="w-4 h-4"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($schedules)): ?>
                            <p class="text-center text-slate-400 text-sm py-4">Nenhum horário cadastrado.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Turmas -->
                <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                    <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center flex-wrap gap-4">
                        <h2 class="font-black text-slate-700">Turmas Cadastradas</h2>
                        
                        <!-- Controles em Massa -->
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="selectAllClassrooms" onchange="toggleAllClassrooms(this)" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mr-2 cursor-pointer">
                            <label for="selectAllClassrooms" class="text-xs font-bold text-slate-500 cursor-pointer">Selecionar Tudo</label>
                            
                            <div class="h-4 w-px bg-slate-300 mx-2"></div>
                            
                            <select id="massScheduleSelect" class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-600 outline-none">
                                <option value="">Atribuir Horário...</option>
                                <?php foreach($schedules as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button onclick="massAssignSchedule()" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-lg text-xs font-bold hover:bg-indigo-200 transition-colors">Aplicar</button>
                            
                            <div class="h-4 w-px bg-slate-300 mx-1"></div>
                            
                            <button onclick="massDeleteClassrooms()" class="bg-red-100 text-red-700 px-3 py-1 rounded-lg text-xs font-bold hover:bg-red-200 transition-colors"><i data-lucide="trash" class="w-3 h-3 inline-block"></i> Excluir</button>
                        </div>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3" id="classroomsList">
                        <?php foreach($classrooms as $cls): ?>
                        <div class="flex items-center justify-between p-4 border border-slate-100 rounded-xl hover:border-slate-300 transition-colors">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" value="<?= $cls['id'] ?>" class="classroom-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                                <div>
                                    <div class="font-bold text-slate-800">
                                        <?= htmlspecialchars($cls['name']) ?> 
                                        <?= $cls['academic_year'] ? '<span class="text-xs text-indigo-400 ml-1">(' . $cls['academic_year'] . ')</span>' : '' ?>
                                    </div>
                                    <div class="text-xs font-medium <?= $cls['schedule_name'] ? 'text-indigo-500' : 'text-red-400' ?> flex items-center gap-1 mt-1">
                                        <i data-lucide="bell" class="w-3 h-3"></i> 
                                        <?= $cls['schedule_name'] ? htmlspecialchars($cls['schedule_name']) . ' (Até ' . substr($cls['cutoff_time'], 0, 5) . ')' : 'Sem horário vinculado' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick='editClassroom(<?= json_encode($cls) ?>)' class="p-2 bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-200"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                                <button onclick="deleteClassroom(<?= $cls['id'] ?>)" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100"><i data-lucide="trash" class="w-4 h-4"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($classrooms)): ?>
                            <div class="col-span-2 text-center text-slate-400 py-8">Nenhuma turma cadastrada.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Horário -->
<div id="modalSchedule" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-black text-slate-800 text-lg" id="scheduleModalTitle">Novo Horário</h3>
            <button onclick="closeModal('modalSchedule')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x"></i></button>
        </div>
        <form onsubmit="saveSchedule(event)" class="p-6 space-y-4">
            <input type="hidden" id="sch_id">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nome do Intervalo (Ex: Manhã)</label>
                <input type="text" id="sch_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-bold text-slate-700 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Horário Limite (Cutoff)</label>
                <input type="time" id="sch_time" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-bold text-slate-700 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                <p class="text-xs text-slate-400 mt-2">Após esse horário, os alunos desta turma não poderão mais reservar/cancelar.</p>
            </div>
            <button type="submit" class="w-full bg-emerald-500 text-white font-black py-4 rounded-xl shadow-lg shadow-emerald-500/30 hover:bg-emerald-600 transition-all uppercase tracking-widest text-sm mt-4">Salvar Horário</button>
        </form>
    </div>
</div>

<!-- Modal Turma -->
<div id="modalClassroom" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-black text-slate-800 text-lg" id="classModalTitle">Nova Turma</h3>
            <button onclick="closeModal('modalClassroom')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x"></i></button>
        </div>
        <form onsubmit="saveClassroom(event)" class="p-6 space-y-4">
            <input type="hidden" id="cls_id">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nome da Turma</label>
                <input type="text" id="cls_name" required placeholder="Ex: 3º Ano A" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-bold text-slate-700 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Ano Letivo</label>
                <input type="number" id="cls_year" placeholder="Ex: <?= date('Y') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-bold text-slate-700 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Horário Vinculado</label>
                <select id="cls_schedule" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-bold text-slate-700 outline-none focus:border-indigo-500">
                    <option value="">Selecione o Horário...</option>
                    <?php foreach($schedules as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (Até <?= substr($s['cutoff_time'], 0, 5) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="w-full bg-indigo-500 text-white font-black py-4 rounded-xl shadow-lg shadow-indigo-500/30 hover:bg-indigo-600 transition-all uppercase tracking-widest text-sm mt-4">Salvar Turma</button>
        </form>
    </div>
</div>

<!-- Modal Custom Dialog -->
<div id="modalCustomDialog" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="p-6 text-center">
            <div id="dialogIcon" class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 bg-indigo-50 text-indigo-500">
                <i data-lucide="info" class="w-8 h-8"></i>
            </div>
            <h3 id="dialogTitle" class="text-xl font-black text-slate-800 mb-2">Aviso</h3>
            <p id="dialogMessage" class="text-slate-500 font-medium mb-6">Mensagem</p>
            <div class="flex gap-3 justify-center">
                <button id="dialogBtnCancel" onclick="closeDialog(false)" class="flex-1 bg-slate-100 text-slate-600 font-bold py-3 rounded-xl hover:bg-slate-200 transition-all hidden">Cancelar</button>
                <button id="dialogBtnConfirm" onclick="closeDialog(true)" class="flex-1 bg-indigo-500 text-white font-bold py-3 rounded-xl hover:bg-indigo-600 transition-all shadow-md">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    // --- CUSTOM DIALOG SYSTEM ---
    let confirmCallback = null;

    function showCustomDialog(type, message, onConfirm = null) {
        document.getElementById('dialogMessage').innerText = message;
        const icon = document.getElementById('dialogIcon');
        const btnCancel = document.getElementById('dialogBtnCancel');
        
        if(type === 'confirm') {
            document.getElementById('dialogTitle').innerText = 'Confirmação';
            icon.innerHTML = '<i data-lucide="help-circle" class="w-8 h-8"></i>';
            icon.className = 'w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 bg-amber-50 text-amber-500';
            btnCancel.classList.remove('hidden');
            confirmCallback = onConfirm;
        } else {
            document.getElementById('dialogTitle').innerText = 'Aviso';
            icon.innerHTML = '<i data-lucide="info" class="w-8 h-8"></i>';
            icon.className = 'w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 bg-indigo-50 text-indigo-500';
            btnCancel.classList.add('hidden');
            confirmCallback = null;
        }
        
        lucide.createIcons();
        document.getElementById('modalCustomDialog').style.display = 'flex';
    }

    function closeDialog(isConfirm) {
        document.getElementById('modalCustomDialog').style.display = 'none';
        if(isConfirm && confirmCallback) {
            confirmCallback();
        }
        confirmCallback = null;
    }

    function customAlert(message) {
        showCustomDialog('alert', message);
    }

    function customConfirm(message, callback) {
        showCustomDialog('confirm', message, callback);
    }
    // -----------------------------

    function openModal(id) {
        document.getElementById(id).style.display = 'flex';
    }
    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
        if(id === 'modalSchedule') {
            document.getElementById('sch_id').value = '';
            document.getElementById('sch_name').value = '';
            document.getElementById('sch_time').value = '';
            document.getElementById('scheduleModalTitle').innerText = 'Novo Horário';
        }
        if(id === 'modalClassroom') {
            document.getElementById('cls_id').value = '';
            document.getElementById('cls_name').value = '';
            document.getElementById('cls_year').value = '';
            document.getElementById('cls_schedule').value = '';
            document.getElementById('classModalTitle').innerText = 'Nova Turma';
        }
    }

    function editSchedule(sch) {
        document.getElementById('sch_id').value = sch.id;
        document.getElementById('sch_name').value = sch.name;
        document.getElementById('sch_time').value = sch.cutoff_time;
        document.getElementById('scheduleModalTitle').innerText = 'Editar Horário';
        openModal('modalSchedule');
    }

    function editClassroom(cls) {
        document.getElementById('cls_id').value = cls.id;
        document.getElementById('cls_name').value = cls.name;
        document.getElementById('cls_year').value = cls.academic_year || '';
        document.getElementById('cls_schedule').value = cls.meal_schedule_id || '';
        document.getElementById('classModalTitle').innerText = 'Editar Turma';
        openModal('modalClassroom');
    }

    async function apiCall(data) {
        try {
            const res = await fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if(json.success) location.reload();
            else customAlert(json.message || "Erro.");
        } catch(e) {
            customAlert("Erro na requisição.");
        }
    }

    function saveSchedule(e) {
        e.preventDefault();
        apiCall({
            action: 'save_schedule',
            id: document.getElementById('sch_id').value,
            name: document.getElementById('sch_name').value,
            cutoff_time: document.getElementById('sch_time').value
        });
    }

    function deleteSchedule(id) {
        customConfirm("Tem certeza? Turmas vinculadas ficarão sem horário!", () => {
            apiCall({ action: 'delete_schedule', id });
        });
    }

    function saveClassroom(e) {
        e.preventDefault();
        apiCall({
            action: 'save_classroom',
            id: document.getElementById('cls_id').value,
            name: document.getElementById('cls_name').value,
            academic_year: document.getElementById('cls_year').value,
            meal_schedule_id: document.getElementById('cls_schedule').value
        });
    }

    function deleteClassroom(id) {
        customConfirm("Tem certeza que deseja apagar a turma?", () => {
            apiCall({ action: 'delete_classroom', id });
        });
    }

    function handleTransfer(student_id, action) {
        apiCall({ action, student_id });
    }

    function toggleAllClassrooms(checkbox) {
        const checkboxes = document.querySelectorAll('.classroom-checkbox');
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
    }

    function getSelectedClassrooms() {
        const checkboxes = document.querySelectorAll('.classroom-checkbox:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }

    function massDeleteClassrooms() {
        const ids = getSelectedClassrooms();
        if (ids.length === 0) return customAlert("Selecione pelo menos uma turma.");
        
        customConfirm(`Tem certeza que deseja apagar as ${ids.length} turmas selecionadas?`, () => {
            apiCall({ action: 'mass_delete_classrooms', ids });
        });
    }

    function massAssignSchedule() {
        const ids = getSelectedClassrooms();
        if (ids.length === 0) return customAlert("Selecione pelo menos uma turma.");
        
        const scheduleId = document.getElementById('massScheduleSelect').value;
        if (!scheduleId) return customAlert("Selecione um horário para atribuir.");

        apiCall({ action: 'mass_assign_schedule', ids, schedule_id: scheduleId });
    }
</script>
</body>
</html>
