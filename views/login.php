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
        logAction('LOGIN_SUCCESS', "O operador " . ($_SESSION['name'] ?? 'Usuário') . " logou no sistema.");
        header("Location: " . $redirect);
        exit;
    } else {
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
                <div class="text-right mt-2 mr-1">
                    <button type="button" onclick="document.getElementById('modalForgot').classList.remove('hidden'); document.getElementById('modalForgot').classList.add('flex');" class="text-[10px] font-bold text-slate-400 hover:text-emerald-500 transition-colors uppercase tracking-widest">
                        Esqueci minha senha
                    </button>
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

<div id="modalForgot" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-[2.5rem] w-full max-w-sm p-8 shadow-2xl animate-in fade-in zoom-in duration-200 relative">
        <button onclick="document.getElementById('modalForgot').classList.add('hidden');" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button>
        
        <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="key-round" class="w-8 h-8"></i>
        </div>
        
        <h3 class="text-2xl font-black text-slate-800 mb-2 text-center tracking-tight">Recuperar Senha</h3>
        <p class="text-slate-400 text-xs font-medium mb-8 text-center px-4">Digite seu e-mail abaixo. Enviaremos um link seguro para redefinir sua senha.</p>
        
        <form id="formForgot" class="space-y-4">
            <div>
                <input type="email" id="forgotEmail" required placeholder="Digite seu e-mail" 
                       class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-bold text-slate-700 text-center">
            </div>
            
            <div id="forgotFeedback" class="hidden p-3 rounded-xl text-center text-xs font-bold"></div>

            <button type="submit" id="btnForgot" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black hover:bg-blue-700 shadow-xl shadow-blue-100 mt-2 active:scale-95 transition-all uppercase text-xs tracking-widest">
                Enviar Link
            </button>
        </form>
    </div>
</div>

<script>
    lucide.createIcons();

    document.getElementById('formForgot').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btnForgot');
        const feedback = document.getElementById('forgotFeedback');
        const email = document.getElementById('forgotEmail').value;

        btn.disabled = true;
        btn.innerText = "ENVIANDO...";
        feedback.classList.add('hidden');

        try {
            const res = await fetch('../api/auth/forgot_password.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ email: email })
            });
            const data = await res.json();

            feedback.classList.remove('hidden');
            if (data.success) {
                feedback.className = "p-3 rounded-xl text-center text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-100";
                feedback.innerText = "Se o e-mail existir, o link foi enviado!";
                btn.innerText = "ENVIADO";
                setTimeout(() => {
                    document.getElementById('modalForgot').classList.add('hidden');
                    btn.disabled = false;
                    btn.innerText = "ENVIAR LINK";
                    feedback.classList.add('hidden');
                    document.getElementById('forgotEmail').value = '';
                }, 3000);
            } else {
                throw new Error(data.message || 'Erro ao enviar.');
            }
        } catch (error) {
            feedback.className = "p-3 rounded-xl text-center text-xs font-bold bg-red-50 text-red-500 border border-red-100";
            feedback.innerText = "Erro de conexão. Tente novamente.";
            btn.disabled = false;
            btn.innerText = "TENTAR NOVAMENTE";
        }
    });
</script>
</body>
</html>