<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');

// --- CONFIGURAÇÃO DA TAXA MERCADO PAGO ---
// Atualizado para 0.99% conforme a página do Mercado Livre/Pago
$mpTaxaPorcentagem = 0.99; 

// Busca configurações para verificar Provedor e Nome da Escola
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$isMercadoPago = ($settings['payment_provider'] ?? '') === 'MERCADO_PAGO';

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

// --- LÓGICA DE FILTROS ---
$range = $_GET['range'] ?? 'hoje';
$todayPHP = date('Y-m-d'); 
$dateFilter = "";

switch ($range) {
    case '7d': $dateFilter = "AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; $rangeLabel = "7 Dias"; break;
    case 'mes': $dateFilter = "AND MONTH(timestamp) = MONTH(CURRENT_DATE()) AND YEAR(timestamp) = YEAR(CURRENT_DATE())"; $rangeLabel = "Mês Atual"; break;
    case 'tudo': $dateFilter = ""; $rangeLabel = "Todo o Período"; break;
    default: $dateFilter = "AND DATE(timestamp) = '$todayPHP'"; $rangeLabel = "Hoje"; break;
}

// 1. DADOS FINANCEIROS GERAIS
$statsQuery = "SELECT 
    SUM(CASE WHEN type = 'PURCHASE' AND status = 'COMPLETED' AND payment_method = 'NFC' THEN ABS(amount) ELSE 0 END) as total_nfc,
    SUM(CASE WHEN type = 'PURCHASE' AND status = 'COMPLETED' AND payment_method = 'CASH' THEN ABS(amount) ELSE 0 END) as total_cash,
    SUM(CASE WHEN type IN ('DEPOSIT', 'RECHARGE') AND status = 'COMPLETED' THEN amount ELSE 0 END) as total_recharges_gross,
    SUM(CASE WHEN type IN ('DEPOSIT', 'RECHARGE') AND status = 'REFUNDED' THEN amount ELSE 0 END) as total_refunds,
    COUNT(CASE WHEN type = 'PURCHASE' AND status = 'COMPLETED' THEN 1 END) as sales_count
    FROM transactions 
    WHERE 1=1 $dateFilter";

$data = $pdo->query($statsQuery)->fetch();

$totalNfc = $data['total_nfc'] ?: 0;
$totalCash = $data['total_cash'] ?: 0;
$salesTotal = $totalNfc + $totalCash;
$rechargesGross = $data['total_recharges_gross'] ?: 0;
$refundsTotal = $data['total_refunds'] ?: 0;

// --- CÁLCULO DAS TAXAS MP (0,99%) ---
$taxaMPValor = 0;
if ($isMercadoPago && $rechargesGross > 0) {
    // Cálculo: Bruto * 0.0099
    $taxaMPValor = $rechargesGross * ($mpTaxaPorcentagem / 100);
}

// Recarga líquida considera: Bruto - Estornos - Taxas do MP
$rechargesNet = $rechargesGross - $refundsTotal - $taxaMPValor; 

$salesCount = $data['sales_count'] ?: 0;
$ticketMedio = ($salesCount > 0) ? ($salesTotal / $salesCount) : 0;
$custodyTotal = $pdo->query("SELECT SUM(balance) FROM nfc_tags WHERE status = 'ACTIVE'")->fetchColumn() ?: 0;

// Saldo Líquido do período: Entradas Reais - Vendas Realizadas
$saldoLiquido = $rechargesNet - $salesTotal;

// 2. TOP 5 PRODUTOS
$topProductsQuery = "SELECT product_name, SUM(qty) as total_qty 
    FROM transaction_items ti 
    JOIN transactions t ON ti.transaction_id = t.id 
    WHERE t.status = 'COMPLETED' $dateFilter
    GROUP BY product_name ORDER BY total_qty DESC LIMIT 5";
$topProducts = $pdo->query($topProductsQuery)->fetchAll();

// 3. HORÁRIOS DE PICO
$peakHoursQuery = "SELECT HOUR(timestamp) as hr, COUNT(*) as qty 
    FROM transactions WHERE type='PURCHASE' AND status='COMPLETED' $dateFilter 
    GROUP BY hr ORDER BY hr";
$peakHoursRaw = $pdo->query($peakHoursQuery)->fetchAll(PDO::FETCH_KEY_PAIR);
$peakHoursData = [];
for($i=7; $i<=18; $i++) { $peakHoursData[$i . "h"] = $peakHoursRaw[$i] ?? 0; }

