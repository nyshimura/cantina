<?php
// views/admin/pos.php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');

// Configurações
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$enableCash = $settings['enable_cash_payment'] ?? '1';

// Dados da Escola (Cabeçalho Dinâmico)
$schoolName = $settings['school_name'] ?? 'Escola Estadual Modelo';
$schoolLogo = $settings['logo_url'] ?? '';

// Busca Produtos e Categorias
try {
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            WHERE p.active = 1 
            ORDER BY c.name, p.name";
    $products = $pdo->query($sql)->fetchAll();
    $categories = array_unique(array_column($products, 'category_name'));
} catch (PDOException $e) {
    die("Erro: Verifique se a tabela 'categories' existe.");
}

require __DIR__ . '/../../includes/header.php';
?>

<div id="toastContainer" class="fixed top-4 left-0 right-0 z-[100] flex flex-col items-center gap-2 pointer-events-none px-4"></div>

<a href="../../views/logout.php" class="md:hidden fixed top-3 right-4 z-[60] bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-md hover:bg-slate-800 transition-colors">
    Sair
</a>

<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50 font-sans">
    
    <div class="bg-white border-b border-slate-100 px-4 md:px-8 py-3 flex items-center justify-between shadow-sm z-20 shrink-0 h-16 md:h-20">
        <div class="flex items-center gap-3">
            <?php if (!empty($schoolLogo)): ?>
                <img src="<?= htmlspecialchars($schoolLogo) ?>" alt="Logo" class="w-10 h-10 rounded-xl object-contain bg-white shrink-0 border border-slate-100 shadow-sm">
            <?php else: ?>
                <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center text-orange-600 shrink-0">
                    <i data-lucide="store" class="w-6 h-6"></i>
                </div>
            <?php endif; ?>
            
            <div>
                <h1 class="font-black text-slate-800 leading-tight text-sm md:text-base"><?= htmlspecialchars($schoolName) ?></h1>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">PDV & Gestão</p>
            </div>
        </div>

        <div class="hidden md:flex bg-slate-100 p-1 rounded-xl items-center gap-1">
            <a href="pos.php" class="px-4 py-2 rounded-lg text-sm font-bold bg-white text-emerald-600 shadow-sm transition-all">PDV</a>
            <button onclick="showShiftSummary()" class="px-4 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 transition-all flex items-center gap-1"><i data-lucide="calculator" class="w-4 h-4"></i> Resumo</button>
            <a href="history.php" class="px-4 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-white/50 transition-all">Histórico</a>
            <a href="products.php" class="px-4 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-white/50 transition-all">Catálogo</a>
            <a href="dashboard.php" class="px-4 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-white/50 transition-all">Gestão</a>
        </div>

        <div class="hidden md:flex items-center gap-4">
            <div class="text-right hidden lg:block">
                <p class="text-xs font-bold text-slate-800"><?= explode(' ', $_SESSION['name'] ?? 'Operador')[0] ?></p>
                <p class="text-[10px] text-emerald-500 font-bold uppercase">Online</p>
            </div>
            <a href="../../views/logout.php" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-800 transition-all shadow-lg shadow-slate-200">
                Sair
            </a>
        </div>
    </div>

    <main class="flex-1 flex overflow-hidden flex-col md:flex-row relative pb-[70px] md:pb-0">
        
        <div class="flex-1 flex flex-col overflow-hidden md:border-r border-slate-200 order-1 h-full">
            
            <div class="px-4 py-3 md:p-6 bg-white border-b border-slate-100 flex flex-col gap-3 shrink-0 z-10">
                <div class="relative w-full">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="searchInput" placeholder="Buscar produto..." 
                           class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-medium">
                </div>
                <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide -mx-4 px-4 md:mx-0 md:px-0">
                    <button onclick="filterCategory('all')" class="category-btn px-4 py-2 rounded-full text-xs font-bold bg-emerald-600 text-white shadow-md shadow-emerald-200 transition-all active:scale-95 shrink-0">Todos</button>
                    <?php foreach($categories as $cat): ?>
                    <button onclick="filterCategory('<?= htmlspecialchars($cat) ?>')" 
                            class="category-btn px-4 py-2 rounded-full text-xs font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all active:scale-95 whitespace-nowrap shrink-0">
                        <?= htmlspecialchars($cat) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex-1 p-4 md:p-6 overflow-y-auto bg-slate-50 scroll-smooth pb-24 md:pb-6">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 md:gap-5">
                    <?php foreach($products as $p): ?>
                    <div class="product-card bg-white p-3 rounded-2xl shadow-sm border border-slate-100 hover:shadow-lg hover:border-emerald-200 cursor-pointer transition-all active:scale-95 group flex flex-col h-full relative overflow-hidden"
                         data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>" 
                         data-category="<?= htmlspecialchars($p['category_name']) ?>"
                         onclick="addToCart(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>', <?= $p['price'] ?>)">
                        
                        <div class="aspect-square mb-3 rounded-xl overflow-hidden bg-slate-50 relative">
                            <img src="<?= htmlspecialchars($p['image_url']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" onerror="this.src='https://via.placeholder.com/150?text=Item'">
                            <div class="absolute bottom-2 right-2 bg-white text-emerald-600 w-8 h-8 rounded-full flex items-center justify-center shadow-md">
                                <i data-lucide="plus" class="w-5 h-5"></i>
                            </div>
                        </div>
                        
                        <h3 class="font-bold text-slate-800 text-xs line-clamp-2 leading-tight mb-auto h-8"><?= htmlspecialchars($p['name']) ?></h3>
                        <p class="text-emerald-600 font-black text-sm mt-2">R$ <?= number_format($p['price'], 2, ',', '.') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="mobileCartTrigger" onclick="toggleMobileCart()" class="md:hidden fixed bottom-[80px] left-4 right-4 bg-slate-900 text-white p-4 rounded-2xl shadow-2xl z-40 flex items-center justify-between cursor-pointer transition-transform hover:scale-[1.02] active:scale-95 border border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-slate-700 rounded-full flex items-center justify-center text-white font-black text-xs" id="mobileCountBadge">0</div>
                <span class="font-bold text-sm">Ver Carrinho</span>
            </div>
            <span class="font-black text-lg" id="mobileTotalDisplay">R$ 0,00</span>
        </div>

        <div id="cartPanel" class="fixed md:static inset-0 z-50 md:z-auto bg-slate-900/50 md:bg-transparent transition-opacity opacity-0 pointer-events-none md:opacity-100 md:pointer-events-auto md:w-[24rem] flex flex-col md:h-full md:border-l border-slate-200 bg-white md:shadow-xl order-2">
            
            <div id="cartContent" class="absolute bottom-0 left-0 right-0 md:static bg-white w-full md:h-full rounded-t-[2rem] md:rounded-none shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.3)] md:shadow-none flex flex-col transition-transform transform translate-y-full md:translate-y-0 h-[85vh] md:h-auto overflow-hidden relative duration-300 ease-out">
                
                <div id="successOverlay" class="absolute inset-0 z-[60] bg-emerald-500 flex flex-col items-center justify-center text-white hidden animate-in fade-in zoom-in-95 duration-300">
                    <div class="bg-white/20 p-6 rounded-full mb-6 animate-bounce shadow-2xl">
                        <i data-lucide="check" class="w-12 h-12 text-white"></i>
                    </div>
                    <h2 class="text-3xl font-black mb-1 tracking-tight">VENDA APROVADA!</h2>
                    <p class="text-emerald-100 text-xs font-bold uppercase tracking-widest mb-8" id="successStudentName">Nome do Aluno</p>
                    
                    <div class="bg-white/10 px-8 py-6 rounded-3xl border border-white/20 backdrop-blur-sm">
                        <span class="text-5xl font-black tracking-tighter" id="successAmount">R$ 0,00</span>
                    </div>
                    <p class="text-emerald-200 text-[10px] font-bold uppercase tracking-widest mt-8 animate-pulse">Redirecionando...</p>
                </div>

                <div id="swipeHandle" class="md:hidden w-full flex justify-center pt-3 pb-1 cursor-grab active:cursor-grabbing touch-none">
                    <div class="w-12 h-1.5 bg-slate-300 rounded-full"></div>
                </div>

                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-white sticky top-0 z-10">
                    <h2 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <i data-lucide="shopping-bag" class="text-emerald-500 w-5 h-5"></i> Venda Atual
                    </h2>
                    <div class="flex gap-2">
                        <button onclick="toggleMobileCart()" class="md:hidden p-2 text-slate-400 hover:text-slate-600 bg-slate-50 rounded-lg"><i data-lucide="chevron-down" class="w-5 h-5"></i></button>
                        <button onclick="clearCart()" class="text-[10px] font-bold text-red-400 uppercase tracking-widest hover:text-red-600 hover:bg-red-50 px-3 py-2 rounded-lg transition-colors bg-slate-50">Limpar</button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/50" id="cartContainer">
                    <div class="h-full flex flex-col items-center justify-center text-slate-300 gap-4 opacity-50">
                        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center">
                            <i data-lucide="scan-barcode" class="w-8 h-8"></i>
                        </div>
                        <p class="text-xs font-bold uppercase tracking-widest">Carrinho vazio</p>
                    </div>
                </div>

                <div class="p-6 bg-white border-t border-slate-100 pb-8 md:pb-6">
                    <div class="flex justify-between items-end mb-6">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total a Pagar</span>
                        <span class="text-4xl font-black text-slate-900 tracking-tighter" id="cartTotalDisplay">R$ 0,00</span>
                    </div>

                    <div class="bg-white border-2 border-dashed border-slate-200 rounded-2xl p-4 relative group hover:border-emerald-200 transition-colors">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center mb-3">Aproxime a Tag NFC ou Digite ID</p>
                        
                        <div class="flex gap-2">
                            <input type="text" id="nfcInput" 
                                   class="flex-1 bg-slate-50 border border-slate-200 text-slate-800 rounded-xl py-3 px-4 outline-none font-bold text-center text-sm focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all uppercase placeholder:text-slate-300" 
                                   placeholder="TAG-ID" autocomplete="off">
                            
                            <button id="btnMobileNfc" onclick="startNFCScan()" class="bg-blue-500 text-white w-14 rounded-xl flex items-center justify-center shadow-lg shadow-blue-200 hover:bg-blue-600 active:scale-95 transition-all" title="Ler com NFC do Celular">
                                <i data-lucide="scan" class="w-5 h-5"></i>
                            </button>
                            
                            <button onclick="processPayment(event)" class="bg-emerald-500 text-white w-14 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-200 hover:bg-emerald-600 active:scale-95 transition-all">
                                <span class="font-bold text-xs">OK</span>
                            </button>
                        </div>

                        <?php if($enableCash == '1'): ?>
                        <button onclick="openCashModal()" class="w-full mt-3 py-3 text-xs font-bold text-slate-500 bg-slate-50 rounded-xl hover:bg-slate-100 transition-colors flex items-center justify-center gap-2 border border-slate-100">
                            <i data-lucide="banknote" class="w-4 h-4 text-emerald-500"></i> Pagamento em Dinheiro
                        </button>
                        <?php endif; ?>
                    </div>
                    <div id="paymentStatus" class="hidden mt-3 p-2 rounded-xl text-center text-[10px] font-bold transition-all"></div>
                </div>
            </div>
        </div>
    </main>

    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 h-[70px] flex items-center justify-around z-50 shadow-[0_-5px_20px_rgba(0,0,0,0.05)] px-2">
        <a href="pos.php" class="flex flex-col items-center gap-1 p-2 text-emerald-600">
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
        <a href="dashboard.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="settings" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Gestão</span>
        </a>
    </div>

