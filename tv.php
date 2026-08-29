<?php
// Public TV Panel for Canteen Orders
require_once __DIR__ . '/config/db.php';

// Fetch settings for branding
$stmtConfig = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('school_name', 'logo_url')");
$settings = $stmtConfig->fetchAll(PDO::FETCH_KEY_PAIR);
$schoolName = $settings['school_name'] ?? 'Minha Escola';
$logoUrl = $settings['logo_url'] ?? '';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Chamada - <?= htmlspecialchars($schoolName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background-color: #0f172a; color: white; overflow: hidden; font-family: system-ui, -apple-system, sans-serif; }
        
        @keyframes slideIn {
            from { transform: translateX(50px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .order-card {
            animation: slideIn 0.3s ease-out forwards;
        }

        /* Hide scrollbar for TV */
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="h-screen w-screen flex flex-col">

    <!-- Header -->
    <header class="bg-slate-900 border-b border-slate-800 p-6 flex justify-between items-center shadow-lg z-10">
        <div class="flex items-center gap-4">
            <?php if ($logoUrl): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="h-16 w-auto object-contain bg-white rounded-xl p-1">
            <?php else: ?>
                <div class="w-16 h-16 bg-emerald-500 rounded-xl flex items-center justify-center">
                    <i data-lucide="school" class="w-8 h-8 text-white"></i>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="text-4xl font-black tracking-tight text-white"><?= htmlspecialchars($schoolName) ?></h1>
                <p class="text-emerald-400 font-bold text-xl uppercase tracking-widest mt-1">Cantina Escolar</p>
            </div>
        </div>
        <div class="flex items-center gap-3 bg-slate-800 px-6 py-3 rounded-2xl border border-slate-700">
            <i data-lucide="clock" class="text-slate-400 w-8 h-8"></i>
            <span id="currentTime" class="text-4xl font-black text-slate-100 font-mono">00:00</span>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex p-8 gap-8 bg-slate-900/50 relative">
        
        <!-- Coluna: Em Preparo -->
        <div class="flex-1 flex flex-col border-r border-slate-700/50 pr-8">
            <h2 class="text-4xl font-black text-slate-400 uppercase tracking-tight flex items-center justify-center gap-4 mb-8">
                <i data-lucide="chef-hat" class="w-10 h-10"></i> Em Preparo
            </h2>
            <div id="pendingContainer" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 overflow-y-hidden content-start">
                <!-- Loading State -->
                <div class="col-span-full flex flex-col items-center justify-center py-20 text-slate-500">
                    <i data-lucide="loader-2" class="w-12 h-12 animate-spin mb-4"></i>
                    <p class="text-xl font-bold">Carregando...</p>
                </div>
            </div>
        </div>

        <!-- Coluna: Prontos -->
        <div class="flex-1 flex flex-col pl-8">
            <h2 class="text-5xl font-black text-emerald-400 uppercase tracking-tight flex items-center justify-center gap-4 mb-8">
                <i data-lucide="bell-ring" class="w-12 h-12"></i> Prontos para Retirar
            </h2>
            <div id="preparedContainer" class="flex flex-col gap-5 overflow-y-hidden">
                <!-- Loading State -->
                <div class="flex flex-col items-center justify-center py-20 text-slate-500">
                    <i data-lucide="loader-2" class="w-12 h-12 animate-spin mb-4"></i>
                    <p class="text-xl font-bold">Carregando...</p>
                </div>
            </div>
        </div>

    </main>


    <!-- Notification Sound -->
    <audio id="bellSound" preload="auto">
        <!-- Using a base64 encoded simple bell sound or public URL -->
        <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
    </audio>

    <script>
        lucide.createIcons();

        // Clock
        setInterval(() => {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
        }, 1000);

        // Audio Setup
        const bellSound = document.getElementById('bellSound');

        // Start polling immediately on load
        startPolling();

        // Polling Logic
        let knownOrderIds = new Set();
        
        async function fetchOrders() {
            try {
                const response = await fetch('api/tv_data.php');
                const json = await response.json();
                
                if (json.success) {
                    updateUI(json.data);
                }
            } catch (error) {
                console.error("Erro ao buscar dados da TV:", error);
            }
        }

        function updateUI(orders) {
            const pendingContainer = document.getElementById('pendingContainer');
            const preparedContainer = document.getElementById('preparedContainer');
            
            const pending = orders.filter(o => o.delivery_status === 'PENDING');
            const prepared = orders.filter(o => o.delivery_status === 'PREPARED');
            
            // Check for new PREPARED orders to play sound
            let hasNewOrder = false;
            let currentPreparedIds = new Set(prepared.map(o => o.order_id));
            
            for (let id of currentPreparedIds) {
                if (!knownOrderIds.has(id)) {
                    hasNewOrder = true;
                    break;
                }
            }
            
            if (hasNewOrder) {
                bellSound.currentTime = 0;
                bellSound.play().catch(e => console.log('Autoplay prevented', e));
            }
            
            knownOrderIds = currentPreparedIds;

            // Render Pending
            if (pending.length === 0) {
                pendingContainer.innerHTML = `<div class="text-slate-600 text-center py-10 font-bold text-xl col-span-full">Nenhum pedido na fila</div>`;
            } else {
                pendingContainer.innerHTML = pending.map(order => `
                    <div class="bg-slate-800/60 rounded-xl p-3 border-b-2 border-slate-600 flex flex-col items-center justify-center text-center gap-1 aspect-[4/3]">
                        <div class="text-slate-500 font-black text-xl">#${String(order.order_id).padStart(4, '0')}</div>
                        <h3 class="text-sm font-black text-slate-200 truncate w-full px-1">${order.display_name}</h3>
                    </div>
                `).join('');
            }

            // Render Prepared
            if (prepared.length === 0) {
                preparedContainer.innerHTML = `<div class="text-slate-600 text-center py-20 font-bold text-2xl flex flex-col items-center gap-4"><i data-lucide="coffee" class="w-16 h-16 opacity-50"></i> Aguardando pedidos...</div>`;
            } else {
                preparedContainer.innerHTML = prepared.map(order => `
                    <div class="order-card bg-slate-800 rounded-3xl p-6 border-l-8 border-emerald-500 shadow-xl flex items-center gap-5">
                        ${order.avatar_url 
                            ? `<img src="${order.avatar_url}" class="w-20 h-20 rounded-full object-cover border-4 border-slate-700 bg-white">` 
                            : `<div class="w-20 h-20 rounded-full bg-slate-700 flex items-center justify-center border-4 border-slate-600"><i data-lucide="user" class="w-10 h-10 text-slate-400"></i></div>`
                        }
                        <div class="flex-1 min-w-0">
                            <h3 class="text-4xl font-black text-white truncate leading-tight">${order.display_name}</h3>
                            <p class="text-xl font-bold text-emerald-400 uppercase tracking-widest mt-1 truncate">${order.class_name || 'Sem Turma'}</p>
                        </div>
                        <div class="text-emerald-500/20 font-black text-4xl">#${String(order.order_id).padStart(4, '0')}</div>
                    </div>
                `).join('');
            }
            
            lucide.createIcons();
        }

        function startPolling() {
            fetchOrders(); // Initial fetch
            setInterval(fetchOrders, 3000); // Poll every 3 seconds
        }
    </script>
</body>
</html>
