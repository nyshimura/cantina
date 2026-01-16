<?php
// api/debug_token.php
require_once __DIR__ . '/../includes/auth.php'; // Carrega conexão com banco e funções

// Habilita exibição de erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🕵️ Diagnóstico de Token e Criptografia</h1>";

try {
    // 1. Busca o valor BRUTO no banco de dados
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'mp_access_token'");
    $tokenCriptografado = $stmt->fetchColumn();

    echo "<h3>1. O que está no Banco de Dados:</h3>";
    if ($tokenCriptografado) {
        echo "<div style='background:#eee; padding:10px; word-break:break-all;'>";
        echo htmlspecialchars($tokenCriptografado);
        echo "</div>";
    } else {
        echo "<p style='color:red'>❌ O campo 'mp_access_token' está VAZIO no banco.</p>";
        exit;
    }

    // 2. Tenta Descriptografar
    echo "<h3>2. Tentativa de Descriptografia:</h3>";
    
    if (function_exists('decryptData')) {
        $tokenLimpo = decryptData($tokenCriptografado);
        
        // Verifica se a descriptografia retornou algo válido
        if (empty($tokenLimpo)) {
            echo "<p style='color:red'>❌ A função decryptData() retornou vazio. A chave de criptografia pode ter mudado ou o dado está corrompido.</p>";
            // Tenta usar o valor original caso não seja criptografado
            $tokenLimpo = $tokenCriptografado; 
            echo "<p>Tentando usar o valor original (sem descriptografar)...</p>";
        } else {
            echo "<p style='color:green'>✅ Descriptografia realizada!</p>";
        }

        // Mostra o Token Mascarado
        $tamanho = strlen($tokenLimpo);
        if ($tamanho > 10) {
            $mascara = substr($tokenLimpo, 0, 10) . "..." . substr($tokenLimpo, -6);
            echo "<p><b>Token Resultante:</b> <span style='background:yellow'>$mascara</span></p>";
            
            // Analisa o formato
            if (strpos($tokenLimpo, 'TEST-') === 0) {
                echo "<p style='color:blue'>ℹ️ Este é um token de <b>SANDBOX (Teste)</b>.</p>";
            } elseif (strpos($tokenLimpo, 'APP_USR-') === 0) {
                echo "<p style='color:orange'>ℹ️ Este é um token de <b>PRODUÇÃO</b>.</p>";
            } else {
                echo "<p style='color:red'>⚠️ O formato parece estranho (não começa com TEST- nem APP_USR-). Verifique se descriptografou certo.</p>";
                echo "<p>Valor real (primeiros 50 chars): " . substr($tokenLimpo, 0, 50) . "</p>";
            }

        } else {
            echo "<p style='color:red'>❌ O token resultante é muito curto ($tamanho caracteres). Provavelmente lixo de criptografia.</p>";
        }

    } else {
        echo "<p style='color:red'>❌ Erro: A função decryptData() não existe no auth.php.</p>";
        $tokenLimpo = $tokenCriptografado;
    }

    // 3. Teste de Conexão com o Mercado Pago (Usando a Lib Nova)
    echo "<h3>3. Teste de Conexão com a API (SDK):</h3>";
    
    if (file_exists(__DIR__ . '/../lib/vendor/autoload.php')) {
        require_once __DIR__ . '/../lib/vendor/autoload.php';
        
        try {
            MercadoPago\SDK::setAccessToken($tokenLimpo);
            echo "<p>✅ SDK Configurada. Tentando criar objeto de pagamento...</p>";
            
            // Cria um pagamento fake apenas para ver se a SDK aceita o token (sem salvar)
            $payment = new MercadoPago\Payment();
            if ($payment) {
                echo "<p style='color:green'><b>✅ SUCESSO!</b> A SDK aceitou o token e instanciou a classe.</p>";
                echo "<p>Se der erro ao gerar o Pix no <code>recharge.php</code>, o problema é nos dados do cliente (email), não no token.</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:red'>❌ Erro ao configurar SDK: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Biblioteca não encontrada para teste.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Erro Geral: " . $e->getMessage() . "</p>";
}
?>