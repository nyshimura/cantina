<?php
// views/student/dashboard.php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('STUDENT');

$studentId = $_SESSION['user_id'];

try {
    // 1. Busca dados do aluno
    $stmt = $pdo->prepare("
        SELECT s.*, COALESCE(n.balance, 0) as balance, n.tag_id as nfc_id
        FROM students s
        LEFT JOIN nfc_tags n ON n.current_student_id = s.id
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) die("Erro: Aluno não encontrado.");

    $student['avatar_url'] = 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . urlencode($student['name']);

    // --- LÓGICA DE LIMITE DE RECARGA CORRIGIDA ---
    $rechargeConfig = json_decode($student['recharge_config'] ?? '[]', true);
    $limitVal = floatval($rechargeConfig['limit'] ?? 0);
    $limitPeriod = $rechargeConfig['period'] ?? 'Diário';
    $currentUsage = 0;
    $hasLimit = false;

    if ($limitVal > 0) {
        $hasLimit = true;
        
        // Filtro de Data (Diário ou Mensal)
        $dateFilter = "DATE(timestamp) = CURRENT_DATE()";
        if ($limitPeriod === 'Mensal') {
            $dateFilter = "MONTH(timestamp) = MONTH(CURRENT_DATE()) AND YEAR(timestamp) = YEAR(CURRENT_DATE())";
        }

        // CORREÇÃO: Soma apenas COMPLETED e PENDING RECENTES (últimos 30 min)
        // Isso evita que tentativas de Pix abandonadas bloqueiem o limite do aluno para sempre.
        $stmtLimit = $pdo->prepare("
            SELECT SUM(amount) 
            FROM transactions 
            WHERE student_id = ? 
            AND type = 'DEPOSIT' 
            AND $dateFilter
            AND (
                status = 'COMPLETED' 
                OR (status = 'PENDING' AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 MINUTE))
            )
        ");
        $stmtLimit->execute([$studentId]);
        $currentUsage = floatval($stmtLimit->fetchColumn() ?: 0);
    }
    
    // Garante que não mostre valor negativo
    $remainingLimit = max(0, $limitVal - $currentUsage);
    // ---------------------------------------------------

    // 2. Histórico
    $stmtH = $pdo->prepare("
        SELECT timestamp, items_summary as display_desc, type, amount, status 
        FROM transactions 
        WHERE student_id = ? AND status IN ('COMPLETED', 'REFUNDED', 'CANCELLED')
        ORDER BY timestamp DESC LIMIT 10
    ");
    $stmtH->execute([$studentId]);
    $txs = $stmtH->fetchAll();

} catch (Exception $e) {
    die("Erro ao carregar dados.");
}

require __DIR__ . '/../../includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    html, body { 
        background-color: #f8fafc; 
        overflow-x: hidden;
        height: 100%;
    }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    .modal-overlay {
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        display: none;
        position: fixed; inset: 0; z-index: 100;
        align-items: center; justify-content: center; padding: 2vh;
        overflow-y: auto;
    }
    .modal-overlay.active { display: flex; }

    .modal-content { 
        width: 100%; max-width: 420px; 
        background: white; border-radius: 2rem; padding: 2rem; 
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        animation: modalIn 0.2s ease-out;
        margin: auto;
    }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

    .credit-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.3);
    }
</style>

