<?php
// views/admin/room_orders.php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('OPERATOR');
requirePermission('canManagePreOrders');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'set_special_snack') {
        $specialId = $_POST['product_id'] ?: null;
        $pdo->query("UPDATE products SET is_special_of_day = 0");
        if ($specialId) {
            $stmt = $pdo->prepare("UPDATE products SET is_special_of_day = 1 WHERE id = ?");
            $stmt->execute([$specialId]);
        }
        header("Location: room_orders.php?class_id=" . ($_GET['class_id'] ?? ''));
        exit;
    } elseif ($_POST['action'] === 'create_quick_product') {
        $name = $_POST['name'] ?? '';
        $price = (float)str_replace(',', '.', $_POST['price'] ?? '0');
        $categoryId = $_POST['category_id'] ?? null;
        $imageUrl = $_POST['image_url'] ?: null;
        $setSpecial = isset($_POST['set_special']) ? 1 : 0;
        
        if ($name && $categoryId && $price > 0) {
            $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, active, is_special_of_day, image_url) VALUES (?, ?, ?, 1, ?, ?)");
            $stmt->execute([$name, $categoryId, $price, $setSpecial, $imageUrl]);
            if ($setSpecial) {
                 $newId = $pdo->lastInsertId();
                 $pdo->prepare("UPDATE products SET is_special_of_day = 0 WHERE id != ?")->execute([$newId]);
            }
        }
        header("Location: room_orders.php?class_id=" . ($_GET['class_id'] ?? ''));
        exit;
    }
}

