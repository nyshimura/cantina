<?php
// api/debug_lib.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🕵️ Diagnóstico da Biblioteca Mercado Pago</h1>";

// 1. Verifica Caminho da Pasta
$pathEsperado = __DIR__ . '/../lib/vendor/autoload.php';
$pathReal = realpath($pathEsperado);

echo "<p>Checking path: <code>" . $pathEsperado . "</code></p>";

if (file_exists($pathEsperado)) {
    echo "<p style='color:green'>✅ Arquivo <b>autoload.php</b> encontrado!</p>";
} else {
    echo "<p style='color:red'>❌ <b>ERRO FATAL:</b> Arquivo não encontrado.</p>";
    echo "<p><b>Onde o sistema procurou:</b> " . $pathEsperado . "</p>";
    echo "<p><b>Dica:</b> Verifique se você não criou uma pasta dentro da outra (ex: <code>/lib/lib/vendor</code>) ou se a pasta se chama <code>MercadoPago-API...</code> em vez de apenas <code>lib</code>.</p>";
    exit;
}

// 2. Tenta Carregar
try {
    require_once $pathEsperado;
    echo "<p style='color:green'>✅ Autoload carregado com sucesso.</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erro ao carregar autoload: " . $e->getMessage() . "</p>";
    exit;
}

// 3. Verifica Classe SDK
if (class_exists('MercadoPago\SDK')) {
    echo "<p style='color:green'>✅ Classe <b>MercadoPago\SDK</b> detectada!</p>";
} else {
    echo "<p style='color:red'>❌ A classe SDK não foi encontrada. O Autoload carregou, mas as classes não vieram.</p>";
    exit;
}

// 4. Teste de Instância
try {
    // Configura um token falso apenas para testar a classe
    MercadoPago\SDK::setAccessToken("APP_USR-2633445812642491-011520-3ecac82c3c00bf62b84b69546e7c20a6-94447671");
    $payment = new MercadoPago\Payment();
    echo "<p style='color:green'>✅ Objeto <b>Payment</b> criado com sucesso! A biblioteca está instalada corretamente.</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erro ao instanciar Payment: " . $e->getMessage() . "</p>";
}

echo "<hr><h3>Resumo</h3>";
echo "Se todos os passos acima ficaram verdes, o problema está no <code>recharge.php</code> (lógica).<br>";
echo "Se deu erro vermelho, siga a dica apresentada.";
?>