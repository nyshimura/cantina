<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManageSettings');

// Pasta de certificados
$certsDir = __DIR__ . '/../../certs/';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Uploads (Itaú)
    if (!is_dir($certsDir)) mkdir($certsDir, 0755, true);

    if (isset($_FILES['itau_cert_file']) && $_FILES['itau_cert_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['itau_cert_file']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['itau_cert_file']['tmp_name'], $certsDir . 'certificado.crt');
    }
    if (isset($_FILES['itau_key_file']) && $_FILES['itau_key_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['itau_key_file']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['itau_key_file']['tmp_name'], $certsDir . 'chave.key');
    }

    // 2. Salva Campos de Texto
    foreach ($_POST as $key => $value) {
        if ($key === 'submit') continue;
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    
    // 3. Checkboxes (Cash / Sandbox / Security PIN)
    // ATUALIZADO: Adicionado 'security_enable_pin' na lista para salvar 0 se desmarcado
    $checkboxes = ['enable_cash_payment', 'mp_sandbox_mode', 'security_enable_pin'];
    foreach ($checkboxes as $chk) {
        if (!isset($_POST[$chk])) {
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, '0') ON DUPLICATE KEY UPDATE setting_value = '0'")->execute([$chk]);
        }
    }

    if (!$error) $success = "Configurações salvas com sucesso!";
}

// Carrega dados
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

// Status dos Arquivos
$hasCert = file_exists($certsDir . 'certificado.crt');
$hasKey = file_exists($certsDir . 'chave.key');

// Defaults
$timezone = $settings['system_timezone'] ?? 'America/Sao_Paulo';
date_default_timezone_set($timezone);
$enableCash = $settings['enable_cash_payment'] ?? '1';
$mpSandbox = $settings['mp_sandbox_mode'] ?? '0';
$activeProvider = $settings['payment_provider'] ?? 'MANUAL_PIX';

// NOVO: Defaults de Segurança
$enablePin = $settings['security_enable_pin'] ?? '0';
$minPinAmount = $settings['security_pin_min_amount'] ?? '0.00';

// Permissões
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

<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50">
    <?php include __DIR__ . '/../../includes/top_header.php'; ?>
    
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 lg:p-12 pb-48 md:pb-12">
            <div class="max-w-3xl mx-auto">
                
                <header class="mb-8">
                    <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                        <i data-lucide="settings" class="text-emerald-500"></i> Ajustes do Sistema
                    </h1>
                </header>

                <?php if($success): ?>
                    <div class="bg-emerald-100 text-emerald-700 p-4 rounded-xl mb-6 flex items-center gap-3 font-bold text-sm shadow-sm">
                        <i data-lucide="check-circle" class="w-5 h-5"></i> <?= $success ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-8" id="settingsForm">
                    
                    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-2">
                            <i data-lucide="school" class="w-4 h-4 text-slate-400"></i>
                            <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Dados da Escola</h2>
                        </div>
                        <div class="p-6 grid gap-6">
                            <div>
                                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Nome Fantasia</label>
                                <input type="text" name="school_name" value="<?= htmlspecialchars($settings['school_name'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 outline-none font-bold text-slate-700 text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">CNPJ</label>
                                    <input type="text" name="school_cnpj" value="<?= htmlspecialchars($settings['school_cnpj'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 font-bold text-slate-700 text-sm">
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Logo URL</label>
                                    <input type="text" name="logo_url" value="<?= htmlspecialchars($settings['logo_url'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 font-bold text-slate-700 text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Endereço</label>
                                <input type="text" name="school_address" value="<?= htmlspecialchars($settings['school_address'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 font-bold text-slate-700 text-sm">
                            </div>
                        </div>
                    </section>

                    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-6 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                    <i data-lucide="banknote" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800">Aceitar Dinheiro</h3>
                                    <p class="text-xs text-slate-500">Habilita pagamentos em espécie no PDV.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="enable_cash_payment" value="1" <?= $enableCash == '1' ? 'checked' : '' ?> class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                            </label>
                        </div>
                    </section>

                    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden ring-1 ring-red-50">
                        <div class="px-6 py-4 bg-red-50/50 border-b border-red-100 flex items-center gap-2">
                            <i data-lucide="lock" class="w-4 h-4 text-red-500"></i>
                            <h2 class="text-sm font-bold text-red-700 uppercase tracking-wide">Segurança do PDV</h2>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center">
                                        <i data-lucide="key-round" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-800">Exigir Senha (PIN)</h3>
                                        <p class="text-xs text-slate-500">O aluno deverá digitar a senha para confirmar a compra.</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="security_enable_pin" value="1" <?= $enablePin == '1' ? 'checked' : '' ?> class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                                </label>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                                <div>
                                    <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Valor Mínimo para Senha</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">R$</span>
                                        <input type="number" step="0.01" name="security_pin_min_amount" value="<?= number_format((float)$minPinAmount, 2, '.', '') ?>" class="w-full pl-10 pr-4 py-2 rounded-lg border border-slate-200 font-bold text-slate-700 text-sm focus:border-red-400 focus:ring-2 focus:ring-red-100 outline-none">
                                    </div>
                                    <p class="text-[10px] text-slate-500 mt-1">Abaixo deste valor, a senha não será solicitada (Agilidade).</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="space-y-4">
                        <div class="flex items-center gap-2 px-2">
                            <i data-lucide="zap" class="w-4 h-4 text-slate-400"></i>
                            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Motor de Pagamento Pix (Selecione um)</h2>
                        </div>

                        <div id="card_MANUAL_PIX" class="bg-white border rounded-2xl overflow-hidden transition-all duration-300">
                            <div class="p-5 flex items-center justify-between cursor-pointer" onclick="selectProvider('MANUAL_PIX')">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center">
                                        <i data-lucide="qr-code" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-800">Pix Estático (Manual)</h3>
                                        <p class="text-xs text-slate-500">Gera um QR Code fixo. Requer conferência manual.</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer pointer-events-none">
                                    <input type="radio" name="payment_provider" value="MANUAL_PIX" <?= $activeProvider === 'MANUAL_PIX' ? 'checked' : '' ?> class="sr-only peer" onchange="updateUI()">
                                    <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-emerald-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full shadow-inner"></div>
                                </label>
                            </div>
                            <div id="content_MANUAL_PIX" class="px-6 pb-6 pt-2 border-t border-slate-100 hidden">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label class="text-[10px] font-bold text-slate-400 uppercase">Tipo Chave</label>
                                        <select name="pix_key_type" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold text-slate-700">
                                            <option value="CNPJ" <?= ($settings['pix_key_type'] ?? '') === 'CNPJ' ? 'selected' : '' ?>>CNPJ</option>
                                            <option value="CPF" <?= ($settings['pix_key_type'] ?? '') === 'CPF' ? 'selected' : '' ?>>CPF</option>
                                            <option value="EMAIL" <?= ($settings['pix_key_type'] ?? '') === 'EMAIL' ? 'selected' : '' ?>>E-mail</option>
                                            <option value="ALEATORIA" <?= ($settings['pix_key_type'] ?? '') === 'ALEATORIA' ? 'selected' : '' ?>>Aleatória</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-slate-400 uppercase">Chave Pix</label>
                                        <input type="text" name="pix_key" value="<?= htmlspecialchars($settings['pix_key'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold text-slate-700">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="card_MERCADO_PAGO" class="bg-white border rounded-2xl overflow-hidden transition-all duration-300">
                            <div class="p-5 flex items-center justify-between cursor-pointer" onclick="selectProvider('MERCADO_PAGO')">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                                        <i data-lucide="zap" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-800">Mercado Pago</h3>
                                        <p class="text-xs text-slate-500">API Oficial. Baixa automática imediata.</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer pointer-events-none">
                                    <input type="radio" name="payment_provider" value="MERCADO_PAGO" <?= $activeProvider === 'MERCADO_PAGO' ? 'checked' : '' ?> class="sr-only peer" onchange="updateUI()">
                                    <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full shadow-inner"></div>
                                </label>
                            </div>

                            <div id="content_MERCADO_PAGO" class="px-6 pb-6 pt-2 border-t border-slate-100 hidden">
                                <div class="space-y-4 mt-4">
                                    <div class="flex items-center gap-2 mb-4 p-3 bg-blue-50/50 rounded-lg">
                                        <input type="checkbox" name="mp_sandbox_mode" value="1" <?= $mpSandbox == '1' ? 'checked' : '' ?> class="accent-blue-600 w-4 h-4">
                                        <span class="text-xs font-bold text-blue-800">Ativar Modo Sandbox (Teste)</span>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-[10px] font-bold text-slate-400 uppercase">Access Token</label>
                                            <input type="password" name="mp_access_token" value="<?= htmlspecialchars($settings['mp_access_token'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold text-slate-700">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold text-slate-400 uppercase">Public Key</label>
                                            <input type="text" name="mp_public_key" value="<?= htmlspecialchars($settings['mp_public_key'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold text-slate-700">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-[10px] font-bold text-slate-400 uppercase">Client ID</label>
                                            <input type="text" name="mp_client_id" value="<?= htmlspecialchars($settings['mp_client_id'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold text-slate-700">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold text-slate-400 uppercase">Client Secret</label>
                                            <input type="password" name="mp_client_secret" value="<?= htmlspecialchars($settings['mp_client_secret'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold text-slate-700">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="card_ITAU_PIX" class="bg-white border rounded-2xl overflow-hidden transition-all duration-300">
                            <div class="p-5 flex items-center justify-between cursor-pointer" onclick="selectProvider('ITAU_PIX')">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center">
                                        <i data-lucide="building-2" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-800">Itaú Empresas</h3>
                                        <p class="text-xs text-slate-500">API V2 com Certificado Digital mTLS.</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer pointer-events-none">
                                    <input type="radio" name="payment_provider" value="ITAU_PIX" <?= $activeProvider === 'ITAU_PIX' ? 'checked' : '' ?> class="sr-only peer" onchange="updateUI()">
                                    <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full shadow-inner"></div>
                                </label>
                            </div>

                            <div id="content_ITAU_PIX" class="px-6 pb-6 pt-2 border-t border-slate-100 hidden">
                                
                                <div class="flex flex-col md:flex-row gap-4 mb-6 mt-4">
                                    <div class="flex bg-white rounded-lg p-1 shadow-sm border border-slate-200 h-fit">
                                        <label class="px-3 py-2 cursor-pointer flex items-center gap-2">
                                            <input type="radio" name="itau_env" value="sandbox" <?= ($settings['itau_env'] ?? 'sandbox') === 'sandbox' ? 'checked' : '' ?> class="accent-orange-500 w-4 h-4">
                                            <span class="text-xs font-bold text-slate-600">Sandbox</span>
                                        </label>
                                        <div class="w-px bg-slate-200 my-1"></div>
                                        <label class="px-3 py-2 cursor-pointer flex items-center gap-2">
                                            <input type="radio" name="itau_env" value="production" <?= ($settings['itau_env'] ?? '') === 'production' ? 'checked' : '' ?> class="accent-orange-500 w-4 h-4">
                                            <span class="text-xs font-bold text-slate-600">Produção</span>
                                        </label>
                                    </div>

                                    <div class="flex gap-2 w-full">
                                        <label class="flex-1 flex flex-col items-center justify-center gap-1 cursor-pointer bg-white border border-dashed border-slate-300 p-3 rounded-lg hover:border-orange-400 hover:bg-orange-50/20 transition-all">
                                            <i data-lucide="<?= $hasCert ? 'check-circle' : 'upload' ?>" class="w-4 h-4 <?= $hasCert ? 'text-emerald-500' : 'text-slate-400' ?>"></i>
                                            <span class="text-[10px] font-bold text-slate-500 uppercase">Certificado .crt</span>
                                            <input type="file" name="itau_cert_file" class="hidden">
                                        </label>
                                        <label class="flex-1 flex flex-col items-center justify-center gap-1 cursor-pointer bg-white border border-dashed border-slate-300 p-3 rounded-lg hover:border-orange-400 hover:bg-orange-50/20 transition-all">
                                            <i data-lucide="<?= $hasKey ? 'check-circle' : 'upload' ?>" class="w-4 h-4 <?= $hasKey ? 'text-emerald-500' : 'text-slate-400' ?>"></i>
                                            <span class="text-[10px] font-bold text-slate-500 uppercase">Chave .key</span>
                                            <input type="file" name="itau_key_file" class="hidden">
                                        </label>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-[10px] font-bold text-slate-400 uppercase">Client ID</label>
                                            <input type="text" name="itau_client_id" value="<?= htmlspecialchars($settings['itau_client_id'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-orange-500/20">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold text-slate-400 uppercase">Client Secret</label>
                                            <input type="password" name="itau_client_secret" value="<?= htmlspecialchars($settings['itau_client_secret'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-orange-500/20">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-[10px] font-bold text-slate-400 uppercase">API Key (x-itau-apikey)</label>
                                            <input type="text" name="itau_api_key" value="<?= htmlspecialchars($settings['itau_api_key'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-orange-500/20">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold text-slate-400 uppercase">Pix Key</label>
                                            <input type="text" name="pix_key" value="<?= htmlspecialchars($settings['pix_key'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-orange-500/20" placeholder="CPF ou CNPJ usado no Itaú">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <div class="flex items-center gap-2 mb-6 border-b border-slate-100 pb-4">
                            <i data-lucide="mail" class="w-4 h-4 text-slate-400"></i>
                            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Servidor de E-mail (SMTP)</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Host SMTP</label>
                                <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 font-bold text-slate-700 text-sm">
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Porta</label>
                                <input type="text" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 font-bold text-slate-700 text-sm">
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Usuário</label>
                                <input type="email" name="smtp_email" value="<?= htmlspecialchars($settings['smtp_email'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 font-bold text-slate-700 text-sm">
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Senha</label>
                                <input type="password" name="smtp_password" value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 font-bold text-slate-700 text-sm">
                            </div>
                        </div>
                    </section>
                    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-2">
                            <i data-lucide="wrench" class="w-4 h-4 text-slate-400"></i>
                            <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Manutenção do Sistema</h2>
                        </div>
                        <div class="p-6">
                            <a href="update_system.php" class="flex items-center justify-between p-4 rounded-xl border border-slate-200 hover:border-blue-300 hover:bg-blue-50 transition-all group">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                                        <i data-lucide="refresh-cw" class="w-6 h-6"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-800">Verificar Atualizações</h3>
                                        <p class="text-xs text-slate-500">Busca e instala novas versões diretamente do repositório oficial.</p>
                                    </div>
                                </div>
                                <i data-lucide="chevron-right" class="text-slate-400 group-hover:text-blue-500"></i>
                            </a>
                        </div>
                    </section>

                    <div class="fixed bottom-6 right-6 z-50">
                        <button type="submit" class="bg-emerald-600 text-white px-8 py-4 rounded-2xl shadow-xl hover:bg-emerald-700 active:scale-95 transition-all flex items-center gap-2 font-bold uppercase tracking-widest text-xs">
                            <i data-lucide="save" class="w-5 h-5"></i> Salvar Tudo
                        </button>
                    </div>

                </form>
            </div>
        </main>
    </div>
</div>

<script>
    lucide.createIcons();

    // Função para clicar no card e selecionar o radio
    function selectProvider(id) {
        const radio = document.querySelector(`input[value="${id}"]`);
        if(radio) {
            radio.checked = true;
            updateUI();
        }
    }

    function updateUI() {
        const selected = document.querySelector('input[name="payment_provider"]:checked').value;
        const providers = ['MANUAL_PIX', 'MERCADO_PAGO', 'ITAU_PIX'];

        providers.forEach(id => {
            const card = document.getElementById('card_' + id);
            const content = document.getElementById('content_' + id);
            const inputs = content.querySelectorAll('input, select');

            if (id === selected) {
                // Ativo: Borda Verde, Fundo Verde Claro, Conteúdo Visível
                card.classList.remove('border-slate-200');
                card.classList.add('ring-2', 'ring-emerald-500', 'bg-emerald-50/30', 'border-transparent');
                content.classList.remove('hidden');
                // Habilita inputs
                inputs.forEach(input => input.disabled = false);
            } else {
                // Inativo
                card.classList.add('border-slate-200');
                card.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50/30', 'border-transparent');
                content.classList.add('hidden');
                // Desabilita inputs para não enviar vazio
                inputs.forEach(input => input.disabled = true);
            }
        });
    }

    // Inicializa ao carregar
    document.addEventListener('DOMContentLoaded', updateUI);
</script>