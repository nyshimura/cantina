<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canViewLogs');

// --- 1. SINCRONIZAÇÃO DE TIMEZONE (PHP + BANCO DE DADOS) ---
try {
    $stmtConfig = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_timezone'");
    $stmtConfig->execute();
    $savedTz = $stmtConfig->fetchColumn() ?: 'America/Sao_Paulo';

    // Define no PHP
    date_default_timezone_set($savedTz);

    // Define no BANCO DE DADOS (Essa linha corrige a diferença de 3h na consulta SQL)
    // Converte o nome do fuso (ex: America/Sao_Paulo) para offset (ex: -03:00)
    $nowTz = new DateTime('now', new DateTimeZone($savedTz));
    $offset = $nowTz->format('P'); 
    $pdo->exec("SET time_zone = '$offset'");

} catch (Exception $e) {
    date_default_timezone_set('America/Sao_Paulo');
    $pdo->exec("SET time_zone = '-03:00'");
}
// -----------------------------------------------------------

$dateFilter = $_GET['date'] ?? date('Y-m-d'); 
$search = $_GET['search'] ?? '';

try {
    $sql = "SELECT a.*, o.name as operator_name 
            FROM audit_logs a 
            LEFT JOIN operators o ON a.operator_id = o.id 
            WHERE 1=1";
    $params = [];

    if ($dateFilter) {
        // Agora o banco filtrará usando o fuso horário que definimos acima
        $sql .= " AND DATE(a.timestamp) = ?";
        $params[] = $dateFilter;
    }

    if ($search) {
        $sql .= " AND (o.name LIKE ? OR a.description LIKE ? OR a.action LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }

    $sql .= " ORDER BY a.timestamp DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Erro ao carregar trilha de auditoria.");
}

// --- LÓGICA DE PERMISSÕES MOBILE ---
$userLevel = $_SESSION['access_level'] ?? 'CASHIER';
$permsRaw  = $_SESSION['permissions'] ?? '{}';
$perms = json_decode($permsRaw, true) ?: [];

function checkMobilePerm($key) {
    global $perms, $userLevel;
    return ($userLevel === 'ADMIN') || (isset($perms[$key]) && $perms[$key] === true);
}

$currentPage = basename($_SERVER['PHP_SELF']);
require __DIR__ . '/../../includes/header.php';
?>

<a href="../../views/logout.php" class="md:hidden fixed top-3 right-4 z-[60] bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-md">
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
            <div class="max-w-7xl mx-auto">
                <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-800 flex items-center gap-3">
                            <i data-lucide="shield-check" class="text-emerald-500"></i> Logs de Auditoria
                        </h1>
                        <p class="text-slate-500 mt-1 font-medium text-sm md:text-base">Investigação de ações críticas.</p>
                    </div>

                    <form class="flex flex-wrap gap-3 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm w-full md:w-auto">
                        <div class="flex items-center px-3 border-r border-slate-100 flex-1 md:flex-none">
                            <i data-lucide="calendar" class="w-4 h-4 text-slate-400 mr-2"></i>
                            <input type="date" name="date" value="<?= $dateFilter ?>" onchange="this.form.submit()" class="text-xs font-bold text-slate-700 outline-none bg-transparent">
                        </div>
                        <div class="flex items-center px-3 flex-1 md:flex-none">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 mr-2"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar..." class="text-xs font-bold text-slate-700 outline-none bg-transparent w-full md:w-40">
                        </div>
                    </form>
                </header>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[800px] md:min-w-0">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                <tr>
                                    <th class="px-6 md:px-8 py-6">Horário Local</th>
                                    <th class="px-6 md:px-8 py-6">Operador</th>
                                    <th class="px-6 md:px-8 py-6">Ação</th>
                                    <th class="px-6 md:px-8 py-6">Descrição</th>
                                    <th class="px-6 md:px-8 py-6 text-right">Origem IP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($logs as $log): 
                                    $badgeColor = 'bg-slate-100 text-slate-500';
                                    if(strpos($log['action'], 'REFUND') !== false) $badgeColor = 'bg-red-50 text-red-600 border-red-100';
                                    if(strpos($log['action'], 'PIX') !== false) $badgeColor = 'bg-emerald-50 text-emerald-600 border-emerald-100';
                                    if(strpos($log['action'], 'LOGIN') !== false) $badgeColor = 'bg-blue-50 text-blue-600 border-blue-100';
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 md:px-8 py-5 font-mono text-xs font-bold text-slate-400 whitespace-nowrap">
                                        <?= date('H:i:s', strtotime($log['timestamp'])) ?>
                                    </td>
                                    <td class="px-6 md:px-8 py-5 whitespace-nowrap">
                                        <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($log['operator_name'] ?: 'Sistema') ?></span>
                                    </td>
                                    <td class="px-6 md:px-8 py-5 whitespace-nowrap">
                                        <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase border <?= $badgeColor ?>">
                                            <?= str_replace('_', ' ', $log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 md:px-8 py-5 min-w-[200px]">
                                        <p class="text-xs text-slate-600 leading-relaxed"><?= htmlspecialchars($log['description']) ?></p>
                                        <?php 
                                        $impactData = json_decode($log['impact'], true);
                                        $impactMsg = is_array($impactData) ? ($impactData['message'] ?? '') : $log['impact'];
                                        if(!empty($impactMsg)): ?>
                                            <div class="mt-1 text-[9px] font-mono text-slate-400 bg-slate-50 p-1 rounded italic w-fit">
                                                Impacto: <?= htmlspecialchars($impactMsg) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 md:px-8 py-5 text-right font-mono text-[10px] text-slate-400 whitespace-nowrap">
                                        <?= $log['ip_address'] ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 h-[70px] flex items-center justify-around z-50">
        <a href="pos.php" class="flex flex-col items-center gap-1 p-2 text-slate-400">
            <i data-lucide="credit-card" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Venda</span>
        </a>
        <a href="history.php" class="flex flex-col items-center gap-1 p-2 text-slate-400">
            <i data-lucide="list" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Histórico</span>
        </a>
        <a href="products.php" class="flex flex-col items-center gap-1 p-2 text-slate-400">
            <i data-lucide="package" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Produtos</span>
        </a>
        <a href="dashboard.php" class="flex flex-col items-center gap-1 p-2 text-emerald-600">
            <i data-lucide="settings" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Gestão</span>
        </a>
    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>