// 4. DISTRIBUIÇÃO DE SALDO
$balanceDist = $pdo->query("SELECT 
    COUNT(CASE WHEN balance <= 10 THEN 1 END) as baixo,
    COUNT(CASE WHEN balance > 10 AND balance <= 50 THEN 1 END) as medio,
    COUNT(CASE WHEN balance > 50 THEN 1 END) as alto
    FROM nfc_tags WHERE status='ACTIVE'")->fetch();

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

        <main class="flex-1 overflow-y-auto p-4 md:p-8 lg:p-12 pb-[110px] md:pb-12">
            <div class="max-w-7xl mx-auto space-y-8">
                
                <header class="hidden md:flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800 flex items-center gap-3">
                            <i data-lucide="bar-chart-big" class="text-emerald-500"></i> Painel de Gestão
                        </h1>
                        <p class="text-slate-500 mt-1">Dados em tempo real • Horário Local: <?= date('H:i') ?></p>
                    </div>
                    <div class="flex bg-white border border-slate-200 rounded-xl p-1 shadow-sm">
                        <?php foreach(['hoje' => 'Hoje', '7d' => '7 Dias', 'mes' => 'Mês', 'tudo' => 'Tudo'] as $k => $v): ?>
                            <a href="?range=<?= $k ?>" class="px-4 py-2 text-xs font-bold <?= $range === $k ? 'bg-emerald-50 text-emerald-700 rounded-lg' : 'text-slate-500' ?>"><?= $v ?></a>
                        <?php endforeach; ?>
                    </div>
                </header>

                <div class="md:hidden flex bg-white border border-slate-200 rounded-xl p-1 shadow-sm overflow-x-auto shrink-0">
                    <?php foreach(['hoje' => 'Hoje', '7d' => '7 Dias', 'mes' => 'Mês', 'tudo' => 'Tudo'] as $k => $v): ?>
                        <a href="?range=<?= $k ?>" class="flex-1 text-center px-2 py-2 text-xs font-bold whitespace-nowrap <?= $range === $k ? 'bg-emerald-50 text-emerald-700 rounded-lg' : 'text-slate-500' ?>"><?= $v ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 md:gap-6">
                    <div class="bg-white p-6 rounded-[2rem] shadow-sm border-l-4 border-blue-500">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Vendas Totais</p>
                        <h3 class="text-2xl font-black text-slate-800">R$ <?= number_format($salesTotal, 2, ',', '.') ?></h3>
                        <div class="mt-2 text-[9px] font-bold text-slate-400">NFC: R$ <?= number_format($totalNfc, 2, ',', '.') ?> | DIN: R$ <?= number_format($totalCash, 2, ',', '.') ?></div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-[2rem] shadow-sm border-l-4 border-emerald-500">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Recargas (Líquido)</p>
                        <h3 class="text-2xl font-black text-slate-800">R$ <?= number_format($rechargesNet, 2, ',', '.') ?></h3>
                        <div class="mt-2 flex flex-col gap-0.5">
                            <?php if($refundsTotal > 0): ?>
                                <span class="text-[9px] font-bold text-red-400 uppercase">Estornos: -R$ <?= number_format($refundsTotal, 2, ',', '.') ?></span>
                            <?php endif; ?>
                            <?php if($taxaMPValor > 0): ?>
                                <span class="text-[9px] font-bold text-blue-400 uppercase">Taxa MP (0.99%): -R$ <?= number_format($taxaMPValor, 2, ',', '.') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-[2rem] shadow-sm border-l-4 border-amber-500">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Ticket Médio</p>
                        <h3 class="text-2xl font-black text-slate-800">R$ <?= number_format($ticketMedio, 2, ',', '.') ?></h3>
                    </div>
                    <div class="bg-white p-6 rounded-[2rem] shadow-sm border-l-4 border-indigo-500">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Saldo nas Tags</p>
                        <h3 class="text-2xl font-black text-slate-800">R$ <?= number_format($custodyTotal, 2, ',', '.') ?></h3>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 bg-white rounded-[2.5rem] shadow-sm border p-6 md:p-8">
                        <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2"><i data-lucide="arrow-left-right" class="w-4 h-4 text-blue-500"></i> Fluxo: Vendas vs Recargas</h3>
                        <div class="h-64 md:h-72"><canvas id="flowChart"></canvas></div>
                    </div>
                    <div class="bg-white rounded-[2.5rem] shadow-sm border p-6 md:p-8 flex flex-col justify-center">
                        <h3 class="font-bold text-slate-800 mb-6 text-[10px] uppercase tracking-widest">Saldo Líquido (Período)</h3>
                        <div class="mb-6">
                            <span class="text-3xl font-black <?= $saldoLiquido >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">R$ <?= number_format($saldoLiquido, 2, ',', '.') ?></span>
                            <div class="w-full bg-slate-100 h-1.5 rounded-full mt-4 overflow-hidden"><div class="<?= $saldoLiquido >= 0 ? 'bg-emerald-500' : 'bg-red-500' ?> h-full" style="width: 100%"></div></div>
                        </div>
                        <ul class="space-y-4 border-t pt-6">
                            <li class="flex justify-between text-xs"><span>Entradas (Líquido)</span><span class="font-bold text-slate-800">R$ <?= number_format($rechargesNet, 2, ',', '.') ?></span></li>
                            <li class="flex justify-between text-xs"><span>Vendas</span><span class="font-bold text-slate-800 text-red-500">- R$ <?= number_format($salesTotal, 2, ',', '.') ?></span></li>
                        </ul>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="bg-white rounded-[2.5rem] shadow-sm border p-6 md:p-8">
                        <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2"><i data-lucide="layers" class="w-4 h-4 text-indigo-500"></i> Detalhamento por Método</h3>
                        <div class="h-64"><canvas id="detailChart"></canvas></div>
                    </div>
                    <div class="bg-white rounded-[2.5rem] shadow-sm border p-6 md:p-8">
                        <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2"><i data-lucide="pizza" class="w-4 h-4 text-orange-500"></i> Top 5 Itens Mais Vendidos</h3>
                        <div class="h-64"><canvas id="productsChart"></canvas></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="bg-white rounded-[2.5rem] shadow-sm border p-6 md:p-8">
                        <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2"><i data-lucide="clock" class="w-4 h-4 text-emerald-500"></i> Vendas por Horário (Pico)</h3>
                        <div class="h-64"><canvas id="hoursChart"></canvas></div>
                    </div>
                    <div class="bg-white rounded-[2.5rem] shadow-sm border p-6 md:p-8">
                        <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2"><i data-lucide="pie-chart" class="w-4 h-4 text-purple-500"></i> Alunos por Faixa de Saldo</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 items-center gap-6">
                            <div class="h-48"><canvas id="balanceChart"></canvas></div>
                            <div class="space-y-3">
                                <div class="p-3 bg-red-50 rounded-xl text-[10px] font-bold text-red-700 flex justify-between border border-red-100"><span>R$ 0-10</span><span><?= $balanceDist['baixo'] ?> alunos</span></div>
                                <div class="p-3 bg-blue-50 rounded-xl text-[10px] font-bold text-blue-700 flex justify-between border border-blue-100"><span>R$ 11-50</span><span><?= $balanceDist['medio'] ?> alunos</span></div>
                                <div class="p-3 bg-emerald-50 rounded-xl text-[10px] font-bold text-emerald-700 flex justify-between border border-emerald-100"><span>R$ 51+</span><span><?= $balanceDist['alto'] ?> alunos</span></div>
                            </div>
                        </div>
                    </div>
                </div>

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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    lucide.createIcons();

    const currencyFormatter = (value) => {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
    };

    const currencyTooltip = {
        callbacks: {
            label: function(context) {
                let label = context.dataset.label || '';
                if (label) label += ': ';
                if (context.parsed.y !== null) label += currencyFormatter(context.parsed.y);
                return label;
            }
        }
    };

    new Chart(document.getElementById('flowChart'), {
        type: 'bar',
        data: {
            labels: [<?= json_encode($rangeLabel) ?>],
            datasets: [
                { label: 'Recargas (Líq)', data: [<?= $rechargesNet ?>], backgroundColor: '#10b981', borderRadius: 12, barThickness: 40 },
                { label: 'Vendas', data: [<?= $salesTotal ?>], backgroundColor: '#3b82f6', borderRadius: 12, barThickness: 40 }
            ]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { tooltip: currencyTooltip }
        }
    });

    new Chart(document.getElementById('detailChart'), {
        type: 'bar',
        data: {
            labels: [<?= json_encode($rangeLabel) ?>],
            datasets: [
                { label: 'Recargas (Líq)', data: [<?= $rechargesNet ?>], backgroundColor: '#8b5cf6', borderRadius: 8 },
                { label: 'Vendas NFC', data: [<?= $totalNfc ?>], backgroundColor: '#10b981', borderRadius: 8 },
                { label: 'Vendas CASH', data: [<?= $totalCash ?>], backgroundColor: '#f59e0b', borderRadius: 8 }
            ]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { tooltip: currencyTooltip }
        }
    });

    new Chart(document.getElementById('productsChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($topProducts, 'product_name')) ?>,
            datasets: [{ label: 'Qtd', data: <?= json_encode(array_column($topProducts, 'total_qty')) ?>, backgroundColor: '#f97316', borderRadius: 8 }]
        },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false }
    });

    new Chart(document.getElementById('hoursChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_keys($peakHoursData)) ?>,
            datasets: [{ label: 'Vendas', data: <?= json_encode(array_values($peakHoursData)) ?>, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', fill: true, tension: 0.4 }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    new Chart(document.getElementById('balanceChart'), {
        type: 'doughnut',
        data: {
            labels: ['0-10', '11-50', '51+'],
            datasets: [{ data: [<?= $balanceDist['baixo'] ?>, <?= $balanceDist['medio'] ?>, <?= $balanceDist['alto'] ?>], backgroundColor: ['#ef4444', '#3b82f6', '#10b981'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
</script>
</body>
</html>