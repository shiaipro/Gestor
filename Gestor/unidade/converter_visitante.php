<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('alunos_criar')) {
    header('Location: eventos_visitantes.php?erro=sem_permissao');
    exit;
}

$visitante_id = (int) ($_POST['visitante_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$visitante_id) {
    header('Location: eventos_visitantes.php');
    exit;
}

$stmt_v = $pdo->prepare("SELECT * FROM eventos_visitantes WHERE id = ? AND unidade_id = ?");
$stmt_v->execute([$visitante_id, $unidade_id]);
$visitante = $stmt_v->fetch();

if (!$visitante) {
    header('Location: eventos_visitantes.php?erro=nao_encontrado');
    exit;
}

if (!empty($visitante['aluno_id'])) {
    header('Location: eventos_visitantes.php?erro=ja_convertido');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt_ins = $pdo->prepare("INSERT INTO alunos
        (unidade_id, nome_completo, email_pessoal, cpf, data_nascimento, genero, telefone, faixa, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ativo')");
    $stmt_ins->execute([
        $unidade_id,
        $visitante['nome'],
        $visitante['email'],
        $visitante['cpf'],
        $visitante['data_nascimento'],
        'masculino',
        $visitante['telefone'],
        $visitante['faixa'] ?: 'Branca',
    ]);
    $novo_aluno_id = $pdo->lastInsertId();

    $stmt_upd = $pdo->prepare("UPDATE eventos_visitantes SET aluno_id = ? WHERE id = ?");
    $stmt_upd->execute([$novo_aluno_id, $visitante_id]);

    $pdo->commit();

    header('Location: editar_aluno.php?id=' . $novo_aluno_id . '&sucesso=convertido');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: eventos_visitantes.php?erro=falha_conversao');
    exit;
}