$classrooms = $pdo->query("SELECT id, name FROM classrooms ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);
$selectedClass = $_GET['class_id'] ?? array_key_first($classrooms);

$students = [];
if ($selectedClass) {
    // Fetch students and their today's orders
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.avatar_url, po.id as order_id, po.payment_status, po.payment_method, p.name as product_name, COALESCE(n.balance, 0) as balance
        FROM students s
        LEFT JOIN nfc_tags n ON n.current_student_id = s.id
        LEFT JOIN pre_orders po ON s.id = po.student_id AND po.order_date = CURDATE() AND po.delivery_status != 'CANCELLED'
        LEFT JOIN pre_order_items poi ON po.id = poi.pre_order_id
        LEFT JOIN products p ON poi.product_id = p.id
        WHERE s.classroom_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();
}

$products = $pdo->query("SELECT id, name, price, is_special_of_day FROM products WHERE active = 1 ORDER BY is_special_of_day DESC, name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories WHERE active=1 ORDER BY name")->fetchAll();

$stmtConfig = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('school_name', 'logo_url')");
$settings = $stmtConfig->fetchAll(PDO::FETCH_KEY_PAIR);
require __DIR__ . '/../../includes/header.php';
?>

<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50">
    <?php include __DIR__ . '/../../includes/top_header.php'; ?>
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50 relative pb-[100px]">
            <header class="flex flex-col md:flex-row justify-between items-start md:items-center bg-white p-4 md:p-6 rounded-2xl border border-slate-200 shadow-sm gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 flex items-center gap-3">
                        <i data-lucide="tablet-smartphone" class="text-indigo-500 w-7 h-7"></i> Coleta na Sala
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Registre lanches direto na sala de aula.</p>
                </div>
                
                <div class="flex items-center gap-3 w-full md:w-auto flex-wrap">
                    <button onclick="document.getElementById('modalSpecialSnack').style.display = 'flex'" class="bg-amber-50 text-amber-600 px-4 py-3 rounded-xl font-bold flex items-center gap-2 hover:bg-amber-100 transition-colors whitespace-nowrap">
                        <i data-lucide="star" class="w-5 h-5"></i> Lanche do Dia
                    </button>
                    <form method="GET" class="w-full md:w-auto">
                    <select name="class_id" onchange="this.form.submit()" class="w-full md:w-64 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-bold text-slate-700 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10">
                        <?php foreach($classrooms as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $selectedClass == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </header>

            <?php if(empty($students) && $selectedClass): ?>
                <div class="text-center p-8 text-slate-400 font-bold">Nenhum aluno nesta turma.</div>
            <?php else: ?>
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach($students as $s): ?>
                        <div class="bg-white rounded-2xl p-4 flex items-center justify-between border <?= $s['order_id'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200' ?> shadow-sm">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <img src="<?= $s['avatar_url'] ?: 'https://api.dicebear.com/9.x/adventurer/svg?seed='.urlencode($s['name']) ?>" class="w-12 h-12 rounded-xl bg-slate-100 object-cover shrink-0">
                                <div class="truncate">
                                    <div class="font-bold text-slate-800 text-sm truncate"><?= htmlspecialchars($s['name']) ?></div>
                                    <?php if($s['order_id']): ?>
                                        <div class="text-xs font-bold text-emerald-600 mt-0.5 truncate flex items-center gap-1">
                                            <i data-lucide="check-circle-2" class="w-3 h-3"></i> <?= htmlspecialchars($s['product_name']) ?>
                                        </div>
                                        <div class="text-[10px] uppercase font-bold <?= $s['payment_method'] == 'CASH' ? 'text-amber-500' : 'text-emerald-500' ?>">
                                            <?= $s['payment_method'] == 'CASH' ? 'Dinheiro (Pendente)' : 'Tag (Pago)' ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-xs text-slate-400 mt-0.5">Sem reserva</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if(!$s['order_id']): ?>
                                <button onclick="openOrderModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>', <?= $s['balance'] ?>)" class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center shrink-0 hover:bg-indigo-500 hover:text-white transition-colors">
                                    <i data-lucide="plus" class="w-5 h-5"></i>
                                </button>
                            <?php else: ?>
                                <div class="w-10 h-10 flex items-center justify-center shrink-0 text-emerald-500">
                                    <i data-lucide="check" class="w-6 h-6"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modal Pedido -->
<div id="modalOrder" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex-col items-center justify-end md:justify-center z-50">
    <div class="bg-white rounded-t-3xl md:rounded-3xl w-full max-w-lg overflow-hidden animate-slide-up md:animate-none">
        <div class="px-6 py-4 flex justify-between items-center bg-slate-50 border-b border-slate-100">
            <h3 class="font-black text-slate-800 text-lg flex items-center gap-2">
                <i data-lucide="shopping-bag" class="text-indigo-500"></i> Pedido para <span id="modalStudentName" class="text-indigo-600"></span>
            </h3>
            <button onclick="closeModal('modalOrder')" class="text-slate-400 hover:text-slate-600 bg-white p-1 rounded-lg border border-slate-200"><i data-lucide="x"></i></button>
        </div>
        
        <form onsubmit="submitOrder(event)" class="p-6">
            <input type="hidden" id="modalStudentId">
            
            <div class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Selecione o Lanche</label>
                    <div class="grid grid-cols-2 gap-3 max-h-48 overflow-y-auto pr-2 pb-2">
                        <?php foreach($products as $p): ?>
                            <label class="relative border-2 border-slate-100 rounded-xl p-3 cursor-pointer hover:border-indigo-200 flex flex-col justify-between <?= $p['is_special_of_day'] ? 'bg-amber-50 border-amber-200' : '' ?>" id="product-label-<?= $p['id'] ?>">
                                <input type="radio" name="product_id" value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" onchange="checkBalance()" class="peer sr-only" required>
                                <div class="peer-checked:border-indigo-500 absolute inset-0 border-2 border-transparent rounded-xl pointer-events-none transition-all"></div>
                                <div class="peer-checked:text-indigo-600 font-bold text-slate-700 text-sm leading-tight relative z-10 flex items-center gap-1">
                                    <?= htmlspecialchars($p['name']) ?>
                                    <?php if($p['is_special_of_day']): ?><i data-lucide="star" class="w-3 h-3 text-amber-500"></i><?php endif; ?>
                                </div>
                                <div class="text-xs font-black text-slate-400 mt-2 relative z-10">R$ <?= number_format($p['price'], 2, ',', '.') ?></div>
                                <div class="peer-checked:block hidden absolute top-2 right-2 text-indigo-500 z-10">
                                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Forma de Pagamento</label>
                    <div class="flex gap-3">
                        <label id="labelPaymentWallet" class="flex-1 relative border-2 border-slate-100 rounded-xl p-3 cursor-pointer hover:border-emerald-200 text-center">
                            <input type="radio" name="payment_method" value="WALLET" id="radioWallet" class="peer sr-only" checked>
                            <div class="peer-checked:border-emerald-500 peer-checked:bg-emerald-50 absolute inset-0 border-2 border-transparent rounded-xl pointer-events-none transition-all"></div>
                            <i data-lucide="wallet" class="relative z-10 w-5 h-5 mx-auto mb-1 peer-checked:text-emerald-600 text-slate-400"></i>
                            <div class="relative z-10 peer-checked:text-emerald-700 font-bold text-slate-600 text-xs uppercase tracking-wide">Saldo Tag</div>
                        </label>
                        <label class="flex-1 relative border-2 border-slate-100 rounded-xl p-3 cursor-pointer hover:border-amber-200 text-center">
                            <input type="radio" name="payment_method" value="CASH" id="radioCash" class="peer sr-only">
                            <div class="peer-checked:border-amber-500 peer-checked:bg-amber-50 absolute inset-0 border-2 border-transparent rounded-xl pointer-events-none transition-all"></div>
                            <i data-lucide="coins" class="relative z-10 w-5 h-5 mx-auto mb-1 peer-checked:text-amber-600 text-slate-400"></i>
                            <div class="relative z-10 peer-checked:text-amber-700 font-bold text-slate-600 text-xs uppercase tracking-wide">Dinheiro</div>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" id="btnSubmit" class="w-full bg-indigo-600 text-white font-black py-4 rounded-xl shadow-lg shadow-indigo-500/30 hover:bg-indigo-700 transition-all uppercase tracking-widest text-sm mt-6 flex justify-center items-center gap-2">
                <i data-lucide="check" class="w-5 h-5"></i> Confirmar Pedido
            </button>
        </form>
    </div>
</div>

<!-- Modal Special Snack -->
<div id="modalSpecialSnack" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-3xl w-full max-w-sm overflow-hidden p-6 relative">
        <button onclick="document.getElementById('modalSpecialSnack').style.display='none'" class="absolute right-6 top-6 text-slate-400 hover:text-slate-600"><i data-lucide="x"></i></button>
        <h3 class="font-black text-slate-800 text-lg mb-4 flex items-center gap-2">
            <i data-lucide="star" class="text-amber-500"></i> Lanche do Dia
        </h3>
        <p class="text-sm text-slate-500 mb-4">Escolha o produto que será destacado hoje. Os alunos verão este lanche em evidência.</p>
        
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="set_special_snack">
            <select name="product_id" class="w-full bg-slate-50 border-2 border-slate-100 rounded-xl px-4 py-3 font-bold text-slate-700 outline-none focus:border-amber-500">
                <option value="">Nenhum destaque</option>
                <?php foreach($products as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $p['is_special_of_day'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex flex-col gap-2">
                <button type="submit" class="w-full bg-amber-500 text-white font-black py-3 rounded-xl shadow-lg shadow-amber-500/30 hover:bg-amber-600 transition-all uppercase tracking-widest text-sm">
                    Salvar Destaque
                </button>
                <button type="button" onclick="closeModal('modalSpecialSnack'); document.getElementById('modalQuickProduct').style.display='flex';" class="w-full bg-slate-50 text-slate-600 font-bold py-3 rounded-xl border border-slate-200 hover:bg-slate-100 transition-all uppercase tracking-widest text-sm text-center flex items-center justify-center gap-2">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Cadastrar Produto
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cadastro Rápido -->
<div id="modalQuickProduct" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex-col items-center justify-end md:justify-center z-50 p-0 md:p-4">
    <div class="bg-white rounded-t-3xl md:rounded-3xl w-full max-w-sm overflow-hidden p-6 relative animate-slide-up md:animate-none">
        <button onclick="closeModal('modalQuickProduct')" class="absolute right-6 top-6 text-slate-400 hover:text-slate-600"><i data-lucide="x"></i></button>
        <h3 class="font-black text-slate-800 text-lg mb-4 flex items-center gap-2">
            <i data-lucide="plus-circle" class="text-indigo-500"></i> Novo Produto
        </h3>
        
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create_quick_product">
            
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nome</label>
                <input type="text" name="name" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-xl px-4 py-3 font-bold text-slate-700 outline-none focus:border-indigo-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Preço (R$)</label>
                    <input type="number" step="0.05" name="price" required placeholder="0.00" class="w-full bg-slate-50 border-2 border-slate-100 rounded-xl px-4 py-3 font-bold text-slate-700 outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Categoria</label>
                    <select name="category_id" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-xl px-4 py-3 font-bold text-slate-700 outline-none focus:border-indigo-500">
                        <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Foto do Produto</label>
                <div class="border-2 border-dashed border-slate-200 rounded-xl p-3 text-center hover:bg-slate-50 hover:border-indigo-500 transition-all cursor-pointer relative" onclick="document.getElementById('quickProdFileInput').click()">
                    <input type="file" id="quickProdFileInput" accept="image/*" class="hidden" onchange="handleImageUpload(this)">
                    <div id="quickUploadPlaceholder" class="flex flex-col items-center justify-center gap-1 text-slate-400">
                        <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                        <span class="text-[10px] font-bold">Clique para enviar uma foto</span>
                    </div>
                    <div id="quickUploadPreviewContainer" class="hidden flex items-center gap-3 text-left">
                        <img id="quickUploadPreview" src="" class="w-10 h-10 rounded-lg object-cover border border-slate-200">
                        <div>
                            <p class="text-[10px] text-indigo-600 font-bold" id="quickUploadStatus">Otimizando...</p>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="image_url" id="quickProdImage">
            </div>
            
            <label class="flex items-center gap-2 cursor-pointer bg-amber-50 border border-amber-100 p-3 rounded-xl mt-2">
                <input type="checkbox" name="set_special" value="1" class="w-4 h-4 text-amber-500 rounded border-amber-300 focus:ring-amber-500">
                <span class="text-sm font-bold text-amber-700">Já definir como Lanche do Dia</span>
            </label>

            <div class="pt-2">
                <button type="submit" class="w-full bg-indigo-600 text-white font-black py-3 rounded-xl shadow-lg shadow-indigo-500/30 hover:bg-indigo-700 transition-all uppercase tracking-widest text-sm flex justify-center items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>


<style>
    .animate-slide-up { animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
    /* Estilizar barra de rolagem dos produtos */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<script>
    lucide.createIcons();

    let currentBalance = 0;

    function openOrderModal(studentId, studentName, balance) {
        document.getElementById('modalStudentId').value = studentId;
        document.getElementById('modalStudentName').innerText = studentName;
        currentBalance = parseFloat(balance);
        
        // Reset form
        document.querySelectorAll('input[name="product_id"]').forEach(el => el.checked = false);
        document.getElementById('radioCash').checked = true; // default cash until product selected
        checkBalance();

        document.getElementById('modalOrder').style.display = 'flex';
    }

    function checkBalance() {
        const selectedProduct = document.querySelector('input[name="product_id"]:checked');
        const walletRadio = document.getElementById('radioWallet');
        const walletLabel = document.getElementById('labelPaymentWallet');
        
        if (selectedProduct) {
            const price = parseFloat(selectedProduct.dataset.price);
            if (currentBalance >= price) {
                walletRadio.disabled = false;
                walletLabel.classList.remove('opacity-50', 'pointer-events-none');
            } else {
                walletRadio.disabled = true;
                walletLabel.classList.add('opacity-50', 'pointer-events-none');
                document.getElementById('radioCash').checked = true;
            }
        }
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    async function handleImageUpload(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        
        if (!file.type.startsWith('image/')) {
            alert('Por favor, selecione uma imagem válida.');
            return;
        }

        document.getElementById('quickUploadPlaceholder').classList.add('hidden');
        document.getElementById('quickUploadPreviewContainer').classList.remove('hidden');
        const statusEl = document.getElementById('quickUploadStatus');
        statusEl.innerText = 'Otimizando...';
        statusEl.className = 'text-[10px] text-amber-500 font-bold animate-pulse';

        try {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (e) => {
                const img = new Image();
                img.src = e.target.result;
                img.onload = async () => {
                    const MAX_WIDTH = 800;
                    const MAX_HEIGHT = 800;
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > MAX_WIDTH) { height *= MAX_WIDTH / width; width = MAX_WIDTH; }
                    } else {
                        if (height > MAX_HEIGHT) { width *= MAX_HEIGHT / height; height = MAX_HEIGHT; }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    const dataUrl = canvas.toDataURL('image/webp', 0.8);
                    
                    document.getElementById('quickUploadPreview').src = dataUrl;
                    statusEl.innerText = 'Enviando...';

                    const res = await fetch('../../api/upload_image.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ image: dataUrl })
                    });
                    const result = await res.json();
                    
                    if (result.success) {
                        document.getElementById('quickProdImage').value = result.url;
                        statusEl.innerText = 'Imagem Salva!';
                        statusEl.className = 'text-[10px] text-indigo-600 font-bold';
                    } else {
                        throw new Error(result.message);
                    }
                };
            };
        } catch (err) {
            statusEl.innerText = 'Falha no upload';
            statusEl.className = 'text-[10px] text-red-600 font-bold';
            alert("Erro ao processar imagem: " + err.message);
        }
    }

    async function submitOrder(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmit');
        btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Processando...';
        btn.disabled = true;

        const formData = new FormData(e.target);
        const data = {
            student_id: document.getElementById('modalStudentId').value,
            product_id: formData.get('product_id'),
            payment_method: formData.get('payment_method')
        };

        try {
            const res = await fetch('../../api/create_pre_order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (json.success) {
                location.reload();
            } else {
                alert("Erro: " + json.message);
                btn.innerHTML = '<i data-lucide="check" class="w-5 h-5"></i> Confirmar Pedido';
                btn.disabled = false;
            }
        } catch(err) {
            alert("Erro de conexão.");
            btn.innerHTML = '<i data-lucide="check" class="w-5 h-5"></i> Confirmar Pedido';
            btn.disabled = false;
        }
    }
</script>
</body>
</html>