<div class="min-h-screen w-full flex flex-col items-center p-4 md:p-8 overflow-y-auto">
    
    <div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <div class="lg:col-span-4 flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="<?= $student['avatar_url'] ?>" class="w-12 h-12 rounded-full border-2 border-white shadow-sm bg-white object-cover">
                    <div class="min-w-0"> <h1 class="text-lg font-black text-slate-800 leading-tight truncate">Olá, <?= explode(' ', $student['name'])[0] ?></h1>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide truncate">Escola Estadual Modelo</p>
                    </div>
                </div>
                <button onclick="openModal('modalProfile')" class="p-2 text-slate-400 hover:text-emerald-500 transition-colors bg-white rounded-xl shadow-sm border border-slate-100 flex-shrink-0"><i data-lucide="settings" class="w-5 h-5"></i></button>
            </div>

            <div class="credit-card relative w-full aspect-[1.586] rounded-3xl p-6 text-white flex flex-col justify-between overflow-hidden group shadow-2xl transform transition-transform hover:scale-[1.02]">
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-white/5 rounded-full blur-3xl group-hover:bg-white/10 transition-all"></div>
                
                <div class="flex justify-between items-start z-10">
                    <div class="w-11 h-8 rounded bg-amber-200/90 flex items-center justify-center shadow-sm">
                        <div class="w-8 h-5 border border-amber-500/30 rounded-[2px] flex items-center justify-center"><div class="w-full h-[1px] bg-amber-500/30"></div></div>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Saldo Atual</p>
                        <p class="text-2xl font-black tracking-tight">R$ <?= number_format($student['balance'], 2, ',', '.') ?></p>
                    </div>
                </div>
                
                <div class="z-10 mt-auto">
                    <div class="flex items-center gap-3 mb-4 opacity-50"><i data-lucide="wifi" class="w-5 h-5 rotate-90"></i></div>
                    <p class="font-mono text-lg tracking-widest mb-1 opacity-90 truncate">
                        •••• •••• •••• <?= !empty($student['nfc_id']) ? strtoupper(substr($student['nfc_id'], -4)) : '0000' ?>
                    </p>
                    <div class="flex justify-between items-end">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-300 truncate max-w-[70%]"><?= $student['name'] ?></p>
                        <div class="flex -space-x-3 opacity-90 flex-shrink-0"><div class="w-8 h-8 rounded-full bg-red-500/80"></div><div class="w-8 h-8 rounded-full bg-amber-500/80"></div></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <?php if ($student['can_self_charge']): ?>
                    <button onclick="openModal('modalRecharge')" class="bg-emerald-50 text-emerald-600 border border-emerald-100 py-4 rounded-2xl font-black text-xs uppercase tracking-widest flex flex-col items-center gap-2 hover:bg-emerald-100 transition-all shadow-sm active:scale-95"><i data-lucide="plus-circle" class="w-5 h-5"></i> Recarregar</button>
                <?php else: ?>
                    <button disabled class="bg-slate-50 text-slate-300 border border-slate-100 py-4 rounded-2xl font-black text-xs uppercase tracking-widest flex flex-col items-center gap-2 cursor-not-allowed opacity-75"><i data-lucide="lock" class="w-5 h-5"></i> Recarregar</button>
                <?php endif; ?>
                
                <div class="bg-white border border-slate-100 py-4 rounded-2xl flex flex-col items-center justify-center gap-1 shadow-sm">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Limite <?= ucfirst($limitPeriod) ?></span>
                    <span class="text-lg font-black text-slate-700">R$ <?= number_format($limitVal, 2, ',', '.') ?></span>
                </div>
            </div>
            
            <a href="../logout.php" class="text-center text-xs font-bold text-red-400 hover:text-red-500 mt-2 flex items-center justify-center gap-2 py-2"><i data-lucide="log-out" class="w-3 h-3"></i> Sair da conta</a>
        </div>

        <div class="lg:col-span-8 bg-white rounded-[2.5rem] p-6 md:p-8 border border-slate-100 shadow-sm min-h-[500px] flex flex-col">
            <div class="flex items-center gap-3 mb-8 sticky top-0 bg-white z-10 pb-2">
                <div class="p-2 bg-slate-50 text-slate-600 rounded-xl flex-shrink-0"><i data-lucide="coffee" class="w-5 h-5"></i></div>
                <h2 class="text-xl font-black text-slate-800 italic">Últimas Movimentações</h2>
            </div>
            
            <div class="flex-1 overflow-y-auto pr-1"> 
                <?php if(empty($txs)): ?>
                    <div class="flex flex-col items-center justify-center h-64 text-slate-300">
                        <i data-lucide="inbox" class="w-12 h-12 mb-2"></i>
                        <p class="font-bold text-sm">Nenhuma movimentação ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                    <?php foreach($txs as $t): 
                        $isEntry = $t['type'] === 'DEPOSIT' || $t['type'] === 'RECHARGE'; 
                        $isRefund = $t['status'] === 'REFUNDED';
                        $isCancelled = $t['status'] === 'CANCELLED';
                        
                        // Visual Logic
                        if ($isCancelled) {
                            $icon = 'x-circle';
                            $color = 'bg-red-50 text-red-500';
                            $amountClass = 'text-red-300 line-through decoration-red-500';
                        } elseif ($isRefund) {
                            $icon = 'rotate-ccw'; 
                            $color = 'bg-orange-50 text-orange-500';
                            $amountClass = 'text-slate-400 line-through decoration-orange-500';
                        } else {
                            $icon = $isEntry ? 'arrow-up-circle' : 'coffee'; 
                            $color = $isEntry ? 'bg-emerald-50 text-emerald-600' : 'bg-orange-50 text-orange-600'; 
                            $amountClass = $isEntry ? 'text-emerald-600' : 'text-slate-800';
                        }
                        
                        $sign = ($isEntry && !$isRefund && !$isCancelled) ? '+' : '-'; 
                    ?>
                    <div class="flex items-center justify-between p-4 hover:bg-slate-50 rounded-2xl transition-colors group cursor-default">
                        <div class="flex items-center gap-4 overflow-hidden">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center <?= $color ?> flex-shrink-0">
                                <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-700 text-sm mb-0.5 group-hover:text-slate-900 transition-colors truncate">
                                    <?= htmlspecialchars($t['display_desc'] ?: 'Compra na Cantina') ?>
                                    <?php if($isRefund): ?>
                                        <span class="text-[9px] bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full border border-orange-200 uppercase ml-1 align-middle">Estornado</span>
                                    <?php elseif($isCancelled): ?>
                                        <span class="text-[9px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full border border-red-200 uppercase ml-1 align-middle">Cancelado</span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide truncate"><?= date('d/m/Y • H:i', strtotime($t['timestamp'])) ?></p>
                            </div>
                        </div>
                        <span class="text-sm font-black <?= $amountClass ?> whitespace-nowrap ml-2"><?= $sign ?> R$ <?= number_format(abs($t['amount']), 2, ',', '.') ?></span>
                    </div>
                    <div class="h-px bg-slate-50 w-full last:hidden"></div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="modalProfile" class="modal-overlay">
    <div class="modal-content relative">
        <button onclick="closeModal('modalProfile')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button>
        <div class="flex items-center gap-3 mb-6">
            <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="user-cog" class="w-5 h-5"></i></div>
            <h2 class="font-black text-slate-800 italic text-xl">Editar Perfil</h2>
        </div>
        <div class="flex justify-center mb-6">
            <img src="<?= $student['avatar_url'] ?>" class="w-20 h-20 rounded-full border-4 border-slate-50 shadow-sm">
        </div>
        <form onsubmit="handleFormSubmit(event, 'update_student_profile.php')">
            <div class="space-y-4">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Nome (Visualização)</label>
                    <input type="text" value="<?= $student['name'] ?>" disabled class="w-full p-3 bg-slate-100 border border-slate-200 rounded-xl font-bold text-slate-500 cursor-not-allowed">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">E-mail</label>
                    <input type="email" name="email" value="<?= $student['email'] ?>" required class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Nova Senha</label>
                    <input type="password" name="password" placeholder="••••••••" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-emerald-500">
                </div>
            </div>
            <button type="submit" class="submit-btn w-full bg-emerald-500 text-white font-black py-4 rounded-xl shadow-lg hover:bg-emerald-600 transition-all mt-6 uppercase text-xs tracking-widest">Salvar Alterações</button>
        </form>
    </div>
