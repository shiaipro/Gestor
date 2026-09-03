<?php
require_once '../Gestor/config.php';
header('Content-Type: application/json; charset=utf-8');

function resposta($ok, $dados = [], $mensagem = '') {
    echo json_encode(array_merge(['success' => $ok, 'message' => $mensagem], $dados));
    exit;
}

$evento_id = (int) ($_POST['evento_id'] ?? 0);
$cpf_digits = preg_replace('/\D/', '', $_POST['cpf'] ?? '');

if (!$evento_id || strlen($cpf_digits) !== 11) {
    resposta(false, [], 'Informe um CPF válido.');
}

// Confirma que o evento existe e pega a unidade dona dele (o aluno precisa ser da mesma unidade)
$stmt_evt = $pdo->prepare("SELECT eg.id, eg.unidade_id, u.slug AS unidade_slug FROM eventos_graduacao eg
                            JOIN unidades u ON u.id = eg.unidade_id
                            WHERE eg.id = ? AND eg.status = 'agendado' AND u.status = 'ativo'");
$stmt_evt->execute([$evento_id]);
$evento = $stmt_evt->fetch();

if (!$evento) {
    resposta(false, [], 'Evento não encontrado.');
}

$voltar_evento = '/eventos/evento.php?tipo=exame&id=' . $evento_id;
$link_cadastro = $evento['unidade_slug']
    ? ('/' . $evento['unidade_slug'] . '/inscricao?voltar=' . urlencode($voltar_evento))
    : null;

$stmt = $pdo->prepare("
    SELECT a.id, a.nome_completo, a.foto, a.faixa, a.data_nascimento, a.unidade_id, a.academia_id,
           (SELECT GROUP_CONCAT(t.nome SEPARATOR ', ') FROM turma_alunos ta JOIN turmas t ON ta.turma_id = t.id WHERE ta.aluno_id = a.id) as turmas_nomes
    FROM alunos a
    WHERE a.unidade_id = ?
      AND a.status = 'ativo'
      AND (
            REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
            OR REPLACE(REPLACE(REPLACE(a.responsavel_cpf, '.', ''), '-', ''), ' ', '') = ?
          )
    ORDER BY a.nome_completo ASC
");
$stmt->execute([$evento['unidade_id'], $cpf_digits, $cpf_digits]);
$alunos = $stmt->fetchAll();

if (!$alunos) {
    resposta(false, ['nao_encontrado' => true, 'link_cadastro' => $link_cadastro], 'Nenhum aluno encontrado com esse CPF nesta unidade.');
}

// Verifica quais já estão inscritos neste evento
$stmt_insc = $pdo->prepare("SELECT aluno_id FROM eventos_graduacao_inscricoes WHERE evento_id = ?");
$stmt_insc->execute([$evento_id]);
$ja_inscritos = array_column($stmt_insc->fetchAll(), 'aluno_id');

$lista = [];
foreach ($alunos as $al) {
    $foto_url = null;
    if (!empty($al['foto'])) {
        $foto_base = '../Gestor/uploads/u_' . (int) $al['unidade_id'];
        if (!empty($al['academia_id'])) {
            $foto_base .= '/academias/a_' . (int) $al['academia_id'];
        }
        $foto_url = $foto_base . '/alunos/' . $al['foto'];
    }

    $lista[] = [
        'id'            => (int) $al['id'],
        'nome'          => $al['nome_completo'],
        'foto'          => $foto_url,
        'faixa'         => $al['faixa'] ?: 'Branca',
        'data_nascimento' => $al['data_nascimento'],
        'turmas'        => $al['turmas_nomes'],
        'ja_inscrito'   => in_array((int) $al['id'], $ja_inscritos, true),
    ];
}

resposta(true, ['alunos' => $lista]);
