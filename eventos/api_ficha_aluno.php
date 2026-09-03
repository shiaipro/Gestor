<?php
require_once '../Gestor/config.php';
require_once __DIR__ . '/inc_exame_shared.php';
header('Content-Type: application/json; charset=utf-8');

function resposta($ok, $dados = [], $mensagem = '') {
    echo json_encode(array_merge(['success' => $ok, 'message' => $mensagem], $dados));
    exit;
}

$evento_id = (int) ($_POST['evento_id'] ?? 0);
$aluno_id  = (int) ($_POST['aluno_id'] ?? 0);

if (!$evento_id || !$aluno_id) {
    resposta(false, [], 'Dados inválidos.');
}

$stmt_evt = $pdo->prepare("SELECT eg.*, u.nome AS unidade_nome FROM eventos_graduacao eg
                            JOIN unidades u ON u.id = eg.unidade_id
                            WHERE eg.id = ? AND eg.status = 'agendado' AND u.status = 'ativo'");
$stmt_evt->execute([$evento_id]);
$evento = $stmt_evt->fetch();

if (!$evento) {
    resposta(false, [], 'Evento não encontrado.');
}

$stmt_al = $pdo->prepare("SELECT a.*
                           FROM alunos a
                           WHERE a.id = ? AND a.unidade_id = ? AND a.status = 'ativo'");
$stmt_al->execute([$aluno_id, $evento['unidade_id']]);
$aluno = $stmt_al->fetch();

if (!$aluno) {
    resposta(false, [], 'Aluno não encontrado nesta unidade.');
}

// Já inscrito?
$stmt_check = $pdo->prepare("SELECT id FROM eventos_graduacao_inscricoes WHERE evento_id = ? AND aluno_id = ?");
$stmt_check->execute([$evento_id, $aluno_id]);
if ($stmt_check->fetch()) {
    resposta(false, [], 'Este aluno já está inscrito neste exame.');
}

// Ordem de graduações da unidade, para calcular a faixa pretendida
$faixa_atual = $aluno['faixa'] ?: '';
$faixa_pretendida = calcularFaixaPretendidaExame($pdo, $evento['unidade_id'], $faixa_atual);

// Turma do aluno nesta unidade (para regras por turma) e cálculo de valor
$stmt_turma_aluno = $pdo->prepare("SELECT t.id, t.nome FROM turma_alunos ta JOIN turmas t ON t.id = ta.turma_id WHERE ta.aluno_id = ? LIMIT 1");
$stmt_turma_aluno->execute([$aluno_id]);
$turma_aluno = $stmt_turma_aluno->fetch();

$calc = calcularValorInscricaoExame($pdo, $evento, $turma_aluno['id'] ?? null);
$valor = $calc['valor'];
$lote_label = $calc['lote_label'];

$foto_url = null;
if (!empty($aluno['foto'])) {
    $foto_base = '../Gestor/uploads/u_' . (int) $aluno['unidade_id'];
    if (!empty($aluno['academia_id'])) {
        $foto_base .= '/academias/a_' . (int) $aluno['academia_id'];
    }
    $foto_url = $foto_base . '/alunos/' . $aluno['foto'];
}

resposta(true, [
    'aluno' => [
        'id'               => (int) $aluno['id'],
        'nome'             => $aluno['nome_completo'],
        'foto'             => $foto_url,
        'data_nascimento'  => $aluno['data_nascimento'],
        'faixa_atual'      => $faixa_atual ?: 'Branca',
        'faixa_pretendida' => $faixa_pretendida,
        'turma'            => $turma_aluno['nome'] ?? null,
        'unidade_nome'     => $evento['unidade_nome'],
    ],
    'valor'      => $valor,
    'lote_label' => $lote_label,
]);
