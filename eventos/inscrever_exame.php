<?php
require_once '../Gestor/config.php';
require_once __DIR__ . '/inc_exame_shared.php';

$evento_id    = (int) ($_POST['evento_id'] ?? 0);
$aluno_id     = (int) ($_POST['aluno_id'] ?? 0);
$tamanho_faixa = trim($_POST['tamanho_faixa'] ?? '');

$redirect = "evento.php?tipo=exame&id=" . $evento_id;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$evento_id || !$aluno_id) {
    header('Location: index.php');
    exit;
}

$stmt_evt = $pdo->prepare("SELECT eg.*, u.status AS unidade_status FROM eventos_graduacao eg
                            JOIN unidades u ON u.id = eg.unidade_id
                            WHERE eg.id = ? AND eg.status = 'agendado'");
$stmt_evt->execute([$evento_id]);
$evento = $stmt_evt->fetch();

if (!$evento || $evento['unidade_status'] !== 'ativo') {
    header('Location: index.php');
    exit;
}

$stmt_al = $pdo->prepare("SELECT * FROM alunos WHERE id = ? AND unidade_id = ? AND status = 'ativo'");
$stmt_al->execute([$aluno_id, $evento['unidade_id']]);
$aluno = $stmt_al->fetch();

if (!$aluno) {
    header('Location: ' . $redirect . '&erro=1');
    exit;
}

// Ordem de graduações da unidade -> faixa pretendida
$faixa_atual = $aluno['faixa'] ?: '';
$faixa_pretendida = calcularFaixaPretendidaExame($pdo, $evento['unidade_id'], $faixa_atual);

// Turma do aluno + cálculo do valor (recalculado no servidor, nunca confiar no front)
$stmt_turma_aluno = $pdo->prepare("SELECT turma_id FROM turma_alunos WHERE aluno_id = ? LIMIT 1");
$stmt_turma_aluno->execute([$aluno_id]);
$turma_aluno = $stmt_turma_aluno->fetch();

$calc = calcularValorInscricaoExame($pdo, $evento, $turma_aluno['turma_id'] ?? null);
$valor_taxa = $calc['valor'];

try {
    $stmt_add = $pdo->prepare("INSERT INTO eventos_graduacao_inscricoes (evento_id, aluno_id, faixa_atual, faixa_pretendida, tamanho_faixa, valor_pago) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_add->execute([$evento_id, $aluno_id, $faixa_atual ?: 'Branca', $faixa_pretendida, $tamanho_faixa ?: null, $valor_taxa]);
} catch (PDOException $e) {
    // Provável duplicidade (UNIQUE evento_id + aluno_id)
    header('Location: ' . $redirect . '&ja_inscrito=1');
    exit;
}

header('Location: ' . $redirect . '&ok=1');
exit;
