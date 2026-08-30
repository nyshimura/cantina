<?php
/**
 * Menu Lateral Centralizado (Desktop)
 * Utiliza o menu_items.php para manter sincronia com o mobile.
 */
require_once __DIR__ . '/menu_items.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$userLevel = $_SESSION['access_level'] ?? 'CASHIER';
$permsRaw  = $_SESSION['permissions'] ?? '{}';
$perms = json_decode($permsRaw, true);
if (!is_array($perms)) { $perms = []; }

if (!function_exists('hasSidebarPerm')) {
    function hasSidebarPerm($key) {
        global $perms, $userLevel;
        if ($userLevel === 'ADMIN') return true; 
        return isset($perms[$key]) && $perms[$key] === true;
    }
}

$activeStyle   = "bg-emerald-50 text-emerald-700 font-bold border-r-4 border-emerald-500 shadow-sm";
$inactiveStyle = "text-slate-600 hover:bg-slate-50 font-medium";
?>

<aside class="hidden md:flex w-64 bg-white border-r border-slate-200 flex-col shrink-0 h-full z-10">
    
    <div class="p-6 flex items-center gap-3 border-b border-slate-100">
         <div class="w-8 h-8 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
         </div>
         <span class="font-black text-slate-800 tracking-tight">Gestão</span>
    </div>

    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <?php foreach($navItems as $item): ?>
            <?php if(hasSidebarPerm($item['perm'])): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-all <?= $currentPage == $item['url'] ? $activeStyle : $inactiveStyle ?>">
                    <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-5 h-5"></i> 
                    <span class="text-sm"><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="p-6 border-t border-slate-100">
        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-red-500 hover:bg-red-50 rounded-xl transition-colors font-bold text-xs uppercase tracking-widest">
            <i data-lucide="log-out" class="w-4 h-4"></i> Sair do Sistema
        </a>
    </div>
</aside>
