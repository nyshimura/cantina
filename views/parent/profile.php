
<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('PARENT');

$parentId = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    try {
        $sql = "UPDATE parents SET name = ?, email = ?, phone = ? WHERE id = ?";
        $params = [$name, $email, $phone, $parentId];
        if (!empty($password)) {
            $sql = "UPDATE parents SET name = ?, email = ?, phone = ?, password_hash = ? WHERE id = ?";
            $params = [$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $parentId];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $_SESSION['name'] = $name;
        $msg = "Perfil atualizado com sucesso!";
    } catch (Exception $e) { $msg = "Erro ao atualizar: " . $e->getMessage(); }
}

$stmt = $pdo->prepare("SELECT * FROM parents WHERE id = ?");
$stmt->execute([$parentId]);
$parent = $stmt->fetch();

require __DIR__ . '/../../includes/header.php';
?>

<div class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <a href="dashboard.php" class="p-2 hover:bg-slate-100 rounded-full"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
        <h1 class="text-xl font-bold text-slate-800">Meus Dados</h1>
    </div>
</div>

<div class="p-6 max-w-md mx-auto w-full">
    <?php if($msg): ?><div class="bg-emerald-100 text-emerald-700 p-3 rounded-lg mb-4 text-sm font-medium flex items-center gap-2"><i data-lucide="check" class="w-4 h-4"></i> <?= $msg ?></div><?php endif; ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <form method="POST" class="space-y-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-1">Nome Completo</label><input type="text" name="name" value="<?= htmlspecialchars($parent['name']) ?>" required class="w-full px-4 py-2 border rounded-lg"></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">E-mail</label><input type="email" name="email" value="<?= htmlspecialchars($parent['email']) ?>" required class="w-full px-4 py-2 border rounded-lg"></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">CPF</label><input type="text" value="<?= htmlspecialchars($parent['cpf']) ?>" disabled class="w-full px-4 py-2 border rounded-lg bg-slate-100 text-slate-500"></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">Telefone</label><input type="text" name="phone" value="<?= htmlspecialchars($parent['phone']) ?>" class="w-full px-4 py-2 border rounded-lg"></div>
            <div class="border-t border-slate-100 pt-4 mt-4"><h3 class="font-bold text-slate-800 mb-2">Alterar Senha</h3><label class="block text-sm font-bold text-slate-700 mb-1">Nova Senha</label><input type="password" name="password" placeholder="Deixe em branco para manter a atual" class="w-full px-4 py-2 border rounded-lg"></div>
            <button type="submit" class="w-full bg-slate-800 text-white font-bold py-3 rounded-lg hover:bg-slate-900 mt-2">Salvar Alterações</button>
        </form>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
