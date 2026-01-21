<?php
// includes/top_header.php

// 1. Garante que temos as configurações, mesmo se a página pai não tiver carregado
if (!isset($pdo)) {
    // Se por acaso o PDO não estiver disponível (raro, mas preventivo)
    require_once __DIR__ . '/db.php'; 
}

if (!isset($settings)) {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
}

// 2. Define variáveis de exibição
$schoolName = $settings['school_name'] ?? 'Escola Estadual Modelo';
$schoolLogo = $settings['logo_url'] ?? '';

$currentPage = basename($_SERVER['PHP_SELF']);
$userInitials = isset($_SESSION['name']) ? strtoupper(substr($_SESSION['name'], 0, 1)) : 'U';
$userRoleLabel = ($_SESSION['access_level'] === 'ADMIN') ? 'Admin' : 'Operador';
?>

<header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-8 shrink-0 w-full z-30">
    <div class="flex items-center gap-3">
        <?php if (!empty($schoolLogo)): ?>
            <img src="<?= htmlspecialchars($schoolLogo) ?>" alt="Logo" class="w-10 h-10 rounded-xl object-contain bg-white shrink-0 shadow-sm border border-slate-100">
        <?php else: ?>
            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-xl shadow-sm border border-slate-100 text-slate-500">
                🏫
            </div>
        <?php endif; ?>

        <div>
            <h2 class="text-sm font-bold text-slate-800 leading-tight">
                <?= htmlspecialchars($schoolName) ?>
            </h2>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">PDV & Gestão</p>
        </div>
    </div>

    <div class="hidden md:flex bg-slate-100 p-1 rounded-2xl border border-slate-200">
        <a href="../../views/admin/pos.php" class="px-6 py-2 text-xs font-bold rounded-xl transition-all <?= ($currentPage == 'pos.php') ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
            PDV
        </a>
        <a href="history.php" class="px-6 py-2 text-xs font-bold rounded-xl transition-all <?= ($currentPage == 'history.php') ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
            Histórico
        </a>
        <a href="products.php" class="px-6 py-2 text-xs font-bold rounded-xl transition-all <?= ($currentPage == 'catalog.php' || $currentPage == 'products.php') ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
            Catálogo
        </a>
        <a href="dashboard.php" class="px-6 py-2 text-xs font-bold rounded-xl transition-all <?= (in_array($currentPage, ['dashboard.php', 'students.php', 'parents.php', 'tags.php', 'team.php', 'settings.php', 'logs.php'])) ? 'bg-white text-emerald-600 shadow-sm border border-slate-200' : 'text-slate-500 hover:text-slate-700' ?>">
            Gestão
        </a>
    </div>

    <div class="flex items-center gap-4">
        <div class="text-right hidden sm:block">
            <p class="text-sm font-bold text-slate-800 leading-tight"><?= htmlspecialchars($_SESSION['name'] ?? 'Usuário') ?></p>
            <div class="flex items-center justify-end gap-1">
                <i data-lucide="shield-check" class="w-3 h-3 text-amber-500"></i>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter"><?= $userRoleLabel ?></span>
            </div>
        </div>
        <div class="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center font-black text-sm border-2 border-white shadow-sm ring-1 ring-emerald-100">
            <?= $userInitials ?>
        </div>
    </div>
</header>