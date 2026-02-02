<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManageSettings');

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Processa os campos enviados no formulário
    foreach ($_POST as $key => $value) {
        if ($key === 'submit') continue;

        $valToSave = $value;
        
        // Salva ou atualiza a configuração no banco
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $valToSave, $valToSave]);
    }
    
    // Checkboxes que não são enviados quando desmarcados
    $checkboxes = ['enable_cash_payment', 'mp_sandbox_mode'];
    foreach ($checkboxes as $chk) {
        if (!isset($_POST[$chk])) {
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, '0') ON DUPLICATE KEY UPDATE setting_value = '0'")->execute([$chk]);
        }
    }

    $success = "Configurações atualizadas com sucesso!";
}

// Busca as configurações atuais
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

// Valores padrão
$timezone = $settings['system_timezone'] ?? 'America/Sao_Paulo';
date_default_timezone_set($timezone);

$enableCash = $settings['enable_cash_payment'] ?? '1';
$mpSandbox = $settings['mp_sandbox_mode'] ?? '0'; // Padrão 0 (Produção)

// --- LÓGICA DE PERMISSÕES ---
$userLevel = $_SESSION['access_level'] ?? 'CASHIER';
$permsRaw  = $_SESSION['permissions'] ?? '{}';
$perms = json_decode($permsRaw, true);
if (!is_array($perms)) { $perms = []; }

function checkMobilePerm($key) {
    global $perms, $userLevel;
    if ($userLevel === 'ADMIN') return true; 
    return isset($perms[$key]) && $perms[$key] === true;
}

$currentPage = basename($_SERVER['PHP_SELF']);

require __DIR__ . '/../../includes/header.php';
?>

<a href="../../views/logout.php" class="md:hidden fixed top-3 right-4 z-[60] bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-md hover:bg-slate-800 transition-colors">
    Sair
</a>