</div>

<div id="modalCash" class="fixed inset-0 bg-slate-900/80 hidden items-center justify-center p-4 z-[80] backdrop-blur-sm transition-all duration-300">
    <div class="bg-white rounded-[2.5rem] w-full max-w-sm p-8 shadow-2xl text-center relative overflow-hidden animate-in zoom-in duration-200">
        <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-emerald-400 to-teal-500"></div>
        <button onclick="closeCashModal()" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button>

        <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-sm">
            <i data-lucide="banknote" class="w-8 h-8"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800 tracking-tight mb-1">Pagamento em Dinheiro</h3>
        <p class="text-slate-400 text-xs font-medium mb-8">Digite o valor recebido do cliente.</p>
        
        <div class="mb-8">
            <div class="relative group">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 font-black text-slate-300 text-xl group-focus-within:text-emerald-500 transition-colors">R$</span>
                <input type="number" id="cashReceived" oninput="calculateChange()" step="0.05" 
                       class="w-full pl-14 pr-6 py-5 bg-slate-50 border-2 border-slate-100 rounded-3xl outline-none focus:border-emerald-500 focus:bg-white font-black text-3xl text-slate-800 transition-all placeholder:text-slate-200" placeholder="0,00">
            </div>
            <div id="changeDisplay" class="mt-4 p-4 rounded-2xl bg-slate-50 border border-dashed border-slate-200 text-slate-400 font-bold text-sm transition-all">
                Aguardando valor...
            </div>
        </div>

        <button id="btnConfirmCash" disabled onclick="processCashPayment()" class="w-full bg-slate-900 text-white py-4 rounded-xl font-bold shadow-xl hover:bg-slate-800 disabled:opacity-50 disabled:shadow-none transition-all flex items-center justify-center gap-2">
            Confirmar Pagamento
        </button>
    </div>
