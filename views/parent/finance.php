async function generatePix() {
    const amount = document.getElementById('amountInput').value;
    const btn = document.getElementById('btnGenerate');
    btn.disabled = true;
    btn.innerText = "Processando...";

    try {
        const res = await fetch('../../api/recharge.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ studentId: <?= $studentId ?>, amount: amount })
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('stepAmount').classList.add('hidden');
            document.getElementById('stepPix').classList.remove('hidden');
            
            const qrImg = document.getElementById('qrCodeImg');
            const copyInput = document.getElementById('copyPasteInput');

            if (data.mode === 'AUTOMATIC') {
                // Modo Mercado Pago: Exibe o Base64 retornado ou o payload dinâmico
                qrImg.src = "data:image/png;base64," + data.qr_code_base64;
                copyInput.value = data.qr_code;
                document.getElementById('pixInfoText').innerText = "Pagamento automático! O saldo cairá em instantes após o pagamento.";
            } else {
                // Modo Manual: Exibe o QR Code estático gerado via API de terceiros
                qrImg.src = data.qrCodeUrl;
                copyInput.value = data.copyPaste;
                document.getElementById('pixInfoText').innerText = "Após pagar, aguarde a conferência manual do administrador.";
            }
        } else {
            alert(data.message);
        }
    } catch (e) {
        alert("Erro na comunicação com o servidor.");
    } finally {
        btn.disabled = false;
        btn.innerText = "Gerar Pix";
    }
}