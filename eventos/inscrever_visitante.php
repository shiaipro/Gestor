<?php
require_once '../Gestor/config.php';
require_once __DIR__ . '/inc_cpf.php';

$tabelas_evento = [
    'exame'   => ['tabela' => 'eventos_graduacao', 'status_col' => "status = 'agendado'"],
    'torneio' => ['tabela' => 'competicoes', 'status_col' => "status = 'aberto'"],
    'oficial' => ['tabela' => 'competicoes_oficiais', 'status_col' => "status = 'aberto'"],
];

$tipo      = $_POST['tipo'] ?? '';
$evento_id = (int) ($_POST['evento_id'] ?? 0);

$redirect = "evento.php?tipo=" . urlencode($tipo) . "&id=" . $evento_id;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$evento_id || !isset($tabelas_evento[$tipo])) {
    header('Location: index.php');
    exit;
}

$cfg = $tabelas_evento[$tipo];

$nome            = preg_replace('/\s+/', ' ', trim(filter_input(INPUT_POST, 'nome', FILTER_DEFAULT) ?? ''));
$cpf_digits      = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$data_nascimento = trim($_POST['data_nascimento'] ?? '') ?: null;
// O formulário de visitante pede só o ano (ex: 2019); grava como 01/01 desse ano
$ano_nascimento  = preg_replace('/\D/', '', $_POST['ano_nascimento'] ?? '');
if (!$data_nascimento && strlen($ano_nascimento) === 4 && (int) $ano_nascimento >= 1900 && (int) $ano_nascimento <= (int) date('Y')) {
    $data_nascimento = $ano_nascimento . '-01-01';
}
$telefone        = trim(filter_input(INPUT_POST, 'telefone', FILTER_DEFAULT) ?? '');
$email           = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: null;
$faixa           = trim(filter_input(INPUT_POST, 'faixa', FILTER_DEFAULT) ?? '') ?: null;

// Delegação/academia e categoria disputada — só fazem sentido para torneios
$delegacao_id    = ($tipo === 'torneio' && !empty($_POST['delegacao_id'])) ? (int) $_POST['delegacao_id'] : null;
$delegacao_nome  = trim(filter_input(INPUT_POST, 'delegacao_nome', FILTER_DEFAULT) ?? '') ?: null;
$categoria_id    = ($tipo === 'torneio' && !empty($_POST['categoria_id'])) ? (int) $_POST['categoria_id'] : null;
$turma_id        = ($tipo === 'torneio' && !empty($_POST['turma_id'])) ? (int) $_POST['turma_id'] : null;
$delegacao_turma_id = ($tipo === 'torneio' && !empty($_POST['delegacao_turma_id'])) ? (int) $_POST['delegacao_turma_id'] : null;
$turma_externa   = null;

if (!$nome || !cpfValido($cpf_digits) || !$telefone) {
    header('Location: ' . $redirect . '&erro=1');
    exit;
}

