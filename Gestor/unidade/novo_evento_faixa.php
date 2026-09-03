<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!moduloAtivo('competicoes') || !temPermissaoModulo('eventos')) {
    header('Location: faixas.php?erro=sem_permissao');
    exit;
}

// 1. Verificação / Migração Automática de Tabelas
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

// 1b. Auto-migração: cronograma do exame (inscrições, requisitos, avaliações) e data do evento
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

// 1c0. Auto-migração: tabela de cadastro completo por turma (quando nem todas as turmas usam os mesmos dados)
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

// 1c1. Auto-migração: campos completos de cadastro por turma (valores de lote, local de entrega, tentativas)
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

// 1c. Auto-migração: tabela de turmas participantes (quando nem todas as turmas participam do exame)
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

// 1d. Auto-migração: tabela de valores de taxa personalizados por turma (legado)
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

// 1e. Auto-migração: valor do 2º lote por turma (legado)
try {
    $pdo->query("SELECT valor_lote2 FROM eventos_graduacao_valores_turma LIMIT 1");
} catch (Exception $e) {
    try { $pdo->exec("ALTER TABLE eventos_graduacao_valores_turma ADD COLUMN valor_lote2 DECIMAL(10,2) DEFAULT NULL"); } catch (Exception $ex) {}
}

// 2. Garantir que a tabela academias existe (necessária para o select)
try {
    $pdo->query("SELECT 1 FROM academias LIMIT 1");
} catch (Exception $e) {
    $sql_acad = "CREATE TABLE IF NOT EXISTS academias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        endereco VARCHAR(255),
        telefone VARCHAR(20),
        email VARCHAR(255),
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_acad);
}

$mensagem = '';
$erro = '';

// Buscar turmas da unidade para o seletor de participação (necessário também no processamento do POST)
$stmt_turmas = $pdo->prepare("SELECT t.id, t.nome, a.nome as academia_nome FROM turmas t LEFT JOIN academias a ON t.academia_id = a.id WHERE t.unidade_id = ? AND t.status = 'ativo' ORDER BY a.nome ASC, t.nome ASC");
$stmt_turmas->execute([$unidade_id]);
$turmas_disponiveis = $stmt_turmas->fetchAll();

// Processar POST antes do header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $academia_id = null;
    $horario = $_POST['horario'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $requisito_pagamento_obrigatorio = isset($_POST['requisito_pagamento_obrigatorio']) ? 1 : 0;
    $requisito_frequencia_minima = $_POST['requisito_frequencia_minima'] !== '' ? $_POST['requisito_frequencia_minima'] : null;
    $requisito_carencia_ativo = ($_POST['requisito_carencia_ativo'] ?? 'sim') === 'sim' ? 1 : 0;

    // Todas as turmas participam (Sim = cadastro único; Não = cadastro completo por turma)
    $todas_turmas = ($_POST['todas_turmas'] ?? 'sim') === 'sim' ? 1 : 0;
    $turmas_selecionadas = $_POST['turmas'] ?? [];
    $turma_dados = $_POST['turma_dados'] ?? [];

    if ($todas_turmas) {
        // Modo único: um único conjunto de campos vale para todas as turmas
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
        // Modo por turma: cada turma selecionada tem seu próprio cadastro completo
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
            $stmt = $pdo->prepare("INSERT INTO eventos_graduacao (unidade_id, academia_id, titulo, descricao, data_evento, horario, local, taxa, taxa_lote2, lote2_data_inicio, inscricoes_inicio, inscricoes_fim, avaliacoes_inicio, avaliacoes_fim, avaliacoes_numero_tentativas, requisito_pagamento_obrigatorio, requisito_frequencia_minima, requisito_carencia_ativo, todas_turmas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $academia_id, $titulo, $descricao, $data_evento, $horario, $local, $taxa, $taxa_lote2, $lote2_data_inicio, $inscricoes_inicio, $inscricoes_fim, $avaliacoes_inicio, $avaliacoes_fim, $avaliacoes_numero_tentativas, $requisito_pagamento_obrigatorio, $requisito_frequencia_minima, $requisito_carencia_ativo, $todas_turmas]);
            $novo_evento_id = $pdo->lastInsertId();

            if (!$todas_turmas && !empty($turmas_selecionadas)) {
                $stmt_turma = $pdo->prepare("INSERT INTO eventos_graduacao_turmas (evento_id, turma_id) VALUES (?, ?)");
                foreach ($turmas_selecionadas as $turma_id) {
                    $stmt_turma->execute([$novo_evento_id, (int)$turma_id]);
                }
            }

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
                        $novo_evento_id,
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
            $mensagem = "Exame de faixa agendado com sucesso!";
            header("refresh:2;url=faixas.php");
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = "Erro ao cadastrar: " . $e->getMessage();
        }
    } else {
        $erro = $todas_turmas
            ? "Por favor, preencha o título e a data do evento."
            : "Por favor, preencha o título, selecione ao menos uma turma e informe a data de entrega dela.";
    }
}

