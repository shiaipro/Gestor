<?php
require_once '../config.php';
header('Content-Type: application/json');

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

try {
    $dados = json_decode(file_get_contents('php://input'), true);
    $mensalidade_id = $dados['mensalidade_id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT m.*, a.nome_completo, a.cpf, a.responsavel_cpf, a.responsavel_nome, a.email_pessoal, a.responsavel_email
        FROM mensalidades m
        JOIN alunos a ON m.aluno_id = a.id
        WHERE m.id = ? AND m.unidade_id = ?
    ");
    $stmt->execute([$mensalidade_id, $unidade_id]);
    $mensalidade = $stmt->fetch();

    if (!$mensalidade) {
        echo json_encode(['success' => false, 'message' => 'Mensalidade não encontrada.']);
        exit;
    }

    if ($mensalidade['status'] === 'pago') {
        echo json_encode(['success' => false, 'message' => 'Esta mensalidade já está paga.']);
        exit;
    }

    $pix = gerarPixParaMensalidade($pdo, $mensalidade, $mensalidade);
    echo json_encode(['success' => true, 'gateway' => $pix['gateway'], 'payload' => $pix['payload'], 'qrcode' => $pix['qrcode'], 'boleto_url' => $pix['boleto_url']]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
