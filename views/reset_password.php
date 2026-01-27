<?php
// views/reset_password.php
require_once __DIR__ . '/../config/db.php';

// Busca configurações visuais (Logo/Nome) para manter a identidade
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$schoolName = $settings['school_name'] ?? 'Cantina Digital';
$schoolLogo = $settings['logo_url'] ?? '';

// Pega o token da URL
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - <?= htmlspecialchars($schoolName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50">

<div class="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 flex items-center justify-center p-4 w-full">
    <div class="bg-white rounded-[2rem] p-8 max-w-sm w-full shadow-2xl overflow-hidden border border-slate-100 relative">
        
        <div id="successState" class="hidden absolute inset-0 bg-white z-20 flex-col items-center justify-center text-center p-8 animate-in fade-in zoom-in duration-300">
            <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mb-6 shadow-sm">
                <i data-lucide="check-circle" class="w-10 h-10"></i>
            </div>
            <h2 class="text-2xl font-black text-slate-800 mb-2">Sucesso!</h2>
            <p class="text-slate-500 font-medium mb-8">Sua senha foi redefinida. Você já pode acessar sua conta.</p>
            <a href="login.php" class="w-full bg-slate-900 text-white py-4 rounded-xl font-bold shadow-xl hover:bg-slate-800 transition-all uppercase text-xs tracking-widest block">
                Ir para Login
            </a>
        </div>

        <div class="text-center mb-8">
            <?php if (!empty($schoolLogo)): ?>
                <img src="<?= htmlspecialchars($schoolLogo) ?>" alt="Logo" class="h-16 mx-auto mb-4 object-contain">
            <?php else: ?>
                <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl shadow-sm border border-blue-50">
                    <i data-lucide="key-round" class="w-8 h-8"></i>
                </div>
            <?php endif; ?>

            <h1 class="text-xl font-black text-slate-800 tracking-tight">Nova Senha</h1>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest mt-1">Crie sua nova credencial</p>
        </div>

        <?php if (empty($token)): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-center font-bold text-sm border border-red-100">
                Link inválido ou expirado.
                <a href="login.php" class="block mt-2 text-red-800 underline">Voltar ao login</a>
            </div>
        <?php else: ?>

            <form id="resetForm" class="space-y-5">
                <input type="hidden" id="token" value="<?= htmlspecialchars($token) ?>">
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Nova Senha</label>
                    <div class="relative">
                        <i data-lucide="lock" class="absolute left-4 top-3.5 w-5 h-5 text-slate-300"></i>
                        <input type="password" id="password" required minlength="6"
                               class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all font-bold" 
                               placeholder="Mínimo 6 caracteres">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Confirmar Senha</label>
                    <div class="relative">
                        <i data-lucide="lock-check" class="absolute left-4 top-3.5 w-5 h-5 text-slate-300"></i>
                        <input type="password" id="confirm_password" required minlength="6"
                               class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all font-bold" 
                               placeholder="Repita a senha">
                    </div>
                </div>

                <div id="errorMsg" class="hidden bg-red-50 text-red-600 p-3 rounded-xl text-center text-xs font-bold border border-red-100"></div>

                <button type="submit" id="btnSubmit" class="w-full py-4 shadow-xl shadow-blue-200 bg-blue-600 text-white font-black rounded-xl hover:bg-blue-700 active:scale-95 transition-all text-sm uppercase tracking-widest">
                    Salvar Nova Senha
                </button>
            </form>

        <?php endif; ?>
    </div>
</div>

<script>
    lucide.createIcons();

    const form = document.getElementById('resetForm');
    const errorMsg = document.getElementById('errorMsg');
    const btn = document.getElementById('btnSubmit');
    const successState = document.getElementById('successState');

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const p1 = document.getElementById('password').value;
            const p2 = document.getElementById('confirm_password').value;
            const token = document.getElementById('token').value;

            errorMsg.classList.add('hidden');

            if (p1 !== p2) {
                showError("As senhas não coincidem.");
                return;
            }

            btn.disabled = true;
            btn.innerText = "SALVANDO...";

            try {
                // Caminho absoluto para garantir que ache a API
                const res = await fetch('/api/auth/reset_password.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ token: token, password: p1 })
                });
                
                const data = await res.json();

                if (data.success) {
                    successState.classList.remove('hidden');
                    successState.classList.add('flex');
                } else {
                    showError(data.message || "Erro ao redefinir senha.");
                    btn.disabled = false;
                    btn.innerText = "SALVAR NOVA SENHA";
                }

            } catch (err) {
                showError("Erro de conexão com o servidor.");
                btn.disabled = false;
                btn.innerText = "TENTAR NOVAMENTE";
            }
        });
    }

    function showError(msg) {
        errorMsg.innerText = msg;
        errorMsg.classList.remove('hidden');
    }
</script>
</body>
</html>