$custom_title = "Exames de Faixas";
$custom_subtitle = "Agende um novo evento de graduação para os alunos da sua unidade.";
$back_link = "faixas.php";
$back_text = "Voltar para Lista";
include 'header.php';
?>

<div style="padding: 1.5rem;">
    <!-- Menu Especial do Departamento -->
    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="eventos_dashboard.php" class="tab-item-sq">Dashboard</a>
        <a href="competicoes_oficiais.php" class="tab-item-sq">Oficiais</a>
        <a href="competicoes.php" class="tab-item-sq">Torneios</a>
        <a href="faixas.php" class="tab-item-sq active">Exames</a>
        <a href="relatorios_eventos.php" class="tab-item-sq">Relatórios</a>
    </div>

    <div>
        <!-- Formulário -->
        <div>
            <?php if ($mensagem): ?>
                <div class="alert"
                    style="background:#dcfce7; color:#166534; padding:1.25rem; margin-bottom:1.5rem; border: 1px solid #bbf7d0; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo $mensagem; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert"
                    style="background:#fee2e2; color:#991b1b; padding:1.25rem; margin-bottom:1.5rem; border: 1px solid #fecaca; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo $erro; ?></span>
                </div>
            <?php endif; ?>

            <div class="card" style="padding: 0; border: none; box-shadow: none; background: transparent;">
                <form method="POST">
                    <div style="background: #ffffff; padding: 1.75rem; border: 1px solid #f1f5f9; margin-bottom: 1.25rem;">
                        <h2 class="section-title-sq">Detalhes do Exame</h2>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Título do Evento *</label>
                            <input type="text" name="titulo" class="form-control" required placeholder="Ex: Graduação de Final de Ano 2026"
                                style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Descrição / Observações</label>
                            <textarea name="descricao" class="form-control" rows="4" placeholder="Detalhes sobre o evento, requisitos, etc."
                                style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0; resize: none;"></textarea>
                        </div>
                    </div>

                    <!-- Turmas Participantes -->
                    <div style="background: #ffffff; padding: 1.75rem; border: 1px solid #f1f5f9; margin-bottom: 1.25rem;">
                        <h2 class="section-title-sq">Turmas Participantes</h2>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.75rem; letter-spacing: 0.05em; display: block;">Todas as turmas participam?</label>
                            <div style="display: flex; gap: 1.5rem;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="radio" name="todas_turmas" value="sim" checked onchange="alternarModoTurmas('sim');" style="width: 18px; height: 18px;">
                                    <span style="font-size: 0.85rem; color: var(--text-dark); font-weight: 700;">Sim</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="radio" name="todas_turmas" value="nao" onchange="alternarModoTurmas('nao');" style="width: 18px; height: 18px;">
                                    <span style="font-size: 0.85rem; color: var(--text-dark); font-weight: 700;">Não</span>
                                </label>
                            </div>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.6rem 0 0 0; line-height: 1.5;">
                                Se "Sim", um único cadastro (data, local, valores e cronograma) vale para todas as turmas. Se "Não", cada turma selecionada abaixo tem seu próprio cadastro completo.
                            </p>
                        </div>

                        <div id="bloco-turmas-selecao" style="display: none;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.75rem; letter-spacing: 0.05em; display: block;">Selecione as turmas que participam</label>
                            <?php if (empty($turmas_disponiveis)): ?>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Nenhuma turma ativa cadastrada.</p>
                            <?php else: ?>
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 1.25rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.9rem; max-height: 280px; overflow-y: auto; margin-bottom: 1.5rem;">
                                    <?php foreach ($turmas_disponiveis as $t_op): ?>
                                        <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="turmas[]" value="<?php echo $t_op['id']; ?>" onchange="atualizarCadastroPorTurma();" style="width: 16px; height: 16px; margin-top: 2px;">
                                            <span style="font-size: 0.8rem; color: var(--text-dark); font-weight: 700;">
                                                <?php echo htmlspecialchars($t_op['nome']); ?>
                                                <?php if (!empty($t_op['academia_nome'])): ?><span style="display: block; font-size: 0.7rem; color: var(--text-muted); font-weight: 600;"><?php echo htmlspecialchars($t_op['academia_nome']); ?></span><?php endif; ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Cadastro completo por turma -->
                                <div id="bloco-cadastro-por-turma">
                                    <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 900; margin-bottom: 0.75rem; letter-spacing: 0.05em; display: block;">Cadastro de Cada Turma</label>
                                    <div style="display: grid; gap: 1.25rem;">
                                        <?php foreach ($turmas_disponiveis as $t_data): ?>
                                            <div class="linha-turma-cadastro" data-turma-id="<?php echo $t_data['id']; ?>" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 1.25rem;">
                                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 1rem;">
                                                    <div style="font-size: 0.85rem; color: var(--text-dark); font-weight: 800;">
                                                        <?php echo htmlspecialchars($t_data['nome']); ?>
                                                        <?php if (!empty($t_data['academia_nome'])): ?><span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600;"> — <?php echo htmlspecialchars($t_data['academia_nome']); ?></span><?php endif; ?>
                                                    </div>
                                                    <button type="button" class="btn-copiar-turma" onclick="copiarDadosTurmaAnterior(this)">
                                                        <i class="fa-solid fa-copy"></i> Copiar da turma anterior
                                                    </button>
                                                </div>
                                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.9rem;">
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Início Inscrições</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][inscricoes_inicio]"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Fim Inscrições</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][inscricoes_fim]"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Início Avaliações</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][avaliacoes_inicio]"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Fim Avaliações</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][avaliacoes_fim]"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Valor Lote 1 (R$)</label>
                                                        <input type="number" step="0.01" min="0" name="turma_dados[<?php echo $t_data['id']; ?>][valor_lote1]" placeholder="0.00"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Lote 1 – Data Limite</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][valor_lote1_data_limite]"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Valor Lote 2 (R$)</label>
                                                        <input type="number" step="0.01" min="0" name="turma_dados[<?php echo $t_data['id']; ?>][valor_lote2]" placeholder="0.00"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Lote 2 – Data Limite</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][valor_lote2_data_limite]"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Data da Entrega</label>
                                                        <input type="date" name="turma_dados[<?php echo $t_data['id']; ?>][data_entrega]"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Horário da Entrega</label>
                                                        <input type="time" name="turma_dados[<?php echo $t_data['id']; ?>][horario_entrega]"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Local da Entrega</label>
                                                        <input type="text" name="turma_dados[<?php echo $t_data['id']; ?>][local_entrega]" placeholder="Ex: Ginásio Municipal"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Nº Tentativas Avaliação</label>
                                                        <input type="number" min="1" step="1" name="turma_dados[<?php echo $t_data['id']; ?>][avaliacoes_numero_tentativas]" placeholder="Ex: 2"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800;">Frequência Mínima (%)</label>
                                                        <input type="number" step="0.1" min="0" max="100" name="turma_dados[<?php echo $t_data['id']; ?>][requisito_frequencia_minima]" placeholder="Ex: 75"
                                                            style="padding:0.6rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.8rem;">
                                                    </div>
                                                </div>
                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 0.9rem;">
                                                    <input type="checkbox" name="turma_dados[<?php echo $t_data['id']; ?>][requisito_pagamento_obrigatorio]" value="1" checked style="width: 16px; height: 16px;">
                                                    <span style="font-size: 0.78rem; color: var(--text-dark); font-weight: 700;">Exigir pagamento da taxa para confirmar a inscrição</span>
                                                </label>
                                                <div style="margin-top: 0.9rem; padding-top: 0.9rem; border-top: 1px solid #e2e8f0;">
                                                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 0.75rem;">
                                                        <i class="fa-solid fa-hourglass-half" style="color: var(--text-muted); margin-top: 2px; font-size: 0.75rem;"></i>
                                                        <div>
                                                            <strong style="font-size: 0.75rem; color: var(--text-dark);">Exigir carência?</strong>
                                                            <p style="font-size: 0.7rem; color: var(--text-muted); margin: 2px 0 0 0; line-height: 1.4;">
                                                                Se "Sim", verificada automaticamente por aluno, com base na graduação pretendida (tempo mínimo cadastrado em Configurações &gt; Graduações) e na data da última graduação do aluno.
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; gap: 1.25rem; padding-left: 22px;">
                                                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                            <input type="radio" name="turma_dados[<?php echo $t_data['id']; ?>][requisito_carencia_ativo]" value="sim" checked style="width: 16px; height: 16px;">
                                                            <span style="font-size: 0.78rem; color: var(--text-dark); font-weight: 700;">Sim</span>
                                                        </label>
                                                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                            <input type="radio" name="turma_dados[<?php echo $t_data['id']; ?>][requisito_carencia_ativo]" value="nao" style="width: 16px; height: 16px;">
                                                            <span style="font-size: 0.78rem; color: var(--text-dark); font-weight: 700;">Não</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pagamento (modo único) -->
                    <div class="bloco-modo-sim" style="background: #ffffff; padding: 1.75rem; border: 1px solid #f1f5f9; margin-bottom: 1.25rem;">
                        <h2 class="section-title-sq">Pagamento</h2>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.75rem; letter-spacing: 0.05em; display: block;">Lotes de Pagamento</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Valor 1º Lote (R$)</label>
                                    <input type="number" step="0.01" min="0" name="taxa" class="form-control" placeholder="0.00"
                                        style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data Início do 2º Lote</label>
                                    <input type="date" name="lote2_data_inicio" class="form-control"
                                        style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Valor 2º Lote (R$)</label>
                                    <input type="number" step="0.01" min="0" name="taxa_lote2" class="form-control" placeholder="0.00"
                                        style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                                </div>
                            </div>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.6rem 0 0 0; line-height: 1.5;">
                                A partir da data de início do 2º lote, o valor cobrado passa automaticamente para o "Valor 2º Lote". Deixe o 2º lote em branco se não houver reajuste.
                            </p>
                        </div>
                    </div>

                    <!-- Cronograma do Exame (modo único) -->
                    <div style="background: #ffffff; padding: 1.75rem; border: 1px solid #f1f5f9; margin-bottom: 1.25rem;">
                        <h2 class="section-title-sq">Cronograma do Exame</h2>

                        <div class="form-group bloco-modo-sim" style="margin-bottom: 1.25rem;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 900; margin-bottom: 0.75rem; letter-spacing: 0.05em; display: block;">1. Data, Horário e Local</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                                <div class="form-group" id="bloco-data-evento-unica">
                                    <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data do Evento *</label>
                                    <input type="date" name="data_evento" id="input-data-evento-unica" class="form-control" required
                                        style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Horário</label>
                                    <input type="time" name="horario" class="form-control"
                                        style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Local do Evento</label>
                                <input type="text" name="local" class="form-control" placeholder="Ex: Sede Principal ou Ginásio Municipal"
                                    style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                            </div>
                        </div>

                        <div class="form-group bloco-modo-sim" style="margin-bottom: 1.25rem;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 900; margin-bottom: 0.75rem; letter-spacing: 0.05em; display: block;">2. Inscrições</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                                <div class="form-group">
                                    <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data Início das Inscrições</label>
                                    <input type="date" name="inscricoes_inicio" class="form-control"
                                        style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data Final das Inscrições</label>
                                    <input type="date" name="inscricoes_fim" class="form-control"
                                        style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Avaliações (modo único): Requisitos + Cronograma de Avaliações -->
                    <div class="bloco-modo-sim" style="background: #ffffff; padding: 1.75rem; border: 1px solid #f1f5f9; margin-bottom: 1.25rem;">
                        <h2 class="section-title-sq">Avaliações</h2>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 900; margin-bottom: 0.75rem; letter-spacing: 0.05em; display: block;">1. Requisitos</label>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 1.5rem; display: grid; gap: 1.25rem;">
                                <div>
                                    <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 0.9rem;">
                                        <i class="fa-solid fa-hourglass-half" style="color: var(--text-muted); margin-top: 3px;"></i>
                                        <div>
                                            <strong style="font-size: 0.85rem; color: var(--text-dark);">Exigir carência?</strong>
                                            <p style="font-size: 0.78rem; color: var(--text-muted); margin: 2px 0 0 0; line-height: 1.5;">
                                                Se "Sim", verificada automaticamente por aluno, com base na graduação pretendida (tempo mínimo cadastrado em Configurações &gt; Graduações) e na data da última graduação do aluno.
                                            </p>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 1.5rem; padding-left: 26px;">
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="radio" name="requisito_carencia_ativo" value="sim" checked style="width: 18px; height: 18px;">
                                            <span style="font-size: 0.85rem; color: var(--text-dark); font-weight: 700;">Sim</span>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="radio" name="requisito_carencia_ativo" value="nao" style="width: 18px; height: 18px;">
                                            <span style="font-size: 0.85rem; color: var(--text-dark); font-weight: 700;">Não</span>
                                        </label>
                                    </div>
                                </div>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="requisito_pagamento_obrigatorio" value="1" checked style="width: 18px; height: 18px;">
                                    <span style="font-size: 0.85rem; color: var(--text-dark); font-weight: 700;">Exigir pagamento da taxa para confirmar a inscrição</span>
                                </label>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Frequência mínima exigida (%)</label>
                                    <input type="number" step="0.1" min="0" max="100" name="requisito_frequencia_minima" class="form-control" placeholder="Ex: 75 (deixe em branco para não exigir)"
                                        style="padding:1.1rem; border:1px solid #e2e8f0; background: #fff; width:100%; font-size: 0.95rem; border-radius: 0;">
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 900; margin-bottom: 0.75rem; letter-spacing: 0.05em; display: block;">2. Cronograma das Avaliações</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                                <div class="form-group">
                                    <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data Início das Avaliações</label>
                                    <input type="date" name="avaliacoes_inicio" class="form-control"
                                        style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data Final das Avaliações</label>
                                    <input type="date" name="avaliacoes_fim" class="form-control"
                                        style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 0; max-width: calc(50% - 1rem);">
                                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Número de Tentativas de Avaliação</label>
                                <input type="number" min="1" step="1" name="avaliacoes_numero_tentativas" class="form-control" placeholder="Ex: 2"
                                    style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                            </div>
                        </div>
                    </div>

                    <style>
                        .btn-copiar-turma {
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                            flex-shrink: 0;
                            background: #ffffff;
                            border: 1px solid #c7d2fe;
                            color: #133080;
                            font-family: inherit;
                            font-size: 0.7rem;
                            font-weight: 700;
                            letter-spacing: 0.02em;
                            padding: 0.45rem 0.8rem;
                            border-radius: 999px;
                            cursor: pointer;
                            white-space: nowrap;
                            transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
                        }
                        .btn-copiar-turma i {
                            font-size: 0.7rem;
                        }
                        .btn-copiar-turma:hover {
                            background: #133080;
                            border-color: #133080;
                            color: #ffffff;
                            transform: translateY(-1px);
                        }
                        .btn-copiar-turma:active {
                            transform: translateY(0);
                        }
                    </style>
                    <script>
                        function turmaEhParticipante(turmaId) {
                            var todasSel = document.querySelector('input[name="todas_turmas"]:checked');
                            if (!todasSel || todasSel.value === 'sim') return true;
                            var chk = document.querySelector('input[name="turmas[]"][value="' + turmaId + '"]');
                            return !!(chk && chk.checked);
                        }
                        function atualizarCadastroPorTurma() {
                            document.querySelectorAll('.linha-turma-cadastro').forEach(function (row) {
                                var id = row.getAttribute('data-turma-id');
                                row.style.display = turmaEhParticipante(id) ? '' : 'none';
                            });
                        }
                        function copiarDadosTurmaAnterior(btn) {
                            var linhaAtual = btn.closest('.linha-turma-cadastro');
                            if (!linhaAtual) return;
                            var linhas = Array.prototype.filter.call(
                                document.querySelectorAll('.linha-turma-cadastro'),
                                function (row) { return row.style.display !== 'none'; }
                            );
                            var idx = linhas.indexOf(linhaAtual);
                            if (idx <= 0) {
                                alert('Não há uma turma anterior visível para copiar os dados.');
                                return;
                            }
                            var linhaAnterior = linhas[idx - 1];
                            linhaAnterior.querySelectorAll('input, select, textarea').forEach(function (campoOrigem) {
                                var nomeOrigem = campoOrigem.getAttribute('name') || '';
                                var campo = nomeOrigem.match(/\[([a-z0-9_]+)\]$/i);
                                if (!campo) return;
                                var seletor = '[name$="[' + campo[1] + ']"]';
                                var campoDestino = linhaAtual.querySelector(seletor);
                                if (!campoDestino) return;
                                if (campoOrigem.type === 'checkbox') {
                                    campoDestino.checked = campoOrigem.checked;
                                } else {
                                    campoDestino.value = campoOrigem.value;
                                }
                            });
                        }
                        function alternarModoTurmas(valor) {
                            var todas = valor === 'sim';
                            document.getElementById('bloco-turmas-selecao').style.display = todas ? 'none' : 'block';
                            document.querySelectorAll('.bloco-modo-sim').forEach(function (el) {
                                el.style.display = todas ? '' : 'none';
                            });
                            document.getElementById('input-data-evento-unica').required = todas;
                            if (!todas) atualizarCadastroPorTurma();
                        }
                    </script>

                    <div style="margin-top: 2rem; display: flex; gap: 1.5rem; padding-top: 1.5rem;">
                        <button type="submit" class="btn-sq" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fa-solid fa-save"></i> AGENDAR EXAME DE FAIXA
                        </button>
                        <a href="faixas.php" class="btn-sq-outline" style="flex: 1; height: 3.5rem; text-decoration: none; display: flex; align-items: center; justify-content: center;">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
