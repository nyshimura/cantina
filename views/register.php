<?php
// views/register.php
require_once __DIR__ . '/../config/db.php';

// Busca configurações para manter o padrão visual da escola
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$schoolName = $settings['school_name'] ?? 'Escola Inteligente';
$schoolLogo = $settings['logo_url'] ?? ''; 
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - <?= htmlspecialchars($schoolName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 flex items-center justify-center p-4 w-full font-sans">

    <div class="bg-white rounded-[2rem] p-8 max-w-sm w-full shadow-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-300">
        
        <div class="text-center mb-8">
            <?php if (!empty($schoolLogo)): ?>
                <img src="<?= htmlspecialchars($schoolLogo) ?>" alt="Logo" class="h-16 mx-auto mb-4 object-contain">
            <?php else: ?>
                <div class="w-16 h-16 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl shadow-sm border border-emerald-50">
                    <i data-lucide="user-plus" class="text-emerald-600"></i>
                </div>
            <?php endif; ?>

            <h1 class="text-2xl font-black text-slate-800 tracking-tight">
                Criar Conta
            </h1>
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-widest mt-1">Cadastro de Responsável</p>
        </div>

        <form id="registerForm" class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Nome Completo</label>
                <div class="relative">
                    <i data-lucide="user" class="absolute left-4 top-3.5 w-5 h-5 text-slate-300"></i>
                    <input type="text" name="name" required 
                           class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold text-sm" 
                           placeholder="Ex: Carlos Silva">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-2">E-mail</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-4 top-3.5 w-5 h-5 text-slate-300"></i>
                    <input type="email" name="email" required 
                           class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold text-sm" 
                           placeholder="carlos@exemplo.com">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-2">CPF</label>
                    <input type="text" name="cpf" id="cpfInput" required maxlength="14"
                           class="w-full px-3 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold text-sm text-center" 
                           placeholder="000.000.000-00">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Celular</label>
                    <input type="text" name="phone" id="phoneInput" required maxlength="15"
                           class="w-full px-3 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold text-sm text-center" 
                           placeholder="(00) 00000-0000">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Senha</label>
                <div class="relative">
                    <i data-lucide="lock" class="absolute left-4 top-3.5 w-5 h-5 text-slate-300"></i>
                    <input type="password" name="password" required 
                           class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold text-sm" 
                           placeholder="••••••••">
                </div>
            </div>

            <button type="submit" id="btnRegister" 
                    class="w-full py-4 shadow-xl shadow-emerald-200 bg-emerald-600 text-white font-black rounded-xl hover:bg-emerald-700 active:scale-95 transition-all text-sm uppercase tracking-widest mt-2">
                Cadastrar Agora
            </button>

            <div id="msgBox" class="hidden p-3 rounded-xl text-center text-[10px] font-black uppercase mt-2 border"></div>

            <div class="mt-6 text-center border-t border-slate-100 pt-4">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-2">Já possui uma conta?</p>
                <a href="login.php" class="text-sm font-black text-emerald-600 hover:text-emerald-700 transition-colors uppercase">
                    Fazer Login
                </a>
            </div>
        </form>
    </div>

    <script>
        lucide.createIcons();

        // Máscaras
        document.getElementById('cpfInput').addEventListener('input', e => {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,3})(\d{0,2})/);
            e.target.value = !x[2] ? x[1] : x[1] + '.' + x[2] + (x[3] ? '.' + x[3] : '') + (x[4] ? '-' + x[4] : '');
        });
        document.getElementById('phoneInput').addEventListener('input', e => {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
        });

        // Submissão
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnRegister');
            const msgBox = document.getElementById('msgBox');
            
            btn.disabled = true;
            btn.innerHTML = 'Processando...';
            msgBox.classList.add('hidden');

            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            try {
                const res = await fetch('../api/auth/register_parent.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    msgBox.className = "p-3 rounded-xl text-center text-[10px] font-black uppercase mt-2 bg-emerald-50 text-emerald-600 border-emerald-100";
                    msgBox.innerText = "Conta criada! Redirecionando...";
                    msgBox.classList.remove('hidden');
                    setTimeout(() => window.location.href = 'login.php', 2000);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                msgBox.className = "p-3 rounded-xl text-center text-[10px] font-black uppercase mt-2 bg-red-50 text-red-500 border-red-100";
                msgBox.innerText = error.message;
                msgBox.classList.remove('hidden');
                btn.disabled = false;
                btn.innerHTML = 'Cadastrar Agora';
            }
        });
    </script>
</body>
</html>