</div>

<?php if($student['can_self_charge']): ?>
<div id="modalRecharge" class="modal-overlay">
    <div class="modal-content relative text-center">
        <button onclick="closeModal('modalRecharge')" class="absolute right-6 top-6 text-slate-300 hover:text-slate-500"><i data-lucide="x"></i></button>
        <div class="w-14 h-14 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4"><i data-lucide="qr-code" class="w-7 h-7"></i></div>
        <h2 class="font-black text-slate-800 italic text-xl mb-2">Recarga via Pix</h2>
        <p class="text-slate-400 text-xs font-medium mb-8 max-w-[200px] mx-auto">O valor será creditado após a confirmação.</p>
        
        <div id="stepAmount">
            <div class="relative mb-6">
                <span class="absolute left-1/2 -translate-x-[60px] top-1/2 -translate-y-1/2 font-black text-slate-300 text-2xl">R$</span>
                <input type="number" id="pixAmount" class="w-full text-center text-4xl font-black text-slate-700 bg-transparent outline-none placeholder-slate-200" placeholder="0,00" step="0.05">
            </div>
            
            <?php if($hasLimit): ?>
                <div id="limitInfo" class="mb-4">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                        Limite Restante: R$ <span class="text-emerald-500"><?= number_format($remainingLimit, 2, ',', '.') ?></span> (<?= $limitPeriod ?>)
                    </p>
                </div>
            <?php endif; ?>
            
            <div id="rechargeError" class="hidden mb-4 p-3 bg-red-50 text-red-500 text-xs font-bold rounded-xl border border-red-100"></div>

            <button onclick="generatePix()" class="w-full bg-slate-800 text-white font-bold py-4 rounded-xl hover:bg-slate-900 transition-all shadow-lg">Gerar QR Code</button>
        </div>

        <div id="stepPix" class="hidden flex flex-col items-center">
            <div id="qrCodeContainer" class="bg-white border-2 border-slate-100 rounded-2xl p-4 inline-block mb-4 shadow-sm"></div>
            
            <div class="relative w-full mb-4">
                <input type="text" id="copyPaste" readonly class="w-full text-[10px] text-center text-slate-400 bg-slate-50 p-3 rounded-xl font-mono truncate cursor-pointer pr-10 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" onclick="copyPixCode()">
                <button onclick="copyPixCode()" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-500 transition-colors">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                </button>
            </div>
            
            <div id="copyFeedback" class="hidden w-full text-center mb-4">
                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">Código PIX Copiado!</span>
            </div>

            <button onclick="location.reload()" class="w-full bg-emerald-500 text-white font-bold py-3 rounded-xl hover:bg-emerald-600 transition-all shadow-lg">Já Paguei</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="modalSuccessPayment" class="modal-overlay">
    <div class="modal-content relative text-center">
        <div class="w-20 h-20 bg-emerald-100 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="check-circle" class="w-10 h-10"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-800 mb-2">Pagamento Confirmado!</h3>
        <p class="text-slate-500 font-medium mb-8">O saldo foi creditado na sua conta.</p>
        <button onclick="location.reload()" class="w-full bg-slate-900 text-white font-bold py-4 rounded-2xl hover:bg-slate-800 transition-all">Entendido</button>
    </div>
