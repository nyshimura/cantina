
<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('PARENT');

$parentId = $_SESSION['user_id'];

// Configuração de limites (Disponível para todos com acesso)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $sId = $_POST['student_id'];
    $limit = $_POST['daily_limit'];
    
    if (isParentOf($sId, $parentId)) {
        $stmt = $pdo->prepare("UPDATE students SET daily_limit = ? WHERE id = ?");
        $stmt->execute([$limit, $sId]);
        $success = "Limite atualizado!";
    }
}

// Cadastro de novos alunos (Apenas para Pai Principal - Simplificação)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_child_name'])) {
    $name = $_POST['new_child_name'];
    $cpf = $_POST['new_child_cpf'];
    $email = $_POST['new_child_email'];
    $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = "Email já cadastrado.";
    } else {
        $seed = !empty($_POST['avatar_seed']) ? $_POST['avatar_seed'] : $name;
        $avatarUrl = 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . urlencode($seed);
        $stmt = $pdo->prepare("INSERT INTO students (parent_id, name, email, cpf, password_hash, avatar_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$parentId, $name, $email, $cpf, password_hash('123', PASSWORD_DEFAULT), $avatarUrl]);
        $success = "Dependente cadastrado!";
    }
}

// Buscar alunos (Próprios + Compartilhados)
$sql = "SELECT DISTINCT s.* 
        FROM students s 
        LEFT JOIN student_co_parents scp ON s.id = scp.student_id 
        WHERE (s.parent_id = ? OR scp.parent_id = ?) AND s.active = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$parentId, $parentId]);
$studentsList = $stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<div class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <a href="dashboard.php" class="p-2 hover:bg-slate-100 rounded-full"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
        <h1 class="text-xl font-bold text-slate-800">Configurações</h1>
    </div>
</div>

<div class="p-6 max-w-4xl mx-auto w-full space-y-8">
    <?php if(isset($success)): ?><div class="bg-green-100 text-green-700 p-3 rounded"><?= $success ?></div><?php endif; ?>
    <?php if(isset($error)): ?><div class="bg-red-100 text-red-700 p-3 rounded"><?= $error ?></div><?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2"><i data-lucide="shield-check" class="w-5 h-5 text-slate-500"></i> Limites de Gastos</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach($studentsList as $s): ?>
            <form method="POST" class="border rounded-lg p-4 bg-slate-50">
                <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                <div class="flex items-center gap-3 mb-3">
                    <img src="<?= $s['avatar_url'] ?>" class="w-10 h-10 rounded-full border border-white">
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($s['name']) ?></span>
                </div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Limite Diário (R$)</label>
                <div class="flex gap-2">
                    <input type="number" step="0.50" name="daily_limit" value="<?= $s['daily_limit'] ?>" class="w-full border rounded px-3 py-2">
                    <button class="bg-emerald-600 text-white px-4 rounded font-bold hover:bg-emerald-700">Salvar</button>
                </div>
            </form>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2"><i data-lucide="user-plus" class="w-5 h-5 text-slate-500"></i> Cadastrar Novo Dependente</h3>
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-sm font-bold text-slate-700 mb-1">Nome Completo</label><input type="text" name="new_child_name" required class="w-full border rounded-lg px-4 py-2"></div>
                <div><label class="block text-sm font-bold text-slate-700 mb-1">CPF</label><input type="text" name="new_child_cpf" required class="w-full border rounded-lg px-4 py-2"></div>
                <div><label class="block text-sm font-bold text-slate-700 mb-1">Email (Login)</label><input type="email" name="new_child_email" required class="w-full border rounded-lg px-4 py-2"></div>
            </div>
            <div class="mb-6 bg-slate-50 border border-slate-200 rounded-xl p-6">
                <label class="block text-sm font-bold text-slate-700 mb-4">Personalizar Avatar <span class="text-xs font-normal text-slate-500">(Opcional)</span></label>
                <div class="flex flex-col md:flex-row gap-8 items-center">
                    
                    <div id="avatarMap" class="relative w-48 h-48 rounded-2xl cursor-crosshair overflow-hidden shadow-inner touch-none" style="background: conic-gradient(from 180deg at 50% 50%, #ff0000, #ff8000, #ffff00, #00ff00, #00ffff, #0000ff, #8000ff, #ff00ff, #ff0000);">
                        <div class="absolute inset-0" style="background: linear-gradient(to bottom, rgba(255,255,255,1), rgba(255,255,255,0) 50%, rgba(0,0,0,0) 50%, rgba(0,0,0,1));"></div>
                        <div id="avatarPointer" class="absolute w-4 h-4 bg-white border-2 border-slate-800 rounded-full shadow-md pointer-events-none -translate-x-1/2 -translate-y-1/2" style="top: 50%; left: 50%;"></div>
                    </div>
                    
                    <div class="flex flex-col items-center">
                        <img id="avatarPreview" src="https://api.dicebear.com/9.x/adventurer/svg?seed=Novo" class="w-24 h-24 rounded-full border-4 border-white shadow-lg mb-2 bg-white">
                        <span class="text-xs font-bold text-slate-500">Preview do Personagem</span>
                    </div>
                </div>
                <input type="hidden" name="avatar_seed" id="avatarSeed" value="Novo">
            </div>

            <button type="submit" class="bg-blue-600 text-white px-6 py-3 w-full rounded-xl font-bold hover:bg-blue-700 shadow-md">Cadastrar Aluno</button>
        </form>
    </div>
</div>
<script>
    lucide.createIcons();

    // Lógica do Mapa Cartesiano de Avatar
    const map = document.getElementById('avatarMap');
    const pointer = document.getElementById('avatarPointer');
    const preview = document.getElementById('avatarPreview');
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
        
        // Gera um HEX baseado na posição (X=Hue, Y=Lightness/Randomness)
        const r = Math.round((x / rect.width) * 255);
        const b = Math.round((y / rect.height) * 255);
        const g = Math.round(((rect.width - x) / rect.width) * 255);
        
        const hex = ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        seedInput.value = hex;

        // Debounce para não travar a API do dicebear enquanto arrasta
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
