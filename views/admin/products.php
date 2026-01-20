<?php
// views/admin/products.php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManageSettings');

// --- LÓGICA DE PROCESSAMENTO BACKEND ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        // Salvar ou Editar Produto
        if ($action === 'save_product') {
            $price = (float)str_replace(',', '.', $input['price']);
            
            // Regra de Ouro: centavos em 0 ou 5
            $cents = round(($price - floor($price)) * 100);
            if ($cents % 5 !== 0) throw new Exception("O preço deve terminar em 0 ou 5 centavos.");

            if (!empty($input['id'])) {
                $stmt = $pdo->prepare("UPDATE products SET name=?, category_id=?, price=?, image_url=? WHERE id=?");
                $stmt->execute([$input['name'], $input['category_id'], $price, $input['image_url'], $input['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, image_url, active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$input['name'], $input['category_id'], $price, $input['image_url']]);
            }
        } 
        // Salvar ou Editar Categoria
        elseif ($action === 'save_category') {
            if (!empty($input['id'])) {
                $pdo->prepare("UPDATE categories SET name=? WHERE id=?")->execute([$input['name'], $input['id']]);
            } else {
                $pdo->prepare("INSERT INTO categories (name, active) VALUES (?, 1)")->execute([$input['name']]);
            }
        }
        // Deletar Produto (Desativar)
        elseif ($action === 'delete_product') {
            $pdo->prepare("UPDATE products SET active = 0 WHERE id = ?")->execute([$input['id']]);
        }

        echo json_encode(['success' => true]); exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit;
    }
}

// --- BUSCA DE DADOS ---
// 1. Configurações da Escola (Cabeçalho Dinâmico)
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$schoolName = $settings['school_name'] ?? 'Escola Estadual Modelo';
$schoolLogo = $settings['logo_url'] ?? '';

// 2. Categorias e Produtos
$categories = $pdo->query("SELECT * FROM categories WHERE active = 1 ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name as category_name 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE p.active = 1 
                         ORDER BY c.name, p.name")->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

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
                    <i data-lucide="package" class="w-6 h-6"></i>
                </div>
            <?php endif; ?>

            <div>
                <h1 class="font-black text-slate-800 leading-tight text-sm md:text-base"><?= htmlspecialchars($schoolName) ?></h1>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Catálogo</p>
            </div>
        </div>

        <div class="hidden md:flex bg-slate-100 p-1 rounded-xl items-center gap-1">
            <a href="pos.php" class="px-4 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-white/50 transition-all">PDV</a>
            <a href="history.php" class="px-4 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-white/50 transition-all">Histórico</a>
            <a href="products.php" class="px-4 py-2 rounded-lg text-sm font-bold bg-white text-emerald-600 shadow-sm transition-all">Catálogo</a>
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

    <main class="flex-1 overflow-y-auto p-4 md:p-8 lg:p-12 pb-[100px] md:pb-12">
        <div class="max-w-6xl mx-auto">
            
            <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800 flex items-center gap-3">
                        Catálogo de Itens
                    </h1>
                    <p class="text-slate-500 mt-1">Gerencie produtos e organize categorias para o PDV.</p>
                </div>
                <div class="flex gap-3 w-full md:w-auto">
                    <button onclick="openCategoryModal()" class="flex-1 md:flex-none justify-center bg-white text-slate-600 border border-slate-200 px-6 py-4 rounded-2xl font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
                        <i data-lucide="layers" class="w-5 h-5"></i> Categorias
                    </button>
                    <button onclick="openProductModal()" class="flex-1 md:flex-none justify-center bg-emerald-600 text-white px-8 py-4 rounded-2xl font-bold hover:bg-emerald-700 shadow-xl flex items-center gap-2 transition-all active:scale-95">
                        <i data-lucide="plus-circle" class="w-5 h-5"></i> Novo Produto
                    </button>
                </div>
            </header>

            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[600px]">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                            <tr>
                                <th class="px-8 py-5 w-24">Foto</th>
                                <th class="px-8 py-5">Nome do Item</th>
                                <th class="px-8 py-5">Categoria</th>
                                <th class="px-8 py-5 text-right">Preço</th>
                                <th class="px-8 py-5 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($products as $p): ?>
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td class="px-8 py-4">
                                    <div class="w-14 h-14 rounded-2xl overflow-hidden bg-slate-100 border border-slate-200">
                                        <img src="<?= htmlspecialchars($p['image_url']) ?>" class="w-full h-full object-cover" onerror="this.src='https://via.placeholder.com/100?text=S/F'">
                                    </div>
                                </td>
                                <td class="px-8 py-4 font-bold text-slate-800 text-sm"><?= htmlspecialchars($p['name']) ?></td>
                                <td class="px-8 py-4">
                                    <span class="bg-blue-50 text-blue-600 px-3 py-1 rounded-lg text-[10px] font-black uppercase border border-blue-100">
                                        <?= htmlspecialchars($p['category_name'] ?: 'Sem Categoria') ?>
                                    </span>
                                </td>
                                <td class="px-8 py-4 text-right font-black text-emerald-600">
                                    R$ <?= number_format($p['price'], 2, ',', '.') ?>
                                </td>
                                <td class="px-8 py-4 text-right">
                                    <div class="flex justify-end gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                                        <button onclick='openProductModal(<?= json_encode($p) ?>)' class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all">
                                            <i data-lucide="edit-3" class="w-5 h-5"></i>
                                        </button>
                                        <button onclick="handleAction('delete_product', {id: <?= $p['id'] ?>}, this)" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all">
                                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 h-[70px] flex items-center justify-around z-50 shadow-[0_-5px_20px_rgba(0,0,0,0.05)] px-2">
        <a href="pos.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="credit-card" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Venda</span>
        </a>
        <a href="history.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="list" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Histórico</span>
        </a>
        <a href="products.php" class="flex flex-col items-center gap-1 p-2 text-emerald-600">
            <i data-lucide="package" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Produtos</span>
        </a>
        <a href="dashboard.php" class="flex flex-col items-center gap-1 p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="settings" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold">Gestão</span>
        </a>
    </div>

</div>

<div id="modalCategory" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center p-4 z-50 backdrop-blur-sm animate-in fade-in duration-200">
    <div class="bg-white rounded-[2.5rem] w-full max-w-md shadow-2xl overflow-hidden scale-in-center">
        <div class="p-8 flex justify-between items-center border-b border-slate-50">
            <h3 class="text-2xl font-bold text-slate-800 tracking-tight">Gerenciar Categorias</h3>
            <button onclick="closeModals()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x"></i></button>
        </div>
        <div class="p-8">
            <div id="catAlert" class="hidden mb-6 p-4 rounded-2xl bg-red-50 text-red-700 border border-red-100 text-xs font-bold flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4"></i> <span id="catAlertMsg"></span>
            </div>

            <form onsubmit="event.preventDefault(); saveCategory(document.getElementById('catInputName').value, document.getElementById('catInputId').value)" class="flex gap-2 mb-8">
                <input type="hidden" id="catInputId">
                <input type="text" id="catInputName" required placeholder="Ex: Bebidas" class="flex-1 px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 font-bold transition-all">
                <button type="submit" class="bg-emerald-600 text-white px-6 rounded-2xl hover:bg-emerald-700 shadow-lg shadow-emerald-100"><i data-lucide="check"></i></button>
            </form>

            <div class="space-y-2 max-h-64 overflow-y-auto pr-2">
                <?php foreach($categories as $cat): ?>
                <div class="flex justify-between items-center p-4 bg-slate-50 rounded-2xl border border-slate-100 group">
                    <span class="font-bold text-slate-700"><?= $cat['name'] ?></span>
                    <div class="flex gap-1">
                        <button onclick="editCategory(<?= $cat['id'] ?>, '<?= $cat['name'] ?>')" class="p-2 text-blue-500 hover:bg-white rounded-lg transition-all"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div id="modalProduct" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center p-4 z-50 backdrop-blur-sm animate-in fade-in duration-200">
    <div class="bg-white rounded-[2.5rem] w-full max-w-lg shadow-2xl overflow-hidden scale-in-center">
        <div class="p-8 pb-4 flex justify-between items-center">
            <h3 id="prodModalTitle" class="text-2xl font-bold text-slate-800 tracking-tight">Dados do Produto</h3>
            <button onclick="closeModals()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x"></i></button>
        </div>
        <form id="formProduct" onsubmit="event.preventDefault(); saveProduct(this)" class="p-8 pt-2 space-y-5">
            <div id="prodAlert" class="hidden p-4 rounded-2xl bg-red-50 text-red-700 border border-red-100 text-xs font-bold flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4"></i> <span id="prodAlertMsg"></span>
            </div>

            <input type="hidden" name="id" id="prodId">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Nome de Exibição</label>
                <input type="text" name="name" id="prodName" required class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 font-bold transition-all">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Categoria</label>
                    <select name="category_id" id="prodCategory" required class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 bg-white font-bold text-slate-700 transition-all">
                        <option value="">Selecione...</option>
                        <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Preço Unitário</label>
                    <input type="number" step="0.05" name="price" id="prodPrice" required placeholder="0.00" class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 font-black text-emerald-600 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">URL da Imagem do Produto</label>
                <input type="text" name="image_url" id="prodImage" class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-500/10 font-mono text-xs transition-all">
            </div>

            <div class="pt-4">
                <button type="submit" id="btnSaveProd" class="w-full bg-emerald-600 text-white py-5 rounded-[1.5rem] font-bold hover:bg-emerald-700 shadow-xl shadow-emerald-100 transition-all active:scale-95 flex items-center justify-center gap-2 uppercase text-xs tracking-widest">
                    <i data-lucide="check-circle" class="w-5 h-5"></i> Confirmar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    lucide.createIcons();

    function closeModals() {
        document.querySelectorAll('.fixed').forEach(m => {
            if(m.id !== 'modalCategory' && m.id !== 'modalProduct') return;
            m.classList.replace('flex', 'hidden');
        });
        document.getElementById('catInputId').value = '';
        document.getElementById('catInputName').value = '';
        document.getElementById('catAlert').classList.add('hidden');
        document.getElementById('prodAlert').classList.add('hidden');
    }

    // --- LÓGICA DE CATEGORIAS ---
    function openCategoryModal() { document.getElementById('modalCategory').classList.replace('hidden', 'flex'); }
    
    function editCategory(id, name) {
        document.getElementById('catInputId').value = id;
        document.getElementById('catInputName').value = name;
        document.getElementById('catInputName').focus();
    }

    async function saveCategory(name, id) {
        const res = await fetch('products.php', { method: 'POST', body: JSON.stringify({ action: 'save_category', id, name }) });
        const result = await res.json();
        if(result.success) location.reload();
        else {
            document.getElementById('catAlertMsg').innerText = result.message;
            document.getElementById('catAlert').classList.remove('hidden');
        }
    }

    // --- LÓGICA DE PRODUTOS ---
    function openProductModal(p = null) {
        const form = document.getElementById('formProduct');
        if(p) {
            document.getElementById('prodId').value = p.id;
            document.getElementById('prodName').value = p.name;
            document.getElementById('prodCategory').value = p.category_id;
            document.getElementById('prodPrice').value = p.price;
            document.getElementById('prodImage').value = p.image_url;
        } else {
            form.reset();
            document.getElementById('prodId').value = '';
        }
        document.getElementById('modalProduct').classList.replace('hidden', 'flex');
    }

    async function saveProduct(form) {
        const btn = document.getElementById('btnSaveProd');
        const data = Object.fromEntries(new FormData(form));
        data.action = 'save_product';

        btn.disabled = true;
        btn.innerHTML = 'Processando...';

        const res = await fetch('products.php', { method: 'POST', body: JSON.stringify(data) });
        const result = await res.json();

        if(result.success) location.reload();
        else {
            document.getElementById('prodAlertMsg').innerText = result.message;
            document.getElementById('prodAlert').classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> Confirmar Alterações';
            lucide.createIcons();
        }
    }

    async function handleAction(action, data, btn) {
        if(action === 'delete_product' && !confirm("Deseja remover este item do catálogo?")) return;
        const res = await fetch('products.php', { method: 'POST', body: JSON.stringify({ action, ...data }) });
        if((await res.json()).success) location.reload();
    }
</script>
</body>
</html>