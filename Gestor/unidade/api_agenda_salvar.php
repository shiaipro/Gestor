<?php
require_once '../config.php';
header('Content-Type: application/json');

$unidade_id = getUnidadeId();
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$id = $data['id'] ?? null;
$titulo = $data['titulo'] ?? '';
$data_inicio = $data['start'] ?? '';
$data_fim = $data['end'] ?? '';
$descricao = $data['descricao'] ?? '';
$cor = $data['cor'] ?? '#3b82f6';
$acao = $data['acao'] ?? ''; // 'salvar' ou 'deletar'

try {
    if ($acao === 'deletar' && $id) {
        $stmt = $pdo->prepare("DELETE FROM cms_agenda WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$id, $unidade_id]);
        echo json_encode(['success' => true]);
    } else {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE cms_agenda SET titulo = ?, data_inicio = ?, data_fim = ?, descricao = ?, cor = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$titulo, $data_inicio, $data_fim, $descricao, $cor, $id, $unidade_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cms_agenda (unidade_id, titulo, data_inicio, data_fim, descricao, cor) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $titulo, $data_inicio, $data_fim, $descricao, $cor]);
        }
        echo json_encode(['success' => true]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>