<?php
// api/test_itau.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/ItauPix.php';

header('Content-Type: text/html; charset=utf-8');

echo "<style>body{font-family:sans-serif; background:#f4f4f9; padding:20px;} .box{background:#fff; padding:20px; margin-bottom:15px; border-radius:8px; border:1px solid #ddd; box-shadow:0 2px 4px rgba(0,0,0,0.05);} h2{margin-top:0;} pre{background:#eee; padding:10px; overflow-x:auto;}</style>";

echo "<h1>🔍 Diagnóstico Itaú (API Conciliações)</h1>";

try {
    // 1. CARREGAR CONFIGURAÇÕES
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'itau_%' OR setting_key = 'payment_provider'");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $token = $settings['itau_api_key'] ?? '';
    $provider = $settings['payment_provider'] ?? '';
    $env = $settings['itau_env'] ?? '';

    echo "<div class='box'>";
    echo "<h2>1. Verificação de Configuração</h2>";
    echo "<ul>";
    echo "<li><strong>Provedor Ativo:</strong> " . ($provider === 'ITAU_PIX' ? '<span style="color:green">✔ ITAU_PIX</span>' : '<span style="color:red">✖ ' . $provider . '</span>') . "</li>";
    echo "<li><strong>Ambiente:</strong> $env</li>";
    echo "<li><strong>Tamanho do Token (API Key):</strong> " . strlen($token) . " caracteres " . (strlen($token) > 100 ? '✔ (Parece um JWT)' : '✖ (Curto demais)') . "</li>";
    echo "</ul>";
    echo "</div>";

    // 2. ANÁLISE DO TOKEN (JWT)
    echo "<div class='box'>";
    echo "<h2>2. Decodificação do Token</h2>";
    
    if (strpos($token, 'ey') === 0) {
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true);
        
        echo "<p><strong>Ambiente do Token:</strong> " . ($payload['source'] ?? 'N/A') . "</p>";
        echo "<p><strong>Expira em:</strong> " . date('d/m/Y H:i:s', $payload['exp'] ?? 0) . "</p>";
        
        $scopes = $payload['scope'] ?? $payload['scopes'] ?? [];
        if(is_string($scopes)) $scopes = explode(' ', $scopes);
        
        echo "<p><strong>Permissões (Scopes):</strong></p><ul>";
        foreach($scopes as $s) {
            echo "<li><code>$s</code></li>";
        }
        echo "</ul>";
        echo "<p><em>*Verifique se o scope acima combina com 'conciliacoes' ou 'pix'.</em></p>";
    } else {
        echo "<p style='color:red'>O conteúdo em 'API Key' não é um Token JWT válido (não começa com 'ey').</p>";
    }
    echo "</div>";

    // 3. TESTE DE GERAÇÃO
    echo "<div class='box'>";
    echo "<h2>3. Tentativa de Criação de Cobrança (R$ 1,00)</h2>";
    
    $itau = new ItauPix($pdo);
    
    // TXID Aleatório (md5 para garantir 32 chars compatíveis)
    $txid = md5('teste-' . time() . mt_rand(1000,9999));
    
    $dadosPagador = [
        'name' => 'Teste Diagnostico',
        'cpf'  => '12345678901' // CPF Válido para Sandbox
    ];

    $resultado = $itau->createCharge($txid, 1.00, $dadosPagador);

    echo "<h3 style='color:green'>✔ SUCESSO! Cobrança Criada.</h3>";
    echo "<p><strong>TXID Gerado:</strong> $txid</p>";
    echo "<p><strong>Copia e Cola:</strong></p>";
    echo "<textarea style='width:100%; height:80px;'>" . ($resultado['copy_paste'] ?? 'N/A') . "</textarea>";
    
    if (empty($resultado['copy_paste'])) {
        echo "<p style='color:orange'>⚠ Aviso: O campo 'copy_paste' veio vazio. Isso é normal em alguns endpoints de conciliação que retornam apenas o status.</p>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='box' style='border-color:red; background:#fff5f5'>";
    echo "<h2 style='color:red'>❌ FALHA</h2>";
    echo "<p><strong>Mensagem:</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>