</div>

<div id="modalPin" class="fixed inset-0 bg-slate-900/90 hidden items-center justify-center p-4 z-[90] backdrop-blur-md transition-all duration-300">
    <div class="bg-white rounded-[2.5rem] w-full max-w-sm p-8 text-center relative overflow-hidden animate-in zoom-in duration-200 shadow-2xl">
        <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-red-400 to-rose-500"></div>
        <button onclick="closePinModal()" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button>

        <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-sm">
            <i data-lucide="lock" class="w-8 h-8"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800 tracking-tight mb-1">Senha de Compra</h3>
        <p class="text-slate-400 text-xs font-medium mb-8">Esta compra exige autenticação.</p>
        
        <div class="mb-6">
            <input type="password" id="pinInput" readonly
                   class="w-full py-4 text-center text-4xl font-black text-slate-800 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none focus:border-red-400 transition-all tracking-[0.5em] h-20 placeholder:text-slate-200" placeholder="••••">
        </div>

        <div class="grid grid-cols-3 gap-3 mb-6">
            <?php for($i=1; $i<=9; $i++): ?>
                <button onclick="appendPin('<?=$i?>')" class="h-14 rounded-xl bg-slate-50 border border-slate-100 text-xl font-bold text-slate-600 hover:bg-slate-100 active:scale-95 transition-all shadow-sm"><?=$i?></button>
            <?php endfor; ?>
            <button onclick="clearPin()" class="h-14 rounded-xl bg-red-50 border border-red-100 text-red-500 hover:bg-red-100 active:scale-95 transition-all flex items-center justify-center"><i data-lucide="trash-2" class="w-5 h-5"></i></button>
            <button onclick="appendPin('0')" class="h-14 rounded-xl bg-slate-50 border border-slate-100 text-xl font-bold text-slate-600 hover:bg-slate-100 active:scale-95 transition-all shadow-sm">0</button>
            <button onclick="confirmPin()" class="h-14 rounded-xl bg-emerald-500 text-white hover:bg-emerald-600 active:scale-95 transition-all flex items-center justify-center shadow-lg shadow-emerald-200"><i data-lucide="check" class="w-6 h-6"></i></button>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    let cart = [];
    let isMobileCartOpen = false;
    let pendingPaymentData = null; // Armazena dados enquanto pede a senha

    // --- LISTENER GLOBAL DE TECLADO (FÍSICO + F9) ---
    document.addEventListener('keydown', function(e) {
        // 1. Se o Modal de PIN estiver aberto
        const modalPin = document.getElementById('modalPin');
        if (modalPin && !modalPin.classList.contains('hidden')) {
            // Números
            if (e.key >= '0' && e.key <= '9') {
                appendPin(e.key);
                e.preventDefault();
            }
            // Enter
            if (e.key === 'Enter') {
                confirmPin();
                e.preventDefault();
            }
            // Backspace (apaga um dígito)
            if (e.key === 'Backspace') {
                const input = document.getElementById('pinInput');
                if (input.value.length > 0) {
                    input.value = input.value.slice(0, -1);
                }
                e.preventDefault();
            }
            // Esc (fecha modal)
            if (e.key === 'Escape') {
                closePinModal();
                e.preventDefault();
            }
            return; // Impede que F9 ou outras teclas globais disparem
        }

        // 2. Atalho F9 para focar na leitura de Tag (Padrão de leitores USB)
        if (e.key === 'F9') {
            e.preventDefault(); 
            const input = document.getElementById('nfcInput');
            if(window.innerWidth < 768 && !isMobileCartOpen) {
                toggleMobileCart();
            }
            input.value = ''; 
            input.focus();    
            input.classList.add('bg-yellow-100');
            setTimeout(() => input.classList.remove('bg-yellow-100'), 200);
        }
    });

    // --- TOAST FUNCTION ---
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        
        let bgClass = "bg-slate-800";
        if (type === 'error') bgClass = "bg-red-500";
        if (type === 'success') bgClass = "bg-emerald-500";
        if (type === 'warning') bgClass = "bg-amber-500"; 

        toast.className = `${bgClass} text-white px-6 py-4 rounded-2xl shadow-2xl font-bold text-sm text-center animate-in slide-in-from-top-5 fade-in duration-300 pointer-events-auto min-w-[200px] flex items-center gap-3`;
        
        let icon = '';
        if(type === 'error') icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
        if(type === 'warning') icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
        
        toast.innerHTML = icon + '<span>' + message + '</span>';
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('fade-out', 'slide-out-to-top-5');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // --- SWIPE LOGIC ---
    const handle = document.getElementById('swipeHandle');
    const content = document.getElementById('cartContent');
    let startY = 0;
    let currentY = 0;
    let isDragging = false;

    handle.addEventListener('touchstart', (e) => {
        startY = e.touches[0].clientY;
        isDragging = true;
        content.style.transition = 'none';
    });

    handle.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        currentY = e.touches[0].clientY;
        let deltaY = currentY - startY;
        if (deltaY > 0) {
            e.preventDefault();
            content.style.transform = `translateY(${deltaY}px)`;
        }
    });

    handle.addEventListener('touchend', (e) => {
        if (!isDragging) return;
        isDragging = false;
        content.style.transition = 'transform 0.3s ease-out';
        let deltaY = currentY - startY;
        if (deltaY > 100) {
            toggleMobileCart();
            setTimeout(() => content.style.transform = '', 300);
        } else {
            content.style.transform = '';
        }
    });

    // --- NFC NATIVO (MOBILE) ---
    async function startNFCScan() {
        const btn = document.getElementById('btnMobileNfc');
        if (!('NDEFReader' in window)) {
            showToast("Seu dispositivo ou navegador não suporta leitura NFC Web.", "error");
            return;
        }
        try {
            const ndef = new NDEFReader();
            await ndef.scan();
            btn.classList.remove('bg-blue-500', 'text-white');
            btn.classList.add('bg-amber-400', 'text-slate-900', 'animate-pulse');
            btn.innerHTML = '<i data-lucide="wifi" class="w-5 h-5"></i>';
            lucide.createIcons();
            
            ndef.onreading = event => {
                const serialNumber = event.serialNumber;
                const cleanID = serialNumber.replaceAll(":", "").toUpperCase();
                document.getElementById('nfcInput').value = cleanID;
                btn.classList.add('bg-blue-500', 'text-white');
                btn.classList.remove('bg-amber-400', 'text-slate-900', 'animate-pulse');
                btn.innerHTML = '<i data-lucide="scan" class="w-5 h-5"></i>';
                lucide.createIcons();
                initiatePaymentProcess({tagId: cleanID, paymentMethod: 'NFC'});
            };
        } catch (error) {
            console.log("Erro NFC: " + error);
            if(error.name === 'NotAllowedError') {
                showToast("Permita o acesso ao NFC nas configurações.", "error");
            } else {
                showToast("Erro ao ativar NFC: " + error.message, "error");
            }
        }
    }

    // --- UI HELPERS ---
    function toggleMobileCart() {
        const panel = document.getElementById('cartPanel');
        const content = document.getElementById('cartContent');
        if (!isMobileCartOpen) {
            panel.classList.remove('pointer-events-none', 'opacity-0');
            content.classList.remove('translate-y-full');
            isMobileCartOpen = true;
        } else {
            panel.classList.add('pointer-events-none', 'opacity-0');
            content.classList.add('translate-y-full');
            content.style.transform = ''; 
            isMobileCartOpen = false;
        }
    }

    // --- CART LOGIC ---
    function addToCart(id, name, price) {
        const item = cart.find(i => i.id === id);
        if(item) item.qty++; else cart.push({id, name, price, qty: 1});
        renderCart();
        const badge = document.getElementById('mobileCountBadge');
        if(badge) {
            badge.classList.add('scale-125', 'bg-white', 'text-slate-900');
            setTimeout(() => badge.classList.remove('scale-125', 'bg-white', 'text-slate-900'), 200);
        }
    }

    function updateQty(id, change) {
        const item = cart.find(i => i.id === id);
        if(!item) return;
        item.qty += change;
        if(item.qty <= 0) cart = cart.filter(i => i.id !== id);
        renderCart();
    }

    function removeItem(id) {
        cart = cart.filter(i => i.id !== id);
        renderCart();
    }

    function clearCart() { 
        if(cart.length === 0) return; 
        cart = []; 
        renderCart(); 
        if(isMobileCartOpen) toggleMobileCart();
        showToast("Carrinho limpo com sucesso!", "warning");
    }

    function renderCart() {
        const container = document.getElementById('cartContainer');
        const totalDisp = document.getElementById('cartTotalDisplay');
        const mobileTotal = document.getElementById('mobileTotalDisplay');
        const mobileBadge = document.getElementById('mobileCountBadge');
        
        let total = 0;
        let qtyTotal = 0;

        if(cart.length === 0) {
            container.innerHTML = `<div class="h-full flex flex-col items-center justify-center text-slate-300 gap-4 opacity-50"><div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center"><i data-lucide="scan-barcode" class="w-8 h-8"></i></div><p class="text-xs font-bold uppercase tracking-widest">Carrinho vazio</p></div>`;
        } else {
            container.innerHTML = cart.map(i => {
                total += i.price * i.qty;
                qtyTotal += i.qty;
                return `<div class="bg-white p-3 rounded-xl border border-slate-100 shadow-sm flex items-center gap-3 animate-in slide-in-from-right-4 duration-300"><div class="flex-1 min-w-0"><p class="text-xs font-bold text-slate-800 truncate mb-0.5">${i.name}</p><p class="text-[10px] text-emerald-600 font-black">R$ ${(i.price * i.qty).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p></div><div class="flex items-center gap-1 bg-slate-50 rounded-lg p-1"><button onclick="updateQty(${i.id}, -1)" class="w-7 h-7 flex items-center justify-center rounded-md bg-white border border-slate-200 text-slate-400 hover:text-red-500 hover:border-red-200 transition-colors"><i data-lucide="minus" class="w-3 h-3"></i></button><span class="w-6 text-center text-xs font-bold text-slate-700">${i.qty}</span><button onclick="updateQty(${i.id}, 1)" class="w-7 h-7 flex items-center justify-center rounded-md bg-white border border-slate-200 text-slate-400 hover:text-emerald-500 hover:border-emerald-200 transition-colors"><i data-lucide="plus" class="w-3 h-3"></i></button></div><button onclick="removeItem(${i.id})" class="text-slate-300 hover:text-red-500 ml-1"><i data-lucide="trash-2" class="w-4 h-4"></i></button></div>`;
            }).join('');
        }

        const formattedTotal = `R$ ${total.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
        totalDisp.innerText = formattedTotal;
        if(mobileTotal) mobileTotal.innerText = formattedTotal;
        if(mobileBadge) mobileBadge.innerText = qtyTotal;
        lucide.createIcons();
    }

    // --- NOVA LÓGICA DE PAGAMENTO COM PIN ---

    // 1. Inicia o processo (chamado pelo botão ou NFC)
    async function initiatePaymentProcess(data) {
        if (data.paymentMethod === 'CASH') {
            // Dinheiro não tem lógica de PIN, vai direto
            finalizePayment(data);
            return;
        }

        // Se for NFC, verifica se precisa de PIN
        const total = parseFloat(document.getElementById('cartTotalDisplay').innerText.replace(/[^\d,]/g, '').replace(',', '.'));
        
        // Armazena dados temporariamente
        pendingPaymentData = data;

        // Pergunta ao servidor
        try {
            const res = await fetch('../../api/purchase.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'check_pin_requirement',
                    tagId: data.tagId,
                    amount: total
                })
            });
            const check = await res.json();

            if (check.required) {
                // Abre Modal de PIN
                openPinModal();
            } else {
                // Não precisa, finaliza direto
                finalizePayment(data);
            }

        } catch (e) {
            showToast("Erro ao verificar segurança do cartão.", 'error');
        }
    }

    // 2. Finaliza a venda (com ou sem PIN)
    async function finalizePayment(data) {
        const status = document.getElementById('paymentStatus');
        status.classList.remove('hidden'); 
        status.innerHTML = '<i class="lucide-loader-2 animate-spin inline-block mr-2 w-3 h-3"></i> Processando...';
        status.className = 'mt-3 p-3 rounded-xl text-center text-xs font-bold bg-blue-50 text-blue-500 border border-blue-100';
        
        try {
            const res = await fetch('../../api/purchase.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({...data, cart})
            });
            const result = await res.json();
            
            if(result.success) {
                const overlay = document.getElementById('successOverlay');
                const totalText = document.getElementById('cartTotalDisplay').innerText;
                document.getElementById('successAmount').innerText = totalText;
                document.getElementById('successStudentName').innerText = result.student_name || (data.paymentMethod === 'CASH' ? "Pagamento em Dinheiro" : "Venda Realizada");
                overlay.classList.remove('hidden');
                overlay.classList.add('flex');
                
                closeCashModal();
                closePinModal(); // Fecha se estiver aberto

                setTimeout(() => {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('flex');
                    cart = []; 
                    renderCart(); 
                    if(isMobileCartOpen) toggleMobileCart();
                    status.classList.add('hidden');
                    if(window.innerWidth > 768) document.getElementById('nfcInput').focus();
                }, 3000);

            } else {
                showToast(result.message, 'error'); 
                status.innerText = result.message.toUpperCase();
                status.className = 'mt-3 p-3 rounded-xl text-center text-xs font-bold bg-red-50 text-red-600 border border-red-100 animate-shake';
                setTimeout(() => {
                    status.classList.add('hidden');
                    if(data.paymentMethod === 'NFC') document.getElementById('nfcInput').value = '';
                }, 3000);
            }
        } catch(e) { 
            showToast("Erro de conexão ao processar.", 'error');
            status.innerText = "ERRO DE CONEXÃO";
            status.className = 'mt-3 p-3 rounded-xl text-center text-xs font-bold bg-orange-50 text-orange-600 border border-orange-100';
        }
    }

    // 3. Funções do Modal de PIN
    function openPinModal() {
        document.getElementById('modalPin').classList.remove('hidden');
        document.getElementById('modalPin').classList.add('flex');
        document.getElementById('pinInput').value = '';
    }

    function closePinModal() {
        document.getElementById('modalPin').classList.add('hidden');
        document.getElementById('modalPin').classList.remove('flex');
        pendingPaymentData = null;
        document.getElementById('nfcInput').value = ''; // Limpa input principal
    }

    function appendPin(num) {
        const input = document.getElementById('pinInput');
        if (input.value.length < 6) input.value += num;
    }

    function clearPin() {
        document.getElementById('pinInput').value = '';
    }

    function confirmPin() {
        const pin = document.getElementById('pinInput').value;
        if (pin.length < 4) {
            showToast("Senha muito curta", "warning");
            return;
        }
        // Adiciona o PIN aos dados pendentes e finaliza
        if (pendingPaymentData) {
            pendingPaymentData.pin = pin;
            finalizePayment(pendingPaymentData);
        }
    }

    // --- BOTÃO PRINCIPAL (OK) ---
    async function processPayment(e) { 
        if(e) e.preventDefault(); 
        const tag = document.getElementById('nfcInput').value.trim();
        
        if(tag && cart.length === 0) {
            // Verifica se tem entrega de Reserva!
            const status = document.getElementById('paymentStatus');
            status.classList.remove('hidden');
            status.innerHTML = '<i class="lucide-loader-2 animate-spin inline-block mr-2 w-3 h-3"></i> Buscando Reservas...';
            status.className = 'mt-3 p-3 rounded-xl text-center text-xs font-bold bg-blue-50 text-blue-500 border border-blue-100';
            
            try {
                const res = await fetch('../../api/pickup_pre_order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ tagId: tag })
                });
                const data = await res.json();
                
                if (data.success && data.has_pre_order) {
                    const overlay = document.getElementById('successOverlay');
                    document.getElementById('successAmount').innerText = 'ENTREGUE';
                    document.getElementById('successStudentName').innerHTML = data.student_name + '<br><span class="text-sm text-slate-500 font-medium">' + data.items + '</span>';
                    overlay.classList.remove('hidden');
                    overlay.classList.add('flex');
                    
                    setTimeout(() => {
                        overlay.classList.add('hidden');
                        overlay.classList.remove('flex');
                        status.classList.add('hidden');
                        document.getElementById('nfcInput').value = '';
                        document.getElementById('nfcInput').focus();
                    }, 4000);
                } else if (data.success === false && data.has_pre_order === false) {
                    showToast("Nenhuma reserva pendente de entrega para hoje.", "warning");
                    status.classList.add('hidden');
                    document.getElementById('nfcInput').value = '';
                    document.getElementById('nfcInput').focus();
                } else {
                    showToast(data.message || "Erro ao buscar reservas", "error");
                    status.classList.add('hidden');
                    document.getElementById('nfcInput').value = '';
                    document.getElementById('nfcInput').focus();
                }
            } catch(err) {
                showToast("Erro de conexão.", "error");
                status.classList.add('hidden');
                document.getElementById('nfcInput').value = '';
            }
            return;
        }

        if(!tag && cart.length > 0) {
            document.getElementById('nfcInput').focus();
            return;
        }

        if(tag && cart.length > 0) initiatePaymentProcess({tagId: tag, paymentMethod: 'NFC'});
    }

    function processCashPayment() { initiatePaymentProcess({paymentMethod: 'CASH'}); }

    function openCashModal() {
        if(cart.length === 0) {
            showToast("Carrinho vazio! Adicione produtos.", "error");
            return;
        }
        document.getElementById('modalCash').classList.remove('hidden');
        document.getElementById('modalCash').classList.add('flex');
        document.getElementById('cashReceived').value = '';
        document.getElementById('changeDisplay').innerText = 'Aguardando valor...';
        document.getElementById('btnConfirmCash').disabled = true;
        setTimeout(() => document.getElementById('cashReceived').focus(), 100);
    }
    
    function closeCashModal() { 
        document.getElementById('modalCash').classList.add('hidden'); 
        document.getElementById('modalCash').classList.remove('flex'); 
    }

    function calculateChange() {
        const total = parseFloat(document.getElementById('cartTotalDisplay').innerText.replace(/[^\d,]/g, '').replace(',', '.'));
        const received = parseFloat(document.getElementById('cashReceived').value) || 0;
        const change = received - total;
        const disp = document.getElementById('changeDisplay');
        const btn = document.getElementById('btnConfirmCash');
        
        if(change >= 0) {
            disp.innerHTML = `<span class="text-xs font-medium uppercase tracking-widest text-emerald-600 block mb-1">Troco</span><span class="text-2xl font-black text-emerald-600">R$ ${change.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>`;
            btn.disabled = false;
        } else {
            disp.innerText = `Faltam R$ ${Math.abs(change).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            btn.disabled = true;
        }
    }

    function filterCategory(cat) {
        document.querySelectorAll('.product-card').forEach(c => {
            const matches = (cat === 'all' || c.dataset.category === cat);
            if (matches) { c.classList.remove('hidden'); c.classList.add('flex'); }
            else { c.classList.add('hidden'); c.classList.remove('flex'); }
        });
        document.querySelectorAll('.category-btn').forEach(b => {
            if (b.innerText === (cat==='all'?'Todos':cat)) b.className = "category-btn px-4 py-2 rounded-full text-xs font-bold bg-emerald-600 text-white shadow-md shadow-emerald-200 transition-all transform scale-105 shrink-0";
            else b.className = "category-btn px-4 py-2 rounded-full text-xs font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all active:scale-95 whitespace-nowrap shrink-0";
        });
    }

    document.getElementById('searchInput').oninput = (e) => {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('.product-card').forEach(c => {
            if (c.dataset.name.includes(q)) { c.classList.remove('hidden'); c.classList.add('flex'); }
            else { c.classList.add('hidden'); c.classList.remove('flex'); }
        });
    };
    
    document.getElementById('nfcInput').addEventListener('keypress', function (e) { if (e.key === 'Enter') processPayment(e); });
    document.getElementById('cartPanel').addEventListener('click', function(e) { if(e.target === this && window.innerWidth < 768) toggleMobileCart(); });

    function showShiftSummary() {
        fetch('../../api/shift_summary.php')
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                let m = document.getElementById('modalShiftSummary');
                if(!m) {
                    m = document.createElement('div');
                    m.id = 'modalShiftSummary';
                    m.className = 'fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4';
                    m.innerHTML = `
                        <div class="bg-white rounded-3xl p-6 w-full max-w-sm shadow-2xl relative">
                            <button onclick="document.getElementById('modalShiftSummary').classList.replace('flex', 'hidden')" class="absolute top-4 right-4 p-2 bg-slate-100 text-slate-400 hover:text-slate-600 rounded-full"><i data-lucide="x" class="w-4 h-4"></i></button>
                            <h3 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-2"><i data-lucide="calculator" class="w-6 h-6 text-emerald-500"></i> Resumo de Hoje</h3>
                            
                            <div class="space-y-3">
                                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 flex justify-between items-center">
                                    <span class="text-slate-500 font-bold text-xs uppercase flex items-center gap-2"><i data-lucide="credit-card" class="w-4 h-4 text-indigo-500"></i> Pulseira NFC</span>
                                    <span class="text-lg font-black text-slate-800" id="summNfc">R$ 0,00</span>
                                </div>
                                <div class="bg-emerald-50 p-4 rounded-2xl border border-emerald-100 flex justify-between items-center">
                                    <span class="text-emerald-600 font-bold text-xs uppercase flex items-center gap-2"><i data-lucide="banknote" class="w-4 h-4"></i> Dinheiro Vivo</span>
                                    <span class="text-lg font-black text-emerald-700" id="summCash">R$ 0,00</span>
                                </div>
                                <div class="bg-slate-800 p-4 rounded-2xl flex justify-between items-center mt-4 shadow-lg shadow-slate-200">
                                    <span class="text-slate-300 font-bold text-xs uppercase">Total Faturado</span>
                                    <span class="text-xl font-black text-white" id="summTotal">R$ 0,00</span>
                                </div>
                            </div>
                            <button onclick="document.getElementById('modalShiftSummary').classList.replace('flex', 'hidden')" class="w-full mt-6 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 rounded-xl transition-colors">Fechar Resumo</button>
                        </div>
                    `;
                    document.body.appendChild(m);
                    lucide.createIcons();
                }
                
                document.getElementById('summNfc').innerText = 'R$ ' + data.data.nfc.toFixed(2).replace('.', ',');
                document.getElementById('summCash').innerText = 'R$ ' + data.data.cash.toFixed(2).replace('.', ',');
                document.getElementById('summTotal').innerText = 'R$ ' + data.data.total.toFixed(2).replace('.', ',');
                
                m.classList.replace('hidden', 'flex');
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => showToast('Erro de conexão.', 'error'));
    }
</script>