<?php
// views/login.php
require_once __DIR__ . '/../includes/auth.php';

// Busca as configurações do banco para o cabeçalho dinâmico
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$schoolName = $settings['school_name'] ?? 'Cantina Digital';
$schoolLogo = $settings['logo_url'] ?? '';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Tenta realizar o login
    $redirect = login($email, $password);
    
    if ($redirect) {
        // REGISTO DE AUDITORIA: Login bem-sucedido
        logAction('LOGIN_SUCCESS', "O operador " . ($_SESSION['name'] ?? 'Usuário') . " logou no sistema.");
        
        header("Location: " . $redirect);
        exit;
    } else {
        // REGISTO DE AUDITORIA: Falha de segurança
        logAction('LOGIN_FAILED', "Tentativa de login falhada para o e-mail: " . $email);
        
        $error = "Credenciais inválidas.";
    }
}
require __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 flex items-center justify-center p-4 w-full font-sans">
    <div class="bg-white rounded-[2rem] p-8 max-w-sm w-full shadow-2xl overflow-hidden border border-slate-100">
        
        <div class="text-center mb-8">
            <?php if (!empty($schoolLogo)): ?>
                <img src="<?= htmlspecialchars($schoolLogo) ?>" alt="Logo" class="h-16 mx-auto mb-4 object-contain">
            <?php else: ?>
                <div class="w-16 h-16 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl shadow-sm border border-emerald-50">
                    🥪
                </div>
            <?php endif; ?>

            <h1 class="text-2xl font-black text-slate-800 tracking-tight">
                <?= htmlspecialchars($schoolName) ?>
            </h1>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest mt-1">Acesse sua conta</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-xl text-xs font-bold mb-4 flex items-center gap-2 border border-red-100 animate-in fade-in slide-in-from-top-1">
                <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-2">E-mail</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-4 top-3.5 w-5 h-5 text-slate-300"></i>
                    <input type="email" name="email" required 
                           class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold" 
                           placeholder="seu@email.com">
                </div>
            </div>
            
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Senha</label>
                <div class="relative">
                    <i data-lucide="lock" class="absolute left-4 top-3.5 w-5 h-5 text-slate-300"></i>
                    <input type="password" name="password" required 
                           class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold" 
                           placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="w-full py-4 shadow-xl shadow-emerald-200 bg-emerald-600 text-white font-black rounded-xl hover:bg-emerald-700 active:scale-95 transition-all text-sm uppercase tracking-widest">
                Entrar no Sistema
            </button>
        </form>

        <div class="mt-8 text-center border-t border-slate-100 pt-6">
            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mb-3">É responsável e não tem conta?</p>
            <a href="register.php" class="inline-flex items-center gap-2 text-sm font-black text-emerald-600 hover:text-emerald-700 transition-colors uppercase">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                Criar meu acesso
            </a>
        </div>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>