<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$unidade_id = getUnidadeId();
$evento_id = $_GET['id'] ?? 0;

// Recuperar e limpar mensagens da sessão
$mensagem = $_SESSION['mensagem'] ?? "";
$sucesso = $_SESSION['sucesso'] ?? "";
unset($_SESSION['mensagem'], $_SESSION['sucesso']);

if (!$evento_id) {
    header("Location: faixas.php");
    exit;
}

// 1. Verificações / Migrações (para garantir que tudo existe)
garantirColunaTipoCadastroAluno($pdo);
try {
    $pdo->query("SELECT 1 FROM eventos_graduacao LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS eventos_graduacao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        academia_id INT DEFAULT NULL,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT,
        data_evento DATE NOT NULL,
        horario TIME,
        local VARCHAR(255),
        taxa DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('agendado', 'concluido', 'cancelado') DEFAULT 'agendado',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE,
        FOREIGN KEY (academia_id) REFERENCES academias(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

try {
    $pdo->query("SELECT 1 FROM eventos_graduacao_inscricoes LIMIT 1");
} catch (Exception $e) {
    $sql_insc = "CREATE TABLE IF NOT EXISTS eventos_graduacao_inscricoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evento_id INT NOT NULL,
        aluno_id INT NOT NULL,
        faixa_atual VARCHAR(50),
        faixa_pretendida VARCHAR(50),
        valor_pago DECIMAL(10,2) DEFAULT 0.00,
        status_pagamento ENUM('pendente', 'pago') DEFAULT 'pendente',
        resultado ENUM('pendente', 'aprovado', 'reprovado') DEFAULT 'pendente',
        observacoes TEXT,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (evento_id) REFERENCES eventos_graduacao(id) ON DELETE CASCADE,
        FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
        UNIQUE KEY unique_inscricao (evento_id, aluno_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_insc);
}

// Auto-migração: Garantir que a coluna tamanho_faixa existe na tabela de inscrições
try {
    $pdo->query("SELECT tamanho_faixa FROM eventos_graduacao_inscricoes LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE eventos_graduacao_inscricoes ADD COLUMN tamanho_faixa VARCHAR(50) DEFAULT NULL;");
    } catch (Exception $ex) {}
}

// Auto-migração: financeiro próprio do evento (conta separada por edição do exame)
try {
    $pdo->query("SELECT 1 FROM eventos_graduacao_financeiro LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS eventos_graduacao_financeiro (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evento_id INT NOT NULL,
        unidade_id INT NOT NULL,
        tipo ENUM('receita', 'despesa') NOT NULL,
        descricao VARCHAR(255) NOT NULL,
        valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        data_lancamento DATE NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (evento_id) REFERENCES eventos_graduacao(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Auto-migração: cronograma do exame (inscrições, requisitos, avaliações) e data do evento
$novas_colunas_evento = [
    'inscricoes_inicio' => "ALTER TABLE eventos_graduacao ADD COLUMN inscricoes_inicio DATE DEFAULT NULL",
    'inscricoes_fim' => "ALTER TABLE eventos_graduacao ADD COLUMN inscricoes_fim DATE DEFAULT NULL",
    'avaliacoes_inicio' => "ALTER TABLE eventos_graduacao ADD COLUMN avaliacoes_inicio DATE DEFAULT NULL",
    'avaliacoes_fim' => "ALTER TABLE eventos_graduacao ADD COLUMN avaliacoes_fim DATE DEFAULT NULL",
    'requisito_pagamento_obrigatorio' => "ALTER TABLE eventos_graduacao ADD COLUMN requisito_pagamento_obrigatorio TINYINT(1) DEFAULT 1",
    'requisito_frequencia_minima' => "ALTER TABLE eventos_graduacao ADD COLUMN requisito_frequencia_minima DECIMAL(5,2) DEFAULT NULL",
    'requisito_carencia_ativo' => "ALTER TABLE eventos_graduacao ADD COLUMN requisito_carencia_ativo TINYINT(1) DEFAULT 1",
    'todas_turmas' => "ALTER TABLE eventos_graduacao ADD COLUMN todas_turmas TINYINT(1) DEFAULT 1",
    'mesmo_valor_todas_turmas' => "ALTER TABLE eventos_graduacao ADD COLUMN mesmo_valor_todas_turmas TINYINT(1) DEFAULT 1",
    'taxa_lote2' => "ALTER TABLE eventos_graduacao ADD COLUMN taxa_lote2 DECIMAL(10,2) DEFAULT NULL",
    'lote2_data_inicio' => "ALTER TABLE eventos_graduacao ADD COLUMN lote2_data_inicio DATE DEFAULT NULL",
    'avaliacoes_numero_tentativas' => "ALTER TABLE eventos_graduacao ADD COLUMN avaliacoes_numero_tentativas INT DEFAULT NULL",
    'mesma_data_todas_turmas' => "ALTER TABLE eventos_graduacao ADD COLUMN mesma_data_todas_turmas TINYINT(1) DEFAULT 1",
];
foreach ($novas_colunas_evento as $coluna => $sql_alter) {
    try {
        $pdo->query("SELECT $coluna FROM eventos_graduacao LIMIT 1");
    } catch (Exception $e) {
        try { $pdo->exec($sql_alter); } catch (Exception $ex) {}
    }
}

// Auto-migração: tabela de datas individuais por turma (quando as turmas não têm a mesma data)
try {
    $pdo->query("SELECT 1 FROM eventos_graduacao_datas_turma LIMIT 1");
} catch (Exception $e) {
    $sql_egd = "CREATE TABLE IF NOT EXISTS eventos_graduacao_datas_turma (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evento_id INT NOT NULL,
        turma_id INT NOT NULL,
        data_evento DATE DEFAULT NULL,
        inscricoes_inicio DATE DEFAULT NULL,
        inscricoes_fim DATE DEFAULT NULL,
        avaliacoes_inicio DATE DEFAULT NULL,
        avaliacoes_fim DATE DEFAULT NULL,
        FOREIGN KEY (evento_id) REFERENCES eventos_graduacao(id) ON DELETE CASCADE,
        FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
        UNIQUE KEY unique_evento_turma_data (evento_id, turma_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_egd);
}

// Auto-migração: campos completos de cadastro por turma (valores de lote, local de entrega, tentativas)
$novas_colunas_datas_turma = [
    'valor_lote1' => "ALTER TABLE eventos_graduacao_datas_turma ADD COLUMN valor_lote1 DECIMAL(10,2) DEFAULT NULL",
    'valor_lote1_data_limite' => "ALTER TABLE eventos_graduacao_datas_turma ADD COLUMN valor_lote1_data_limite DATE DEFAULT NULL",
    'valor_lote2' => "ALTER TABLE eventos_graduacao_datas_turma ADD COLUMN valor_lote2 DECIMAL(10,2) DEFAULT NULL",
    'valor_lote2_data_limite' => "ALTER TABLE eventos_graduacao_datas_turma ADD COLUMN valor_lote2_data_limite DATE DEFAULT NULL",
    'local_entrega' => "ALTER TABLE eventos_graduacao_datas_turma ADD COLUMN local_entrega VARCHAR(255) DEFAULT NULL",
    'avaliacoes_numero_tentativas' => "ALTER TABLE eventos_graduacao_datas_turma ADD COLUMN avaliacoes_numero_tentativas INT DEFAULT NULL",
    'requisito_pagamento_obrigatorio' => "ALTER TABLE eventos_graduacao_datas_turma ADD COLUMN requisito_pagamento_obrigatorio TINYINT(1) DEFAULT 1",
    'requisito_frequencia_minima' => "ALTER TABLE eventos_graduacao_datas_turma ADD COLUMN requisito_frequencia_minima DECIMAL(5,2) DEFAULT NULL",
    'requisito_carencia_ativo' => "ALTER TABLE eventos_graduacao_datas_turma ADD COLUMN requisito_carencia_ativo TINYINT(1) DEFAULT 1",
    'horario_entrega' => "ALTER TABLE eventos_graduacao_datas_turma ADD COLUMN horario_entrega TIME DEFAULT NULL",
];
foreach ($novas_colunas_datas_turma as $coluna => $sql_alter) {
    try {
        $pdo->query("SELECT $coluna FROM eventos_graduacao_datas_turma LIMIT 1");
    } catch (Exception $e) {
        try { $pdo->exec($sql_alter); } catch (Exception $ex) {}
    }
}

// Auto-migração: tabela de turmas participantes (quando nem todas as turmas participam do exame)
try {
    $pdo->query("SELECT 1 FROM eventos_graduacao_turmas LIMIT 1");
} catch (Exception $e) {
    $sql_egt = "CREATE TABLE IF NOT EXISTS eventos_graduacao_turmas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evento_id INT NOT NULL,
        turma_id INT NOT NULL,
        FOREIGN KEY (evento_id) REFERENCES eventos_graduacao(id) ON DELETE CASCADE,
        FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
        UNIQUE KEY unique_evento_turma (evento_id, turma_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_egt);
}
try {
    $pdo->query("SELECT liberado_visitante FROM eventos_graduacao_turmas LIMIT 1");
} catch (Exception $e) {
    try { $pdo->exec("ALTER TABLE eventos_graduacao_turmas ADD COLUMN liberado_visitante TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $ex) {}
}

// Auto-migração: tabela de valores de taxa personalizados por turma
try {
    $pdo->query("SELECT 1 FROM eventos_graduacao_valores_turma LIMIT 1");
} catch (Exception $e) {
    $sql_egv = "CREATE TABLE IF NOT EXISTS eventos_graduacao_valores_turma (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evento_id INT NOT NULL,
        turma_id INT NOT NULL,
        valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (evento_id) REFERENCES eventos_graduacao(id) ON DELETE CASCADE,
        FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
        UNIQUE KEY unique_evento_turma_valor (evento_id, turma_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_egv);
}

// Auto-migração: valor do 2º lote por turma
try {
    $pdo->query("SELECT valor_lote2 FROM eventos_graduacao_valores_turma LIMIT 1");
} catch (Exception $e) {
    try { $pdo->exec("ALTER TABLE eventos_graduacao_valores_turma ADD COLUMN valor_lote2 DECIMAL(10,2) DEFAULT NULL"); } catch (Exception $ex) {}
}

// Buscar dados iniciais do evento para o POST
$stmt_init = $pdo->prepare("SELECT * FROM eventos_graduacao WHERE id = ? AND unidade_id = ?");
$stmt_init->execute([$evento_id, $unidade_id]);
$evento = $stmt_init->fetch();

if (!$evento) {
    header("Location: faixas.php");
    exit;
}

// Turmas da unidade (necessário também no processamento do POST)
$stmt_turmas = $pdo->prepare("SELECT t.id, t.nome, a.nome as academia_nome FROM turmas t LEFT JOIN academias a ON t.academia_id = a.id WHERE t.unidade_id = ? AND t.status = 'ativo' ORDER BY a.nome ASC, t.nome ASC");
$stmt_turmas->execute([$unidade_id]);
$turmas_disponiveis = $stmt_turmas->fetchAll();

// 2. Processamento do POST (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['excluir'])) {
        try {
            $pdo->prepare("DELETE FROM eventos_graduacao WHERE id = ? AND unidade_id = ?")->execute([$evento_id, $unidade_id]);
            header("Location: faixas.php?excluido=1");
            exit;
        } catch (Exception $e) {
            $_SESSION['mensagem'] = "Erro ao excluir: " . $e->getMessage();
        }
    } elseif (isset($_POST['adicionar_aluno'])) {
        $aluno_id = $_POST['aluno_id'] ?? 0;
        $faixa_pretendida = $_POST['faixa_pretendida'] ?? '';
        $tamanho_faixa = $_POST['tamanho_faixa'] ?? '';

        if ($aluno_id) {
            try {
                $stmt_faixa = $pdo->prepare("SELECT faixa FROM alunos WHERE id = ?");
                $stmt_faixa->execute([$aluno_id]);
                $aluno_data = $stmt_faixa->fetch();
                $faixa_atual = $aluno_data['faixa'] ?? '';

                if (empty($evento['todas_turmas'])) {
                    // Cadastro por turma: usa o valor/lote específico da turma do aluno
                    $valor_taxa = 0.00;
                    $stmt_valor_aluno = $pdo->prepare("SELECT egd.valor_lote1, egd.valor_lote1_data_limite, egd.valor_lote2, egd.valor_lote2_data_limite
                                                        FROM eventos_graduacao_datas_turma egd
                                                        JOIN turma_alunos ta ON ta.turma_id = egd.turma_id
                                                        WHERE egd.evento_id = ? AND ta.aluno_id = ? LIMIT 1");
                    $stmt_valor_aluno->execute([$evento_id, $aluno_id]);
                    $valor_aluno_row = $stmt_valor_aluno->fetch();
                    if ($valor_aluno_row) {
                        $hoje = date('Y-m-d');
                        $lote1_expirado = !empty($valor_aluno_row['valor_lote1_data_limite']) && $hoje > $valor_aluno_row['valor_lote1_data_limite'];
                        if ($lote1_expirado && $valor_aluno_row['valor_lote2'] !== null) {
                            $valor_taxa = $valor_aluno_row['valor_lote2'];
                        } elseif ($valor_aluno_row['valor_lote1'] !== null) {
                            $valor_taxa = $valor_aluno_row['valor_lote1'];
                        }
                    }
                } else {
                    // Lote vigente hoje: se já passou da data de início do 2º lote, usa o valor do 2º lote
                    $lote2_vigente = !empty($evento['lote2_data_inicio']) && date('Y-m-d') >= $evento['lote2_data_inicio'];
                    $valor_taxa = ($lote2_vigente && $evento['taxa_lote2'] !== null && $evento['taxa_lote2'] !== '') ? $evento['taxa_lote2'] : $evento['taxa'];
                }

                $stmt_add = $pdo->prepare("INSERT INTO eventos_graduacao_inscricoes (evento_id, aluno_id, faixa_atual, faixa_pretendida, tamanho_faixa, valor_pago) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_add->execute([$evento_id, $aluno_id, $faixa_atual, $faixa_pretendida, $tamanho_faixa, $valor_taxa]);
                $_SESSION['sucesso'] = "Aluno inscrito com sucesso!";
            } catch (Exception $e) {
                $_SESSION['mensagem'] = "Erro ao inscrever aluno: Talvez já esteja inscrito.";
            }
        }
    } elseif (isset($_POST['atualizar_tamanho_faixa'])) {
        $inscricao_id = $_POST['inscricao_id'] ?? 0;
        $tamanho_faixa_edit = $_POST['tamanho_faixa'] ?? '';

        try {
            $stmt_upd_tam = $pdo->prepare("UPDATE eventos_graduacao_inscricoes SET tamanho_faixa = ? WHERE id = ? AND evento_id = ?");
            $stmt_upd_tam->execute([$tamanho_faixa_edit, $inscricao_id, $evento_id]);
            $_SESSION['sucesso'] = "Tamanho da faixa atualizado!";
        } catch (Exception $e) {
            $_SESSION['mensagem'] = "Erro ao atualizar tamanho da faixa.";
        }
    } elseif (isset($_POST['atualizar_faixa_pretendida'])) {
        $inscricao_id = $_POST['inscricao_id'] ?? 0;
        $faixa_pretendida_edit = $_POST['faixa_pretendida'] ?? '';

        try {
            $stmt_upd_faixa = $pdo->prepare("UPDATE eventos_graduacao_inscricoes SET faixa_pretendida = ? WHERE id = ? AND evento_id = ?");
            $stmt_upd_faixa->execute([$faixa_pretendida_edit, $inscricao_id, $evento_id]);
            $_SESSION['sucesso'] = "Faixa pretendida atualizada!";
        } catch (Exception $e) {
            $_SESSION['mensagem'] = "Erro ao atualizar faixa pretendida.";
        }
    } elseif (isset($_POST['atualizar_inscricao'])) {
        $inscricao_id = $_POST['inscricao_id'] ?? 0;
        $status_pagamento = $_POST['status_pagamento'] ?? 'pendente';
        $resultado = $_POST['resultado'] ?? 'pendente';

        try {
            $stmt_upd_insc = $pdo->prepare("UPDATE eventos_graduacao_inscricoes SET status_pagamento = ?, resultado = ? WHERE id = ?");
            $stmt_upd_insc->execute([$status_pagamento, $resultado, $inscricao_id]);

            if ($resultado === 'aprovado') {
                $stmt_get_aluno = $pdo->prepare("SELECT aluno_id, faixa_pretendida FROM eventos_graduacao_inscricoes WHERE id = ?");
                $stmt_get_aluno->execute([$inscricao_id]);
                $insc_data = $stmt_get_aluno->fetch();

                if ($insc_data && $insc_data['faixa_pretendida']) {
                    $stmt_upd_aluno = $pdo->prepare("UPDATE alunos SET faixa = ?, data_graduacao = ? WHERE id = ?");
                    $stmt_upd_aluno->execute([$insc_data['faixa_pretendida'], $evento['data_evento'], $insc_data['aluno_id']]);
                }
            }
            $_SESSION['sucesso'] = "Inscrição atualizada!";
        } catch (Exception $e) {
            $_SESSION['mensagem'] = "Erro ao atualizar inscrição.";
        }
    } elseif (isset($_POST['remover_inscricao'])) {
        $inscricao_id = $_POST['inscricao_id'] ?? 0;
        try {
            $pdo->prepare("DELETE FROM eventos_graduacao_inscricoes WHERE id = ?")->execute([$inscricao_id]);
            $_SESSION['sucesso'] = "Inscrição removida.";
        } catch (Exception $e) {
            $_SESSION['mensagem'] = "Erro ao remover inscrição.";
        }
    } elseif (isset($_POST['novo_lancamento_evento'])) {
        $tipo_lanc = $_POST['tipo_lancamento'] ?? 'despesa';
        $descricao_lanc = trim($_POST['descricao_lancamento'] ?? '');
        $valor_lanc = str_replace(['.', ','], ['', '.'], $_POST['valor_lancamento'] ?? '0');
        $data_lanc = $_POST['data_lancamento'] ?: date('Y-m-d');

        if (in_array($tipo_lanc, ['receita', 'despesa'], true) && $descricao_lanc && (float)$valor_lanc > 0) {
            try {
                $stmt_lanc = $pdo->prepare("INSERT INTO eventos_graduacao_financeiro (evento_id, unidade_id, tipo, descricao, valor, data_lancamento) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_lanc->execute([$evento_id, $unidade_id, $tipo_lanc, $descricao_lanc, $valor_lanc, $data_lanc]);
                $_SESSION['sucesso'] = "Lançamento registrado!";
            } catch (Exception $e) {
                $_SESSION['mensagem'] = "Erro ao registrar lançamento.";
            }
        } else {
            $_SESSION['mensagem'] = "Preencha descrição e valor do lançamento corretamente.";
        }
    } elseif (isset($_POST['remover_lancamento_evento'])) {
        $lancamento_id = $_POST['lancamento_id'] ?? 0;
        try {
            $pdo->prepare("DELETE FROM eventos_graduacao_financeiro WHERE id = ? AND evento_id = ?")->execute([$lancamento_id, $evento_id]);
            $_SESSION['sucesso'] = "Lançamento removido.";
        } catch (Exception $e) {
            $_SESSION['mensagem'] = "Erro ao remover lançamento.";
        }
    } elseif (isset($_POST['atualizar_evento'])) {
        $titulo = $_POST['titulo'] ?? '';
        $academia_id = $evento['academia_id']; // campo removido do formulário; mantém o valor já salvo
        $horario = $_POST['horario'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        $status = $_POST['status'] ?? 'agendado';
        $requisito_pagamento_obrigatorio = isset($_POST['requisito_pagamento_obrigatorio']) ? 1 : 0;
        $requisito_frequencia_minima = $_POST['requisito_frequencia_minima'] !== '' ? $_POST['requisito_frequencia_minima'] : null;
        $requisito_carencia_ativo = ($_POST['requisito_carencia_ativo'] ?? 'sim') === 'sim' ? 1 : 0;

        // Todas as turmas participam (Sim = cadastro único; Não = cadastro completo por turma)
        $todas_turmas = ($_POST['todas_turmas'] ?? 'sim') === 'sim' ? 1 : 0;
        $turmas_selecionadas = $_POST['turmas'] ?? [];
        $turmas_visitante_selecionadas = array_map('intval', $_POST['turmas_visitante'] ?? []);
        $turma_dados = $_POST['turma_dados'] ?? [];

        if ($todas_turmas) {
            $data_evento = $_POST['data_evento'] ?? '';
            $local = $_POST['local'] ?? '';
            $taxa = $_POST['taxa'] ?: 0.00;
            $taxa_lote2 = $_POST['taxa_lote2'] !== '' ? $_POST['taxa_lote2'] : null;
            $lote2_data_inicio = $_POST['lote2_data_inicio'] ?: null;
            $inscricoes_inicio = $_POST['inscricoes_inicio'] ?: null;
            $inscricoes_fim = $_POST['inscricoes_fim'] ?: null;
            $avaliacoes_inicio = $_POST['avaliacoes_inicio'] ?: null;
            $avaliacoes_fim = $_POST['avaliacoes_fim'] ?: null;
            $avaliacoes_numero_tentativas = $_POST['avaliacoes_numero_tentativas'] !== '' ? (int)$_POST['avaliacoes_numero_tentativas'] : null;
            $turmas_participantes_ids = array_column($turmas_disponiveis, 'id');
        } else {
            $turmas_participantes_ids = array_map('intval', $turmas_selecionadas);
            $local = null;
            $taxa = 0.00;
            $taxa_lote2 = null;
            $lote2_data_inicio = null;
            $avaliacoes_numero_tentativas = null;

            // Deriva as datas "resumo" do evento a partir do conjunto de dados por turma preenchidos
            $vals_data_evento = $vals_insc_inicio = $vals_insc_fim = $vals_aval_inicio = $vals_aval_fim = [];
            foreach ($turmas_participantes_ids as $tid) {
                $d = $turma_dados[$tid] ?? [];
                if (!empty($d['data_entrega'])) { $vals_data_evento[] = $d['data_entrega']; }
                if (!empty($d['inscricoes_inicio'])) { $vals_insc_inicio[] = $d['inscricoes_inicio']; }
                if (!empty($d['inscricoes_fim'])) { $vals_insc_fim[] = $d['inscricoes_fim']; }
                if (!empty($d['avaliacoes_inicio'])) { $vals_aval_inicio[] = $d['avaliacoes_inicio']; }
                if (!empty($d['avaliacoes_fim'])) { $vals_aval_fim[] = $d['avaliacoes_fim']; }
            }
            $data_evento = !empty($vals_data_evento) ? min($vals_data_evento) : '';
            $inscricoes_inicio = !empty($vals_insc_inicio) ? min($vals_insc_inicio) : null;
            $inscricoes_fim = !empty($vals_insc_fim) ? max($vals_insc_fim) : null;
            $avaliacoes_inicio = !empty($vals_aval_inicio) ? min($vals_aval_inicio) : null;
            $avaliacoes_fim = !empty($vals_aval_fim) ? max($vals_aval_fim) : null;
        }

        if ($titulo && $data_evento) {
            try {
                $pdo->beginTransaction();
                $stmt_upd = $pdo->prepare("UPDATE eventos_graduacao SET academia_id = ?, titulo = ?, descricao = ?, data_evento = ?, horario = ?, local = ?, taxa = ?, taxa_lote2 = ?, lote2_data_inicio = ?, status = ?, inscricoes_inicio = ?, inscricoes_fim = ?, avaliacoes_inicio = ?, avaliacoes_fim = ?, avaliacoes_numero_tentativas = ?, requisito_pagamento_obrigatorio = ?, requisito_frequencia_minima = ?, requisito_carencia_ativo = ?, todas_turmas = ? WHERE id = ? AND unidade_id = ?");
                $stmt_upd->execute([$academia_id, $titulo, $descricao, $data_evento, $horario, $local, $taxa, $taxa_lote2, $lote2_data_inicio, $status, $inscricoes_inicio, $inscricoes_fim, $avaliacoes_inicio, $avaliacoes_fim, $avaliacoes_numero_tentativas, $requisito_pagamento_obrigatorio, $requisito_frequencia_minima, $requisito_carencia_ativo, $todas_turmas, $evento_id, $unidade_id]);

                $pdo->prepare("DELETE FROM eventos_graduacao_turmas WHERE evento_id = ?")->execute([$evento_id]);
                if (!$todas_turmas && !empty($turmas_selecionadas)) {
                    $stmt_turma = $pdo->prepare("INSERT INTO eventos_graduacao_turmas (evento_id, turma_id, liberado_visitante) VALUES (?, ?, ?)");
                    foreach ($turmas_selecionadas as $turma_id) {
                        $liberado_visitante = in_array((int)$turma_id, $turmas_visitante_selecionadas, true) ? 1 : 0;
                        $stmt_turma->execute([$evento_id, (int)$turma_id, $liberado_visitante]);
                    }
                }

                $pdo->prepare("DELETE FROM eventos_graduacao_datas_turma WHERE evento_id = ?")->execute([$evento_id]);
                if (!$todas_turmas && !empty($turmas_participantes_ids)) {
                    $stmt_dados = $pdo->prepare("INSERT INTO eventos_graduacao_datas_turma
                        (evento_id, turma_id, data_evento, inscricoes_inicio, inscricoes_fim, avaliacoes_inicio, avaliacoes_fim, valor_lote1, valor_lote1_data_limite, valor_lote2, valor_lote2_data_limite, local_entrega, avaliacoes_numero_tentativas, requisito_pagamento_obrigatorio, requisito_frequencia_minima, requisito_carencia_ativo, horario_entrega)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($turmas_participantes_ids as $tid) {
                        $d = $turma_dados[$tid] ?? [];
                        $tudo_vazio = empty($d['data_entrega']) && empty($d['inscricoes_inicio']) && empty($d['inscricoes_fim'])
                            && empty($d['avaliacoes_inicio']) && empty($d['avaliacoes_fim']) && empty($d['valor_lote1'])
                            && empty($d['valor_lote1_data_limite']) && empty($d['valor_lote2']) && empty($d['valor_lote2_data_limite'])
                            && empty($d['local_entrega']) && empty($d['avaliacoes_numero_tentativas']) && empty($d['horario_entrega']);
                        if ($tudo_vazio) { continue; }
                        $stmt_dados->execute([
                            $evento_id,
                            $tid,
                            $d['data_entrega'] ?: null,
                            $d['inscricoes_inicio'] ?: null,
                            $d['inscricoes_fim'] ?: null,
                            $d['avaliacoes_inicio'] ?: null,
                            $d['avaliacoes_fim'] ?: null,
                            isset($d['valor_lote1']) && $d['valor_lote1'] !== '' ? (float)$d['valor_lote1'] : null,
                            $d['valor_lote1_data_limite'] ?: null,
                            isset($d['valor_lote2']) && $d['valor_lote2'] !== '' ? (float)$d['valor_lote2'] : null,
                            $d['valor_lote2_data_limite'] ?: null,
                            $d['local_entrega'] ?: null,
                            isset($d['avaliacoes_numero_tentativas']) && $d['avaliacoes_numero_tentativas'] !== '' ? (int)$d['avaliacoes_numero_tentativas'] : null,
                            isset($d['requisito_pagamento_obrigatorio']) ? 1 : 0,
                            isset($d['requisito_frequencia_minima']) && $d['requisito_frequencia_minima'] !== '' ? (float)$d['requisito_frequencia_minima'] : null,
                            ($d['requisito_carencia_ativo'] ?? 'sim') === 'sim' ? 1 : 0,
                            $d['horario_entrega'] ?: null,
                        ]);
                    }
                }
                $pdo->commit();
                $_SESSION['sucesso'] = "Evento atualizado com sucesso!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['mensagem'] = "Erro ao atualizar: " . $e->getMessage();
            }
        } else {
            $_SESSION['mensagem'] = $todas_turmas
                ? "Por favor, preencha o título e a data do evento."
                : "Por favor, preencha o título, selecione ao menos uma turma e informe a data de entrega dela.";
        }
    }
    header("Location: editar_evento_faixa.php?id=" . $evento_id);
    exit;
}

// 3. Buscar dados para o formulário

// Turmas já participantes deste exame
$stmt_turmas_sel = $pdo->prepare("SELECT turma_id, liberado_visitante FROM eventos_graduacao_turmas WHERE evento_id = ?");
$stmt_turmas_sel->execute([$evento_id]);
$turmas_sel_rows = $stmt_turmas_sel->fetchAll();
$turmas_selecionadas_ids = array_column($turmas_sel_rows, 'turma_id');
$turmas_visitante_selecionadas_ids = array_column(array_filter($turmas_sel_rows, function ($r) {
    return (int) $r['liberado_visitante'] === 1;
}), 'turma_id');

// Cadastro completo por turma já salvo para este exame (quando não usa cadastro único)
$stmt_datas_turma = $pdo->prepare("SELECT turma_id, data_evento, inscricoes_inicio, inscricoes_fim, avaliacoes_inicio, avaliacoes_fim, valor_lote1, valor_lote1_data_limite, valor_lote2, valor_lote2_data_limite, local_entrega, avaliacoes_numero_tentativas, requisito_pagamento_obrigatorio, requisito_frequencia_minima, requisito_carencia_ativo, horario_entrega FROM eventos_graduacao_datas_turma WHERE evento_id = ?");
$stmt_datas_turma->execute([$evento_id]);
$datas_turma_salvas = [];
foreach ($stmt_datas_turma->fetchAll() as $dt_row) {
    $datas_turma_salvas[$dt_row['turma_id']] = $dt_row;
}

// Buscar graduações/faixas configuradas para a unidade
$stmt_grads = $pdo->prepare("SELECT nome, grau, carencia_meses FROM unidade_graduacoes WHERE unidade_id = ? ORDER BY ordem ASC, nome ASC");
$stmt_grads->execute([$unidade_id]);
$graduacoes_list = $stmt_grads->fetchAll();

// Mapa nome da graduação (ex: "Azul - 2") -> carência mínima em meses, para checagem automática de elegibilidade
$carencia_por_graduacao = [];
foreach ($graduacoes_list as $g_car) {
    $nome_completo_grad = $g_car['nome'] . (!empty($g_car['grau']) ? ' - ' . $g_car['grau'] : '');
    $carencia_por_graduacao[$nome_completo_grad] = (int)$g_car['carencia_meses'];
}

// calcularRequisitosExame() e carenciaAtivaParaAluno() agora vivem em config.php (compartilhadas com chamada.php)

$stmt_inscritos = $pdo->prepare("SELECT egi.*, a.nome_completo as aluno_nome, a.faixa as aluno_faixa, a.data_nascimento, a.data_graduacao as data_graduacao_atual, a.tipo_cadastro,
                                       ac.nome as academia_nome,
                                       (SELECT GROUP_CONCAT(t.nome SEPARATOR ', ') FROM turma_alunos ta JOIN turmas t ON ta.turma_id = t.id WHERE ta.aluno_id = a.id) as turmas_nomes
                                FROM eventos_graduacao_inscricoes egi
                                JOIN alunos a ON egi.aluno_id = a.id
                                LEFT JOIN academias ac ON a.academia_id = ac.id
                                WHERE egi.evento_id = ?
                                ORDER BY a.nome_completo ASC");
$inscritos = $stmt_inscritos->execute([$evento_id]) ? $stmt_inscritos->fetchAll() : [];

// Calcular requisitos (carência automática + frequência) de cada inscrito
foreach ($inscritos as &$insc_req) {
    $carencia_ativo_aluno = carenciaAtivaParaAluno($pdo, $insc_req['aluno_id'], $evento, $turmas_selecionadas_ids, $datas_turma_salvas);
    $insc_req['requisitos'] = calcularRequisitosExame($pdo, $insc_req, $evento, $carencia_por_graduacao, $carencia_ativo_aluno);
}
unset($insc_req);

$where_turmas_restricao = "";
$params_alunos = [$unidade_id, $evento_id];
if (empty($evento['todas_turmas']) && !empty($turmas_selecionadas_ids)) {
    $placeholders_turmas = implode(',', array_fill(0, count($turmas_selecionadas_ids), '?'));
    $where_turmas_restricao = " AND a.id IN (SELECT aluno_id FROM turma_alunos WHERE turma_id IN ($placeholders_turmas)) ";
    $params_alunos = array_merge($params_alunos, $turmas_selecionadas_ids);
}

$stmt_alunos = $pdo->prepare("SELECT a.id, a.nome_completo, a.faixa, a.data_nascimento, a.data_graduacao as data_graduacao_atual,
                                     ac.nome as academia_nome,
                                     (SELECT GROUP_CONCAT(t.nome SEPARATOR ', ') FROM turma_alunos ta JOIN turmas t ON ta.turma_id = t.id WHERE ta.aluno_id = a.id) as turmas_nomes
                              FROM alunos a
                              LEFT JOIN academias ac ON a.academia_id = ac.id
                              WHERE a.unidade_id = ? AND a.status = 'ativo'
                              AND a.id NOT IN (SELECT aluno_id FROM eventos_graduacao_inscricoes WHERE evento_id = ?)
                              $where_turmas_restricao
                              ORDER BY a.nome_completo ASC");
$stmt_alunos->execute($params_alunos);
$todos_alunos = $stmt_alunos->fetchAll();

// Só oferece para inscrição os alunos aptos: sem pendência de carência/frequência mínima do exame,
// calculados com base na faixa atual do aluno (a faixa pretendida real é escolhida na hora da inscrição).
foreach ($todos_alunos as &$ta_apto) {
    $carencia_ativo_ta = carenciaAtivaParaAluno($pdo, $ta_apto['id'], $evento, $turmas_selecionadas_ids, $datas_turma_salvas);
    $pseudo_insc = ['aluno_id' => $ta_apto['id'], 'faixa_pretendida' => $ta_apto['faixa'], 'data_graduacao_atual' => $ta_apto['data_graduacao_atual']];
    $ta_apto['requisitos'] = calcularRequisitosExame($pdo, $pseudo_insc, $evento, $carencia_por_graduacao, $carencia_ativo_ta);
    $ta_apto['apto'] = $ta_apto['requisitos']['carencia'] !== false && $ta_apto['requisitos']['frequencia'] !== false;
}
unset($ta_apto);
$todos_alunos = array_values(array_filter($todos_alunos, fn($ta) => $ta['apto']));

// Inscrição automática: todo aluno apto (dentro das turmas do exame, cumprindo carência/frequência) entra
// direto na lista de inscritos, sem precisar de ação manual.
if (!empty($todos_alunos)) {
    // Próxima graduação de cada faixa, na ordem cadastrada em unidade_graduacoes, para sugerir a faixa pretendida.
    $ordem_faixas = array_map(function ($g) {
        return $g['nome'] . (!empty($g['grau']) ? ' - ' . $g['grau'] : '');
    }, $graduacoes_list);

    $stmt_auto_insc = $pdo->prepare("INSERT INTO eventos_graduacao_inscricoes (evento_id, aluno_id, faixa_atual, faixa_pretendida, tamanho_faixa, valor_pago) VALUES (?, ?, ?, ?, ?, ?)");
    $houve_insc_automatica = false;

    foreach ($todos_alunos as $ta_auto) {
        $faixa_atual_auto = $ta_auto['faixa'] ?: '';
        $idx_faixa_atual = array_search($faixa_atual_auto, $ordem_faixas);
        $faixa_pretendida_auto = ($idx_faixa_atual !== false && isset($ordem_faixas[$idx_faixa_atual + 1]))
            ? $ordem_faixas[$idx_faixa_atual + 1]
            : $faixa_atual_auto;

        if (empty($evento['todas_turmas'])) {
            $valor_taxa_auto = 0.00;
            $stmt_valor_auto = $pdo->prepare("SELECT egd.valor_lote1, egd.valor_lote1_data_limite, egd.valor_lote2, egd.valor_lote2_data_limite
                                                FROM eventos_graduacao_datas_turma egd
                                                JOIN turma_alunos ta ON ta.turma_id = egd.turma_id
                                                WHERE egd.evento_id = ? AND ta.aluno_id = ? LIMIT 1");
            $stmt_valor_auto->execute([$evento_id, $ta_auto['id']]);
            $valor_auto_row = $stmt_valor_auto->fetch();
            if ($valor_auto_row) {
                $hoje = date('Y-m-d');
                $lote1_expirado_auto = !empty($valor_auto_row['valor_lote1_data_limite']) && $hoje > $valor_auto_row['valor_lote1_data_limite'];
                if ($lote1_expirado_auto && $valor_auto_row['valor_lote2'] !== null) {
                    $valor_taxa_auto = $valor_auto_row['valor_lote2'];
                } elseif ($valor_auto_row['valor_lote1'] !== null) {
                    $valor_taxa_auto = $valor_auto_row['valor_lote1'];
                }
            }
        } else {
            $lote2_vigente_auto = !empty($evento['lote2_data_inicio']) && date('Y-m-d') >= $evento['lote2_data_inicio'];
            $valor_taxa_auto = ($lote2_vigente_auto && $evento['taxa_lote2'] !== null && $evento['taxa_lote2'] !== '') ? $evento['taxa_lote2'] : $evento['taxa'];
        }

        try {
            $stmt_auto_insc->execute([$evento_id, $ta_auto['id'], $faixa_atual_auto, $faixa_pretendida_auto, null, $valor_taxa_auto]);
            $houve_insc_automatica = true;
        } catch (Exception $e) {
            // Já inscrito (corrida entre requisições) - ignora e segue.
        }
    }

    if ($houve_insc_automatica) {
        header("Location: editar_evento_faixa.php?id=" . $evento_id);
        exit;
    }
}

// Financeiro do evento: conta separada por edição, somando receita automática das inscrições pagas
// com os lançamentos manuais (receitas/despesas) desta tela.
$stmt_lancamentos_evento = $pdo->prepare("SELECT * FROM eventos_graduacao_financeiro WHERE evento_id = ? ORDER BY data_lancamento DESC, id DESC");
$stmt_lancamentos_evento->execute([$evento_id]);
$lancamentos_evento = $stmt_lancamentos_evento->fetchAll();

$inscritos_pagos_fin = array_filter($inscritos, fn($i) => $i['status_pagamento'] === 'pago');
$receita_inscricoes = array_sum(array_column($inscritos_pagos_fin, 'valor_pago'));
$receita_manual = array_sum(array_map(fn($l) => $l['tipo'] === 'receita' ? (float)$l['valor'] : 0, $lancamentos_evento));
$despesa_manual = array_sum(array_map(fn($l) => $l['tipo'] === 'despesa' ? (float)$l['valor'] : 0, $lancamentos_evento));
$receita_total_evento = $receita_inscricoes + $receita_manual;
$saldo_evento = $receita_total_evento - $despesa_manual;

$custom_title = "GERENCIAR EVENTO / GRADUAÇÃO";
$back_link = "faixas.php";
$custom_shortcuts = [
    ['label' => 'NOVO OFICIAL', 'link' => 'nova_competicao_oficial.php', 'icon' => 'fa-solid fa-trophy', 'class' => 'btn-sq'],
    ['label' => 'NOVO TORNEIO', 'link' => 'nova_competicao.php', 'icon' => 'fa-solid fa-plus-circle', 'class' => 'btn-sq'],
    ['label' => 'NOVO EXAME', 'link' => 'novo_evento_faixa.php', 'icon' => 'fa-solid fa-medal', 'class' => 'btn-sq-outline']
];
include 'header.php';
?>

<section class="dashboard-section-sq">

    <!-- Menu Especial do Departamento -->
    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="eventos_dashboard.php" class="tab-item-sq">Dashboard</a>
        <a href="competicoes_oficiais.php" class="tab-item-sq">Oficiais</a>
        <a href="competicoes.php" class="tab-item-sq">Torneios</a>
        <a href="faixas.php" class="tab-item-sq active">Exames</a>
        <a href="relatorios_eventos.php" class="tab-item-sq">Relatórios</a>
    </div>

    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 20px; background: #fff; padding: 30px; border: 1px solid var(--border-color); border-left: 10px solid var(--primary-green);">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="width: 56px; height: 56px; background: #133080; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fa-solid fa-medal" style="font-size: 22px; color: var(--primary-green);"></i>
            </div>
            <div>
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 3px;">Exame de Faixas</span>
                <h1 style="font-size: 1.6rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                    <?php echo htmlspecialchars($evento['titulo']); ?>
                </h1>
                <p style="color: var(--text-muted); font-size: var(--fs-sm); margin: 4px 0 0 0; font-weight: 700; text-transform: uppercase;">
                    <?php echo $evento['data_evento'] ? date('d/m/Y', strtotime($evento['data_evento'])) : ''; ?>
                    <?php if ($evento['horario']): ?> • <?php echo substr($evento['horario'], 0, 5); ?><?php endif; ?>
                    <?php if ($evento['local']): ?> • <?php echo htmlspecialchars($evento['local']); ?><?php endif; ?>
                    <span style="display: inline-block; padding: 2px 10px; font-size: var(--fs-xs); font-weight: 900; background: <?php echo $evento['status'] === 'concluido' ? '#133080' : ($evento['status'] === 'cancelado' ? '#ef4444' : '#dcfce7'); ?>; color: <?php echo $evento['status'] === 'concluido' ? 'var(--primary-green)' : ($evento['status'] === 'cancelado' ? '#fff' : '#166534'); ?>; margin-left: 8px; text-transform: uppercase;">
                        <?php echo ucfirst($evento['status']); ?>
                    </span>
                </p>
                <?php if (!empty($evento['inscricoes_inicio']) || !empty($evento['inscricoes_fim']) || !empty($evento['avaliacoes_inicio']) || !empty($evento['avaliacoes_fim'])): ?>
                <p style="color: var(--text-muted); font-size: 11px; margin: 8px 0 0 0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;">
                    <?php if (!empty($evento['inscricoes_inicio']) || !empty($evento['inscricoes_fim'])): ?>
                        <i class="fa-solid fa-door-open" style="margin-right: 4px;"></i> Inscrições: <?php echo !empty($evento['inscricoes_inicio']) ? date('d/m/Y', strtotime($evento['inscricoes_inicio'])) : '—'; ?> a <?php echo !empty($evento['inscricoes_fim']) ? date('d/m/Y', strtotime($evento['inscricoes_fim'])) : '—'; ?>
                    <?php endif; ?>
                    <?php if (!empty($evento['avaliacoes_inicio']) || !empty($evento['avaliacoes_fim'])): ?>
                        &nbsp;•&nbsp; <i class="fa-solid fa-calendar-week" style="margin-right: 4px;"></i> Avaliações: <?php echo !empty($evento['avaliacoes_inicio']) ? date('d/m/Y', strtotime($evento['avaliacoes_inicio'])) : '—'; ?> a <?php echo !empty($evento['avaliacoes_fim']) ? date('d/m/Y', strtotime($evento['avaliacoes_fim'])) : '—'; ?>
                    <?php endif; ?>
                    &nbsp;•&nbsp; <i class="fa-solid fa-award" style="margin-right: 4px;"></i> Evento: <?php echo $evento['data_evento'] ? date('d/m/Y', strtotime($evento['data_evento'])) : '—'; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="faixas.php" class="btn-sq-light" style="width: auto; padding: 10px 20px; font-size: var(--fs-sm);">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
            <button type="button"
                onclick="document.getElementById('modal_excluir_evento').style.display='flex'"
                style="width: auto; padding: 10px 20px; font-size: var(--fs-sm); font-weight: 700; font-family: inherit; line-height: 1; cursor: pointer; border: 1px solid #ef4444; background: transparent; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; transition: all 0.2s ease;"
                onmouseover="this.style.background='#ef4444'; this.style.color='#fff';"
                onmouseout="this.style.background='transparent'; this.style.color='#ef4444';">
                <i class="fa-solid fa-trash-can"></i> Excluir
            </button>
        </div>
    </div>

    <!-- Alertas -->
    <?php if ($mensagem): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 15px 25px; border-left: 4px solid #ef4444; margin-bottom: 25px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>
    <?php if ($sucesso): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px 25px; border-left: 4px solid var(--primary-green); margin-bottom: 25px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $sucesso; ?>
        </div>
    <?php endif; ?>

    <!-- Abas do Evento -->
    <div style="display: flex; gap: 5px; margin-bottom: 25px; background: #fafafa; padding: 5px; border: 1px solid var(--border-color);">
        <button class="tab-btn-inner active" onclick="openTabEvento(event, 'tab-dados')" style="flex: 1; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s;">DASHBOARD</button>
        <button class="tab-btn-inner" onclick="openTabEvento(event, 'tab-inscritos')" style="flex: 1; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s;">INSCRITOS (<?php echo count($inscritos); ?>)</button>
        <button class="tab-btn-inner" onclick="openTabEvento(event, 'tab-financeiro')" style="flex: 1; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fa-solid fa-sack-dollar" style="font-size:11px;"></i>FINANCEIRO</button>
    </div>

    <style>
        .tab-btn-inner.active { background: #133080 !important; color: var(--primary-green) !important; }
        .tab-btn-inner:not(.active):hover { background: #eee; }
        .tab-content-evento { display: none; }
        .tab-content-evento.active { display: block; animation: fadeInSqEvento 0.3s ease; }
        @keyframes fadeInSqEvento { from { opacity: 0; } to { opacity: 1; } }
    </style>

    <!-- TAB DASHBOARD -->
    <div id="tab-dados" class="tab-content-evento active">
    <div style="display: flex; flex-direction: column; gap: 25px;">

        <!-- Detalhes do Evento -->
        <div>
            <div class="dashboard-container" style="padding: 35px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Detalhes do Evento</h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="atualizar_evento" value="1">
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div>
                            <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Título *</label>
                            <input type="text" name="titulo" required value="<?php echo htmlspecialchars($evento['titulo']); ?>"
                                style="width: 100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700; color: var(--text-dark); text-transform: uppercase;">
                        </div>
                        <?php $todas_turmas_inicial = !empty($evento['todas_turmas']) || $evento['todas_turmas'] === null; ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="bloco-modo-sim-edit" id="bloco-data-evento-unica-edit" style="display: <?php echo $todas_turmas_inicial ? 'block' : 'none'; ?>;">
                                <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Data do Evento *</label>
                                <input type="date" name="data_evento" id="input-data-evento-unica-edit" <?php echo $todas_turmas_inicial ? 'required' : ''; ?> value="<?php echo $evento['data_evento']; ?>"
                                    style="width: 100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                            </div>
                            <div class="bloco-modo-sim-edit" style="display: <?php echo $todas_turmas_inicial ? 'block' : 'none'; ?>;">
                                <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Hora</label>
                                <input type="time" name="horario" value="<?php echo $evento['horario'] ? substr($evento['horario'], 0, 5) : ''; ?>"
                                    style="width: 100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                            </div>
                        </div>
                        <div class="bloco-modo-sim-edit" id="bloco-local-unico-edit" style="display: <?php echo $todas_turmas_inicial ? 'block' : 'none'; ?>;">
                            <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Local</label>
                            <input type="text" name="local" value="<?php echo htmlspecialchars($evento['local'] ?? ''); ?>"
                                style="width: 100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;" placeholder="Ex: Academia Central">
                        </div>
                        <div>
                            <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Status</label>
                            <select name="status" style="width: 100%; height: 50px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark); appearance: none;">
                                <option value="agendado" <?php echo $evento['status'] === 'agendado' ? 'selected' : ''; ?>>Agendado</option>
                                <option value="concluido" <?php echo $evento['status'] === 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                                <option value="cancelado" <?php echo $evento['status'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Observações</label>
                            <textarea name="descricao" rows="3"
                                style="width: 100%; background: #fafafa; border: 1px solid var(--border-color); padding: 12px 15px; font-size: var(--fs-sm); font-weight: 600; color: var(--text-dark); resize: vertical;"
                                placeholder="Informações adicionais..."><?php echo htmlspecialchars($evento['descricao'] ?? ''); ?></textarea>
                        </div>

                        <div style="border-top: 2px solid var(--border-color); margin-top: 10px; padding-top: 25px;">
                            <h3 style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dark); margin: 0 0 18px 0;">Turmas Participantes</h3>

                            <div style="margin-bottom: 18px;">
                                <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em;">Todas as turmas participam?</label>
                                <div style="display: flex; gap: 1.5rem;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="radio" name="todas_turmas" value="sim" <?php echo $todas_turmas_inicial ? 'checked' : ''; ?> onchange="alternarModoTurmasEdit('sim');" style="width: 18px; height: 18px;">
                                        <span style="font-size: 0.85rem; color: var(--text-dark); font-weight: 700;">Sim</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="radio" name="todas_turmas" value="nao" <?php echo !$todas_turmas_inicial ? 'checked' : ''; ?> onchange="alternarModoTurmasEdit('nao');" style="width: 18px; height: 18px;">
                                        <span style="font-size: 0.85rem; color: var(--text-dark); font-weight: 700;">Não</span>
                                    </label>
                                </div>
                                <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.6rem 0 0 0; line-height: 1.5;">
                                    Se "Sim", um único cadastro (data, local, valores e cronograma) vale para todas as turmas. Se "Não", cada turma selecionada abaixo tem seu próprio cadastro completo.
                                </p>
                            </div>

                            <div id="bloco-turmas-selecao-edit" style="display: <?php echo $todas_turmas_inicial ? 'none' : 'block'; ?>;">
                                <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em;">Turmas que participam</label>
                                <?php if (empty($turmas_disponiveis)): ?>
                                    <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0;">Nenhuma turma ativa cadastrada.</p>
                                <?php else: ?>
                                    <div style="background: #fafafa; border: 1px solid var(--border-color); padding: 15px; display: grid; gap: 10px; max-height: 240px; overflow-y: auto; margin-bottom: 18px;">
                                        <?php foreach ($turmas_disponiveis as $t_op): ?>
                                            <div style="border-bottom: 1px dashed var(--border-color); padding-bottom: 8px;">
                                                <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
                                                    <input type="checkbox" name="turmas[]" value="<?php echo $t_op['id']; ?>" <?php echo in_array($t_op['id'], $turmas_selecionadas_ids) ? 'checked' : ''; ?> onchange="atualizarCadastroPorTurmaEdit();" style="width: 16px; height: 16px; margin-top: 2px;">
                                                    <span style="font-size: 0.78rem; color: var(--text-dark); font-weight: 700;">
                                                        <?php echo htmlspecialchars($t_op['nome']); ?>
                                                        <?php if (!empty($t_op['academia_nome'])): ?><span style="display: block; font-size: 0.68rem; color: var(--text-muted); font-weight: 600;"><?php echo htmlspecialchars($t_op['academia_nome']); ?></span><?php endif; ?>
                                                    </span>
                                                </label>
                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 6px 0 0 24px;">
                                                    <input type="checkbox" name="turmas_visitante[]" value="<?php echo $t_op['id']; ?>" <?php echo in_array($t_op['id'], $turmas_visitante_selecionadas_ids) ? 'checked' : ''; ?> style="width: 14px; height: 14px;">
                                                    <span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Liberar turma no site para inscrição de visitante</span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Cadastro completo por turma -->
                                    <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em;">Cadastro de Cada Turma</label>
                                    <div style="display: grid; gap: 12px;">
                                        <?php foreach ($turmas_disponiveis as $t_data):
                                            $dt_salva = $datas_turma_salvas[$t_data['id']] ?? [];
                                        ?>
                                            <div class="linha-turma-cadastro-edit" data-turma-id="<?php echo $t_data['id']; ?>" style="background: #fafafa; border: 1px solid var(--border-color); padding: 12px;">
                                                <div style="font-size: 0.8rem; color: var(--text-dark); font-weight: 800; margin-bottom: 10px;">
                                                    <?php echo htmlspecialchars($t_data['nome']); ?>
                                                    <?php if (!empty($t_data['academia_nome'])): ?><span style="font-size: 0.68rem; color: var(--text-muted); font-weight: 600;"> — <?php echo htmlspecialchars($t_data['academia_nome']); ?></span><?php endif; ?>
                                                </div>
                                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px;">
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Início Inscrições</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][inscricoes_inicio]" value="<?php echo $dt_salva['inscricoes_inicio'] ?? ''; ?>"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Fim Inscrições</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][inscricoes_fim]" value="<?php echo $dt_salva['inscricoes_fim'] ?? ''; ?>"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Início Avaliações</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][avaliacoes_inicio]" value="<?php echo $dt_salva['avaliacoes_inicio'] ?? ''; ?>"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Fim Avaliações</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][avaliacoes_fim]" value="<?php echo $dt_salva['avaliacoes_fim'] ?? ''; ?>"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Valor Lote 1 (R$)</label>
                                                        <input type="number" step="0.01" min="0" name="turma_dados[<?php echo $t_data['id']; ?>][valor_lote1]" value="<?php echo $dt_salva['valor_lote1'] ?? ''; ?>" placeholder="0.00"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Lote 1 – Data Limite</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][valor_lote1_data_limite]" value="<?php echo $dt_salva['valor_lote1_data_limite'] ?? ''; ?>"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Valor Lote 2 (R$)</label>
                                                        <input type="number" step="0.01" min="0" name="turma_dados[<?php echo $t_data['id']; ?>][valor_lote2]" value="<?php echo $dt_salva['valor_lote2'] ?? ''; ?>" placeholder="0.00"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Lote 2 – Data Limite</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][valor_lote2_data_limite]" value="<?php echo $dt_salva['valor_lote2_data_limite'] ?? ''; ?>"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Data da Entrega</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][data_entrega]" value="<?php echo $dt_salva['data_evento'] ?? ''; ?>"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Horário da Entrega</label>
                                                        <input type="time" name="turma_dados[<?php echo $t_data['id']; ?>][horario_entrega]" value="<?php echo $dt_salva['horario_entrega'] ? substr($dt_salva['horario_entrega'], 0, 5) : ''; ?>"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Local da Entrega</label>
                                                        <input type="text" name="turma_dados[<?php echo $t_data['id']; ?>][local_entrega]" value="<?php echo htmlspecialchars($dt_salva['local_entrega'] ?? ''); ?>" placeholder="Ex: Ginásio Municipal"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Nº Tentativas Avaliação</label>
                                                        <input type="number" min="1" step="1" name="turma_dados[<?php echo $t_data['id']; ?>][avaliacoes_numero_tentativas]" value="<?php echo $dt_salva['avaliacoes_numero_tentativas'] ?? ''; ?>" placeholder="Ex: 2"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                    <div>
                                                        <label style="display: block; font-size: 0.62rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Frequência Mínima (%)</label>
                                                        <input type="number" step="0.1" min="0" max="100" name="turma_dados[<?php echo $t_data['id']; ?>][requisito_frequencia_minima]" value="<?php echo $dt_salva['requisito_frequencia_minima'] ?? ''; ?>" placeholder="Ex: 75"
                                                            style="width: 100%; padding: 6px; border: 1px solid var(--border-color); background: #fff; font-size: 0.75rem;">
                                                    </div>
                                                </div>
                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 10px;">
                                                    <input type="checkbox" name="turma_dados[<?php echo $t_data['id']; ?>][requisito_pagamento_obrigatorio]" value="1" <?php echo (!isset($dt_salva['requisito_pagamento_obrigatorio']) || !empty($dt_salva['requisito_pagamento_obrigatorio'])) ? 'checked' : ''; ?> style="width: 16px; height: 16px;">
                                                    <span style="font-size: 0.75rem; color: var(--text-dark); font-weight: 700;">Exigir pagamento da taxa para confirmar a inscrição</span>
                                                </label>
                                                <?php $carencia_turma_valor = (!isset($dt_salva['requisito_carencia_ativo']) || (int)$dt_salva['requisito_carencia_ativo'] === 1) ? 'sim' : 'nao'; ?>
                                                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border-color);">
                                                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                                                        <i class="fa-solid fa-hourglass-half" style="color: var(--text-muted); margin-top: 2px; font-size: 0.7rem;"></i>
                                                        <div>
                                                            <strong style="font-size: 0.7rem; color: var(--text-dark);">Exigir carência?</strong>
                                                            <p style="font-size: 0.65rem; color: var(--text-muted); margin: 2px 0 0 0; line-height: 1.4;">
                                                                Se "Sim", verificada automaticamente por aluno, com base na graduação pretendida e na data da última graduação.
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; gap: 1.1rem; padding-left: 20px;">
                                                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                            <input type="radio" name="turma_dados[<?php echo $t_data['id']; ?>][requisito_carencia_ativo]" value="sim" <?php echo $carencia_turma_valor === 'sim' ? 'checked' : ''; ?> style="width: 14px; height: 14px;">
                                                            <span style="font-size: 0.72rem; color: var(--text-dark); font-weight: 700;">Sim</span>
                                                        </label>
                                                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                            <input type="radio" name="turma_dados[<?php echo $t_data['id']; ?>][requisito_carencia_ativo]" value="nao" <?php echo $carencia_turma_valor === 'nao' ? 'checked' : ''; ?> style="width: 14px; height: 14px;">
                                                            <span style="font-size: 0.72rem; color: var(--text-dark); font-weight: 700;">Não</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="bloco-modo-sim-edit" style="border-top: 2px solid var(--border-color); margin-top: 10px; padding-top: 25px; display: <?php echo $todas_turmas_inicial ? 'block' : 'none'; ?>;">
                            <h3 style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dark); margin: 0 0 18px 0;">Pagamento</h3>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                                <div>
                                    <label style="display: block; font-size: 0.68rem; font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Valor 1º Lote (R$)</label>
                                    <input type="number" step="0.01" min="0" name="taxa" value="<?php echo $evento['taxa']; ?>"
                                        style="width: 100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.68rem; font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Início 2º Lote</label>
                                    <input type="date" name="lote2_data_inicio" value="<?php echo $evento['lote2_data_inicio'] ?? ''; ?>"
                                        style="width: 100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.68rem; font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Valor 2º Lote (R$)</label>
                                    <input type="number" step="0.01" min="0" name="taxa_lote2" value="<?php echo $evento['taxa_lote2'] ?? ''; ?>"
                                        style="width: 100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                                </div>
                            </div>
                        </div>

                        <div style="border-top: 2px solid var(--border-color); margin-top: 10px; padding-top: 25px;">
                            <h3 style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dark); margin: 0 0 18px 0;">Cronograma do Exame</h3>

                            <div class="bloco-modo-sim-edit" style="margin-bottom: 18px; display: <?php echo $todas_turmas_inicial ? 'block' : 'none'; ?>;">
                                <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">1. Inscrições</label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div>
                                        <label style="display: block; font-size: 0.68rem; font-weight: 800; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Início</label>
                                        <input type="date" name="inscricoes_inicio" value="<?php echo $evento['inscricoes_inicio'] ?? ''; ?>"
                                            style="width: 100%; height: 44px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.68rem; font-weight: 800; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Fim</label>
                                        <input type="date" name="inscricoes_fim" value="<?php echo $evento['inscricoes_fim'] ?? ''; ?>"
                                            style="width: 100%; height: 44px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                                    </div>
                                </div>
                            </div>

                            <div class="bloco-modo-sim-edit" style="margin-bottom: 18px; display: <?php echo $todas_turmas_inicial ? 'block' : 'none'; ?>;">
                                <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">2. Requisitos</label>
                                <?php $carencia_evento_valor = (int)($evento['requisito_carencia_ativo'] ?? 1) === 1 ? 'sim' : 'nao'; ?>
                                <div style="background: #fafafa; border: 1px solid var(--border-color); padding: 15px; display: grid; gap: 14px;">
                                    <div>
                                        <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0 0 8px 0; line-height: 1.5;">
                                            <i class="fa-solid fa-hourglass-half" style="margin-right: 5px;"></i> Exigir carência? Se "Sim", verificada automaticamente pela graduação pretendida de cada aluno.
                                        </p>
                                        <div style="display: flex; gap: 1.25rem;">
                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                <input type="radio" name="requisito_carencia_ativo" value="sim" <?php echo $carencia_evento_valor === 'sim' ? 'checked' : ''; ?> style="width: 16px; height: 16px;">
                                                <span style="font-size: 0.8rem; color: var(--text-dark); font-weight: 700;">Sim</span>
                                            </label>
                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                <input type="radio" name="requisito_carencia_ativo" value="nao" <?php echo $carencia_evento_valor === 'nao' ? 'checked' : ''; ?> style="width: 16px; height: 16px;">
                                                <span style="font-size: 0.8rem; color: var(--text-dark); font-weight: 700;">Não</span>
                                            </label>
                                        </div>
                                    </div>
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                        <input type="checkbox" name="requisito_pagamento_obrigatorio" value="1" <?php echo !empty($evento['requisito_pagamento_obrigatorio']) ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                                        <span style="font-size: 0.8rem; color: var(--text-dark); font-weight: 700;">Exigir pagamento da taxa</span>
                                    </label>
                                    <div>
                                        <label style="display: block; font-size: var(--fs-xs); font-weight: 800; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;">Frequência mínima (%)</label>
                                        <input type="number" step="0.1" min="0" max="100" name="requisito_frequencia_minima" value="<?php echo $evento['requisito_frequencia_minima'] ?? ''; ?>" placeholder="Deixe em branco para não exigir"
                                            style="width: 100%; height: 44px; background: #fff; border: 1px solid var(--border-color); padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                                    </div>
                                </div>
                            </div>

                            <div class="bloco-modo-sim-edit" style="display: <?php echo $todas_turmas_inicial ? 'block' : 'none'; ?>;">
                                <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">3. Avaliações</label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                    <div>
                                        <label style="display: block; font-size: 0.68rem; font-weight: 800; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Início</label>
                                        <input type="date" name="avaliacoes_inicio" value="<?php echo $evento['avaliacoes_inicio'] ?? ''; ?>"
                                            style="width: 100%; height: 44px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.68rem; font-weight: 800; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Fim</label>
                                        <input type="date" name="avaliacoes_fim" value="<?php echo $evento['avaliacoes_fim'] ?? ''; ?>"
                                            style="width: 100%; height: 44px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                                    </div>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.68rem; font-weight: 800; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Número de Tentativas de Avaliação</label>
                                    <input type="number" min="1" step="1" name="avaliacoes_numero_tentativas" value="<?php echo $evento['avaliacoes_numero_tentativas'] ?? ''; ?>" placeholder="Ex: 2"
                                        style="width: 100%; height: 44px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-sq" style="width: 100%; padding: 15px; font-size: var(--fs-sm);">
                            <i class="fa-solid fa-floppy-disk" style="margin-right: 8px;"></i> ATUALIZAR EVENTO
                        </button>
                    </div>
                </form>
            </div>

            <script>
                function turmaEhParticipanteEdit(turmaId) {
                    var todasSel = document.querySelector('input[name="todas_turmas"]:checked');
                    if (!todasSel || todasSel.value === 'sim') return true;
                    var chk = document.querySelector('input[name="turmas[]"][value="' + turmaId + '"]');
                    return !!(chk && chk.checked);
                }
                function atualizarCadastroPorTurmaEdit() {
                    document.querySelectorAll('.linha-turma-cadastro-edit').forEach(function (row) {
                        var id = row.getAttribute('data-turma-id');
                        row.style.display = turmaEhParticipanteEdit(id) ? '' : 'none';
                    });
                }
                function alternarModoTurmasEdit(valor) {
                    var todas = valor === 'sim';
                    document.getElementById('bloco-turmas-selecao-edit').style.display = todas ? 'none' : 'block';
                    document.querySelectorAll('.bloco-modo-sim-edit').forEach(function (el) {
                        el.style.display = todas ? 'block' : 'none';
                    });
                    document.getElementById('input-data-evento-unica-edit').required = todas;
                    if (!todas) atualizarCadastroPorTurmaEdit();
                }
                atualizarCadastroPorTurmaEdit();
            </script>

            <!-- Stats rápidos -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
                <div class="dashboard-container" style="padding: 20px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 900; color: #133080;"><?php echo count($inscritos); ?></div>
                    <div style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-top: 4px;">Inscritos</div>
                </div>
                <div class="dashboard-container" style="padding: 20px; text-align: center;">
                    <?php
                        $inscritos_pagos = array_filter($inscritos, fn($i) => $i['status_pagamento'] === 'pago');
                        $total_arrecadado = array_sum(array_column($inscritos_pagos, 'valor_pago'));
                    ?>
                    <div style="font-size: 2rem; font-weight: 900; color: var(--primary-green);">R$ <?php echo number_format($total_arrecadado, 2, ',', '.'); ?></div>
                    <div style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-top: 4px;"><?php echo count($inscritos_pagos); ?> Pagos</div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /tab-dados -->

    <!-- TAB INSCRITOS -->
    <div id="tab-inscritos" class="tab-content-evento">
        <!-- Inscrições -->
        <div>
            <!-- Inscrever Aluno -->
            <div class="dashboard-container" style="padding: 30px; margin-bottom: 25px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Inscrever Aluno</h2>
                </div>
                <form method="POST" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;" onsubmit="return validarInscricaoAluno()">
                    <div style="flex: 2; min-width: 200px; position: relative;">
                        <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Selecionar Aluno <span style="font-weight: 600; text-transform: none; letter-spacing: normal; color: var(--text-muted);">(apenas aptos, conforme requisitos do exame)</span></label>
                        <input type="hidden" name="aluno_id" id="aluno_id_selecionado">
                        <input type="text" id="busca_aluno_inscrever" autocomplete="off" placeholder="— Escolha um aluno —"
                            onfocus="filtrarAlunosInscrever(); document.getElementById('lista_alunos_inscrever').style.display='block';"
                            oninput="filtrarAlunosInscrever()"
                            style="width: 100%; height: 50px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark);">
                        <div id="lista_alunos_inscrever" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 20; background: #fff; border: 1px solid var(--border-color); border-top: none; max-height: 280px; overflow-y: auto; box-shadow: 0 8px 16px rgba(0,0,0,0.08);">
                            <?php foreach ($todos_alunos as $ta):
                                $ano_nasc = $ta['data_nascimento'] ? date('Y', strtotime($ta['data_nascimento'])) : 'N/D';
                                $meta_desc = ($ta['faixa'] ?: 'Sem faixa') . ' • Nasc: ' . $ano_nasc;
                                if (!empty($ta['academia_nome'])) {
                                    $meta_desc .= ' • ' . $ta['academia_nome'];
                                }
                                if (!empty($ta['turmas_nomes'])) {
                                    $meta_desc .= ' • Turma: ' . $ta['turmas_nomes'];
                                }
                                $texto_opcao = $ta['nome_completo'] . ' (' . $meta_desc . ')';
                            ?>
                                <div class="opcao-aluno-inscrever" data-id="<?php echo $ta['id']; ?>" data-texto="<?php echo htmlspecialchars(strtolower($texto_opcao)); ?>"
                                    onclick="selecionarAlunoInscrever(<?php echo $ta['id']; ?>, '<?php echo htmlspecialchars(addslashes($texto_opcao)); ?>')"
                                    style="padding: 12px 15px; cursor: pointer; font-size: var(--fs-sm); font-weight: 700; border-bottom: 1px solid var(--border-color);">
                                    <?php echo htmlspecialchars($ta['nome_completo']); ?>
                                    <span style="font-weight: 600; color: var(--text-muted);"> (<?php echo htmlspecialchars($meta_desc); ?>)</span>
                                </div>
                            <?php endforeach; ?>
                            <div id="nenhum_aluno_encontrado" style="display: none; padding: 15px; text-align: center; font-size: var(--fs-sm); color: var(--text-muted); font-weight: 700;">Nenhum aluno encontrado.</div>
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Faixa Pretendida</label>
                        <select name="faixa_pretendida" required
                            style="width: 100%; height: 50px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark); appearance: none;">
                            <option value="">— Selecionar —</option>
                            <?php 
                            if (empty($graduacoes_list)) {
                                // Fallback padrão
                                $static_list = ['Branca', 'Cinza', 'Amarela', 'Laranja', 'Verde', 'Azul', 'Roxa', 'Marrom', 'Preta'];
                                foreach ($static_list as $f_st) {
                                    echo '<option value="' . $f_st . '">' . strtoupper($f_st) . '</option>';
                                }
                            } else {
                                foreach ($graduacoes_list as $g_opt) {
                                    $display_val = $g_opt['nome'];
                                    if (!empty($g_opt['grau'])) {
                                        $display_val .= ' - ' . $g_opt['grau'];
                                    }
                                    echo '<option value="' . htmlspecialchars($display_val) . '">' . htmlspecialchars(strtoupper($display_val)) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div style="width: 100px;">
                        <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Tamanho</label>
                        <input type="text" name="tamanho_faixa"
                            style="width: 100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 700;"
                            placeholder="Ex: M3, A2">
                    </div>
                    <button type="submit" name="adicionar_aluno" class="btn-sq" style="height: 50px; padding: 0 25px; white-space: nowrap;">
                        <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Inscrever
                    </button>
                </form>
            </div>

            <script>
                function filtrarAlunosInscrever() {
                    const termo = document.getElementById('busca_aluno_inscrever').value.trim().toLowerCase();
                    const opcoes = document.querySelectorAll('.opcao-aluno-inscrever');
                    let algumVisivel = false;
                    opcoes.forEach(function (op) {
                        const visivel = op.getAttribute('data-texto').includes(termo);
                        op.style.display = visivel ? 'block' : 'none';
                        if (visivel) algumVisivel = true;
                    });
                    document.getElementById('nenhum_aluno_encontrado').style.display = algumVisivel ? 'none' : 'block';
                }

                function selecionarAlunoInscrever(id, texto) {
                    document.getElementById('aluno_id_selecionado').value = id;
                    document.getElementById('busca_aluno_inscrever').value = texto;
                    document.getElementById('lista_alunos_inscrever').style.display = 'none';
                }

                function validarInscricaoAluno() {
                    if (!document.getElementById('aluno_id_selecionado').value) {
                        alert('Selecione um aluno na lista antes de inscrever.');
                        document.getElementById('busca_aluno_inscrever').focus();
                        return false;
                    }
                    return true;
                }

                document.addEventListener('click', function (evt) {
                    const caixa = document.getElementById('lista_alunos_inscrever');
                    const campo = document.getElementById('busca_aluno_inscrever');
                    if (caixa && !caixa.contains(evt.target) && evt.target !== campo) {
                        caixa.style.display = 'none';
                    }
                });

                document.querySelectorAll('.opcao-aluno-inscrever').forEach(function (op) {
                    op.addEventListener('mouseover', function () { op.style.background = '#f1f5f9'; });
                    op.addEventListener('mouseout', function () { op.style.background = '#fff'; });
                });

                function filtrarInscritos() {
                    const termo = document.getElementById('busca_inscritos').value.trim().toLowerCase();
                    document.querySelectorAll('.linha-aluno-inscrito').forEach(function (linha) {
                        linha.style.display = linha.getAttribute('data-nome').includes(termo) ? '' : 'none';
                    });
                }
            </script>

            <!-- Lista de Inscritos -->
            <div class="dashboard-container" style="padding: 0;">
                <div style="padding: 20px 30px; border-bottom: 1px solid var(--border-color); background: #fff; display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;">
                    <h3 style="margin: 0; font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.1em; white-space: nowrap;">Alunos Inscritos (<?php echo count($inscritos); ?>)</h3>
                    <div style="display: flex; gap: 10px; align-items: center; flex: 1; max-width: 420px; min-width: 200px;">
                        <input type="text" id="busca_inscritos" oninput="filtrarInscritos()" placeholder="Buscar aluno inscrito..."
                            style="width: 100%; height: 38px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                    </div>
                    <?php if (!empty($inscritos)): ?>
                    <a href="relatorio_faixas.php?id=<?php echo $evento_id; ?>" target="_blank" class="btn-sq-outline" style="width: auto; padding: 8px 18px; font-size: var(--fs-xs); white-space: nowrap;">
                        <i class="fa-solid fa-print" style="margin-right: 6px;"></i> Imprimir Lista
                    </a>
                    <?php endif; ?>
                </div>
                <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="table-sq" style="width: 100%; min-width: 720px;">
                    <thead>
                        <tr>
                            <th style="padding: 15px 20px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Aluno</th>
                            <th style="padding: 15px 20px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Graduação Atual</th>
                            <th style="padding: 15px 20px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Faixa Pretendida</th>
                            <th style="padding: 15px 20px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Requisitos</th>
                            <th style="padding: 15px 20px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Pagamento</th>
                            <th style="padding: 15px 20px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Resultado</th>
                            <th style="padding: 15px 20px; text-align: right;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inscritos)): ?>
                            <tr><td colspan="7" style="padding: 60px; text-align: center; color: var(--text-muted); font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase;">Nenhum aluno inscrito ainda.</td></tr>
                        <?php else: ?>
                            <?php foreach ($inscritos as $insc): 
                                $ano_nasc_insc = $insc['data_nascimento'] ? date('Y', strtotime($insc['data_nascimento'])) : 'N/D';
                                $sub_meta_insc = 'Nasc: ' . $ano_nasc_insc;
                                if (!empty($insc['academia_nome'])) {
                                    $sub_meta_insc .= ' • Local: ' . htmlspecialchars($insc['academia_nome']);
                                }
                                if (!empty($insc['turmas_nomes'])) {
                                    $sub_meta_insc .= ' • Turma: ' . htmlspecialchars($insc['turmas_nomes']);
                                }
                            ?>
                                <tr class="linha-aluno-inscrito" data-nome="<?php echo htmlspecialchars(strtolower($insc['aluno_nome'])); ?>">
                                    <td style="padding: 18px 20px;">
                                        <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-sm); text-transform: uppercase;">
                                            <?php echo htmlspecialchars($insc['aluno_nome']); ?>
                                            <?php if (($insc['tipo_cadastro'] ?? 'fixo') === 'visitante'): ?>
                                                <span style="background:#f59e0b; color:#fff; font-size:10px; font-weight:800; padding:2px 6px; border-radius:4px; margin-left:6px; vertical-align:middle;">VISITANTE</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 3px;"><?php echo $sub_meta_insc; ?></div>
                                    </td>
                                    <td style="padding: 18px 20px;">
                                        <span style="font-size: var(--fs-sm); font-weight: 900; color: #133080; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($insc['faixa_atual'] ?: $insc['aluno_faixa'] ?: '—'); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 18px 20px;">
                                        <form method="POST" style="margin: 0 0 6px 0;">
                                            <input type="hidden" name="inscricao_id" value="<?php echo $insc['id']; ?>">
                                            <input type="hidden" name="atualizar_faixa_pretendida" value="1">
                                            <select name="faixa_pretendida" onchange="this.form.submit()"
                                                style="width: 160px; height: 30px; font-size: var(--fs-xs); font-weight: 900; border: 1px solid var(--border-color); padding: 0 8px; color: #4f46e5; background: #fafafa; text-transform: uppercase; appearance: none;">
                                                <option value="" <?php echo empty($insc['faixa_pretendida']) ? 'selected' : ''; ?>>— Selecionar —</option>
                                                <?php
                                                    $opcoes_faixa_insc = !empty($graduacoes_list) ? $graduacoes_list : array_map(fn($f) => ['nome' => $f, 'grau' => ''], ['Branca', 'Cinza', 'Amarela', 'Laranja', 'Verde', 'Azul', 'Roxa', 'Marrom', 'Preta']);
                                                    foreach ($opcoes_faixa_insc as $g_opt):
                                                        $display_val_insc = $g_opt['nome'] . (!empty($g_opt['grau']) ? ' - ' . $g_opt['grau'] : '');
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($display_val_insc); ?>" <?php echo $insc['faixa_pretendida'] === $display_val_insc ? 'selected' : ''; ?>><?php echo htmlspecialchars(strtoupper($display_val_insc)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                        <form method="POST" style="margin: 0;" onsubmit="return true;">
                                            <input type="hidden" name="inscricao_id" value="<?php echo $insc['id']; ?>">
                                            <input type="hidden" name="atualizar_tamanho_faixa" value="1">
                                            <input type="text" name="tamanho_faixa" value="<?php echo htmlspecialchars($insc['tamanho_faixa'] ?? ''); ?>" placeholder="Tamanho (ex: A2)" onblur="if(this.value !== this.defaultValue) this.form.submit()"
                                                style="width: 160px; height: 28px; font-size: 11px; font-weight: 700; border: 1px solid var(--border-color); padding: 0 8px; background: #fafafa;">
                                        </form>
                                    </td>
                                    <td style="padding: 18px 20px;">
                                        <?php $req = $insc['requisitos']; ?>
                                        <div style="display: flex; flex-direction: column; gap: 5px;">
                                            <?php
                                                $badge_carencia_cor = $req['carencia'] === null ? ['bg' => '#f1f5f9', 'fg' => '#64748b'] : ($req['carencia'] ? ['bg' => '#dcfce7', 'fg' => '#166534'] : ['bg' => '#fee2e2', 'fg' => '#991b1b']);
                                                $badge_carencia_txt = $req['carencia'] === null ? 'CARÊNCIA: SEM DADOS' : ($req['carencia'] ? 'CARÊNCIA OK' : 'FALTAM ' . $req['carencia_faltam_meses'] . ' MESES');
                                            ?>
                                            <span style="font-size: 10px; font-weight: 900; padding: 3px 8px; text-transform: uppercase; letter-spacing: 0.03em; background: <?php echo $badge_carencia_cor['bg']; ?>; color: <?php echo $badge_carencia_cor['fg']; ?>; width: fit-content;">
                                                <i class="fa-solid fa-hourglass-half" style="margin-right: 4px;"></i><?php echo $badge_carencia_txt; ?>
                                            </span>
                                            <?php if ($req['frequencia'] !== null): ?>
                                                <span style="font-size: 10px; font-weight: 900; padding: 3px 8px; text-transform: uppercase; letter-spacing: 0.03em; background: <?php echo $req['frequencia'] ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $req['frequencia'] ? '#166534' : '#991b1b'; ?>; width: fit-content;">
                                                    <i class="fa-solid fa-chart-line" style="margin-right: 4px;"></i>FREQ. <?php echo $req['frequencia_pct']; ?>%
                                                </span>
                                            <?php elseif (!empty($evento['requisito_frequencia_minima'])): ?>
                                                <span style="font-size: 10px; font-weight: 900; padding: 3px 8px; text-transform: uppercase; letter-spacing: 0.03em; background: #f1f5f9; color: #64748b; width: fit-content;">
                                                    <i class="fa-solid fa-chart-line" style="margin-right: 4px;"></i>FREQ. SEM DADOS
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 18px 20px;">
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="inscricao_id" value="<?php echo $insc['id']; ?>">
                                            <input type="hidden" name="resultado" value="<?php echo $insc['resultado']; ?>">
                                            <select name="status_pagamento" onchange="this.form.submit()"
                                                style="height: 32px; font-size: var(--fs-xs); font-weight: 900; border: 2px solid <?php echo $insc['status_pagamento'] == 'pago' ? '#22c55e' : '#ef4444'; ?>; padding: 0 10px; color: <?php echo $insc['status_pagamento'] == 'pago' ? '#166534' : '#991b1b'; ?>; background: <?php echo $insc['status_pagamento'] == 'pago' ? '#dcfce7' : '#fee2e2'; ?>; text-transform: uppercase; font-weight: 900; appearance: none;">
                                                <option value="pendente" <?php echo $insc['status_pagamento'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                                <option value="pago" <?php echo $insc['status_pagamento'] == 'pago' ? 'selected' : ''; ?>>Pago</option>
                                            </select>
                                            <input type="hidden" name="atualizar_inscricao" value="1">
                                        </form>
                                    </td>
                                    <td style="padding: 18px 20px;">
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="inscricao_id" value="<?php echo $insc['id']; ?>">
                                            <input type="hidden" name="status_pagamento" value="<?php echo $insc['status_pagamento']; ?>">
                                            <select name="resultado" onchange="this.form.submit()"
                                                style="height: 32px; font-size: var(--fs-xs); font-weight: 900; border: 2px solid <?php echo $insc['resultado'] == 'aprovado' ? '#22c55e' : ($insc['resultado'] == 'reprovado' ? '#ef4444' : '#94a3b8'); ?>; padding: 0 10px; color: <?php echo $insc['resultado'] == 'aprovado' ? '#166534' : ($insc['resultado'] == 'reprovado' ? '#991b1b' : '#64748b'); ?>; background: <?php echo $insc['resultado'] == 'aprovado' ? '#dcfce7' : ($insc['resultado'] == 'reprovado' ? '#fee2e2' : '#f8fafc'); ?>; text-transform: uppercase; font-weight: 900; appearance: none;">
                                                <option value="pendente" <?php echo $insc['resultado'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                                <option value="aprovado" <?php echo $insc['resultado'] == 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                                                <option value="reprovado" <?php echo $insc['resultado'] == 'reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                                            </select>
                                            <input type="hidden" name="atualizar_inscricao" value="1">
                                        </form>
                                        <?php if (($insc['nota'] ?? null) !== null): ?>
                                            <div style="font-size: 10px; font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-top: 6px;">Nota: <span style="color: #133080;"><?php echo number_format($insc['nota'], 1, ',', '.'); ?></span></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 18px 20px; text-align: right;">
                                        <form method="POST" onsubmit="return confirm('Remover este aluno do exame?')" style="margin: 0;">
                                            <input type="hidden" name="inscricao_id" value="<?php echo $insc['id']; ?>">
                                            <button type="submit" name="remover_inscricao"
                                                style="background: #fff0f0; border: 1px solid #fecaca; padding: 6px 12px; cursor: pointer; color: #ef4444; font-size: 13px;" title="Remover">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div><!-- /tab-inscritos -->

    <!-- TAB FINANCEIRO -->
    <div id="tab-financeiro" class="tab-content-evento">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div class="dashboard-container" style="padding: 20px; text-align: center;">
                <div style="font-size: 1.6rem; font-weight: 900; color: var(--primary-green);">R$ <?php echo number_format($receita_total_evento, 2, ',', '.'); ?></div>
                <div style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-top: 4px;">Receitas Totais</div>
            </div>
            <div class="dashboard-container" style="padding: 20px; text-align: center;">
                <div style="font-size: 1.6rem; font-weight: 900; color: #ef4444;">R$ <?php echo number_format($despesa_manual, 2, ',', '.'); ?></div>
                <div style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-top: 4px;">Despesas Totais</div>
            </div>
            <div class="dashboard-container" style="padding: 20px; text-align: center;">
                <div style="font-size: 1.6rem; font-weight: 900; color: #133080;">R$ <?php echo number_format($saldo_evento, 2, ',', '.'); ?></div>
                <div style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-top: 4px;">Saldo do Evento</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 320px 1fr; gap: 25px; align-items: start;">
            <!-- Novo lançamento -->
            <div class="dashboard-container" style="padding: 30px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Novo Lançamento</h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="novo_lancamento_evento" value="1">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Tipo</label>
                        <select name="tipo_lancamento" style="width: 100%; height: 44px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 12px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase;">
                            <option value="despesa">Despesa</option>
                            <option value="receita">Receita</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Descrição</label>
                        <input type="text" name="descricao_lancamento" required placeholder="Ex: Aluguel do ginásio, Patrocínio..." style="width: 100%; height: 44px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Valor</label>
                        <input type="text" name="valor_lancamento" required placeholder="0,00" style="width: 100%; height: 44px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Data</label>
                        <input type="date" name="data_lancamento" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; height: 44px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 12px; font-size: var(--fs-sm); font-weight: 700;">
                    </div>
                    <button type="submit" class="btn-sq" style="width: 100%; padding: 14px;">
                        <i class="fa-solid fa-plus-circle" style="margin-right: 8px;"></i> LANÇAR
                    </button>
                </form>
            </div>

            <!-- Extrato -->
            <div class="dashboard-container" style="padding: 30px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Extrato do Evento</h2>
                    <p style="font-size: var(--fs-xs); color: var(--text-muted); margin: 6px 0 0 0; font-weight: 600;">Conta separada desta edição do exame.</p>
                </div>

                <?php foreach ($inscritos_pagos_fin as $insc_pago): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                        <div>
                            <div style="font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase;">
                                <?php echo htmlspecialchars($insc_pago['aluno_nome']); ?>
                                <span style="font-weight: 700; color: var(--text-muted); text-transform: none; font-size: var(--fs-xs);">— Inscrição no exame</span>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600;">
                                <?php echo !empty($insc_pago['turmas_nomes']) ? htmlspecialchars($insc_pago['turmas_nomes']) : 'Sem turma'; ?>
                            </div>
                        </div>
                        <div style="font-weight: 900; color: #166534;">+ R$ <?php echo number_format($insc_pago['valor_pago'], 2, ',', '.'); ?></div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($lancamentos_evento) && $receita_inscricoes <= 0): ?>
                    <div style="padding: 50px 20px; text-align: center; color: var(--text-muted);">
                        <i class="fa-solid fa-sack-dollar" style="font-size: 2.5rem; opacity: 0.15; display: block; margin-bottom: 15px;"></i>
                        <p style="font-weight: 800; text-transform: uppercase; font-size: var(--fs-sm);">Nenhum lançamento registrado.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($lancamentos_evento as $lanc): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                            <div>
                                <div style="font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase;"><?php echo htmlspecialchars($lanc['descricao']); ?></div>
                                <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600;"><?php echo date('d/m/Y', strtotime($lanc['data_lancamento'])); ?></div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span style="font-weight: 900; color: <?php echo $lanc['tipo'] === 'receita' ? '#166534' : '#991b1b'; ?>;">
                                    <?php echo $lanc['tipo'] === 'receita' ? '+' : '-'; ?> R$ <?php echo number_format($lanc['valor'], 2, ',', '.'); ?>
                                </span>
                                <form method="POST" onsubmit="return confirm('Remover este lançamento?')" style="margin: 0;">
                                    <input type="hidden" name="lancamento_id" value="<?php echo $lanc['id']; ?>">
                                    <button type="submit" name="remover_lancamento_evento" style="background: #fff0f0; border: 1px solid #fecaca; padding: 6px 10px; cursor: pointer; color: #ef4444; font-size: 12px;" title="Remover">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /tab-financeiro -->
</section>

<script>
    function openTabEvento(evt, tabName) {
        document.querySelectorAll('.tab-content-evento').forEach(function (el) { el.classList.remove('active'); });
        document.querySelectorAll('.tab-btn-inner').forEach(function (el) { el.classList.remove('active'); });
        document.getElementById(tabName).classList.add('active');
        evt.currentTarget.classList.add('active');
    }
</script>

<!-- Modal Excluir Evento -->
<div id="modal_excluir_evento" class="modal-sq" style="display: none;">
    <div class="modal-content-sq" style="max-width: 460px;">
        <h3 style="font-size: var(--fs-sm); font-weight: 900; color: #ef4444; text-transform: uppercase; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #fee2e2;">
            <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i> Excluir Evento
        </h3>
        <p style="font-size: var(--fs-sm); color: var(--text-muted); margin-bottom: 25px; font-weight: 600;">
            Você está prestes a excluir <strong style="color: var(--text-dark);"><?php echo htmlspecialchars($evento['titulo']); ?></strong> e todas as suas inscrições. Esta ação não pode ser desfeita.
        </p>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button onclick="document.getElementById('modal_excluir_evento').style.display='none'" class="btn-sq-outline" style="width: auto; padding: 10px 20px;">Cancelar</button>
            <form method="POST" style="margin: 0;">
                <button type="submit" name="excluir" class="btn-sq" style="width: auto; padding: 10px 20px; background: #ef4444; border-color: #ef4444;">
                    <i class="fa-solid fa-trash-can" style="margin-right: 8px;"></i> Sim, Excluir
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>