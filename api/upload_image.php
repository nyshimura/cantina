<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Apenas operadores ou admins devem fazer upload de imagens para catálogo
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['image'])) {
        throw new Exception("Nenhuma imagem fornecida.");
    }

    $base64 = $input['image'];
    
    // Valida se o formato é de fato base64 de imagem
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
        throw new Exception("Formato de imagem inválido.");
    }

    // Pega a extensão real
    $extension = strtolower($type[1]);
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
        throw new Exception("Formato não suportado.");
    }

    // Decodifica a imagem
    $base64Data = substr($base64, strpos($base64, ',') + 1);
    $imageData = base64_decode($base64Data);

    if ($imageData === false) {
        throw new Exception("Falha ao decodificar a imagem.");
    }

    // Cria o diretório de uploads se não existir
    $uploadDir = __DIR__ . '/../uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Define nome único e salva
    $fileName = uniqid('prod_', true) . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    if (file_put_contents($filePath, $imageData) === false) {
        throw new Exception("Erro ao salvar a imagem no servidor.");
    }

    // Retorna caminho acessível pelo sistema
    // Baseado na arquitetura atual de views/admin/ (ex: URL salva no banco como /uploads/products/nome.ext)
    // Para simplificar, vou usar o caminho relativo que será lido a partir das views:
    $publicUrl = '../../uploads/products/' . $fileName;

    echo json_encode(['success' => true, 'url' => $publicUrl]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