<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50">
    
    <?php include __DIR__ . '/../../includes/top_header.php'; ?>

    <div class="md:hidden bg-white border-b border-slate-200 w-full overflow-x-auto scrollbar-hide z-10 shrink-0">
        <div class="flex items-center gap-2 p-3 whitespace-nowrap">
            <?php 
            function renderMobileLink($perm, $url, $label, $icon, $current) {
                if (!checkMobilePerm($perm)) return;
                $activeClass = $current == $url ? 'bg-emerald-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 border border-slate-100';
                echo "<a href='$url' class='px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2 $activeClass'>";
                echo "<i data-lucide='$icon' class='w-4 h-4'></i> $label";
                echo "</a>";
            }

            renderMobileLink('canViewDashboard', 'dashboard.php', 'Dashboard', 'layout-grid', $currentPage);
            renderMobileLink('canManageSettings', 'settings.php', 'Configurações', 'settings', $currentPage);
            renderMobileLink('canManageFinancial', 'financial.php', 'Financeiro', 'dollar-sign', $currentPage);
            renderMobileLink('canManageStudents', 'students.php', 'Alunos', 'graduation-cap', $currentPage);
            renderMobileLink('canManageParents', 'parents.php', 'Responsáveis', 'users', $currentPage);
            renderMobileLink('canManageTags', 'tags.php', 'Tags NFC', 'rss', $currentPage);
            renderMobileLink('canManageTeam', 'team.php', 'Equipe', 'shield-check', $currentPage);
            renderMobileLink('canViewLogs', 'logs.php', 'Auditoria', 'file-text', $currentPage);
            ?>
        </div>
    </div>

    <div class="flex flex-1 overflow-hidden">
        
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 lg:p-12 pb-48 md:pb-12">
            <div class="max-w-4xl mx-auto">
                
                <header class="mb-8 md:mb-10">
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800 flex items-center gap-3">
                        <i data-lucide="settings" class="text-emerald-500"></i> Ajustes do Sistema
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm md:text-base">Gerencie a identidade da escola, pagamentos e regionalização.</p>
                </header>

                <?php if($success): ?>
                    <div class="bg-emerald-500 text-white p-4 rounded-2xl mb-8 flex items-center gap-3 shadow-lg shadow-emerald-100 animate-in fade-in slide-in-from-top-4">
                        <i data-lucide="check-circle" class="w-5 h-5"></i> 
                        <span class="font-bold text-sm"><?= $success ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6 md:space-y-8">
                    
                    <section class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                            <i data-lucide="school" class="w-5 h-5 text-slate-400"></i>
                            <h2 class="font-bold text-slate-800 uppercase tracking-tight text-sm">Informações da Instituição</h2>
                        </div>
                        <div class="p-6 md:p-8 space-y-6">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Nome da Unidade Escolar</label>
                                <input type="text" name="school_name" value="<?= htmlspecialchars($settings['school_name'] ?? '') ?>" 
                                       class="w-full px-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Número do CNPJ</label>
                                    <input type="text" name="school_cnpj" value="<?= htmlspecialchars($settings['school_cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00" 
                                           class="w-full px-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">URL da Logomarca (.png/.svg)</label>
                                    <input type="text" name="logo_url" value="<?= htmlspecialchars($settings['logo_url'] ?? '') ?>" placeholder="https://..." 
                                           class="w-full px-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Endereço de Localização</label>
                                <input type="text" name="school_address" value="<?= htmlspecialchars($settings['school_address'] ?? '') ?>" 
                                       class="w-full px-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                            </div>
                        </div>
                    </section>

                    <section class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                            <i data-lucide="landmark" class="w-5 h-5 text-slate-400"></i>
                            <h2 class="font-bold text-slate-800 uppercase tracking-tight text-sm">Meios de Pagamento</h2>
                        </div>
                        <div class="p-6 md:p-8 space-y-8">
                            <div class="flex items-center justify-between p-4 md:p-6 bg-amber-50/50 rounded-3xl border border-amber-100">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center shadow-sm shrink-0">
                                        <i data-lucide="banknote" class="w-6 h-6"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-800 text-sm md:text-base">Aceitar Dinheiro</h3>
                                        <p class="text-[10px] md:text-xs text-slate-500">Habilita venda em espécie no PDV.</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="enable_cash_payment" value="1" <?= $enableCash == '1' ? 'checked' : '' ?> class="sr-only peer">
                                    <div class="w-12 h-7 md:w-14 md:h-8 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 md:after:h-6 md:after:w-6 after:transition-all peer-checked:bg-emerald-500"></div>
                                </label>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4 ml-1">Recarga de Saldo (Responsável)</label>
                                <div class="flex flex-col md:flex-row p-1 bg-slate-100 rounded-[1.25rem] w-full md:w-fit">
                                    <label class="cursor-pointer flex-1">
                                        <input type="radio" name="payment_provider" value="MERCADO_PAGO" <?= ($settings['payment_provider'] ?? '') === 'MERCADO_PAGO' ? 'checked' : '' ?> onchange="toggleFinance(this.value)" class="hidden peer">
                                        <span class="px-6 py-3 block rounded-2xl text-xs font-bold peer-checked:bg-white peer-checked:text-emerald-600 peer-checked:shadow-md text-slate-500 transition-all uppercase tracking-tighter text-center">Mercado Pago</span>
                                    </label>
                                    <label class="cursor-pointer flex-1">
                                        <input type="radio" name="payment_provider" value="MANUAL_PIX" <?= ($settings['payment_provider'] ?? '') === 'MANUAL_PIX' ? 'checked' : '' ?> onchange="toggleFinance(this.value)" class="hidden peer">
                                        <span class="px-6 py-3 block rounded-2xl text-xs font-bold peer-checked:bg-white peer-checked:text-emerald-600 peer-checked:shadow-md text-slate-500 transition-all uppercase tracking-tighter text-center">Pix Manual</span>
                                    </label>
                                </div>
                            </div>

                            <div id="sectionPix" class="<?= ($settings['payment_provider'] ?? '') === 'MANUAL_PIX' ? '' : 'hidden' ?> grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Tipo da Chave Pix</label>
                                    <select name="pix_key_type" class="w-full px-5 py-4 rounded-2xl border border-slate-200 bg-white outline-none focus:ring-4 focus:ring-emerald-500/10 transition-all font-bold text-slate-700 text-sm">
                                        <option value="CNPJ" <?= ($settings['pix_key_type'] ?? '') === 'CNPJ' ? 'selected' : '' ?>>CNPJ</option>
                                        <option value="CPF" <?= ($settings['pix_key_type'] ?? '') === 'CPF' ? 'selected' : '' ?>>CPF</option>
                                        <option value="EMAIL" <?= ($settings['pix_key_type'] ?? '') === 'EMAIL' ? 'selected' : '' ?>>E-mail</option>
                                        <option value="PHONE" <?= ($settings['pix_key_type'] ?? '') === 'PHONE' ? 'selected' : '' ?>>Telefone</option>
                                        <option value="ALEATORIA" <?= ($settings['pix_key_type'] ?? '') === 'ALEATORIA' ? 'selected' : '' ?>>Chave Aleatória</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Valor da Chave</label>
                                    <input type="text" name="pix_key" value="<?= htmlspecialchars($settings['pix_key'] ?? '') ?>" placeholder="Sua chave" 
                                           class="w-full px-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                </div>
                            </div>

                            <div id="sectionMP" class="<?= ($settings['payment_provider'] ?? '') === 'MERCADO_PAGO' ? '' : 'hidden' ?> space-y-6 pt-2">
                                <div class="flex items-center justify-between p-4 bg-blue-50/50 rounded-2xl border border-blue-100">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center shrink-0">
                                            <i data-lucide="test-tube-2" class="w-5 h-5"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-slate-800 text-sm">Modo Sandbox (Teste)</h3>
                                            <p class="text-[10px] text-slate-500">Use credenciais de teste para simular pagamentos.</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="mp_sandbox_mode" value="1" <?= $mpSandbox == '1' ? 'checked' : '' ?> class="sr-only peer">
                                        <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                                    </label>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Access Token</label>
                                    <input type="password" name="mp_access_token" value="<?= htmlspecialchars($settings['mp_access_token'] ?? '') ?>" placeholder="APP_USR... ou TEST-..." 
                                           class="w-full px-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                    <p class="text-[10px] text-slate-400 mt-3 flex items-center gap-1 ml-1"><i data-lucide="lock" class="w-3 h-3"></i> O token será salvo sem criptografia para evitar erros.</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Public Key</label>
                                    <input type="text" name="mp_public_key" value="<?= htmlspecialchars($settings['mp_public_key'] ?? '') ?>" placeholder="APP_USR... ou TEST-..." 
                                           class="w-full px-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Client ID</label>
                                        <input type="text" name="mp_client_id" value="<?= htmlspecialchars($settings['mp_client_id'] ?? '') ?>" placeholder="Ex: 123456789" 
                                               class="w-full px-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Client Secret</label>
                                        <input type="password" name="mp_client_secret" value="<?= htmlspecialchars($settings['mp_client_secret'] ?? '') ?>" placeholder="**********" 
                                               class="w-full px-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                        <p class="text-[10px] text-slate-400 mt-3 flex items-center gap-1 ml-1"><i data-lucide="lock" class="w-3 h-3"></i> O secret será salvo sem criptografia.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                            <i data-lucide="mail" class="w-5 h-5 text-slate-400"></i>
                            <h2 class="font-bold text-slate-800 uppercase tracking-tight text-sm">Servidor de E-mail (SMTP)</h2>
                        </div>
                        <div class="p-6 md:p-8 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Servidor SMTP (Host)</label>
                                    <div class="relative">
                                        <i data-lucide="server" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-300"></i>
                                        <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="ex: smtp.gmail.com" 
                                               class="w-full pl-11 pr-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Porta SMTP</label>
                                    <div class="relative">
                                        <i data-lucide="hash" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-300"></i>
                                        <input type="text" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? '') ?>" placeholder="ex: 587" 
                                               class="w-full pl-11 pr-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">E-mail Remetente</label>
                                <div class="relative">
                                    <i data-lucide="at-sign" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-300"></i>
                                    <input type="email" name="smtp_email" value="<?= htmlspecialchars($settings['smtp_email'] ?? '') ?>" placeholder="ex: nao-responda@escola.com" 
                                           class="w-full pl-11 pr-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Senha do E-mail (App Password)</label>
                                <div class="relative">
                                    <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-300"></i>
                                    <input type="password" name="smtp_password" value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>" placeholder="**********" 
                                           class="w-full pl-11 pr-5 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all text-sm font-bold text-slate-700">
                                </div>
                                <p class="text-[10px] text-slate-400 mt-2 ml-1 italic">Utilize uma senha de aplicativo para maior segurança.</p>
                            </div>
                        </div>
                    </section>

                    <section class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                            <i data-lucide="clock" class="w-5 h-5 text-slate-400"></i>
                            <h2 class="font-bold text-slate-800 uppercase tracking-tight text-sm">Auditoria e Regionalização</h2>
                        </div>
                        <div class="p-6 md:p-8">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 ml-1">Fuso Horário Brasileiro</label>
                            <select name="system_timezone" class="w-full px-5 py-4 rounded-2xl border border-slate-200 bg-white outline-none focus:ring-4 focus:ring-emerald-500/10 font-bold text-slate-700 transition-all text-sm">
                                <option value="America/Sao_Paulo" <?= $timezone == 'America/Sao_Paulo' ? 'selected' : '' ?>>Brasília / Sudeste / Sul / Nordeste (GMT-3)</option>
                                <option value="America/Manaus" <?= $timezone == 'America/Manaus' ? 'selected' : '' ?>>Amazonas / MS / MT / RO / RR (GMT-4)</option>
                                <option value="America/Rio_Branco" <?= $timezone == 'America/Rio_Branco' ? 'selected' : '' ?>>Acre (GMT-5)</option>
                                <option value="America/Noronha" <?= $timezone == 'America/Noronha' ? 'selected' : '' ?>>Fernando de Noronha (GMT-2)</option>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-4 italic">Esta configuração garante que o horário das vendas e recargas seja registrado corretamente.</p>
                        </div>
                    </section>

                    <div class="fixed bottom-[90px] right-4 md:bottom-8 md:right-8 z-30">
                        <button type="submit" class="bg-emerald-600 text-white px-8 md:px-10 py-4 md:py-5 rounded-[1.5rem] font-black uppercase tracking-widest text-xs hover:bg-emerald-700 shadow-xl shadow-emerald-500/30 transition-all flex items-center gap-3 group active:scale-95">
                            <i data-lucide="save" class="w-5 h-5 group-hover:rotate-12 transition-transform"></i> 
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 h-[70px] flex items-center justify-around z-50 shadow-[0_-5px_20px_rgba(0,0,0,0.05)] px-2">
        <a href="pos.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="credit-card" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Venda</span>
        </a>
        <a href="history.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="list" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Histórico</span>
        </a>
        <a href="products.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="package" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Produtos</span>
        </a>
        <a href="dashboard.php" class="flex flex-col items-center gap-1 p-2 text-emerald-600">
            <i data-lucide="settings" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Gestão</span>
        </a>
    </div>

</div>

<script>
    lucide.createIcons();
    
    function toggleFinance(provider) {
        const pix = document.getElementById('sectionPix');
        const mp = document.getElementById('sectionMP');
        
        if (provider === 'MANUAL_PIX') {
            pix.classList.remove('hidden');
            mp.classList.add('hidden');
        } else {
            pix.classList.add('hidden');
            mp.classList.remove('hidden');
        }
    }
</script>