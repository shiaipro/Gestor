<?php
require_once '../config.php';
header('Content-Type: application/json');

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

// Auto-migração: colunas de nota/checklist respondido na inscrição do exame
try {
    $pdo->query("SELECT nota FROM eventos_graduacao_inscricoes LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE eventos_graduacao_inscricoes
        ADD COLUMN nota DECIMAL(4,2) DEFAULT NULL,
        ADD COLUMN itens_avaliados TEXT DEFAULT NULL");
}

try {
    $dados = json_decode(file_get_contents('php://input'), true);
    $inscricao_id = $dados['inscricao_id'] ?? 0;
    $resultado = $dados['resultado'] ?? '';
    $nota = $dados['nota'] ?? null;
    $itens_marcados = $dados['itens_marcados'] ?? [];

    if (!in_array($resultado, ['aprovado', 'reprovado'], true)) {
        echo json_encode(['success' => false, 'message' => 'Resultado inválido.']);
        exit;
    }

    // Ownership: a inscrição precisa pertencer a um evento desta unidade.
    $stmt = $pdo->prepare("
        SELECT ei.*, eg.data_evento, eg.unidade_id
        FROM eventos_graduacao_inscricoes ei
        JOIN eventos_graduacao eg ON eg.id = ei.evento_id
        WHERE ei.id = ? AND eg.unidade_id = ?
    ");
    $stmt->execute([$inscricao_id, $unidade_id]);
    $inscricao = $stmt->fetch();

    if (!$inscricao) {
        echo json_encode(['success' => false, 'message' => 'Inscrição não encontrada.']);
        exit;
    }

    $itens_marcados_ids = array_map('intval', is_array($itens_marcados) ? $itens_marcados : []);

    $pdo->prepare("UPDATE eventos_graduacao_inscricoes SET resultado = ?, nota = ?, itens_avaliados = ? WHERE id = ?")
        ->execute([$resultado, $nota !== null ? round((float)$nota, 2) : null, json_encode($itens_marcados_ids), $inscricao_id]);

    if ($resultado === 'aprovado' && $inscricao['faixa_pretendida']) {
        $pdo->prepare("UPDATE alunos SET faixa = ?, data_graduacao = ? WHERE id = ?")
            ->execute([$inscricao['faixa_pretendida'], $inscricao['data_evento'], $inscricao['aluno_id']]);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
