<?php
/**
 * Centralização do Menu Lateral (Sidebar) - VISÍVEL APENAS NO DESKTOP/TABLET
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$userLevel = $_SESSION['access_level'] ?? 'CASHIER';
$permsRaw  = $_SESSION['permissions'] ?? '{}';
$perms = json_decode($permsRaw, true);
if (!is_array($perms)) { $perms = []; }

function hasPerm($key) {
    global $perms, $userLevel;
    if ($userLevel === 'ADMIN') return true; 
    return isset($perms[$key]) && $perms[$key] === true;
}

$activeStyle   = "bg-emerald-50 text-emerald-700 font-bold border-r-4 border-emerald-500 shadow-sm";
$inactiveStyle = "text-slate-600 hover:bg-slate-50 font-medium";
?>

<aside class="hidden md:flex w-64 bg-white border-r border-slate-200 flex-col shrink-0 h-full">
    
    <div class="p-6 flex items-center gap-3 border-b border-slate-100">
         <div class="w-8 h-8 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
         </div>
         <span class="font-black text-slate-800 tracking-tight">Gestão</span>
    </div>

    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        
        <?php if(hasPerm('canViewDashboard')): ?>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-all <?= $currentPage == 'dashboard.php' ? $activeStyle : $inactiveStyle ?>">
            <i data-lucide="layout-grid" class="w-5 h-5"></i> 
            <span class="text-sm">Dashboard</span>
        </a>
        <?php endif; ?>

        <?php if(hasPerm('canManageSettings')): ?>
        <a href="settings.php" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-all <?= $currentPage == 'settings.php' ? $activeStyle : $inactiveStyle ?>">
            <i data-lucide="settings" class="w-5 h-5"></i> 
            <span class="text-sm">Configurações</span>
        </a>
        <?php endif; ?>

        <?php if(hasPerm('canManageFinancial')): ?>
        <a href="financial.php" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-all <?= $currentPage == 'financial.php' ? $activeStyle : $inactiveStyle ?>">
            <i data-lucide="dollar-sign" class="w-5 h-5"></i> 
            <span class="text-sm">Financeiro</span>
        </a>
        <?php endif; ?>

        <?php if(hasPerm('canManageStudents')): ?>
        <a href="students.php" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-all <?= $currentPage == 'students.php' ? $activeStyle : $inactiveStyle ?>">
            <i data-lucide="graduation-cap" class="w-5 h-5"></i> 
            <span class="text-sm">Alunos</span>
        </a>
        <?php endif; ?>

        <?php if(hasPerm('canManageParents')): ?>
        <a href="parents.php" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-all <?= $currentPage == 'parents.php' ? $activeStyle : $inactiveStyle ?>">
            <i data-lucide="users" class="w-5 h-5"></i> 
            <span class="text-sm">Responsáveis</span>
        </a>
        <?php endif; ?>

        <?php if(hasPerm('canManageTags')): ?>
        <a href="tags.php" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-all <?= $currentPage == 'tags.php' ? $activeStyle : $inactiveStyle ?>">
            <i data-lucide="rss" class="w-5 h-5"></i> 
            <span class="text-sm">Tags NFC</span>
        </a>
        <?php endif; ?>

        <?php if(hasPerm('canManageTeam')): ?>
        <a href="team.php" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-all <?= $currentPage == 'team.php' ? $activeStyle : $inactiveStyle ?>">
            <i data-lucide="shield-check" class="w-5 h-5"></i> 
            <span class="text-sm">Equipe</span>
        </a>
        <?php endif; ?>

        <?php if(hasPerm('canViewLogs')): ?>
        <a href="logs.php" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-all <?= $currentPage == 'logs.php' ? $activeStyle : $inactiveStyle ?>">
            <i data-lucide="file-text" class="w-5 h-5"></i> 
            <span class="text-sm">Auditoria</span>
        </a>
        <?php endif; ?>

    </nav>

    <div class="p-6 border-t border-slate-100">
        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-red-500 hover:bg-red-50 rounded-xl transition-colors font-bold text-xs uppercase tracking-widest">
            <i data-lucide="log-out" class="w-4 h-4"></i> Sair do Sistema
        </a>
    </div>
</aside>