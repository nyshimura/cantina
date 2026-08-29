
<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('STUDENT');

$studentId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    if (!empty($_POST['avatar_seed'])) {
        $avatarSeed = $_POST['avatar_seed'];
        $newUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed=" . urlencode($avatarSeed);
        $stmt = $pdo->prepare("UPDATE students SET avatar_url = ? WHERE id = ?");
        $stmt->execute([$newUrl, $studentId]);
        $msg = "Perfil atualizado com sucesso!";
    }
    
    if (isset($_POST['classroom_id'])) {
        $newClass = (int)$_POST['classroom_id'];
        $stmtCheck = $pdo->prepare("SELECT classroom_id FROM students WHERE id = ?");
        $stmtCheck->execute([$studentId]);
        $currentClass = $stmtCheck->fetchColumn();
        
        if ($newClass !== $currentClass) {
            $stmtUpdate = $pdo->prepare("UPDATE students SET pending_classroom_id = ? WHERE id = ?");
            $stmtUpdate->execute([$newClass, $studentId]);
            $msg = "Perfil e pedido de mudança de turma enviados!";
        }
    }

    if (!empty($password) && $password === $confirm) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE students SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $studentId]);
        $msg = "Perfil atualizado com sucesso!";
    } elseif (!empty($password)) {
        $err = "As senhas não conferem.";
    }
}

$stmt = $pdo->prepare("SELECT s.*, c.name as classroom_name, pc.name as pending_classroom_name FROM students s LEFT JOIN classrooms c ON s.classroom_id = c.id LEFT JOIN classrooms pc ON s.pending_classroom_id = pc.id WHERE s.id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

$classrooms = $pdo->query("SELECT * FROM classrooms ORDER BY name")->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<div class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <a href="dashboard.php" class="p-2 hover:bg-slate-100 rounded-full"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
        <h1 class="text-xl font-bold text-slate-800">Meu Perfil</h1>
    </div>
</div>

<div class="p-6 max-w-md mx-auto w-full">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 text-center">
        <img src="<?= $student['avatar_url'] ?>" class="w-24 h-24 rounded-full mx-auto mb-4 border-4 border-slate-50">
        <h2 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($student['name']) ?></h2>
        <p class="text-slate-500 mb-6"><?= htmlspecialchars($student['email']) ?></p>

        <?php if(isset($msg)): ?><div class="bg-green-100 text-green-700 p-2 rounded mb-4 text-sm"><?= $msg ?></div><?php endif; ?>
        <?php if(isset($err)): ?><div class="bg-red-100 text-red-700 p-2 rounded mb-4 text-sm"><?= $err ?></div><?php endif; ?>

        <form method="POST" class="text-left space-y-4">
            
            <h3 class="font-bold text-sm text-slate-700 border-b pb-2">Turma Atual</h3>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Selecione sua turma</label>
                <select name="classroom_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-700 outline-none focus:border-indigo-500">
                    <option value="">Sem Turma</option>
                    <?php foreach($classrooms as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $student['classroom_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if($student['pending_classroom_id']): ?>
                    <p class="text-[10px] font-bold text-amber-500 mt-1"><i data-lucide="clock" class="inline w-3 h-3"></i> Aguardando aprovação para: <?= htmlspecialchars($student['pending_classroom_name']) ?></p>
                <?php endif; ?>
            </div>

            <h3 class="font-bold text-sm text-slate-700 border-b pb-2 mt-6">Mudar Aparência</h3>
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-widest">Arraste para mudar seu avatar</label>
                <div id="avatarMap" class="relative w-full h-12 rounded-xl cursor-crosshair overflow-hidden shadow-inner touch-none" style="background: conic-gradient(from 180deg at 50% 50%, #ff0000, #ff8000, #ffff00, #00ff00, #00ffff, #0000ff, #8000ff, #ff00ff, #ff0000);">
                    <div class="absolute inset-0" style="background: linear-gradient(to bottom, rgba(255,255,255,1), rgba(255,255,255,0) 50%, rgba(0,0,0,0) 50%, rgba(0,0,0,1));"></div>
                    <div id="avatarPointer" class="absolute w-4 h-4 bg-white border-2 border-slate-800 rounded-full shadow-md pointer-events-none -translate-x-1/2 -translate-y-1/2" style="top: 50%; left: 50%;"></div>
                </div>
                <input type="hidden" name="avatar_seed" id="avatarSeed" value="">
            </div>
            <h3 class="font-bold text-sm text-slate-700 border-b pb-2">Alterar Senha</h3>
            <div><label class="block text-xs font-bold text-slate-500 mb-1">Nova Senha</label><input type="password" name="password" class="w-full border rounded-lg px-3 py-2"></div>
            <div><label class="block text-xs font-bold text-slate-500 mb-1">Confirmar Nova Senha</label><input type="password" name="confirm_password" class="w-full border rounded-lg px-3 py-2"></div>
            <button class="w-full bg-slate-800 text-white font-bold py-3 rounded-lg hover:bg-slate-900">Salvar Alterações</button>
        </form>
    </div>
</div>
<script>
    lucide.createIcons();

    // Lógica do Avatar Cartesiano
    const map = document.getElementById('avatarMap');
    const pointer = document.getElementById('avatarPointer');
    const preview = document.querySelector('.bg-white.rounded-xl > img');
    const seedInput = document.getElementById('avatarSeed');
    
    let isDragging = false;
    let debounceTimer;

    function updateAvatar(e) {
        const rect = map.getBoundingClientRect();
        let clientX = e.touches ? e.touches[0].clientX : e.clientX;
        let clientY = e.touches ? e.touches[0].clientY : e.clientY;

        let x = clientX - rect.left;
        let y = clientY - rect.top;
        
        x = Math.max(0, Math.min(x, rect.width));
        y = Math.max(0, Math.min(y, rect.height));
        
        pointer.style.left = x + 'px';
        pointer.style.top = y + 'px';
        
        const r = Math.round((x / rect.width) * 255);
        const b = Math.round((y / rect.height) * 255);
        const g = Math.round(((rect.width - x) / rect.width) * 255);
        
        const hex = ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        seedInput.value = hex;

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            preview.src = `https://api.dicebear.com/9.x/adventurer/svg?seed=${hex}`;
        }, 100);
    }

    map.addEventListener('mousedown', (e) => { isDragging = true; updateAvatar(e); });
    window.addEventListener('mousemove', (e) => { if(isDragging) updateAvatar(e); });
    window.addEventListener('mouseup', () => { isDragging = false; });
    
    map.addEventListener('touchstart', (e) => { isDragging = true; updateAvatar(e); }, {passive: false});
    window.addEventListener('touchmove', (e) => { if(isDragging) { updateAvatar(e); e.preventDefault(); } }, {passive: false});
    window.addEventListener('touchend', () => { isDragging = false; });
</script>
</body>
</html>
