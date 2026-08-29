
<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('PARENT');

$parentId = $_SESSION['user_id'];
$msg = '';
$error = '';

// Adicionar Novo Responsável
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coparent_email'])) {
    $email = $_POST['coparent_email'];
    $name = $_POST['coparent_name'];
    $cpf = $_POST['coparent_cpf'];
    $phone = $_POST['coparent_phone'];

    try {
        $pdo->beginTransaction();

        // 1. Verifica se já existe
        $stmt = $pdo->prepare("SELECT id FROM parents WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $newParentId = $existing['id'];
        } else {
            // Cria novo responsável
            $stmt = $pdo->prepare("INSERT INTO parents (name, email, cpf, phone, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $cpf, $phone, password_hash('123', PASSWORD_DEFAULT)]);
            $newParentId = $pdo->lastInsertId();
        }

        // 2. Vincula aos filhos do pai atual que foram SELECIONADOS
        $selectedStudents = $_POST['student_ids'] ?? [];
        $linkedCount = 0;

        if (!empty($selectedStudents)) {
            foreach ($selectedStudents as $sId) {
                // Valida se o aluno pertence ao pai principal
                $stmtCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? AND parent_id = ?");
                $stmtCheck->execute([$sId, $parentId]);
                if ($stmtCheck->fetch()) {
                    $stmtLink = $pdo->prepare("INSERT IGNORE INTO student_co_parents (student_id, parent_id) VALUES (?, ?)");
                    $stmtLink->execute([$sId, $newParentId]);
                    if ($stmtLink->rowCount() > 0) $linkedCount++;
                }
            }
        }

        $pdo->commit();
        $msg = $linkedCount > 0 ? "Responsável vinculado aos alunos selecionados com sucesso!" : "Responsável criado, mas nenhum aluno válido foi selecionado para vinculação.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro: " . $e->getMessage();
    }
}

// Listar Co-Responsáveis
// Logica: Pega todos os co-parentes vinculados a qualquer filho que EU sou o dono principal e agrupa os nomes
$sql = "SELECT p.*, GROUP_CONCAT(s.name SEPARATOR ', ') as linked_students 
        FROM parents p
        JOIN student_co_parents scp ON p.id = scp.parent_id
        JOIN students s ON scp.student_id = s.id
        WHERE s.parent_id = ?
        GROUP BY p.id";
$stmt = $pdo->prepare($sql);
$stmt->execute([$parentId]);
$coParents = $stmt->fetchAll();

// Listar meus filhos para o checkbox
$stmt = $pdo->prepare("SELECT id, name, avatar_url FROM students WHERE parent_id = ? AND active = 1");
$stmt->execute([$parentId]);
$myStudentsList = $stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<div class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <a href="dashboard.php" class="p-2 hover:bg-slate-100 rounded-full"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
        <h1 class="text-xl font-bold text-slate-800">Gestão Familiar</h1>
    </div>
</div>

<div class="p-6 max-w-4xl mx-auto w-full space-y-8">
    <?php if($msg): ?><div class="bg-green-100 text-green-700 p-3 rounded-lg flex items-center gap-2"><i data-lucide="check" class="w-4 h-4"></i> <?= $msg ?></div><?php endif; ?>
    <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded-lg flex items-center gap-2"><i data-lucide="alert-circle" class="w-4 h-4"></i> <?= $error ?></div><?php endif; ?>

    <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 text-blue-800 text-sm">
        <p><strong>Como funciona:</strong> Adicione cônjuges, avós ou tios. Eles terão acesso para visualizar o saldo, histórico e realizar recargas nos cartões dos seus filhos.</p>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-slate-800">Co-Responsáveis Ativos</h3>
        </div>
        <?php if(empty($coParents)): ?>
            <div class="p-8 text-center text-slate-500">Nenhum responsável adicional cadastrado.</div>
        <?php else: ?>
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs text-slate-500 uppercase"><tr><th class="px-6 py-3">Nome</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Alunos Vinculados</th><th class="px-6 py-3 text-right">Ação</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($coParents as $cp): ?>
                    <tr>
                        <td class="px-6 py-4 font-medium"><?= htmlspecialchars($cp['name']) ?></td>
                        <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($cp['email']) ?></td>
                        <td class="px-6 py-4 text-slate-500 text-sm"><?= htmlspecialchars($cp['linked_students']) ?></td>
                        <td class="px-6 py-4 text-right"><span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full">Ativo</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2"><i data-lucide="user-plus" class="w-5 h-5 text-slate-500"></i> Adicionar Novo Responsável</h3>
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-sm font-bold text-slate-700 mb-1">Nome Completo</label><input type="text" name="coparent_name" required class="w-full border rounded-lg px-4 py-2"></div>
                <div><label class="block text-sm font-bold text-slate-700 mb-1">CPF</label><input type="text" name="coparent_cpf" required class="w-full border rounded-lg px-4 py-2"></div>
                <div><label class="block text-sm font-bold text-slate-700 mb-1">E-mail (Login)</label><input type="email" name="coparent_email" required class="w-full border rounded-lg px-4 py-2"></div>
                <div><label class="block text-sm font-bold text-slate-700 mb-1">Telefone</label><input type="text" name="coparent_phone" required class="w-full border rounded-lg px-4 py-2"></div>
            </div>
            
            <?php if(!empty($myStudentsList)): ?>
            <div class="mt-6 border-t pt-4">
                <label class="block text-sm font-bold text-slate-700 mb-3">Dar acesso a quais filhos?</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach($myStudentsList as $s): ?>
                    <label class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                        <input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="w-5 h-5 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500">
                        <img src="<?= $s['avatar_url'] ?>" class="w-8 h-8 rounded-full bg-white border border-slate-200">
                        <span class="font-bold text-slate-700"><?= htmlspecialchars($s['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
                <div class="p-4 bg-yellow-50 text-yellow-800 rounded-lg text-sm mt-4">Você precisa cadastrar um aluno antes de adicionar um co-responsável.</div>
            <?php endif; ?>

            <button type="submit" class="bg-slate-800 text-white px-6 py-2 rounded-lg font-bold hover:bg-slate-900 w-full md:w-auto mt-4">Convidar e Vincular</button>
        </form>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
