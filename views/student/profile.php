
<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('STUDENT');

$studentId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    if ($password && $password === $confirm) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE students SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $studentId]);
        $msg = "Senha alterada com sucesso!";
    } else {
        $err = "As senhas não conferem.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

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
            <h3 class="font-bold text-sm text-slate-700 border-b pb-2">Alterar Senha</h3>
            <div><label class="block text-xs font-bold text-slate-500 mb-1">Nova Senha</label><input type="password" name="password" class="w-full border rounded-lg px-3 py-2"></div>
            <div><label class="block text-xs font-bold text-slate-500 mb-1">Confirmar Nova Senha</label><input type="password" name="confirm_password" class="w-full border rounded-lg px-3 py-2"></div>
            <button class="w-full bg-slate-800 text-white font-bold py-3 rounded-lg hover:bg-slate-900">Salvar Alterações</button>
        </form>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
