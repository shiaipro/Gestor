<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erro' => 'Método inválido']);
    exit;
}

$unidade_id   = getUnidadeId();
$nome         = trim($_POST['nome'] ?? '');
$categoria_id = (int)($_POST['categoria_id'] ?? 0);

if (!$nome) {
    echo json_encode(['ok' => false, 'erro' => 'Nome é obrigatório']);
    exit;
}

$slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nome)));

try {
    // Garantir tabela existe
    try { $pdo->query("SELECT id FROM cms_produtos_subcategorias LIMIT 1"); }
    catch (Exception $e) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_produtos_subcategorias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidade_id INT NOT NULL,
            categoria_id INT NOT NULL,
            nome VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            descricao TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $stmt = $pdo->prepare("INSERT INTO cms_produtos_subcategorias (unidade_id, categoria_id, nome, slug) VALUES (?,?,?,?)");
    $stmt->execute([$unidade_id, $categoria_id, $nome, $slug]);
    $new_id = $pdo->lastInsertId();

    echo json_encode([
        'ok'           => true,
        'id'           => $new_id,
        'nome'         => $nome,
        'categoria_id' => $categoria_id
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
}