</div>

<script>
    let statusInterval;
    function openModal(id) { 
        document.getElementById(id).classList.add('active'); 
        if(id === 'modalRecharge') {
            document.getElementById('rechargeError').classList.add('hidden'); // Limpa erros anteriores
            document.getElementById('pixAmount').value = '';
        }
    }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); if(statusInterval) clearInterval(statusInterval); }

    // VARIÁVEIS INJETADAS PELO PHP PARA O JS
    const hasLimit = <?= $hasLimit ? 'true' : 'false' ?>;
    const remainingLimit = <?= $remainingLimit ?>;
    const limitPeriod = "<?= $limitPeriod ?>";

    async function generatePix() {
        const amountInput = document.getElementById('pixAmount');
        const errorBox = document.getElementById('rechargeError');
        const val = parseFloat(amountInput.value);
        
        errorBox.classList.add('hidden'); // Reseta erro

        if(!val || val <= 0) {
            errorBox.textContent = 'Digite um valor válido.';
            errorBox.classList.remove('hidden');
            return;
        }
        
        // VERIFICAÇÃO DE LIMITE VISUAL
        if (hasLimit && val > remainingLimit) {
            errorBox.textContent = `Limite excedido! Máximo permitido: R$ ${remainingLimit.toFixed(2).replace('.', ',')}`;
            errorBox.classList.remove('hidden');
            return;
        }
        
        const btn = document.querySelector('#stepAmount button');
        const oldText = btn.textContent;
        btn.textContent = 'Gerando...'; btn.disabled = true;

        try {
            const res = await fetch('../../api/recharge.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ amount: val })
            });
            const data = await res.json();

            if (data.success) {
                document.getElementById('stepAmount').classList.add('hidden');
                document.getElementById('stepPix').classList.remove('hidden');
                document.getElementById('stepPix').classList.add('flex');
                
                const container = document.getElementById('qrCodeContainer');
                container.innerHTML = ''; 
                
                if (data.qr_code_base64 && data.method === 'MERCADO_PAGO') {
                    const img = document.createElement('img');
                    img.src = 'data:image/png;base64,' + data.qr_code_base64;
                    img.className = 'w-48 h-48 object-contain';
                    container.appendChild(img);
                } else {
                    new QRCode(container, { text: data.copy_paste, width: 190, height: 190, colorDark : "#0f172a", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.M });
                }
                document.getElementById('copyPaste').value = data.copy_paste;
                startStatusPolling(data.external_reference);
            } else {
                errorBox.textContent = 'Erro: ' + data.message;
                errorBox.classList.remove('hidden');
                btn.textContent = oldText; btn.disabled = false;
            }
        } catch(e) {
            errorBox.textContent = 'Erro de conexão com o servidor.';
            errorBox.classList.remove('hidden');
            btn.textContent = oldText; btn.disabled = false;
        }
    }

    async function handleFormSubmit(e, api) {
        e.preventDefault();
        const btn = e.target.querySelector('.submit-btn');
        const oldText = btn.textContent;
        btn.textContent = 'Salvando...'; btn.disabled = true;
        const fd = new FormData(e.target);
        try {
            const res = await fetch('../../api/' + api, { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) { btn.textContent = 'Sucesso!'; btn.classList.replace('bg-emerald-500', 'bg-emerald-700'); setTimeout(() => location.reload(), 1000); } 
            else { alert(data.message); btn.textContent = oldText; btn.disabled = false; }
        } catch(err) { alert('Erro de conexão'); btn.disabled = false; }
    }

    function copyPixCode() {
        const copyText = document.getElementById("copyPaste");
        copyText.select();
        copyText.setSelectionRange(0, 99999); 
        document.execCommand("copy");
        const feedback = document.getElementById('copyFeedback');
        feedback.classList.remove('hidden');
        setTimeout(() => feedback.classList.add('hidden'), 3000);
    }

    function startStatusPolling(ref) {
        if(!ref) return;
        statusInterval = setInterval(async () => {
            try {
                const res = await fetch('../../api/check_status.php?ref=' + ref);
                const data = await res.json();
                if(data.status === 'COMPLETED') { 
                    clearInterval(statusInterval); 
                    closeModal('modalRecharge');
                    openModal('modalSuccessPayment');
                }
            } catch(e) {}
        }, 3000);
    }

    window.onclick = function(e) { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('active'); }
    lucide.createIcons();
</script>