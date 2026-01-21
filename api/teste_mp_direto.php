<?php
// api/debug_mp.php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste com test@testuser.com</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        textarea { width: 100%; height: 100px; padding: 10px; }
        button { background: #009ee3; color: white; border: none; padding: 10px 20px; cursor: pointer; margin-top: 10px; }
        pre { background: #2d2d2d; color: #76e094; padding: 15px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Teste Específico</h1>
        <p>Usando e-mail: <b>test@testuser.com</b></p>
        
        <form method="POST">
            <textarea name="token" placeholder="Cole seu token TEST- aqui..."><?php echo isset($_POST['token']) ? htmlspecialchars($_POST['token']) : ''; ?></textarea>
            <br>
            <button type="submit">Gerar Pix</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['token'])) {
            $accessToken = trim($_POST['token']);
            
            // DADOS EXATOS DA DOCUMENTAÇÃO
            $data = [
                "transaction_amount" => 1.00,
                "description" => "Teste Documentacao",
                "payment_method_id" => "pix",
                "payer" => [
                    "email" => "test@testuser.com", // <--- O E-MAIL QUE VOCÊ PEDIU
                    "first_name" => "APRO", 
                    "last_name" => "U",
                    "identification" => [
                        "type" => "CPF",
                        "number" => "19119119100"
                    ]
                ],
                "date_of_expiration" => date('Y-m-d\TH:i:s.000P', strtotime('+30 minutes'))
            ];

            $ch = curl_init('https://api.mercadopago.com/v1/payments');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'X-Idempotency-Key: ' . uniqid('', true)
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 201) {
                echo "<div style='color:green; margin-top:10px;'><h3>✅ SUCESSO! (HTTP 201)</h3></div>";
            } else {
                echo "<div style='color:red; margin-top:10px;'><h3>❌ ERRO (HTTP $httpCode)</h3></div>";
            }

            echo "<pre>";
            $json = json_decode($response, true);
            echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "</pre>";
        }
        ?>
    </div>
</body>
</html>