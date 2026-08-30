<?php
require_once __DIR__ . '/../../includes/auth.php';

// CORREÇÃO: Nivelar permissão com a página de Settings
// Antes estava requireRole('ADMIN'), o que expulsava operadores com permissão
requireRole('OPERATOR');
requirePermission('canManageSettings');

$currentPage = 'settings.php'; // Mantém o menu "Configurações" ativo
require __DIR__ . '/../../includes/header.php';
?>

<div class="flex flex-col h-screen w-full overflow-hidden bg-slate-50">
    <?php include __DIR__ . '/../../includes/top_header.php'; ?>
    
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../includes/app_sidebar.php'; ?>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 lg:p-12 pb-48 md:pb-12">
            <div class="max-w-3xl mx-auto">
                
                <header class="mb-8 flex items-center gap-4">
                    <a href="settings.php" class="p-2 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-500 transition-colors">
                        <i data-lucide="arrow-left" class="w-5 h-5"></i>
                    </a>
                    <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                        <i data-lucide="refresh-cw" class="text-blue-500"></i> Atualização do Sistema
                    </h1>
                </header>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6">
                    <div class="p-8 text-center">
                        
                        <div class="flex justify-center items-center gap-8 mb-8">
                            <div class="flex flex-col items-center">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Versão Atual</span>
                                <div class="w-20 h-20 rounded-full bg-slate-100 flex items-center justify-center text-xl font-black text-slate-600 shadow-inner" id="localVersion">...</div>
                            </div>

                            <div class="hidden md:block h-px w-16 bg-slate-200"></div>

                            <div class="flex flex-col items-center">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Disponível</span>
                                <div class="w-20 h-20 rounded-full bg-blue-50 flex items-center justify-center text-xl font-black text-blue-600 relative shadow-sm border border-blue-100">
                                    <span id="remoteVersion">...</span>
                                    <span class="flex h-3 w-3 absolute top-1 right-1" id="pingDot" style="display:none">
                                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                      <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div id="statusMessage" class="text-sm font-medium text-slate-500 mb-6">Verificando...</div>

                        <button id="btnUpdate" onclick="performUpdate()" disabled class="bg-slate-100 text-slate-400 px-8 py-3 rounded-xl font-bold transition-all flex items-center gap-2 mx-auto cursor-not-allowed">
                            <i data-lucide="download-cloud" class="w-5 h-5"></i> Instalar Atualização
                        </button>
                    </div>
                </div>

                <div class="bg-slate-900 rounded-2xl shadow-lg overflow-hidden font-mono text-xs">
                    <div class="bg-slate-800 px-4 py-2 flex items-center gap-2 border-b border-slate-700">
                        <i data-lucide="terminal" class="w-4 h-4 text-slate-400"></i>
                        <span class="text-slate-300 font-bold">Log de Processamento</span>
                    </div>
                    <div id="logConsole" class="p-4 h-64 overflow-y-auto space-y-1 text-slate-300 scrollbar-thin scrollbar-thumb-slate-700">
                        <p class="text-slate-500">> Aguardando ação...</p>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
    lucide.createIcons();

    function log(msg, type = 'info') {
        const consoleDiv = document.getElementById('logConsole');
        let color = 'text-slate-300';
        if(type === 'success') color = 'text-emerald-400';
        if(type === 'error') color = 'text-red-400';
        if(type === 'warning') color = 'text-amber-400';

        const line = document.createElement('p');
        line.className = `${color} break-all border-b border-slate-800/50 pb-1 mb-1`;
        line.innerHTML = `<span class="opacity-50 mr-2">[${new Date().toLocaleTimeString()}]</span> ${msg}`;
        consoleDiv.appendChild(line);
        consoleDiv.scrollTop = consoleDiv.scrollHeight;
    }

    async function checkUpdate() {
        const btn = document.getElementById('btnUpdate');
        const status = document.getElementById('statusMessage');
        const ping = document.getElementById('pingDot');
        
        log('Conectando ao servidor de atualização...', 'info');

        try {
            const res = await fetch('../../api/system_update.php?action=check');
            
            // Verifica se a resposta é JSON válido (evita erros de HTML/Redirect)
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                // Se der erro de parse, provavelmente retornou HTML de erro (ou login)
                log('Erro na resposta da API. Verifique permissões.', 'error');
                console.error(text);
                return;
            }

            document.getElementById('localVersion').innerText = data.version_local;
            document.getElementById('remoteVersion').innerText = data.version_remote;

            if (data.update_available) {
                status.innerText = "Nova versão disponível!";
                status.className = "text-sm font-bold text-emerald-600 mb-6 bg-emerald-50 px-4 py-2 rounded-lg inline-block border border-emerald-100";
                btn.disabled = false;
                btn.className = "bg-blue-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all flex items-center gap-2 mx-auto active:scale-95";
                ping.style.display = 'flex';
                log('Versão v' + data.version_remote + ' encontrada.', 'success');
            } else {
                status.innerText = "Seu sistema está atualizado.";
                log('Nenhuma atualização pendente.', 'success');
            }

            if(data.logs) data.logs.forEach(l => log(l.msg, l.type));

        } catch (e) {
            log('Falha de conexão: ' + e.message, 'error');
        }
    }

    async function performUpdate() {
        if(!confirm("⚠️ AVISO DE SEGURANÇA ⚠️\n\nO sistema será atualizado e reiniciado.\nRecomendamos fortemente fazer um backup do banco de dados antes.\n\nDeseja continuar?")) return;

        const btn = document.getElementById('btnUpdate');
        btn.disabled = true;
        btn.innerHTML = '<i class="lucide-loader-2 animate-spin w-5 h-5"></i> Atualizando...';
        
        log('Iniciando download e instalação...', 'warning');

        try {
            const res = await fetch('../../api/system_update.php?action=perform_update');
            const data = await res.json();

            if (data.logs) data.logs.forEach(l => log(l.msg, l.type));

            if (data.success) {
                log('Processo finalizado!', 'success');
                btn.innerHTML = '<i data-lucide="check" class="w-5 h-5"></i> Concluído';
                btn.className = "bg-emerald-500 text-white px-8 py-3 rounded-xl font-bold shadow-lg transition-all flex items-center gap-2 mx-auto";
                document.getElementById('localVersion').innerText = data.version_local;
                
                // Recarrega após 3 segundos
                setTimeout(() => {
                    log('Reiniciando sistema...', 'info');
                    location.reload();
                }, 3000);
            } else {
                btn.innerHTML = 'Tentar Novamente';
                btn.disabled = false;
                log('A atualização falhou. Verifique os logs acima.', 'error');
            }

        } catch (e) {
            log('Erro crítico: ' + e, 'error');
            btn.innerHTML = 'Erro';
        }
        lucide.createIcons();
    }

    document.addEventListener('DOMContentLoaded', checkUpdate);
</script>