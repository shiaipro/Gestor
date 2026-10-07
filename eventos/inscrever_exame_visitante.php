<?php
require_once '../Gestor/config.php';
require_once __DIR__ . '/inc_cpf.php';
require_once __DIR__ . '/inc_exame_shared.php';

$evento_id       = (int) ($_POST['evento_id'] ?? 0);
$turma_id        = (int) ($_POST['turma_id'] ?? 0);
$nome_completo   = preg_replace('/\s+/', ' ', trim($_POST['nome_completo'] ?? ''));
$cpf             = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$data_nascimento = trim($_POST['data_nascimento'] ?? '');
$genero          = trim($_POST['genero'] ?? '');
$telefone        = trim($_POST['telefone'] ?? '');
$email           = trim($_POST['email'] ?? '');
$tamanho_faixa   = trim($_POST['tamanho_faixa'] ?? '');

$redirect = "evento.php?tipo=exame&id=" . $evento_id;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$evento_id || !$turma_id || $nome_completo === '' || !cpfValido($cpf)) {
    header('Location: ' . $redirect . '&erro=1');
    exit;
}

if (!in_array($genero, ['masculino', 'feminino', 'outro'], true)) {
    $genero = 'masculino';
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

// Nunca confiar no turma_id do cliente: precisa ser uma das turmas liberadas para este evento.
$turmas_liberadas = turmasLiberadasParaExame($pdo, $evento);
$turma_valida = null;
foreach ($turmas_liberadas as $t) {
    if ((int) $t['id'] === $turma_id) {
        $turma_valida = $t;
        break;
    }
}
if (!$turma_valida) {
    header('Location: ' . $redirect . '&erro=1');
    exit;
}

$stmt_turma = $pdo->prepare("SELECT id, unidade_id, academia_id FROM turmas WHERE id = ? AND unidade_id = ? AND status = 'ativo'");
$stmt_turma->execute([$turma_id, $evento['unidade_id']]);
$turma = $stmt_turma->fetch();
if (!$turma) {
    header('Location: ' . $redirect . '&erro=1');
    exit;
}

garantirColunaTipoCadastroAluno($pdo);

try {
    $pdo->beginTransaction();

    // Evita duplicidade: se já existe aluno com esse CPF (do responsável) e esse nome na
    // unidade, reaproveita o cadastro em vez de criar outro (senão o UNIQUE evento_id +
    // aluno_id nunca pega o reenvio). Irmãos com o mesmo responsável viram alunos distintos.
    $stmt_existente = $pdo->prepare("SELECT id, faixa, data_nascimento FROM alunos
        WHERE unidade_id = ?
          AND REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?
          AND TRIM(nome_completo) = ?
        ORDER BY (status = 'ativo') DESC, id ASC
        LIMIT 1");
    $stmt_existente->execute([$evento['unidade_id'], $cpf, $nome_completo]);
    $aluno_existente = $stmt_existente->fetch();

    if ($aluno_existente) {
        $aluno_id = $aluno_existente['id'];

        $stmt_ja = $pdo->prepare("SELECT 1 FROM eventos_graduacao_inscricoes WHERE evento_id = ? AND aluno_id = ?");
        $stmt_ja->execute([$evento_id, $aluno_id]);
        if ($stmt_ja->fetch()) {
            $pdo->rollBack();
            header('Location: ' . $redirect . '&ja_inscrito=1');
            exit;
        }

        $stmt_ta_chk = $pdo->prepare("SELECT 1 FROM turma_alunos WHERE aluno_id = ? AND turma_id = ?");
        $stmt_ta_chk->execute([$aluno_id, $turma_id]);
        if (!$stmt_ta_chk->fetch()) {
            $stmt_ta = $pdo->prepare("INSERT INTO turma_alunos (aluno_id, turma_id) VALUES (?, ?)");
            $stmt_ta->execute([$aluno_id, $turma_id]);
        }

        $faixa_atual = $aluno_existente['faixa'] ?: '';
        $nascimento_calc = $aluno_existente['data_nascimento'] ?: ($data_nascimento ?: null);
    } else {
        $stmt_ins = $pdo->prepare("INSERT INTO alunos
            (unidade_id, academia_id, nome_completo, cpf, data_nascimento, genero, telefone, email_pessoal, status, tipo_cadastro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ativo', 'visitante')");
        $stmt_ins->execute([
            $evento['unidade_id'],
            $turma['academia_id'],
            $nome_completo,
            $cpf,
            $data_nascimento ?: null,
            $genero,
            $telefone ?: null,
            $email ?: null,
        ]);
        $aluno_id = $pdo->lastInsertId();

        $stmt_ta = $pdo->prepare("INSERT INTO turma_alunos (aluno_id, turma_id) VALUES (?, ?)");
        $stmt_ta->execute([$aluno_id, $turma_id]);

        $faixa_atual = '';
        $nascimento_calc = $data_nascimento ?: null;
    }

    $faixa_pretendida = calcularFaixaPretendidaExame($pdo, $evento['unidade_id'], $faixa_atual, $nascimento_calc, $evento['data_evento'] ?? null);
    $calc = calcularValorInscricaoExame($pdo, $evento, $turma_id);

    $stmt_add = $pdo->prepare("INSERT INTO eventos_graduacao_inscricoes (evento_id, aluno_id, faixa_atual, faixa_pretendida, tamanho_faixa, valor_pago) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_add->execute([$evento_id, $aluno_id, $faixa_atual ?: 'Branca', $faixa_pretendida, $tamanho_faixa ?: null, $calc['valor']]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . $redirect . '&ja_inscrito=1');
    exit;
}

notificarInscricaoExame($pdo, $evento, $nome_completo, filter_var($email, FILTER_VALIDATE_EMAIL) ?: null, $nome_completo,
    $faixa_atual ?: 'Branca', $faixa_pretendida, $calc['valor'],
    trim($telefone . ($email ? ' · ' . $email : ''), ' ·'), 'Visitante (site de eventos)');

header('Location: ' . $redirect . '&ok=1');
exit;