$stmt_evt = $pdo->prepare("SELECT ev.id, ev.unidade_id FROM {$cfg['tabela']} ev
                            JOIN unidades u ON u.id = ev.unidade_id
                            WHERE ev.id = ? AND ev.{$cfg['status_col']} AND u.status = 'ativo'");
$stmt_evt->execute([$evento_id]);
$evento = $stmt_evt->fetch();

if (!$evento) {
    header('Location: index.php');
    exit;
}

// Valida que a delegação/categoria informadas realmente pertencem a este torneio
// (evita gravar id de outra unidade vindo de um POST manipulado)
if ($delegacao_id) {
    $chk = $pdo->prepare("SELECT 1 FROM competicao_convidados WHERE competicao_id = ? AND delegacao_id = ?");
    $chk->execute([$evento_id, $delegacao_id]);
    if (!$chk->fetch()) {
        $delegacao_id = null;
    }
}
// Turma / professor da delegação: precisa pertencer à delegação (já validada acima)
if ($delegacao_turma_id) {
    $turma_externa = null;
    if ($delegacao_id) {
        try {
            $chk_td = $pdo->prepare("SELECT nome FROM delegacoes_turmas WHERE id = ? AND delegacao_id = ?");
            $chk_td->execute([$delegacao_turma_id, $delegacao_id]);
            $turma_externa = $chk_td->fetchColumn() ?: null;
        } catch (PDOException $e) {
            $turma_externa = null;
        }
    }
    if (!$turma_externa) {
        $delegacao_turma_id = null;
    }
}
if ($turma_id) {
    $chk_turma = $pdo->prepare("SELECT 1 FROM turmas WHERE id = ? AND unidade_id = ? AND status = 'ativo'");
    $chk_turma->execute([$turma_id, $evento['unidade_id']]);
    if (!$chk_turma->fetch()) {
        $turma_id = null;
    }
}
if ($categoria_id) {
    $chk = $pdo->prepare("SELECT limite_atletas FROM competicao_categorias WHERE id = ? AND competicao_id = ?");
    $chk->execute([$categoria_id, $evento_id]);
    $categoria = $chk->fetch();
    if (!$categoria) {
        $categoria_id = null;
    } elseif ($categoria['limite_atletas']) {
        $stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM competicao_inscricoes WHERE categoria_id = ?");
        $stmt_cnt->execute([$categoria_id]);
        if ((int) $stmt_cnt->fetchColumn() >= (int) $categoria['limite_atletas']) {
            header('Location: ' . $redirect . '&categoria_lotada=1');
            exit;
        }
    }
}

// Auto-migração (mesma tabela usada em api_visitante_cpf.php)
try {
    $pdo->query("SELECT 1 FROM eventos_visitantes LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS eventos_visitantes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evento_tipo ENUM('exame','torneio','oficial') NOT NULL,
        evento_id INT NOT NULL,
        unidade_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        cpf VARCHAR(14) NOT NULL,
        data_nascimento DATE DEFAULT NULL,
        telefone VARCHAR(30) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        faixa VARCHAR(50) DEFAULT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE,
        UNIQUE KEY unique_visitante_evento (evento_tipo, evento_id, cpf, nome(150))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Auto-migração: delegação (academia visitante) e categoria disputada, usadas em torneios
$col_deleg = $pdo->query("SHOW COLUMNS FROM eventos_visitantes LIKE 'delegacao_id'")->fetch();
if (!$col_deleg) {
    $pdo->exec("ALTER TABLE eventos_visitantes
        ADD COLUMN delegacao_id INT DEFAULT NULL AFTER faixa,
        ADD COLUMN delegacao_nome VARCHAR(255) DEFAULT NULL AFTER delegacao_id,
        ADD COLUMN categoria_id INT DEFAULT NULL AFTER delegacao_nome,
        ADD FOREIGN KEY (delegacao_id) REFERENCES delegacoes_visitantes(id) ON DELETE SET NULL,
        ADD FOREIGN KEY (categoria_id) REFERENCES competicao_categorias(id) ON DELETE SET NULL
    ");
}

// Auto-migração: turma escolhida (para visitantes de torneio que já são alunos da unidade)
$col_turma = $pdo->query("SHOW COLUMNS FROM eventos_visitantes LIKE 'turma_id'")->fetch();
if (!$col_turma) {
    $pdo->exec("ALTER TABLE eventos_visitantes
        ADD COLUMN turma_id INT DEFAULT NULL AFTER categoria_id,
        ADD FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE SET NULL
    ");
}

// Auto-migração: turma / professor da delegação (delegacoes_turmas) escolhida pelo visitante
$col_turma_ext = $pdo->query("SHOW COLUMNS FROM eventos_visitantes LIKE 'turma_externa'")->fetch();
if (!$col_turma_ext) {
    $pdo->exec("ALTER TABLE eventos_visitantes
        ADD COLUMN delegacao_turma_id INT DEFAULT NULL AFTER turma_id,
        ADD COLUMN turma_externa VARCHAR(255) DEFAULT NULL AFTER delegacao_turma_id
    ");
}

// Auto-migração: o CPF é do responsável, então um mesmo CPF pode inscrever vários atletas
// (ex: irmãos). A trava de duplicidade passa a ser CPF + nome do atleta.
$idx_nome = $pdo->query("SHOW INDEX FROM eventos_visitantes WHERE Key_name = 'unique_visitante_evento' AND Column_name = 'nome'")->fetch();
if (!$idx_nome) {
    $pdo->exec("ALTER TABLE eventos_visitantes
        DROP INDEX unique_visitante_evento,
        ADD UNIQUE KEY unique_visitante_evento (evento_tipo, evento_id, cpf, nome(150))
    ");
}

try {
    $stmt = $pdo->prepare("INSERT INTO eventos_visitantes (evento_tipo, evento_id, unidade_id, nome, cpf, data_nascimento, telefone, email, faixa, delegacao_id, delegacao_nome, categoria_id, turma_id, delegacao_turma_id, turma_externa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tipo, $evento_id, $evento['unidade_id'], $nome, $cpf_digits, $data_nascimento, $telefone, $email, $faixa, $delegacao_id, $delegacao_nome, $categoria_id, $turma_id, $delegacao_turma_id, $turma_externa]);
} catch (PDOException $e) {
    // Provável duplicidade (UNIQUE evento_tipo + evento_id + cpf + nome do atleta)
    header('Location: ' . $redirect . '&ja_inscrito=1');
    exit;
}

// Torneio: além do registro genérico acima, grava também em competicao_inscricoes —
// é essa tabela que a aba "Inscrições" de editar_competicao.php lê para montar a lista
// de "Atletas Inscritos". Sem isso, a inscrição feita pelo site nunca aparecia lá.
if ($tipo === 'torneio') {
    $col_check = $pdo->query("SHOW COLUMNS FROM competicao_inscricoes LIKE 'cpf_externo'")->fetch();
    if (!$col_check) {
        $pdo->exec("ALTER TABLE competicao_inscricoes
            ADD COLUMN cpf_externo VARCHAR(14) DEFAULT NULL AFTER faixa_externa,
            ADD COLUMN telefone_externo VARCHAR(30) DEFAULT NULL AFTER cpf_externo,
            ADD COLUMN email_externo VARCHAR(255) DEFAULT NULL AFTER telefone_externo
        ");
    }

    $col_check_turma = $pdo->query("SHOW COLUMNS FROM competicao_inscricoes LIKE 'turma_id'")->fetch();
    if (!$col_check_turma) {
        $pdo->exec("ALTER TABLE competicao_inscricoes
            ADD COLUMN turma_id INT DEFAULT NULL AFTER email_externo,
            ADD FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE SET NULL
        ");
    }

    $col_check_turma_ext = $pdo->query("SHOW COLUMNS FROM competicao_inscricoes LIKE 'turma_externa'")->fetch();
    if (!$col_check_turma_ext) {
        $pdo->exec("ALTER TABLE competicao_inscricoes
            ADD COLUMN delegacao_turma_id INT DEFAULT NULL AFTER turma_id,
            ADD COLUMN turma_externa VARCHAR(255) DEFAULT NULL AFTER delegacao_turma_id
        ");
    }

    $equipe_externa = $delegacao_nome;
    if ($delegacao_id) {
        $stmt_deleg = $pdo->prepare("SELECT nome FROM delegacoes_visitantes WHERE id = ?");
        $stmt_deleg->execute([$delegacao_id]);
        $equipe_externa = $stmt_deleg->fetchColumn() ?: $delegacao_nome;
    }

    // Evita duplicar o mesmo atleta neste torneio caso reenviem o formulário
    // (CPF do responsável + nome do atleta: irmãos com o mesmo responsável podem se inscrever)
    $chk_dup = $pdo->prepare("SELECT 1 FROM competicao_inscricoes WHERE competicao_id = ? AND cpf_externo = ? AND nome_externo = ?");
    $chk_dup->execute([$evento_id, $cpf_digits, $nome]);
    if (!$chk_dup->fetch()) {
        // Valor vem do lote vigente do torneio (nunca do formulário)
        $cobranca_torneio = prepararCobrancaInscricaoTorneio($pdo, $evento_id);
        $stmt_insc = $pdo->prepare("INSERT INTO competicao_inscricoes
            (competicao_id, categoria_id, lote_id, nome_externo, equipe_externa, faixa_externa, cpf_externo, telefone_externo, email_externo, turma_id, delegacao_turma_id, turma_externa, valor_pago, status_pagamento, token_pagamento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?)");
        $stmt_insc->execute([$evento_id, $categoria_id, $cobranca_torneio['lote_id'], $nome, $equipe_externa, $faixa, $cpf_digits, $telefone, $email, $turma_id, $delegacao_turma_id, $turma_externa, $cobranca_torneio['valor'], $cobranca_torneio['token']]);

        if ($cobranca_torneio['valor'] > 0 && getGatewayAtivoParaUnidade($pdo, $evento['unidade_id'])) {
            $token_pagamento_torneio = $cobranca_torneio['token'];
        }
    }
}
$token_pagamento_torneio = $token_pagamento_torneio ?? null;

// E-mails: confirmação ao visitante (se informou e-mail) e aviso ao admin da unidade.
// A inscrição já foi gravada — falha de e-mail nunca pode derrubar o redirecionamento.
try {
    $cols_evento = [
        'exame'   => "SELECT titulo AS nome, data_evento AS data, `local` AS local_evt FROM eventos_graduacao WHERE id = ?",
        'torneio' => "SELECT nome, data_evento AS data, localizacao AS local_evt FROM competicoes WHERE id = ?",
        'oficial' => "SELECT nome, data_inicio AS data, localizacao AS local_evt FROM competicoes_oficiais WHERE id = ?",
    ];
    $labels_evento = ['exame' => 'Exame de Faixa', 'torneio' => 'Torneio', 'oficial' => 'Competição Oficial'];

    $stmt_dados = $pdo->prepare($cols_evento[$tipo]);
    $stmt_dados->execute([$evento_id]);
    $dados_evt = $stmt_dados->fetch() ?: [];
    $nome_evento = $dados_evt['nome'] ?? $labels_evento[$tipo];

    $nome_categoria = '';
    if ($categoria_id) {
        $stmt_cat = $pdo->prepare("SELECT nome FROM competicao_categorias WHERE id = ? AND competicao_id = ?");
        $stmt_cat->execute([$categoria_id, $evento_id]);
        $nome_categoria = (string) $stmt_cat->fetchColumn();
    }

    $equipe_email = $equipe_externa ?? $delegacao_nome ?? '';

    if ($email) {
        $stmt_un = $pdo->prepare("SELECT razao_social, nome FROM unidades WHERE id = ?");
        $stmt_un->execute([$evento['unidade_id']]);
        $un = $stmt_un->fetch();

        $corpo = montarEmailConfirmacaoEvento([
            'unidade'    => ($un['razao_social'] ?? '') ?: (($un['nome'] ?? '') ?: 'SHIAI PRO'),
            'evento'     => $nome_evento,
            'tipo_label' => $labels_evento[$tipo],
            'atleta'     => $nome,
            'data'       => !empty($dados_evt['data']) ? date('d/m/Y', strtotime($dados_evt['data'])) : '',
            'local'      => $dados_evt['local_evt'] ?? '',
            'equipe'     => $equipe_email,
            'faixa'      => $faixa ?? '',
            'categoria'  => $nome_categoria,
            'pagamento'  => 'pendente',
            'valor'      => $cobranca_torneio['valor'] ?? null,
            'link_pagamento' => $token_pagamento_torneio ? urlPagamentoTorneio($token_pagamento_torneio) : null,
        ]);
        enviarEmailUnidade($pdo, $evento['unidade_id'], $email, $nome, 'Inscrição confirmada - ' . $nome_evento, $corpo, true);
    }

    notificarAdminsNovaInscricao($pdo, $evento['unidade_id'], 'Nova inscrição - ' . $nome_evento, [
        'evento'     => $nome_evento,
        'tipo_label' => $labels_evento[$tipo],
        'atleta'     => $nome,
        'faixa'      => $faixa ?? '',
        'equipe'     => $equipe_email,
        'categoria'  => $nome_categoria,
        'contato'    => trim($telefone . ($email ? ' · ' . $email : '')),
        'origem'     => 'Visitante (site de eventos)',
    ]);
} catch (\Throwable $e) {
    error_log('inscrever_visitante: falha ao notificar por e-mail: ' . $e->getMessage());
}

// Torneio com valor e pagamento online: leva direto para a tela do PIX
if ($token_pagamento_torneio) {
    header('Location: pagar_torneio.php?t=' . urlencode($token_pagamento_torneio));
    exit;
}

header('Location: ' . $redirect . '&ok=1');
exit;
