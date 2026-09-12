<?php
require_once '../config.php';

$id = $_GET['id'] ?? null;
$unidade_id = getUnidadeId();

if (!$id) {
    header('Location: competicoes.php');
    exit;
}

// Handler AJAX para carregar chaves
if (isset($_GET['ajax_chave'])) {
    $cat_id = $_GET['cat_id'];
    $lutas = $pdo->prepare("
        SELECT l.*, 
        i1.nome_externo as i1_nome_ext, a1.nome_completo as i1_nome_int,
        i2.nome_externo as i2_nome_ext, a2.nome_completo as i2_nome_int
        FROM competicao_lutas l
        LEFT JOIN competicao_inscricoes i1 ON l.inscricao1_id = i1.id
        LEFT JOIN alunos a1 ON i1.aluno_id = a1.id
        LEFT JOIN competicao_inscricoes i2 ON l.inscricao2_id = i2.id
        LEFT JOIN alunos a2 ON i2.aluno_id = a2.id
        WHERE l.categoria_id = ?
        ORDER BY l.fase DESC, l.posicao ASC
    ");
    $lutas->execute([$cat_id]);
    header('Content-Type: application/json');
    echo json_encode($lutas->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Handler AJAX para Pesagem
if (isset($_POST['ajax_pesagem'])) {
    header('Content-Type: application/json');
    try {
        if (isset($_POST['update_status'])) {
            $insc_id = $_POST['insc_id'];
            $status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE competicao_inscricoes SET pesagem_status = ? WHERE id = ?");
            $stmt->execute([$status, $insc_id]);
            echo json_encode(['success' => true]);
        } elseif (isset($_POST['update_peso'])) {
            $insc_id = $_POST['insc_id'];
            $peso = str_replace(',', '.', $_POST['peso']);
            $stmt = $pdo->prepare("UPDATE competicao_inscricoes SET peso_atleta = ? WHERE id = ?");
            $stmt->execute([$peso, $insc_id]);
            echo json_encode(['success' => true]);
        } elseif (isset($_POST['update_resultado'])) {
            $insc_id = $_POST['insc_id'];
            $res = $_POST['resultado'];
            $stmt = $pdo->prepare("UPDATE competicao_inscricoes SET resultado = ? WHERE id = ?");
            $stmt->execute([$res, $insc_id]);
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Buscar dados da competição
$stmt_comp = $pdo->prepare("SELECT * FROM competicoes WHERE id = ? AND unidade_id = ?");
$stmt_comp->execute([$id, $unidade_id]);
$comp = $stmt_comp->fetch();

if (!$comp) {
    header('Location: competicoes.php');
    exit;
}

// Automigração de campos novos se não existirem
try {
    // Tabela Competicoes
    $column_check = $pdo->query("SHOW COLUMNS FROM competicoes LIKE 'slogan'")->fetch();
    if (!$column_check) {
        $pdo->exec("ALTER TABLE competicoes 
            ADD COLUMN slogan VARCHAR(255) AFTER nome,
            ADD COLUMN chamada VARCHAR(255) AFTER slogan,
            ADD COLUMN descricao TEXT AFTER chamada,
            ADD COLUMN data_fim_evento DATE AFTER data_evento,
            ADD COLUMN hora_inicio_evento TIME AFTER data_fim_evento,
            ADD COLUMN hora_fim_evento TIME AFTER hora_inicio_evento,
            ADD COLUMN data_inicio_inscricoes DATE AFTER hora_fim_evento,
            ADD COLUMN data_fim_inscricoes DATE AFTER data_inicio_inscricoes,
            ADD COLUMN hora_limite_inscricoes TIME AFTER data_fim_inscricoes,
            ADD COLUMN mapa_embed TEXT AFTER localizacao,
            ADD COLUMN video_embed TEXT AFTER mapa_embed,
            ADD COLUMN fotos JSON AFTER video_embed
        ");
        // Recarregar os dados após migração
        $stmt_comp->execute([$id, $unidade_id]);
        $comp = $stmt_comp->fetch();
    }

    // Automigração Tabela Competicoes (Regras e LGPD)
    $column_check_legal = $pdo->query("SHOW COLUMNS FROM competicoes LIKE 'regras'")->fetch();
    if (!$column_check_legal) {
        $pdo->exec("ALTER TABLE competicoes 
            ADD COLUMN regras TEXT AFTER fotos,
            ADD COLUMN lgpd TEXT AFTER regras
        ");
    }

    // Automigração Tabela Competicoes (Publicação no site)
    $column_check_publicar = $pdo->query("SHOW COLUMNS FROM competicoes LIKE 'publicar_site'")->fetch();
    if (!$column_check_publicar) {
        $pdo->exec("ALTER TABLE competicoes
            ADD COLUMN publicar_site TINYINT(1) NOT NULL DEFAULT 1 AFTER status
        ");
        // Recarregar os dados após migração
        $stmt_comp->execute([$id, $unidade_id]);
        $comp = $stmt_comp->fetch();
    }

    // Automigração: cadastro de academias/delegações visitantes (reutilizável em vários eventos)
    try {
        $pdo->query("SELECT 1 FROM delegacoes_visitantes LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS delegacoes_visitantes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidade_id INT NOT NULL,
            nome VARCHAR(255) NOT NULL,
            unidade_vinculada_id INT DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            site VARCHAR(255) DEFAULT NULL,
            whatsapp VARCHAR(30) DEFAULT NULL,
            cidade VARCHAR(100) DEFAULT NULL,
            estado VARCHAR(2) DEFAULT NULL,
            pais VARCHAR(100) DEFAULT 'Brasil',
            responsavel_nome VARCHAR(255) DEFAULT NULL,
            responsavel_telefone VARCHAR(30) DEFAULT NULL,
            responsavel_email VARCHAR(255) DEFAULT NULL,
            tecnico_nome VARCHAR(255) DEFAULT NULL,
            tecnico_telefone VARCHAR(30) DEFAULT NULL,
            financeiro_nome VARCHAR(255) DEFAULT NULL,
            financeiro_telefone VARCHAR(30) DEFAULT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE,
            FOREIGN KEY (unidade_vinculada_id) REFERENCES unidades(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // Automigração: convidados (academias autorizadas a inscrever alunos) de cada competição
    try {
        $pdo->query("SELECT 1 FROM competicao_convidados LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS competicao_convidados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            competicao_id INT NOT NULL,
            delegacao_id INT NOT NULL,
            observacao VARCHAR(255) DEFAULT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_competicao_delegacao (competicao_id, delegacao_id),
            FOREIGN KEY (competicao_id) REFERENCES competicoes(id) ON DELETE CASCADE,
            FOREIGN KEY (delegacao_id) REFERENCES delegacoes_visitantes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // Automigração Tabela Inscricoes (Pesagem)
    $column_check_pesagem = $pdo->query("SHOW COLUMNS FROM competicao_inscricoes LIKE 'pesagem_status'")->fetch();
    if (!$column_check_pesagem) {
        $pdo->exec("ALTER TABLE competicao_inscricoes
            ADD COLUMN peso_atleta DECIMAL(10,2) DEFAULT NULL AFTER status_pagamento,
            ADD COLUMN pesagem_status ENUM('pendente', 'aprovado', 'reprovado') DEFAULT 'pendente' AFTER peso_atleta
        ");
    }

    // Automigração: novos campos de contato completo da academia (email, site, whatsapp, país, técnico, financeiro)
    $col_check_deleg = $pdo->query("SHOW COLUMNS FROM delegacoes_visitantes LIKE 'email'")->fetch();
    if (!$col_check_deleg) {
        $pdo->exec("ALTER TABLE delegacoes_visitantes
            ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER unidade_vinculada_id,
            ADD COLUMN site VARCHAR(255) DEFAULT NULL AFTER email,
            ADD COLUMN whatsapp VARCHAR(30) DEFAULT NULL AFTER site,
            ADD COLUMN pais VARCHAR(100) DEFAULT 'Brasil' AFTER estado,
            ADD COLUMN tecnico_nome VARCHAR(255) DEFAULT NULL AFTER responsavel_email,
            ADD COLUMN tecnico_telefone VARCHAR(30) DEFAULT NULL AFTER tecnico_nome,
            ADD COLUMN financeiro_nome VARCHAR(255) DEFAULT NULL AFTER tecnico_telefone,
            ADD COLUMN financeiro_telefone VARCHAR(30) DEFAULT NULL AFTER financeiro_nome
        ");
    }
} catch (Exception $e) {
    // Ignorar erros se já existir
}

$custom_title = "Gerenciar Competição: " . $comp['nome'];
$back_link = "competicoes.php";
$custom_shortcuts = [
    ['label' => 'NOVO OFICIAL', 'link' => 'nova_competicao_oficial.php', 'icon' => 'fa-solid fa-trophy', 'class' => 'btn-sq'],
    ['label' => 'NOVO TORNEIO', 'link' => 'nova_competicao.php', 'icon' => 'fa-solid fa-plus-circle', 'class' => 'btn-sq'],
    ['label' => 'NOVO EXAME', 'link' => 'novo_evento_faixa.php', 'icon' => 'fa-solid fa-medal', 'class' => 'btn-sq-outline']
];
include 'header.php';
?>


<div style="max-width: 1600px; margin: 0 auto; padding: 1.5rem;">
    <h2 class="section-title-sq" style="margin-bottom: 2.5rem;"><?php echo htmlspecialchars($comp['nome']); ?></h2>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <button class="tab-btn active" id="btn-dashboard" onclick="openTab(event, 'dashboard')">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </button>
        <button class="tab-btn" id="btn-info" onclick="openTab(event, 'info')">
            <i class="fa-solid fa-gears"></i> Ajustes Evento
        </button>
        <button class="tab-btn" id="btn-categorias" onclick="openTab(event, 'categorias')">
            <i class="fa-solid fa-list-ul"></i> Categorias e Pesos
        </button>
        <button class="tab-btn" id="btn-inscricoes" onclick="openTab(event, 'inscricoes')">
            <i class="fa-solid fa-users"></i> Inscrições
        </button>
        <button class="tab-btn" id="btn-financeiro" onclick="openTab(event, 'financeiro')">
            <i class="fa-solid fa-money-bill-transfer"></i> Financeiro
        </button>
        <button class="tab-btn" id="btn-pesagem" onclick="openTab(event, 'pesagem')">
            <i class="fa-solid fa-weight-hanging"></i> Pesagem
        </button>
        <button class="tab-btn" id="btn-chaves" onclick="openTab(event, 'chaves')">
            <i class="fa-solid fa-diagram-project"></i> Chaves / Lutas
        </button>
        <button class="tab-btn" id="btn-documentos" onclick="openTab(event, 'documentos')">
            <i class="fa-solid fa-file-pdf"></i> Crachás / Diplomas
        </button>
    </div>


    <?php
    $mensagem = '';
    $erro = '';

    if (($_GET['sucesso'] ?? '') === 'convidada') {
        $mensagem = "Academia cadastrada e convidada com sucesso!";
    }

    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 1. Atualizar Dados da Competição
        if (isset($_POST['salvar_evento'])) {
            try {
                // Processar fotos (se houver)
                $fotos_atuais = json_decode($comp['fotos'] ?? '[]', true);
                if (!is_array($fotos_atuais))
                    $fotos_atuais = [];

                if (isset($_FILES['novas_fotos']) && count($_FILES['novas_fotos']['name']) > 0) {
                    if (!is_dir("../uploads/competicoes/"))
                        mkdir("../uploads/competicoes/", 0777, true);

                    foreach ($_FILES['novas_fotos']['name'] as $k => $name) {
                        if ($_FILES['novas_fotos']['error'][$k] === 0) {
                            $ext = pathinfo($name, PATHINFO_EXTENSION);
                            $novo_nome = "comp_" . $id . "_" . time() . "_" . $k . "." . $ext;
                            if (move_uploaded_file($_FILES['novas_fotos']['tmp_name'][$k], "../uploads/competicoes/" . $novo_nome)) {
                                $fotos_atuais[] = $novo_nome;
                            }
                        }
                    }
                }
                $fotos_json = json_encode($fotos_atuais);

                $sql = "UPDATE competicoes SET 
                        nome = ?, slogan = ?, chamada = ?, descricao = ?,
                        data_evento = ?, data_fim_evento = ?, hora_inicio_evento = ?, hora_fim_evento = ?,
                        data_inicio_inscricoes = ?, data_fim_inscricoes = ?, hora_limite_inscricoes = ?,
                        localizacao = ?, mapa_embed = ?, video_embed = ?, fotos = ?, 
                        regras = ?, lgpd = ?,
                        status = ?, publicar_site = ?
                        WHERE id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['nome'],
                    $_POST['slogan'],
                    $_POST['chamada'],
                    $_POST['descricao'],
                    $_POST['data_evento'],
                    $_POST['data_fim_evento'] ?: null,
                    $_POST['hora_inicio_evento'] ?: null,
                    $_POST['hora_fim_evento'] ?: null,
                    $_POST['data_inicio_inscricoes'] ?: null,
                    $_POST['data_fim_inscricoes'] ?: null,
                    $_POST['hora_limite_inscricoes'] ?: null,
                    $_POST['localizacao'],
                    $_POST['mapa_embed'],
                    $_POST['video_embed'],
                    $fotos_json,
                    $_POST['regras'],
                    $_POST['lgpd'],
                    $_POST['status'],
                    isset($_POST['publicar_site']) ? 1 : 0,
                    $id
                ]);

                $mensagem = "Dados do evento atualizados!";
                $stmt_comp->execute([$id, $unidade_id]);
                $comp = $stmt_comp->fetch();
            } catch (PDOException $e) {
                $erro = $e->getMessage();
            }
        }

        // 2. Gestão de Categorias
        if (isset($_POST['nova_categoria'])) {
            try {
                $stmt = $pdo->prepare("INSERT INTO competicao_categorias (competicao_id, nome, sexo, ano_nascimento_min,
ano_nascimento_max, faixas, peso_max) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $id,
                    $_POST['cat_nome'],
                    $_POST['cat_sexo'],
                    $_POST['ano_min'] ?: null,
                    $_POST['ano_max'] ?: null,
                    $_POST['cat_faixas'],
                    $_POST['cat_peso'] ?: null
                ]);
                $mensagem = "Categoria criada!";
            } catch (PDOException $e) {
                $erro = $e->getMessage();
            }
        }

        if (isset($_POST['remover_categoria']) || (isset($_GET['remover_categoria']) && !empty($_GET['remover_categoria']))) {
            $cat_id_to_del = $_POST['cat_delete_id'] ?? $_GET['remover_categoria'];
            $pdo->prepare("DELETE FROM competicao_categorias WHERE id = ?")->execute([$cat_id_to_del]);
            $mensagem = "Categoria removida!";
        }

        // 3. Gestão de Lotes de Preço
        if (isset($_POST['novo_lote'])) {
            try {
                $lote_id_edit = $_POST['edit_lote_id'] ?: null;
                $valor = str_replace(',', '.', str_replace('.', '', $_POST['lote_valor']));

                if ($lote_id_edit) {
                    $stmt = $pdo->prepare("UPDATE competicao_lotes SET nome = ?, valor = ?, data_limite = ? WHERE id = ?");
                    $stmt->execute([$_POST['lote_nome'], $valor, $_POST['lote_data'], $lote_id_edit]);
                    $mensagem = "Lote de preço atualizado!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO competicao_lotes (competicao_id, nome, valor, data_limite) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id, $_POST['lote_nome'], $valor, $_POST['lote_data']]);
                    $mensagem = "Lote de preço adicionado!";
                }
            } catch (Exception $e) {
                $erro = "Erro no lote: " . $e->getMessage();
            }
        }

        if (isset($_POST['remover_lote'])) {
            try {
                // Verificar se há inscritos com este lote
                $check = $pdo->prepare("SELECT COUNT(*) FROM competicao_inscricoes WHERE lote_id = ?");
                $check->execute([$_POST['lote_delete_id']]);
                if ($check->fetchColumn() > 0) {
                    $erro = "Não é possível remover este lote pois existem atletas inscritos nele!";
                } else {
                    $pdo->prepare("DELETE FROM competicao_lotes WHERE id = ?")->execute([$_POST['lote_delete_id']]);
                    $mensagem = "Lote removido com sucesso!";
                }
            } catch (Exception $e) {
                $erro = "Erro ao remover lote: " . $e->getMessage();
            }
        }

        // 4. Gestão de Patrocínios
        if (isset($_POST['novo_patrocinio'])) {
            $valor = str_replace(',', '.', $_POST['patr_valor']);
            $stmt = $pdo->prepare("INSERT INTO competicao_patrocinios (competicao_id, empresa, valor_cota, contato, status) VALUES
(?, ?, ?, ?, 'fechado')");
            $stmt->execute([$id, $_POST['patr_empresa'], $valor, $_POST['patr_contato']]);
            $mensagem = "Patrocínio registrado!";
        }

        // 5. Gestão Financeira (Lançamentos)
        if (isset($_POST['novo_lancamento'])) {
            $valor = str_replace(',', '.', $_POST['fin_valor']);
            $stmt = $pdo->prepare("INSERT INTO competicao_lancamentos (competicao_id, tipo, descricao, valor, data_lancamento)
VALUES (?, ?, ?, ?, CURDATE())");
            $stmt->execute([$id, $_POST['fin_tipo'], $_POST['fin_desc'], $valor]);
            $mensagem = "Lançamento financeiro realizado!";
        }

        // 6. Inscrever/Editar Aluno
        if (isset($_POST['inscrever_aluno'])) {
            try {
                $insc_id = $_POST['edit_insc_id'] ?: null;
                $aluno_id = $_POST['aluno_id'] ?: null;
                $cat_id = $_POST['categoria_id'] ?: null;
                $lote_id = $_POST['lote_id'] ?: null;
                $resultado = $_POST['resultado'] ?? null;

                // Dados Externos
                $nome_ext = $_POST['nome_ext'] ?? null;
                $equipe_ext = $_POST['equipe_ext'] ?? null;
                $faixa_ext = $_POST['faixa_ext'] ?? null;

                $valor_pago = str_replace(',', '.', str_replace('.', '', $_POST['valor_pago'] ?? '0,00'));
                $status_pagto = $_POST['status_pagamento'] ?? 'pendente';

                if ($insc_id) {
                    // UPDATE
                    $stmt = $pdo->prepare("UPDATE competicao_inscricoes SET aluno_id = ?, categoria_id = ?, lote_id = ?, resultado = ?,
nome_externo = ?, equipe_externa = ?, faixa_externa = ?, valor_pago = ?, status_pagamento = ? WHERE id = ?");
                    $stmt->execute([
                        $aluno_id,
                        $cat_id,
                        $lote_id,
                        $resultado,
                        $nome_ext,
                        $equipe_ext,
                        $faixa_ext,
                        $valor_pago,
                        $status_pagto,
                        $insc_id
                    ]);
                    $mensagem = "Inscrição atualizada com sucesso!";
                } else {
                    // INSERT
                    $stmt = $pdo->prepare("INSERT INTO competicao_inscricoes (competicao_id, aluno_id, categoria_id, lote_id, nome_externo,
equipe_externa, faixa_externa, valor_pago, status_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $aluno_id, $cat_id, $lote_id, $nome_ext, $equipe_ext, $faixa_ext, $valor_pago, $status_pagto]);
                    $mensagem = "Inscrição realizada com sucesso!";
                }
            } catch (PDOException $e) {
                $erro = "Erro ao processar inscrição: " . $e->getMessage();
            }
        }

        if (isset($_POST['remover_inscricao'])) {
            $pdo->prepare("DELETE FROM competicao_inscricoes WHERE id = ?")->execute([$_POST['insc_id']]);
            $mensagem = "Inscrição removida!";
        }

        // 6b. Academias Convidadas
        if (isset($_POST['adicionar_convidado'])) {
            try {
                $delegacao_id = (int) ($_POST['delegacao_id'] ?? 0);
                if (!$delegacao_id) {
                    throw new Exception("Selecione uma academia para convidar.");
                }

                $stmt = $pdo->prepare("INSERT IGNORE INTO competicao_convidados (competicao_id, delegacao_id) VALUES (?, ?)");
                $stmt->execute([$id, $delegacao_id]);
                $mensagem = "Academia convidada com sucesso!";
            } catch (Exception $e) {
                $erro = "Erro ao convidar academia: " . $e->getMessage();
            }
        }

        if (isset($_POST['remover_convidado'])) {
            $pdo->prepare("DELETE FROM competicao_convidados WHERE id = ? AND competicao_id = ?")
                ->execute([$_POST['convidado_id'], $id]);
            $mensagem = "Convite removido!";
        }

        // 7. Gerar Chaves (Brackets)
        if (isset($_POST['gerar_chaves'])) {
            $cat_id = $_POST['categoria_id_sh'];
            try {
                // Limpar chaves anteriores desta categoria
                $pdo->prepare("DELETE FROM competicao_lutas WHERE categoria_id = ?")->execute([$cat_id]);

                // Buscar atletas elegíveis (Pagamento Pago AND Pesagem Aprovada)
                $stmt = $pdo->prepare("SELECT id FROM competicao_inscricoes WHERE categoria_id = ? AND competicao_id = ? AND status_pagamento = 'pago' AND pesagem_status = 'aprovado'");
                $stmt->execute([$cat_id, $id]);
                $inscritos_luta = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $num_atle = count($inscritos_luta);

                if ($num_atle < 2) {
                    $erro = "Não há atletas elegíveis suficientes (Mínimo 2). Elegíveis (Pago+Pesado): $num_atle";
                } else {
                    shuffle($inscritos_luta); // Embaralhar
                    
                    // Calcular estrutura da chave (potência de 2)
                    $pot = 1;
                    while ($pot < $num_atle) $pot *= 2;

                    $lutas_ids = []; // [fase][posicao] => id_luta
    
                    // 1. Criar todas as lutas necessárias
                    // Fase 1 = Final (1 luta)
                    // Fase 2 = Semi (2 lutas)
                    // Fase 4 = Quartas (4 lutas)
                    for ($f = 1; $f <= $pot / 2; $f *= 2) {
                        $num_lutas_fase = $f;
                        for ($p = 0; $p < $num_lutas_fase; $p++) {
                            $stmt = $pdo->prepare("INSERT INTO competicao_lutas (competicao_id, categoria_id, fase, posicao) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$id, $cat_id, $f, $p]);
                            $lutas_ids[$f][$p] = $pdo->lastInsertId();
                        }
                    }

                    // 2. Vincular proxima_luta_id
                    foreach ($lutas_ids as $f => $posicoes) {
                        if ($f == 1) continue; // Final não tem próxima
                        $prox_f = $f / 2;
                        foreach ($posicoes as $p => $luta_id) {
                            $prox_p = floor($p / 2);
                            $prox_id = $lutas_ids[$prox_f][$prox_p];
                            $pdo->prepare("UPDATE competicao_lutas SET proxima_luta_id = ? WHERE id = ?")->execute([$prox_id, $luta_id]);
                        }
                    }

                    // 3. Alocar inscritos na maior fase (Primeira Rodada)
                    $maior_fase = $pot / 2;
                    $num_lutas_maior = count($lutas_ids[$maior_fase]);
                    $atle_index = 0;

                    for ($p = 0; $p < $num_lutas_maior; $p++) {
                        $l_id = $lutas_ids[$maior_fase][$p];
                        $i1 = $inscritos_luta[$atle_index++] ?? null;
                        $i2 = $inscritos_luta[$atle_index++] ?? null;
                        
                        // Se só houver um atleta nesta luta (BYE), ele já avança
                        $vencedor_id = null;
                        $status = 'pendente';
                        if ($i1 && !$i2) {
                            $vencedor_id = $i1;
                            $status = 'finalizada';
                        }
                        
                        $pdo->prepare("UPDATE competicao_lutas SET inscricao1_id = ?, inscricao2_id = ?, vencedor_id = ?, status = ? WHERE id = ?")
                            ->execute([$i1, $i2, $vencedor_id, $status, $l_id]);
                            
                        // Se avançou por WO/BYE, atualizar a próxima luta
                        if ($vencedor_id) {
                            $stmt_prox = $pdo->prepare("SELECT proxima_luta_id, posicao FROM competicao_lutas WHERE id = ?");
                            $stmt_prox->execute([$l_id]);
                            $luta_atual = $stmt_prox->fetch();
                            
                            if ($luta_atual['proxima_luta_id']) {
                                $campo_prox = ($luta_atual['posicao'] % 2 == 0) ? 'inscricao1_id' : 'inscricao2_id';
                                $pdo->prepare("UPDATE competicao_lutas SET $campo_prox = ? WHERE id = ?")
                                    ->execute([$vencedor_id, $luta_atual['proxima_luta_id']]);
                            }
                        }
                    }
                    $mensagem = "Chave olímpica gerada com sucesso para $num_atle atletas!";
                }
            } catch (Exception $e) {
                $erro = "Erro ao gerar chaves: " . $e->getMessage();
            }
        }

        // 8. Definir Vencedor
        if (isset($_POST['definir_vencedor'])) {
            $luta_id = $_POST['luta_id'];
            $vencedor_id = $_POST['vencedor_id'];
            try {
                // Atualizar luta atual
                $stmt = $pdo->prepare("UPDATE competicao_lutas SET vencedor_id = ?, status = 'finalizada' WHERE id = ?");
                $stmt->execute([$vencedor_id, $luta_id]);

                // Avançar para próxima luta
                $stmt = $pdo->prepare("SELECT proxima_luta_id, posicao FROM competicao_lutas WHERE id = ?");
                $stmt->execute([$luta_id]);
                $luta = $stmt->fetch();

                if ($luta['proxima_luta_id']) {
                    $prox_id = $luta['proxima_luta_id'];
                    // Determinar se entra como inscricao1 ou inscricao2 na próxima
                    // Se a posição atual for par, é inscricao1. Se impar, é inscricao2.
                    $campo = ($luta['posicao'] % 2 == 0) ? 'inscricao1_id' : 'inscricao2_id';
                    $pdo->prepare("UPDATE competicao_lutas SET $campo = ? WHERE id = ?")->execute([$vencedor_id, $prox_id]);
                }
                $mensagem = "Vencedor definido e avançado na chave!";
            } catch (Exception $e) {
                $erro = "Erro ao definir vencedor: " . $e->getMessage();
            }
        }

        // 9. Baixa de Pagamento via Financeiro
        if (isset($_POST['baixar_pagamento'])) {
            try {
                $insc_id = $_POST['insc_id_baixa'];
                $stmt = $pdo->prepare("UPDATE competicao_inscricoes SET status_pagamento = 'pago' WHERE id = ?");
                $stmt->execute([$insc_id]);
                $mensagem = "Pagamento baixado com sucesso! Inscrição efetivada.";
            } catch (Exception $e) {
                $erro = "Erro ao baixar pagamento: " . $e->getMessage();
            }
        }
    }

    // Queries de Exibição
    $categorias = $pdo->prepare("SELECT * FROM competicao_categorias WHERE competicao_id = ? ORDER BY nome
            ASC");
    $categorias->execute([$id]);
    $categorias = $categorias->fetchAll();

    $lotes = $pdo->prepare("SELECT * FROM competicao_lotes WHERE competicao_id = ? ORDER BY data_limite ASC");
    $lotes->execute([$id]);
    $lotes = $lotes->fetchAll();

    $patrocinios = $pdo->prepare("SELECT * FROM competicao_patrocinios WHERE competicao_id = ?");
    $patrocinios->execute([$id]);
    $patrocinios = $patrocinios->fetchAll();

    $lancamentos = $pdo->prepare("SELECT * FROM competicao_lancamentos WHERE competicao_id = ? ORDER BY id
            DESC");
    $lancamentos->execute([$id]);
    $lancamentos = $lancamentos->fetchAll();

    $inscritos = $pdo->prepare("
            SELECT ci.*, a.nome_completo as aluno_nome, a.faixa as aluno_faixa, a.data_nascimento, cc.nome as cat_nome, cc.peso_max
            FROM competicao_inscricoes ci
            LEFT JOIN alunos a ON ci.aluno_id = a.id
            LEFT JOIN competicao_categorias cc ON ci.categoria_id = cc.id
            WHERE ci.competicao_id = ?
            ORDER BY ci.id DESC
            ");
    $inscritos->execute([$id]);
    $inscritos = $inscritos->fetchAll();

    $todos_alunos = $pdo->prepare("SELECT id, nome_completo, faixa FROM alunos WHERE unidade_id = ? AND
            status='ativo' ORDER BY nome_completo ASC");
    $todos_alunos->execute([$unidade_id]);
    $todos_alunos = $todos_alunos->fetchAll();

    // Academias Convidadas
    $convidados = $pdo->prepare("SELECT cc.id, cc.observacao, d.id AS delegacao_id, d.nome, d.cidade, d.estado
            FROM competicao_convidados cc
            JOIN delegacoes_visitantes d ON d.id = cc.delegacao_id
            WHERE cc.competicao_id = ?
            ORDER BY d.nome ASC");
    $convidados->execute([$id]);
    $convidados = $convidados->fetchAll();

    $delegacoes_disponiveis = $pdo->prepare("SELECT id, nome FROM delegacoes_visitantes
            WHERE unidade_id = ? AND id NOT IN (SELECT delegacao_id FROM competicao_convidados WHERE competicao_id = ?)
            ORDER BY nome ASC");
    $delegacoes_disponiveis->execute([$unidade_id, $id]);
    $delegacoes_disponiveis = $delegacoes_disponiveis->fetchAll();

    // Totais Financeiros
    $total_receita = 0;
    $total_despesa = 0;
    foreach ($lancamentos as $l) {
        if ($l['tipo'] == 'receita')
            $total_receita += $l['valor'];
        else
            $total_despesa += $l['valor'];
    }
    foreach ($patrocinios as $p) {
        $total_receita += $p['valor_cota'];
    }
    ?>

    <style>
        /* Tabs Styling - Premium Square */
        .nav-tabs-sq {
            display: flex;
            gap: 2px;
            background: #e2e8f0;
            padding: 2px;
            margin-bottom: 30px;
            width: 100%;
            max-width: 100%;
            border-radius: 0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab-btn {
            padding: 12px 20px;
            background: #f1f5f9;
            color: var(--text-muted);
            text-decoration: none;
            font-size: var(--fs-base);
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            flex: 0 0 auto;
        }

        .tab-btn:hover {
            background: #cbd5e1;
            color: #1e293b;
        }

        .tab-btn.active {
            background: #08153a;
            color: var(--primary-green);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 0;
            border: 1px solid var(--border);
            text-align: center;
        }

        .inscrito-row:hover {
            background: #f8fafc;
            cursor: pointer;
        }

        /* Estilos das Chaves */
        .fase-col {
            min-width: 200px;
        }

        .luta-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .luta-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .vencedor-btn {
            background: #f1f5f9;
            border: 1px solid var(--border);
            border-radius: 0;
            cursor: pointer;
            padding: 2px 5px;
            transition: all 0.2s;
        }

        .vencedor-btn:hover {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }

        /* Pesagem Status Buttons */
        .btn-status-pesagem {
            width: 32px;
            height: 32px;
            border-radius: 0;
            border: 1px solid var(--border);
            background: white;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-status-pesagem:hover {
            background: #f1f5f9;
        }

        .btn-status-pesagem.active-ok {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }

        .btn-status-pesagem.active-fail {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
    </style>



    <?php if ($mensagem): ?>
        <div class="alert"
            style="background:#dcfce7; color:#166534; padding:1rem; margin-bottom:1rem; border: 1px solid #bbf7d0; font-weight: 600; border-radius: 0;">
            <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert"
            style="background:#fee2e2; color:#991b1b; padding:1rem; border: 1px solid #fecaca; font-weight: 600; border-radius: 0; margin-bottom:1.5rem;">
            <?php echo $erro; ?>
        </div>
    <?php endif; ?>


    <!-- Conteúdo das Abas -->
    <div style="margin-top: 2rem;">
        <!-- DASHBOARD -->
        <div id="dashboard" class="tab-content">
            <style>
                .dash-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 1rem;
                    margin-bottom: 2rem;
                }

                .dash-card {
                    background: white;
                    padding: 1.5rem;
                    border-radius: 0;
                    border: 1px solid var(--border);
                    box-shadow: var(--shadow-sm);
                }

                .dash-card h4 {
                    color: var(--text-muted);
                    font-size: 0.875rem;
                    margin-bottom: 0.5rem;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .dash-card .value {
                    font-size: 2rem;
                    font-weight: 800;
                    color: var(--text-main);
                }

                .dash-card .footer {
                    margin-top: 0.5rem;
                    font-size: 0.75rem;
                    color: var(--text-muted);
                }
            </style>
            <?php
            $tot_atle = count($inscritos);
            $tot_int = 0;
            $tot_ext = 0;
            foreach ($inscritos as $in) {
                if ($in['aluno_id'])
                    $tot_int++;
                else
                    $tot_ext++;
            }
            $receita_insc = 0;
            foreach ($inscritos as $in)
                if ($in['status_pagamento'] == 'pago')
                    $receita_insc += $in['valor_pago'];
            ?>
            <div class="dash-grid">
                <div class="dash-card">
                    <h4>Atletas Total</h4>
                    <div class="value">
                        <?php echo $tot_atle; ?>
                    </div>
                    <div class="footer">
                        <?php echo $tot_int; ?> Internos /
                        <?php echo $tot_ext; ?>
                        Externos
                    </div>
                </div>
                <div class="dash-card">
                    <h4>Receita Inscrições</h4>
                    <div class="value">R$
                        <?php echo number_format($receita_insc, 2, ',', '.'); ?>
                    </div>
                    <div class="footer">Pagamentos confirmados</div>
                </div>
                <div class="dash-card">
                    <h4>Patrocínios</h4>
                    <div class="value">R$
                        <?php echo number_format($total_receita_patr = array_sum(array_column($patrocinios, 'valor_cota')), 2, ',', '.'); ?>
                    </div>
                    <div class="footer">
                        <?php echo count($patrocinios); ?> parceiros
                    </div>
                </div>
                <div class="dash-card">
                    <h4>Saldo Atual</h4>
                    <div class="value" style="color:var(--success);">R$
                        <?php echo number_format($total_receita - $total_despesa, 2, ',', '.'); ?>
                    </div>
                    <div class="footer">Consolidado Geral</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
                <div class="card">
                    <h3>Próximas Ações</h3><br>
                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        <button onclick="openTab(null, 'inscricoes')" class="btn-sq-light"
                            style="text-align:left; width:100%; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; justify-content: flex-start; border: 1px solid rgba(34, 197, 94, 0.1);">
                            <i class="fa-solid fa-user-plus" style="font-size: 1.25rem; width: 24px; text-align: center;"></i>
                            <span style="font-weight: 700;">Inscrever novo atleta</span>
                        </button>
                        <button onclick="openTab(null, 'financeiro')" class="btn-sq-outline"
                            style="text-align:left; width:100%; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; justify-content: flex-start;">
                            <i class="fa-solid fa-money-bill-transfer" style="font-size: 1.25rem; width: 24px; text-align: center;"></i>
                            <span style="font-weight: 700;">Lançar nova despesa do evento</span>
                        </button>
                        <button onclick="openTab(null, 'categorias')" class="btn-sq-outline"
                            style="text-align:left; width:100%; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; justify-content: flex-start;">
                            <i class="fa-solid fa-tags" style="font-size: 1.25rem; width: 24px; text-align: center;"></i>
                            <span style="font-weight: 700;">Gerenciar categorias e pesos</span>
                        </button>
                    </div>
                </div>
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Inscritos Recentes</h3>
                    <div style="overflow-x: auto;">
                        <table style="width:100%; border-collapse: collapse;">
                            <thead>
                                <tr style="text-align: left; border-bottom: 2px solid var(--border);">
                                    <th
                                        style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">
                                        Atleta</th>
                                    <th
                                        style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">
                                        Data</th>
                                    <th
                                        style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; text-align: right;">
                                        Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inscritos)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; padding:2rem; color:var(--text-muted);">
                                            Nenhum
                                            atleta inscrito ainda.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php for ($i = 0; $i < min(5, count($inscritos)); $i++):
                                        $at = $inscritos[$i];
                                        $at_n = $at['aluno_id'] ? $at['aluno_nome'] : $at['nome_externo'];
                                        ?>
                                        <tr style="border-bottom: 1px solid var(--border);">
                                            <td style="padding: 1rem 0.5rem;">
                                                <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);">
                                                    <?php echo htmlspecialchars($at_n); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 1rem 0.5rem; font-size: 0.8rem; color: var(--text-muted);">
                                                <?php echo date('d/m/Y', strtotime($at['criado_em'] ?? 'now')); ?>
                                            </td>
                                            <td style="padding: 1rem 0.5rem; text-align: right;">
                                                <span
                                                    class="badge <?php echo $at['status_pagamento'] == 'pago' ? 'badge-ativo' : 'badge-inativo'; ?>"
                                                    style="font-size: 0.65rem; font-weight: 700;">
                                                    <?php echo strtoupper($at['status_pagamento']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- INFO -->
        <div id="info" class="tab-content" style="display:none;">
            <div class="card"
                style="border: 1px solid var(--border); padding: 2rem; padding-bottom: 6rem; border-radius: 0; box-shadow: var(--shadow); position: relative;">

                <!-- Header da Seção -->
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; border-bottom: 1px solid var(--border); padding-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div
                            style="width: 45px; height: 45px; background: rgba(var(--primary-rgb), 0.1); border-radius: 0; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-gears" style="color: var(--primary); font-size: 1.25rem;"></i>
                        </div>
                        <div>
                            <h2 class="section-title-sq" style="margin:0; font-size: 1.25rem;">Ajustes Completos do Evento</h2>
                            <p style="margin:0; color: var(--text-muted); font-size: 0.85rem;">Gerencie a
                                identidade, prazos e regulamentos.</p>
                        </div>
                    </div>
                </div>

                <form id="form-ajustes-evento" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="salvar_evento" value="1">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem;">

                        <!-- Coluna Esquerda: Identidade e Mídia -->
                        <div style="display: flex; flex-direction: column; gap: 2rem;">

                            <!-- Bloco 1: Identidade -->
                            <section>
                                <h4
                                    style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; color: var(--primary); font-size: 0.9rem; text-transform: uppercase; font-weight: 800;">
                                    <i class="fa-solid fa-flag"></i> Identidade Visual
                                </h4>

                                <div
                                    style="background: #f8fafc; border: 1px solid var(--border); padding: 1.25rem; border-radius: 0; display: flex; flex-direction: column; gap: 1.25rem;">
                                    <div class="form-group">
                                        <label
                                            style="font-size:0.7rem; color:var(--text); text-transform:uppercase; font-weight: 800; margin-bottom: 0.4rem; display: block;">Título
                                            Oficial</label>
                                        <input type="text" name="nome" class="form-control"
                                            value="<?php echo htmlspecialchars($comp['nome'] ?? ''); ?>" required
                                            style="background: #fff; border-color: var(--border); font-weight: 700; height: 45px;">
                                    </div>

                                    <div class="form-group">
                                        <label
                                            style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800; margin-bottom: 0.4rem; display: block;">Slogan
                                            / Tagline</label>
                                        <input type="text" name="slogan" class="form-control"
                                            value="<?php echo htmlspecialchars($comp['slogan'] ?? ''); ?>"
                                            placeholder="Frase de efeito"
                                            style="background: #fff; border-color: var(--border);">
                                    </div>

                                    <div class="form-group">
                                        <label
                                            style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800; margin-bottom: 0.4rem; display: block;">Chamada
                                            Principal (Headline)</label>
                                        <input type="text" name="chamada" class="form-control"
                                            value="<?php echo htmlspecialchars($comp['chamada'] ?? ''); ?>"
                                            placeholder="Frase de destaque para marketing"
                                            style="background: #fff; border-color: var(--border);">
                                    </div>

                                    <div class="form-group">
                                        <label
                                            style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800; margin-bottom: 0.4rem; display: block;">Descrição
                                            Detalhada</label>
                                        <textarea name="descricao" class="form-control" rows="5"
                                            style="background: #fff; border-color: var(--border); resize: vertical;"><?php echo htmlspecialchars($comp['descricao'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </section>

                            <!-- Bloco 2: Mídias -->
                            <section>
                                <h4
                                    style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; color: #7c3aed; font-size: 0.9rem; text-transform: uppercase; font-weight: 800;">
                                    <i class="fa-solid fa-share-nodes"></i> Localização e Mídias
                                </h4>

                                <div
                                    style="background: #f8fafc; border: 1px solid var(--border); padding: 1.25rem; border-radius: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label
                                            style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800; margin-bottom: 0.4rem; display: block;">Mapa
                                            (Iframe Google)</label>
                                        <textarea name="mapa_embed" class="form-control" rows="3"
                                            placeholder="Cole o <iframe> aqui"
                                            style="background: #fff; border-color: var(--border); font-family: monospace; font-size: 0.75rem;"><?php echo htmlspecialchars($comp['mapa_embed'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label
                                            style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800; margin-bottom: 0.4rem; display: block;">Vídeo
                                            (Iframe Teaser)</label>
                                        <textarea name="video_embed" class="form-control" rows="3"
                                            placeholder="Cole o <iframe> aqui"
                                            style="background: #fff; border-color: var(--border); font-family: monospace; font-size: 0.75rem;"><?php echo htmlspecialchars($comp['video_embed'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </section>

                        </div>

                        <!-- Coluna Direita: Cronograma e Fotos -->
                        <div style="display: flex; flex-direction: column; gap: 2rem;">

                            <!-- Bloco 3: Cronograma -->
                            <section>
                                <h4
                                    style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; color: #d97706; font-size: 0.9rem; text-transform: uppercase; font-weight: 800;">
                                    <i class="fa-solid fa-calendar-days"></i> Cronograma do Evento
                                </h4>

                                <div
                                    style="background: #f8fafc; border: 1px solid var(--border); padding: 1.25rem; border-radius: 0; display: flex; flex-direction: column; gap: 1rem;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                        <div class="form-group">
                                            <label
                                                style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800;">Início
                                                Evento</label>
                                            <input type="date" name="data_evento" class="form-control"
                                                value="<?php echo $comp['data_evento'] ?? ''; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label
                                                style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800;">Fim
                                                Evento</label>
                                            <input type="date" name="data_fim_evento" class="form-control"
                                                value="<?php echo $comp['data_fim_evento'] ?? ''; ?>">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                        <div class="form-group">
                                            <label
                                                style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800;">Hora
                                                Início</label>
                                            <input type="time" name="hora_inicio_evento" class="form-control"
                                                value="<?php echo $comp['hora_inicio_evento'] ?? ''; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label
                                                style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800;">Hora
                                                Fim (Est.)</label>
                                            <input type="time" name="hora_fim_evento" class="form-control"
                                                value="<?php echo $comp['hora_fim_evento'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>

                                <h5
                                    style="margin: 1.5rem 0 1rem; color: var(--danger); font-size: 0.75rem; text-transform: uppercase; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fa-solid fa-clock-rotate-left"></i> Prazos de Inscrição
                                </h5>

                                <div
                                    style="background: #fff5f5; border: 1px dashed #feb2b2; padding: 1.25rem; border-radius: 0; display: flex; flex-direction: column; gap: 1rem;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                        <div class="form-group">
                                            <label
                                                style="font-size:0.65rem; color:#c53030; text-transform:uppercase; font-weight: 800;">Início
                                                das Inscrições</label>
                                            <input type="date" name="data_inicio_inscricoes" class="form-control"
                                                value="<?php echo $comp['data_inicio_inscricoes'] ?? ''; ?>"
                                                style="border-color: #feb2b2;">
                                        </div>
                                        <div class="form-group">
                                            <label
                                                style="font-size:0.65rem; color:#c53030; text-transform:uppercase; font-weight: 800;">Data
                                                Limite</label>
                                            <input type="date" name="data_fim_inscricoes" class="form-control"
                                                value="<?php echo $comp['data_fim_inscricoes'] ?? ''; ?>"
                                                style="border-color: #feb2b2;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label
                                            style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800;">Hora
                                            Limite de Encerramento (Último dia)</label>
                                        <input type="time" name="hora_limite_inscricoes" class="form-control"
                                            value="<?php echo $comp['hora_limite_inscricoes'] ?? ''; ?>">
                                    </div>
                                </div>
                            </section>

                            <!-- Bloco 4: Fotos -->
                            <section>
                                <h4
                                    style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; color: var(--success); font-size: 0.9rem; text-transform: uppercase; font-weight: 800;">
                                    <i class="fa-solid fa-image"></i> Galeria e Exposição
                                </h4>

                                <div id="drop-area"
                                    style="border: 2px dashed var(--border); border-radius: 0; padding: 2.5rem; text-align: center; background: #fafafa; transition: all 0.2s ease; cursor: pointer;">
                                    <i class="fa-solid fa-cloud-arrow-up"
                                        style="font-size: 1.75rem; color: var(--text-muted); margin-bottom: 0.5rem; display: block;"></i>
                                    <p
                                        style="margin: 0; font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
                                        Arraste fotos aqui ou clique para selecionar</p>
                                    <input type="file" id="fileElem" name="novas_fotos[]" multiple accept="image/*"
                                        style="display:none" onchange="handleFiles(this.files)">

                                    <div id="gallery"
                                        style="display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 0.75rem; margin-top: 1.5rem;">
                                        <?php
                                        $fotos = json_decode($comp['fotos'] ?? '[]', true);
                                        if (is_array($fotos)):
                                            foreach ($fotos as $f):
                                                ?>
                                                <div
                                                    style="position: relative; aspect-ratio: 1; border-radius: 0; overflow: hidden; border: 2px solid var(--border);">
                                                    <img src="../uploads/competicoes/<?php echo $f; ?>"
                                                        style="width: 100%; height: 100%; object-fit: cover;">
                                                </div>
                                            <?php endforeach; endif; ?>
                                    </div>
                                </div>
                            </section>

                            <!-- Bloco 5: Status e Local -->
                            <section
                                style="background: #f1f5f9; padding: 1.25rem; border-radius: 0; border: 1px solid var(--border);">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label
                                            style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800;">Localização
                                            Técnica (Link/Nome)</label>
                                        <input type="text" name="localizacao" class="form-control"
                                            value="<?php echo htmlspecialchars($comp['localizacao'] ?? ''); ?>"
                                            placeholder="Ex: Ginásio Municipal">
                                    </div>
                                    <div class="form-group">
                                        <label
                                            style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800;">Status
                                            do Evento</label>
                                        <select name="status" class="form-control" style="font-weight: 700;">
                                            <option value="aberto" <?php echo ($comp['status'] ?? '') == 'aberto' ? 'selected' : ''; ?>>Ativo</option>
                                            <option value="finalizado" <?php echo ($comp['status'] ?? '') == 'finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                                            <option value="cancelado" <?php echo ($comp['status'] ?? '') == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label
                                        style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: var(--text);">
                                        <input type="checkbox" name="publicar_site" value="1"
                                            style="width: 18px; height: 18px; cursor: pointer;"
                                            <?php echo (($comp['publicar_site'] ?? 1) == 1) ? 'checked' : ''; ?>>
                                        Exibir este evento em shiaipro.com.br/eventos
                                    </label>
                                    <span
                                        style="font-size: 0.7rem; color: var(--text-muted); margin-left: 1.9rem; display: block;">
                                        Desmarque para manter o evento visível apenas internamente, sem publicá-lo na
                                        listagem pública do site.
                                    </span>
                                </div>
                            </section>

                            <!-- Bloco 6: Regras e LGPD -->
                            <section>
                                <h4
                                    style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; color: var(--danger); font-size: 0.9rem; text-transform: uppercase; font-weight: 800;">
                                    <i class="fa-solid fa-scale-balanced"></i> Regras e LGPD
                                </h4>
                                <div
                                    style="background: #f8fafc; border: 1px solid var(--border); padding: 1.25rem; border-radius: 0; display: flex; flex-direction: column; gap: 1.25rem;">
                                    <div class="form-group">
                                        <label
                                            style="font-size:0.7rem; color:var(--text); text-transform:uppercase; font-weight: 800; margin-bottom: 0.4rem; display: block;">Regras
                                            e Disposições Gerais</label>
                                        <textarea name="regras" class="form-control" rows="10"
                                            placeholder="Descreva as regras, premiações e regulamento do evento..."
                                            style="background: #fff; border-color: var(--border); resize: vertical;"><?php echo htmlspecialchars($comp['regras'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label
                                            style="font-size:0.7rem; color:var(--text); text-transform:uppercase; font-weight: 800; margin-bottom: 0.4rem; display: block;">Termos
                                            de LGPD e Privacidade</label>
                                        <textarea name="lgpd" class="form-control" rows="5"
                                            placeholder="Informações sobre uso de dados e imagem..."
                                            style="background: #fff; border-color: var(--border); resize: vertical;"><?php echo htmlspecialchars($comp['lgpd'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </section>
                            <!-- Categorias movidas para aba própria -->
                        </div> <!-- Fim Coluna Direita -->
                    </div> <!-- Fim Grid 2 Colunas -->



                </form>

                <!-- Bloco: Academias Convidadas -->
                <section style="margin-top: 2rem;">
                    <h4
                        style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; color: var(--danger); font-size: 0.9rem; text-transform: uppercase; font-weight: 800;">
                        <i class="fa-solid fa-people-group"></i> Academias Convidadas
                    </h4>
                    <p style="color: var(--text-muted); font-size: 0.8rem; margin: -1rem 0 1.25rem 0;">
                        Cadastre as academias autorizadas a inscrever seus alunos neste evento. A lista é reaproveitada
                        do seu cadastro de <a href="delegacoes.php" target="_blank">Delegações Visitantes</a>.
                    </p>
                    <div
                        style="background: #f8fafc; border: 1px solid var(--border); padding: 1.25rem; border-radius: 0;">
                        <form method="POST" action="editar_competicao.php?id=<?php echo $id; ?>&tab=info"
                            style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 1rem; margin-bottom: 1.5rem;">
                            <div class="form-group" style="flex: 1; min-width: 220px;">
                                <label
                                    style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; font-weight: 800;">Academia</label>
                                <select name="delegacao_id" id="convidado_delegacao_id" class="form-control">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($delegacoes_disponiveis as $d): ?>
                                        <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="adicionar_convidado" value="1" class="btn-sq"
                                style="width: auto; padding: 0.85rem 1.75rem; font-weight: 800;">
                                <i class="fa-solid fa-plus"></i> Convidar
                            </button>
                            <a href="nova_delegacao.php?evento_id=<?php echo $id; ?>" class="btn-sq-light"
                                style="width: auto; padding: 0.85rem 1.75rem; font-weight: 800; white-space: nowrap;">
                                <i class="fa-solid fa-circle-plus"></i> Cadastrar Nova Academia
                            </a>
                        </form>

                        <?php if (empty($convidados)): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                Nenhuma academia convidada até o momento.
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width:100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="text-align:left; border-bottom: 1px solid var(--border);">
                                            <th style="padding: 10px; font-size: var(--fs-xs); color: var(--text-muted); text-transform: uppercase;">Academia</th>
                                            <th style="padding: 10px; font-size: var(--fs-xs); color: var(--text-muted); text-transform: uppercase;">Cidade</th>
                                            <th style="padding: 10px; font-size: var(--fs-xs); color: var(--text-muted); text-transform: uppercase; text-align:right;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($convidados as $c): ?>
                                            <tr style="border-bottom: 1px solid var(--border);">
                                                <td style="padding: 10px; font-weight: 700;"><?php echo htmlspecialchars($c['nome']); ?></td>
                                                <td style="padding: 10px;"><?php echo htmlspecialchars(trim(($c['cidade'] ?: '') . ($c['estado'] ? ' - ' . $c['estado'] : '')) ?: '—'); ?></td>
                                                <td style="padding: 10px; text-align:right; white-space: nowrap;">
                                                    <a href="editar_delegacao.php?id=<?php echo (int) $c['delegacao_id']; ?>" class="btn-sq-light"
                                                        style="width:auto; padding: 6px 12px; font-size: var(--fs-xs); display: inline-block;">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </a>
                                                    <form method="POST" action="editar_competicao.php?id=<?php echo $id; ?>&tab=info"
                                                        style="display:inline;"
                                                        onsubmit="return confirm('Remover o convite desta academia?');">
                                                        <input type="hidden" name="convidado_id" value="<?php echo (int) $c['id']; ?>">
                                                        <button type="submit" name="remover_convidado" value="1" class="btn-sq-light"
                                                            style="width:auto; padding: 6px 12px; font-size: var(--fs-xs);">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Footer do Form -->
                <div
                    style="position: sticky; bottom: 0; background: rgba(255,255,255,0.98); backdrop-filter: blur(10px); padding: 1.25rem 2rem; margin-top: 1.5rem; display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 1rem 1.5rem; z-index: 100; border-radius: 0; border-top: 1px solid var(--border);">
                    <p
                        style="color: var(--text-muted); font-size: 0.8rem; margin: 0 auto 0 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-circle-info" style="color: #08153a;"></i>
                        Verifique as informações antes de salvar o evento.
                    </p>
                    <button type="submit" form="form-ajustes-evento" class="btn-sq"
                        style="padding: 0.85rem 3rem; font-weight: 800; font-size: 1rem; border-radius: 0; box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3); display: flex; align-items: center; justify-content: center; gap: 0.75rem; white-space: nowrap; flex: 0 0 auto;">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        ATUALIZAR COMPETIÇÃO
                    </button>
                </div>
            </div>
        </div>

        <!-- INSCRICOES -->
        <div id="inscricoes" class="tab-content" style="display:none;">
            <div style="display:grid; grid-template-columns: 1fr 340px; gap:1.5rem;">
                <div class="card" style="padding:0; overflow:hidden;">
                    <div style="padding:1.5rem; border-bottom:1px solid var(--border);">
                        <h3 style="margin:0;">Atletas Inscritos</h3>
                    </div>
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr
                                style="text-align:left; background:rgba(255,255,255,0.02); border-bottom:1px solid var(--border);">
                                <th
                                    style="padding:1rem; text-transform:uppercase; font-size:0.7rem; color:var(--text-muted);">
                                    Atleta</th>
                                <th
                                    style="padding:1rem; text-transform:uppercase; font-size:0.7rem; color:var(--text-muted);">
                                    Categoria</th>
                                <th
                                    style="padding:1rem; text-transform:uppercase; font-size:0.7rem; color:var(--text-muted);">
                                    Pódio</th>
                                <th
                                    style="padding:1rem; text-transform:uppercase; font-size:0.7rem; color:var(--text-muted);">
                                    Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inscritos)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding:3rem; color:var(--text-muted);">
                                        Nenhum
                                        atleta inscrito até o momento.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($inscritos as $i):
                                $nome = $i['aluno_id'] ? $i['aluno_nome'] : $i['nome_externo'];
                                $is_interno = (bool) $i['aluno_id'];
                                $info = $is_interno ? "Interno (" . $i['aluno_faixa'] . ")" : "Externo (" . $i['equipe_externa'] . " - " . $i['faixa_externa'] . ")";
                                ?>
                                <tr style="border-bottom:1px solid var(--border); transition:background 0.2s;"
                                    onmouseover="this.style.background='rgba(235,0,0,0.03)'"
                                    onmouseout="this.style.background='transparent'">
                                    <td style="padding:1.25rem 1rem;">
                                        <div style="font-weight:700; color:var(--text);">
                                            <?php echo htmlspecialchars($nome); ?>
                                        </div>
                                        <div
                                            style="font-size:0.75rem; color:<?php echo $is_interno ? 'var(--success)' : 'var(--text-muted)'; ?>; font-weight:600;">
                                            <?php echo $info; ?>
                                        </div>
                                    </td>
                                    <td style="padding:1rem;">
                                        <div style="font-weight:600; font-size:0.9rem;">
                                            <?php echo htmlspecialchars($i['cat_nome'] ?: 'Livre / Absoluto'); ?>
                                        </div>
                                    </td>
                                    <td style="padding:1rem;">
                                        <?php if ($i['resultado']): ?>
                                            <span class="badge" style="background:var(--primary); color:#fff; font-weight:800;">
                                                <?php echo $i['resultado']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.8rem;">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:1rem;">
                                        <div style="display:flex; gap:0.5rem;">
                                            <button onclick='editInscrito(<?php echo json_encode($i); ?>)'
                                                class="btn btn-secondary"
                                                style="padding:0.4rem 0.8rem; font-size:0.7rem; border-color:var(--border);">
                                                <i class="fa-solid fa-pen"></i> EDITAR
                                            </button>
                                            <form method="POST">
                                                <input type="hidden" name="insc_id" value="<?php echo $i['id']; ?>">
                                                <button type="submit" name="remover_inscricao" class="btn"
                                                    style="background:rgba(239, 68, 68, 0.1); color:var(--danger); padding:0.4rem 0.8rem; border:none; border-radius: 0; font-weight:700;">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <h3 id="form_insc_title">Gerenciar Inscrição</h3><br>
                    <form method="POST" id="form_inscricao"
                        style="background:rgba(255,255,255,0.01); padding:1.5rem; border-radius: 0; border:1px solid var(--border);">
                        <input type="hidden" name="inscrever_aluno" value="1">
                        <input type="hidden" name="edit_insc_id" id="edit_insc_id" value="">

                        <div class="form-group">
                            <label
                                style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em;">Origem
                                do Atleta</label>
                            <select id="atleta_origem" class="form-control" onchange="toggleAtletaType(this.value)"
                                style="background:#fff; border:1px solid var(--border); font-weight:600;">
                                <option value="interno">Aluno da Unidade (Interno)</option>
                                <option value="externo">Competidor Visitante (Externo)</option>
                            </select>
                        </div>

                        <!-- Modo Interno -->
                        <div id="box_interno" class="form-group">
                            <label
                                style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">Selecionar
                                Aluno</label>
                            <select name="aluno_id" id="field_aluno_id" class="form-control"
                                style="background:#fff; border:1px solid var(--border); font-weight:600;">
                                <option value="">Escolher Atleta...</option>
                                <?php foreach ($todos_alunos as $al): ?>
                                    <option value="<?php echo $al['id']; ?>">
                                        <?php echo htmlspecialchars($al['nome_completo']); ?>
                                        (
                                        <?php echo $al['faixa']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Modo Externo -->
                        <div id="box_externo" style="display:none;">
                            <div class="form-group"><label
                                    style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">Nome
                                    Completo</label><input type="text" name="nome_ext" id="field_nome_ext"
                                    class="form-control" placeholder="Nome do Visitante"
                                    style="background:#fff; border:1px solid var(--border);">
                            </div>
                            <div class="form-group"><label
                                    style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">Equipe/Academia</label><input
                                    type="text" name="equipe_ext" id="field_equipe_ext" class="form-control"
                                    list="lista_convidados" placeholder="Ex: Gracie Barra"
                                    style="background:#fff; border:1px solid var(--border);">
                                <datalist id="lista_convidados">
                                    <?php foreach ($convidados as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['nome']); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <?php if (!empty($convidados)): ?>
                                    <span style="font-size: 0.65rem; color: var(--text-muted); display: block; margin-top: 4px;">
                                        Sugestões da lista de <a href="?id=<?php echo $id; ?>&tab=info" style="color: var(--primary-green);">Academias Convidadas</a>.
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="form-group"><label
                                    style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">Faixa</label><input
                                    type="text" name="faixa_ext" id="field_faixa_ext" class="form-control"
                                    placeholder="Ex: Azul" style="background:#fff; border:1px solid var(--border);">
                            </div>
                        </div>

                        <div class="form-group">
                            <label
                                style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">Categoria
                                de Disputa</label>
                            <select name="categoria_id" id="field_cat_id" class="form-control"
                                style="background:#fff; border:1px solid var(--border); font-weight:600;">
                                <option value="">Livre / Sem Categoria</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">Lote /
                                Pagamento</label>
                            <select name="lote_id" id="field_lote_id" class="form-control"
                                onchange="updateValorPorLote(this.value)"
                                style="background:#fff; border:1px solid var(--border); font-weight:600;">
                                <option value="">Manual / Outro</option>
                                <?php foreach ($lotes as $lt): ?>
                                    <option value="<?php echo $lt['id']; ?>"
                                        data-valor="<?php echo number_format($lt['valor'], 2, ',', ''); ?>">
                                        <?php echo htmlspecialchars($lt['nome']); ?> (R$
                                        <?php echo number_format($lt['valor'], 2, ',', '.'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                            <div class="form-group">
                                <label
                                    style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">Valor
                                    Final</label>
                                <input type="text" name="valor_pago" id="field_valor_pago" class="form-control"
                                    placeholder="0,00"
                                    style="background:#fff; border:1px solid var(--border); font-weight:700;">
                            </div>
                            <div class="form-group">
                                <label
                                    style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">Status</label>
                                <select name="status_pagamento" id="field_status_pagto" class="form-control"
                                    style="background:#fff; border:1px solid var(--border); font-weight:700;">
                                    <option value="pendente">Aguardando</option>
                                    <option value="pago">Confirmado</option>
                                </select>
                            </div>
                        </div>

                        <div id="box_resultado" class="form-group" style="display:none;">
                            <label style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">Pódio
                                /
                                Resultado</label>
                            <input type="text" name="resultado" id="field_resultado" class="form-control"
                                placeholder="Ex: Campeão / Ouro"
                                style="background:#fff; border:1px solid var(--border);">
                        </div>

                        <button type="submit" id="btn_save_insc" class="btn btn-primary"
                            style="width:100%; padding:1rem;">Confirmar Inscrição</button>
                        <button type="button" id="btn_cancel_edit" class="btn btn-secondary"
                            style="width:100%; margin-top:0.75rem; display:none; padding:1rem;"
                            onclick="cancelInscEdit()">Cancelar Edição</button>
                    </form>
                </div>
            </div>
        </div>


        <!-- CHAVES -->
        <div id="chaves" class="tab-content" style="display:none;">
            <div class="card" style="margin-bottom: 2.5rem; border-left:4px solid var(--primary);">
                <h3 style="margin-bottom:1.5rem;">Gerenciamento de Confrontos</h3>
                <div
                    style="display:flex; gap:1.25rem; align-items:flex-end; background:rgba(255,255,255,0.01); padding:1.5rem; border-radius: 0; border:1px solid var(--border);">
                    <div class="form-group" style="margin-bottom:0; flex:1;">
                        <label
                            style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Filtrar
                            Categoria</label>
                        <select id="select_cat_chaves" class="form-control" onchange="loadChave(this.value)"
                            style="background:#fff; height:3rem; border:1px solid var(--border); font-weight: 700;">
                            <option value="">-- Selecione para Visualizar --</option>
                            <?php foreach ($categorias as $ct): ?>
                                <option value="<?php echo $ct['id']; ?>">
                                    <?php echo htmlspecialchars($ct['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <form method="POST">
                            <input type="hidden" name="categoria_id_sh" id="form_cat_id_sh">
                            <button type="submit" name="gerar_chaves" class="btn btn-primary"
                                style="height:3rem; padding:0 2rem; font-weight:800;"
                                onclick="confirmGerar(event)">SORTEAR
                                CHAVE ÓLIMPICA</button>
                        </form>
                        <button onclick="imprimirChave()" class="btn btn-secondary"
                            style="height:3rem; font-weight:800; border-color:var(--border);">
                            <i class="fa-solid fa-print"></i> GERAR PDF
                        </button>
                    </div>
                </div>
            </div>

            <div id="canvas_chaves"
                style="overflow-x:auto; padding:3rem; background:rgba(255,255,255,0.01); border:1px dashed var(--border); border-radius: 0; min-height:500px; display:flex; gap:4rem;">
                <!-- Chave será carregada via JS/PHP aqui -->
                <div style="text-align:center; width:100%; color:var(--text-muted); padding-top:8rem;">
                    <div style="font-size:3rem; margin-bottom:1.5rem; opacity:0.1;"><i class="fa-solid fa-trophy"></i></div>
                    <p style="font-size:0.9rem; font-weight:600; letter-spacing:0.05em;">SELECIONE UMA CATEGORIA
                        PARA
                        CARREGAR OS CONFRONTOS</p>
                </div>
            </div>
        </div>

        <!-- FINANCEIRO -->
        <div id="financeiro" class="tab-content" style="display:none;">
            <!-- Row 1: Stats -->
            <div
                style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
                <div class="stat-card"
                    style=" display: flex; flex-direction: column; align-items: flex-start; padding: 1.5rem;">
                    <span
                        style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Receitas
                        Totais</span>
                    <p style="font-size:1.75rem; font-weight:800; color: var(--success); margin: 0;">R$
                        <?php echo number_format($total_receita, 2, ',', '.'); ?>
                    </p>
                    <span style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem;">Inscrições +
                        Patrocínios + Extras</span>
                </div>
                <div class="stat-card"
                    style=" display: flex; flex-direction: column; align-items: flex-start; padding: 1.5rem;">
                    <span
                        style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Despesas
                        Totais</span>
                    <p style="font-size:1.75rem; font-weight:800; color: var(--danger); margin: 0;">R$
                        <?php echo number_format($total_despesa, 2, ',', '.'); ?>
                    </p>
                    <span style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem;">Custos
                        operacionais registrados</span>
                </div>
                <div class="stat-card"
                    style=" display: flex; flex-direction: column; align-items: flex-start; padding: 1.5rem;">
                    <span
                        style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Saldo
                        do Evento</span>
                    <p style="font-size:1.75rem; font-weight:800; color: var(--primary); margin: 0;">R$
                        <?php echo number_format($total_receita - $total_despesa, 2, ',', '.'); ?>
                    </p>
                    <span style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem;">Balanço
                        consolidado atual</span>
                </div>
            </div>

            <!-- Row 2: Lotes and Sponsorships -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom: 1.5rem;">
                <!-- LOTES -->
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div
                        style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 1rem;">Tabela de Preços (Lotes)</h3>
                    </div>
                    <div style="padding: 1.5rem;">
                        <form method="POST" id="form_lote"
                            style="background: #fff; padding: 1.25rem; border-radius: 0; margin-bottom: 1.5rem; border: 1px solid var(--border);">
                            <input type="hidden" name="novo_lote" value="1">
                            <input type="hidden" name="edit_lote_id" id="edit_lote_id" value="">
                            <h4 id="lote_form_title"
                                style="margin-top: 0; margin-bottom: 1rem; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">
                                Novo Lote de Inscrição</h4>
                            <div
                                style="display:grid; grid-template-columns: 1fr 100px; gap: 0.75rem; margin-bottom: 0.75rem;">
                                <input type="text" name="lote_nome" id="lote_field_nome" class="form-control"
                                    placeholder="Nome do Lote" required>
                                <input type="text" name="lote_valor" id="lote_field_valor" class="form-control"
                                    placeholder="R$ 0,00" required>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr auto; gap: 0.75rem;">
                                <input type="date" name="lote_data" id="lote_field_data" class="form-control">
                                <button type="submit" id="btn_lote_save" class="btn btn-primary"
                                    style="padding: 0 1.5rem;">ADICIONAR</button>
                            </div>
                            <button type="button" id="btn_cancel_lote" class="btn btn-secondary"
                                style="display:none; margin-top:0.75rem; width:100%;"
                                onclick="cancelLoteEdit()">Cancelar
                                Edição</button>
                        </form>

                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr
                                    style="text-align:left; color:var(--text-muted); font-size:0.7rem; text-transform:uppercase; border-bottom: 1px solid var(--border);">
                                    <th style="padding: 0.75rem 0;">Lote</th>
                                    <th style="padding: 0.75rem 0;">Valor</th>
                                    <th style="padding: 0.75rem 0; text-align: right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lotes)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; padding:2rem; color:var(--text-muted);">
                                            Nenhum
                                            lote configurado.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($lotes as $l): ?>
                                    <tr style="border-bottom:1px solid var(--border);">
                                        <td style="padding:0.75rem 0; font-weight:600; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($l['nome']); ?>
                                            <div style="font-size: 0.7rem; color: var(--text-muted);">Até
                                                <?php echo date('d/m/Y', strtotime($l['data_limite'])); ?>
                                            </div>
                                        </td>
                                        <td style="padding:0.75rem 0; color:var(--primary); font-weight:700;">R$
                                            <?php echo number_format($l['valor'], 2, ',', '.'); ?>
                                        </td>
                                        <td style="padding:0.75rem 0; text-align: right;">
                                            <div style="display:flex; gap:0.4rem; justify-content: flex-end;">
                                                <button type="button" onclick='editLote(<?php echo json_encode($l); ?>)'
                                                    class="btn btn-secondary"
                                                    style="padding:0.3rem 0.6rem; font-size:0.65rem;">EDITAR</button>
                                                <form method="POST" style="display:inline;"
                                                    onsubmit="return confirm('Excluir este lote?')">
                                                    <input type="hidden" name="lote_delete_id"
                                                        value="<?php echo $l['id']; ?>">
                                                    <button type="submit" name="remover_lote" class="btn"
                                                        style="background:rgba(239, 68, 68, 0.1); color:var(--danger); padding:0.3rem 0.6rem; font-size:0.65rem; border:none; border-radius: 0; font-weight: 700;">REMOVER</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PATROCINIOS -->
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div
                        style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 1rem;">Patrocínios e Parcerias</h3>
                    </div>
                    <div style="padding: 1.5rem;">
                        <form method="POST"
                            style="background: #fff; padding: 1.25rem; border-radius: 0; margin-bottom: 1.5rem; border: 1px solid var(--border);">
                            <input type="hidden" name="novo_patrocinio" value="1">
                            <h4
                                style="margin-top: 0; margin-bottom: 1rem; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">
                                Registrar Novo Patrocínio</h4>
                            <div class="form-group" style="margin-bottom: 0.75rem;">
                                <input type="text" name="patr_empresa" class="form-control"
                                    placeholder="Empresa Parceira" required>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 120px auto; gap: 0.75rem;">
                                <input type="text" name="patr_valor" class="form-control" placeholder="Valor R$"
                                    required>
                                <input type="text" name="patr_contato" class="form-control" placeholder="Contato">
                                <button type="submit" class="btn btn-primary" style="padding: 0 1.5rem;">SALVAR</button>
                            </div>
                        </form>

                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr
                                    style="text-align:left; color:var(--text-muted); font-size:0.7rem; text-transform:uppercase; border-bottom: 1px solid var(--border);">
                                    <th style="padding: 0.75rem 0;">Empresa / Parceiro</th>
                                    <th style="padding: 0.75rem 0;">Cota</th>
                                    <th style="padding: 0.75rem 0; text-align: right;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($patrocinios)): ?>
                                    <tr>
                                        <td colspan="3"
                                            style="text-align:center; padding:2rem; color:var(--text-muted); font-size: 0.85rem;">
                                            Nenhum patrocínio registrado.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($patrocinios as $p): ?>
                                    <tr style="border-bottom:1px solid var(--border);">
                                        <td style="padding:0.75rem 0;">
                                            <div style="font-weight:600; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($p['empresa']); ?>
                                            </div>
                                            <div style="font-size: 0.7rem; color: var(--text-muted);">
                                                <?php echo htmlspecialchars($p['contato'] ?: '--'); ?>
                                            </div>
                                        </td>
                                        <td style="padding:0.75rem 0; font-weight:700;">R$
                                            <?php echo number_format($p['valor_cota'], 2, ',', '.'); ?>
                                        </td>
                                        <td style="padding:0.75rem 0; text-align:right;"><span class="badge badge-ativo"
                                                style="font-size: 0.6rem;">FECHADO</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Row 3: Lancamentos and Inscricoes -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
                <!-- LANCAMENTOS EXTRAS -->
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border);">
                        <h3 style="margin: 0; font-size: 1rem;">Outros Lançamentos (Eventuais)</h3>
                    </div>
                    <div style="padding: 1.5rem;">
                        <form method="POST"
                            style="background: #fff; padding: 1.25rem; border-radius: 0; margin-bottom: 1.5rem; border: 1px solid var(--border);">
                            <input type="hidden" name="novo_lancamento" value="1">
                            <h4
                                style="margin-top: 0; margin-bottom: 1rem; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">
                                Novo Lançamento Extra</h4>
                            <div
                                style="display:grid; grid-template-columns: 100px 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                                <select name="fin_tipo" class="form-control">
                                    <option value="receita">Receita</option>
                                    <option value="despesa">Despesa</option>
                                </select>
                                <input type="text" name="fin_desc" class="form-control"
                                    placeholder="Descrição (Ex: Aluguel, Troféus...)" required>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr auto; gap: 0.75rem;">
                                <input type="text" name="fin_valor" class="form-control" placeholder="Valor R$ 0,00"
                                    required>
                                <button type="submit" class="btn btn-primary" style="padding: 0 1.5rem;">LANÇAR</button>
                            </div>
                        </form>

                        <div style="max-height: 300px; overflow-y: auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr
                                        style="text-align:left; color:var(--text-muted); font-size:0.7rem; text-transform:uppercase; border-bottom: 1px solid var(--border);">
                                        <th style="padding: 0.75rem 0;">Data / Descrição</th>
                                        <th style="padding: 0.75rem 0; text-align: right;">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lancamentos)): ?>
                                        <tr>
                                            <td colspan="2"
                                                style="text-align:center; padding:1.5rem; color:var(--text-muted); font-size: 0.85rem;">
                                                Sem lançamentos extras.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($lancamentos as $l): ?>
                                        <tr style="border-bottom:1px solid var(--border);">
                                            <td style="padding:0.75rem 0;">
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">
                                                    <?php echo date('d/m/Y', strtotime($l['data_lancamento'])); ?>
                                                </div>
                                                <div style="font-weight:600; font-size: 0.85rem;">
                                                    <?php echo htmlspecialchars($l['descricao']); ?>
                                                </div>
                                            </td>
                                            <td
                                                style="padding:0.75rem 0; text-align:right; font-weight:700; color:<?php echo $l['tipo'] == 'receita' ? 'var(--success)' : 'var(--danger)'; ?>">
                                                <?php echo $l['tipo'] == 'receita' ? '+' : '-'; ?> R$
                                                <?php echo number_format($l['valor'], 2, ',', '.'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- PAGAMENTOS INSCRICOES -->
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border);">
                        <h3 style="margin: 0; font-size: 1rem;">Controle de Inscrições</h3>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="max-height: 480px; overflow-y: auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr
                                        style="text-align:left; color:var(--text-muted); font-size:0.7rem; text-transform:uppercase; border-bottom: 1px solid var(--border);">
                                        <th style="padding: 0.75rem 0;">Atleta</th>
                                        <th style="padding: 0.75rem 0;">Pagamento</th>
                                        <th style="padding: 0.75rem 0; text-align: right;">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($inscritos)): ?>
                                        <tr>
                                            <td colspan="3"
                                                style="text-align:center; padding:2rem; color:var(--text-muted); font-size: 0.85rem;">
                                                Nenhuma inscrição encontrada.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($inscritos as $ins):
                                        $at_nome = $ins['aluno_id'] ? $ins['aluno_nome'] : $ins['nome_externo'];
                                        ?>
                                        <tr style="border-bottom:1px solid var(--border);">
                                            <td style="padding:0.75rem 0;">
                                                <div style="font-weight:700; font-size: 0.85rem;">
                                                    <?php echo htmlspecialchars($at_nome); ?>
                                                </div>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">
                                                    <?php echo htmlspecialchars($ins['cat_nome'] ?: 'Sem Categoria'); ?>
                                                </div>
                                            </td>
                                            <td style="padding:0.75rem 0;">
                                                <div style="font-weight:600; font-size: 0.85rem;">R$
                                                    <?php echo number_format($ins['valor_pago'], 2, ',', '.'); ?>
                                                </div>
                                                <span
                                                    class="badge <?php echo $ins['status_pagamento'] == 'pago' ? 'badge-ativo' : 'badge-inativo'; ?>"
                                                    style="font-size: 0.6rem;">
                                                    <?php echo strtoupper($ins['status_pagamento']); ?>
                                                </span>
                                            </td>
                                            <td style="padding:0.75rem 0; text-align: right;">
                                                <?php if ($ins['status_pagamento'] == 'pendente'): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="insc_id_baixa"
                                                            value="<?php echo $ins['id']; ?>">
                                                        <button type="submit" name="baixar_pagamento" class="btn btn-auto"
                                                            style="background:var(--success); color:white; padding:0.35rem 0.6rem; font-size:0.65rem; border-radius: 0; border: none; font-weight: 700;">BAIXAR</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color: var(--success); font-size: 0.7rem; font-weight: 700;"><i
                                                            class="fa-solid fa-check"></i> OK</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div> <!-- max-height -->
                    </div> <!-- padding -->
                </div> <!-- card -->
            </div> <!-- grid row 3 -->
        </div> <!-- financeiro tab -->

        <!-- PESAGEM -->
        <div id="pesagem" class="tab-content" style="display:none;">
            <div class="card" style="margin-bottom: 2rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <h3 style="margin:0;">Lista de Pesagem e Conferência</h3>
                    <div style="display:flex; gap:1rem;">
                        <select id="select_cat_pesagem" class="form-control"
                            style="width:250px; background:#fff; border:1px solid var(--border);"
                            onchange="filtrarPesagem(this.value)">
                            <option value="todas">Todas as Categorias</option>
                            <?php foreach ($categorias as $ct): ?>
                                <option value="<?php echo $ct['id']; ?>">
                                    <?php echo htmlspecialchars($ct['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="imprimirPesagem()" class="btn btn-primary" style="font-weight:800;">
                            <i class="fa-solid fa-print"></i> GERAR PDF DE PESAGEM
                        </button>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width:100%; border-collapse: collapse; min-width: 600px;">
                        <thead>
                            <tr style="text-align: left; background: #f8fafc; border-bottom: 2px solid var(--border);">
                                <th
                                    style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Atleta</th>
                                <th
                                    style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Categoria</th>
                                <th
                                    style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Equipe / Academia</th>
                                <th
                                    style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); text-align: center;">
                                    Status Pagto</th>
                                <th
                                    style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); text-align: center;">
                                    Peso (Limite)</th>
                                <th
                                    style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); text-align: center;">
                                    Resultado</th>
                            </tr>
                        </thead>
                        <tbody id="lista_pesagem_body">
                            <?php foreach ($inscritos as $in):
                                $at_n = $in['aluno_id'] ? $in['aluno_nome'] : $in['nome_externo'];
                                $at_e = $in['aluno_id'] ? $unidade_nome : $in['equipe_externa'];
                                ?>
                                <tr class="pesagem-row" data-cat="<?php echo $in['categoria_id']; ?>"
                                    style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1rem; font-weight: 700; color: var(--text-main);">
                                        <?php echo htmlspecialchars($at_n); ?>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($in['cat_nome'] ?: 'Sem Categoria'); ?>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($at_e); ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span
                                            class="badge <?php echo $in['status_pagamento'] == 'pago' ? 'badge-ativo' : 'badge-inativo'; ?>"
                                            style="font-size:0.6rem;">
                                            <?php echo strtoupper($in['status_pagamento']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <div style="display:flex; align-items:center; justify-content:center; gap:0.5rem;">
                                            <input type="text" class="form-control"
                                                value="<?php echo $in['peso_atleta'] ? number_format($in['peso_atleta'], 2, ',', '.') : ''; ?>"
                                                placeholder="0,00"
                                                style="width:80px; text-align:center; font-size:0.8rem; padding:0.25rem;"
                                                onchange="savePeso(<?php echo $in['id']; ?>, this.value)">
                                            <span style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">
                                                (<?php echo $in['peso_max']; ?>kg)
                                            </span>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <div style="display:flex; justify-content:center; gap:0.25rem;">
                                            <button onclick="setPesagemStatus(<?php echo $in['id']; ?>, 'aprovado')"
                                                class="btn-status-pesagem <?php echo $in['pesagem_status'] == 'aprovado' ? 'active-ok' : ''; ?>"
                                                title="Aprovar">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            <button onclick="setPesagemStatus(<?php echo $in['id']; ?>, 'reprovado')"
                                                class="btn-status-pesagem <?php echo $in['pesagem_status'] == 'reprovado' ? 'active-fail' : ''; ?>"
                                                title="Reprovar">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="documentos" class="tab-content" style="display:none;">
            <div class="card" style="margin-bottom: 2rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <h3 style="margin:0;">Gerar Crachás e Diplomas</h3>
                    <div style="display:flex; gap:0.5rem;">
                        <button onclick="printDocs('crachas')" class="btn btn-primary"
                            style="background:var(--text-main);">
                            <i class="fa-solid fa-id-card"></i> IMPRIMIR CRACHÁS
                        </button>
                        <button onclick="printDocs('etiquetas')" class="btn btn-primary" style="background:#0ea5e9;">
                            <i class="fa-solid fa-tags"></i> IMPRIMIR ETIQUETAS
                        </button>
                        <button onclick="printDocs('diplomas')" class="btn btn-primary">
                            <i class="fa-solid fa-certificate"></i> IMPRIMIR DIPLOMAS
                        </button>
                    </div>
                </div>

                <div
                    style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.8rem;">
                    FILTRAR LISTA:</div>
                <div
                    style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; align-items:end;">
                    <div>
                        <label
                            style="font-size:0.65rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Atleta</label>
                        <input type="text" id="filter_nome_docs" class="form-control" placeholder="Nome..."
                            onkeyup="filterDocs()">
                    </div>
                    <div>
                        <label
                            style="font-size:0.65rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Categoria</label>
                        <select id="filter_cat_docs" class="form-control" onchange="filterDocs()">
                            <option value="todas">Todas</option>
                            <?php foreach ($categorias as $ct): ?>
                                <option value="<?php echo $ct['id']; ?>">
                                    <?php echo htmlspecialchars($ct['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label
                            style="font-size:0.65rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Academia</label>
                        <input type="text" id="filter_equipe_docs" class="form-control" placeholder="Equipe..."
                            onkeyup="filterDocs()">
                    </div>
                    <div>
                        <label
                            style="font-size:0.65rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Status</label>
                        <select id="filter_status_docs" class="form-control" onchange="filterDocs()">
                            <option value="todos">Todos</option>
                            <option value="aprovado">Aprovado</option>
                            <option value="reprovado">Reprovado</option>
                        </select>
                    </div>
                    <div>
                        <label
                            style="font-size:0.65rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Ano
                            / Peso</label>
                        <div style="display:flex; gap:0.5rem;">
                            <input type="text" id="filter_ano_docs" class="form-control" placeholder="Ano"
                                onkeyup="filterDocs()" style="width:70px;">
                            <input type="text" id="filter_peso_docs" class="form-control" placeholder="Peso"
                                onkeyup="filterDocs()" style="flex:1;">
                        </div>
                    </div>
                    <div style="padding-bottom:2px;">
                        <label
                            style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.8rem; font-weight:700; background:#f8fafc; padding:0.6rem; border-radius: 0; border:1px solid var(--border); margin:0;">
                            <input type="checkbox" id="select_all_docs" onclick="toggleSelectAllDocs(this.checked)">
                            SELECIONAR EXIBIDOS
                        </label>
                    </div>
                </div>
            </div>

            <div style="max-height: 600px; overflow-y: auto;">
                <table style="width:100%; border-collapse:collapse;" id="table_docs">
                    <thead>
                        <tr
                            style="text-align:left; color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; border-bottom: 2px solid var(--border);">
                            <th style="padding: 1rem; width: 40px;"></th>
                            <th style="padding: 1rem;">Atleta</th>
                            <th style="padding: 1rem;">Categoria</th>
                            <th style="padding: 1rem; text-align: center;">Resultado/Colocação</th>
                            <th style="padding: 1rem; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inscritos as $in):
                            $nome = $in['aluno_id'] ? $in['aluno_nome'] : $in['nome_externo'];
                            $equipe = $in['aluno_id'] ? $unidade_nome : $in['equipe_externa'];
                            $ano_nasc = $in['data_nascimento'] ? date('Y', strtotime($in['data_nascimento'])) : '';
                            ?>
                            <tr class="doc-row" data-nome="<?php echo strtolower($nome); ?>"
                                data-cat="<?php echo $in['categoria_id']; ?>"
                                data-equipe="<?php echo strtolower($equipe); ?>"
                                data-status="<?php echo $in['pesagem_status']; ?>" data-ano="<?php echo $ano_nasc; ?>"
                                data-peso="<?php echo $in['peso_atleta']; ?>"
                                style="border-bottom:1px solid var(--border);">
                                <td style="padding: 1rem; text-align: center;">
                                    <input type="checkbox" class="doc-checkbox" value="<?php echo $in['id']; ?>">
                                </td>
                                <td style="padding: 1rem;">
                                    <div style="font-weight:700; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($nome); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($equipe); ?>
                                    </div>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($in['cat_nome'] ?: 'Sem Categoria'); ?>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <input type="text" class="form-control"
                                        value="<?php echo htmlspecialchars($in['resultado']); ?>" placeholder="Ex: 1º Lugar"
                                        style="width:150px; text-align:center; font-size:0.8rem; padding:0.25rem; margin: 0 auto;"
                                        onchange="saveResultado(<?php echo $in['id']; ?>, this.value)">
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span
                                        class="badge <?php echo $in['pesagem_status'] == 'aprovado' ? 'badge-ativo' : 'badge-inativo'; ?>"
                                        style="font-size:0.6rem;">
                                        <?php echo strtoupper($in['pesagem_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

<!-- CATEGORIAS -->
<div id="categorias" class="tab-content" style="display:none;">
    <div class="card"
        style="padding: 2rem; border-radius: 0; border: 1px solid var(--border); box-shadow: var(--shadow);">
        <div style="margin-bottom: 2rem;">
            <h3 style="margin:0;">Gerenciar Categorias e Pesos</h3>
            <p style="color:var(--text-muted); font-size:0.9rem;">Configure as divisões de peso, idade e faixa para
                o evento.</p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
            <div class="card"
                style="padding:0; overflow:hidden; border: 1px solid var(--border); border-radius: 0;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left; background:#f8fafc; border-bottom:1px solid var(--border);">
                            <th
                                style="padding:1rem; text-transform:uppercase; font-size:0.7rem; color:var(--text-muted);">
                                Identificação</th>
                            <th
                                style="padding:1rem; text-transform:uppercase; font-size:0.7rem; color:var(--text-muted);">
                                Anos</th>
                            <th
                                style="padding:1rem; text-transform:uppercase; font-size:0.7rem; color:var(--text-muted);">
                                Faixas</th>
                            <th
                                style="padding:1rem; text-transform:uppercase; font-size:0.7rem; color:var(--text-muted);">
                                Peso Limite</th>
                            <th
                                style="padding:1rem; text-transform:uppercase; font-size:0.7rem; color:var(--text-muted); text-align: center;">
                                Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categorias)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:2rem; color:var(--text-muted);">
                                    Nenhuma categoria cadastrada.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($categorias as $cat): ?>
                            <tr style="border-bottom:1px solid var(--border);">
                                <td style="padding:1rem;">
                                    <div style="font-weight:700;">
                                        <?php echo htmlspecialchars($cat['nome']); ?>
                                    </div>
                                    <div style="font-size:0.65rem; color:#08153a; text-transform:uppercase; font-weight:600;">
                                        <?php echo $cat['sexo']; ?>
                                    </div>
                                </td>
                                <td style="padding:1rem; font-size:0.8rem;">
                                    <?php echo ($cat['ano_nascimento_min'] ?: '∞') . ' a ' . ($cat['ano_nascimento_max'] ?: '∞'); ?>
                                </td>
                                <td style="padding:1rem; font-size:0.8rem;">
                                    <?php echo $cat['faixas'] ?: 'Livre'; ?>
                                </td>
                                <td style="padding:1rem; font-weight:800; color:var(--danger);">
                                    <?php echo $cat['peso_max'] ? $cat['peso_max'] . 'kg' : 'Livre'; ?>
                                </td>
                                <td style="padding:1rem; text-align: center;">
                                    <button type="button"
                                        onclick="if(confirm('Excluir categoria?')) { window.location.href='editar_competicao.php?id=<?php echo $id; ?>&remover_categoria=<?php echo $cat['id']; ?>&tab=categorias'; }"
                                        class="btn"
                                        style="background:rgba(255,23,68,0.05); color:var(--danger); padding:0.4rem 0.6rem; border:none; border-radius: 0; transition: 0.2s;"
                                        onmouseover="this.style.background='rgba(255,23,68,0.1)'"
                                        onmouseout="this.style.background='rgba(255,23,68,0.05)'">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="background: #f8fafc; border: 1px dashed var(--primary); padding: 1.5rem; border-radius: 0;">
                <h5
                    style="margin-bottom:1.5rem; font-size:0.75rem; color:#08153a; font-weight: 800; text-transform: uppercase;">
                    <i class="fa-solid fa-plus-circle"></i> Adicionar Configuração de Categoria
                </h5>
                <form method="POST" action="editar_competicao.php?id=<?php echo $id; ?>&tab=categorias">
                    <input type="hidden" name="nova_categoria" value="1">
                    <div id="form-cat-inline"
                        style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1.5fr 1fr auto; gap: 1rem; align-items: end;">
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.6rem; font-weight: 800; color:var(--text-muted);">NOME</label>
                            <input type="text" name="nome" class="form-control" required
                                placeholder="Ex: Mirim I / Pena" style="height:40px; font-size:0.85rem;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.6rem; font-weight: 800; color:var(--text-muted);">GÊNERO</label>
                            <select name="sexo" class="form-control"
                                style="height:40px; font-size:0.85rem; font-weight: 600;">
                                <option value="unissex">Uni</option>
                                <option value="masculino">Masc</option>
                                <option value="feminino">Fem</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.6rem; font-weight: 800; color:var(--text-muted);">ANO
                                MÍN</label>
                            <input type="number" name="ano_nascimento_min" class="form-control" placeholder="2010"
                                style="height:40px; font-size:0.85rem;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.6rem; font-weight: 800; color:var(--text-muted);">ANO
                                MÁX</label>
                            <input type="number" name="ano_nascimento_max" class="form-control" placeholder="2015"
                                style="height:40px; font-size:0.85rem;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.6rem; font-weight: 800; color:var(--text-muted);">FAIXAS</label>
                            <input type="text" name="faixas" class="form-control" placeholder="Branca"
                                style="height:40px; font-size:0.85rem;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.6rem; font-weight: 800; color:var(--text-muted);">PESO
                                (KG)</label>
                            <input type="number" step="0.1" name="peso_max" class="form-control" placeholder="0.0"
                                style="height:40px; font-size:0.85rem;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="height:40px; padding:0 1.25rem;"><i
                                class="fa-solid fa-plus"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</main>
<script>           function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) tabcontent[i].style.display = "none";
        tablinks = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tablinks.length; i++) tablinks[i].classList.remove("active");

        document.getElementById(tabName).style.display = "block";

        if (evt) {
            evt.currentTarget.classList.add("active");
        } else {
            document.getElementById('btn-' + tabName).classList.add("active");
        }
    }

    // Categoria Inline Add
    function inlineAddCategoria() {
        const nome = document.getElementById('cat_nome_in').value;
        const sexo = document.getElementById('cat_sexo_in').value;
        const min = document.getElementById('ano_min_in').value;
        const max = document.getElementById('ano_max_in').value;
        const faixas = document.getElementById('cat_faixas_in').value;
        const peso = document.getElementById('cat_peso_in').value;

        if (!nome) { alert('Nome obrigatório'); return; }

        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const fields = {
            'nova_categoria': '1',
            'cat_nome': nome,
            'cat_sexo': sexo,
            'ano_min': min,
            'ano_max': max,
            'cat_faixas': faixas,
            'cat_peso': peso
        };

        for (const [k, v] of Object.entries(fields)) {
            const inp = document.createElement('input');
            inp.name = k;
            inp.value = v;
            form.appendChild(inp);
        }

        document.body.appendChild(form);
        form.submit();
    }

    function updateValorPorLote(loteId) {
        if (!loteId) return;
        const select = document.getElementById('field_lote_id');
        const selectedOption = select.options[select.selectedIndex];
        const valor = selectedOption.getAttribute('data-valor');
        if (valor) {
            document.getElementById('field_valor_pago').value = valor;
        }
    }

    function toggleAtletaType(type) {
        document.getElementById('box_interno').style.display = (type === 'interno' ? 'block' : 'none');
        document.getElementById('box_externo').style.display = (type === 'externo' ? 'block' : 'none');
    }

    function editInscrito(data) {
        openTab(null, 'inscricoes');
        document.getElementById('form_insc_title').innerText = "Editar Inscrição";
        document.getElementById('edit_insc_id').value = data.id;
        document.getElementById('field_cat_id').value = data.categoria_id || "";
        document.getElementById('field_lote_id').value = data.lote_id || "";
        document.getElementById('field_resultado').value = data.resultado || "";
        document.getElementById('field_valor_pago').value = parseFloat(data.valor_pago).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) || "0,00";
        document.getElementById('field_status_pagto').value = data.status_pagamento || "pendente";

        document.getElementById('box_resultado').style.display = "block";
        document.getElementById('btn_save_insc').innerText = "Salvar Alterações";
        document.getElementById('btn_cancel_edit').style.display = "block";

        if (data.aluno_id) {
            document.getElementById('atleta_origem').value = "interno";
            document.getElementById('field_aluno_id').value = data.aluno_id;
            toggleAtletaType('interno');
        } else {
            document.getElementById('atleta_origem').value = "externo";
            document.getElementById('field_nome_ext').value = data.nome_externo;
            document.getElementById('field_equipe_ext').value = data.equipe_externa;
            document.getElementById('field_faixa_ext').value = data.faixa_externa;
            toggleAtletaType('externo');
        }

        // Scroll to form
        document.getElementById('form_inscricao').scrollIntoView({ behavior: 'smooth' });
    }

    function editLote(data) {
        document.getElementById('lote_form_title').innerText = "Editar Lote";
        document.getElementById('edit_lote_id').value = data.id;
        document.getElementById('lote_field_nome').value = data.nome;
        document.getElementById('lote_field_valor').value = parseFloat(data.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
        document.getElementById('lote_field_data').value = data.data_limite;
        document.getElementById('btn_lote_save').innerText = "Salvar";
        document.getElementById('btn_cancel_lote').style.display = "block";
        document.getElementById('form_lote').scrollIntoView({ behavior: 'smooth' });
    }

    function cancelLoteEdit() {
        document.getElementById('lote_form_title').innerText = "Adicionar Lote";
        document.getElementById('edit_lote_id').value = "";
        document.getElementById('form_lote').reset();
        document.getElementById('btn_lote_save').innerText = "+";
        document.getElementById('btn_cancel_lote').style.display = "none";
    }

    function cancelInscEdit() {
        document.getElementById('form_insc_title').innerText = "Nova Inscrição";
        document.getElementById('edit_insc_id').value = "";
        document.getElementById('form_inscricao').reset();
        document.getElementById('box_resultado').style.display = "none";
        document.getElementById('btn_save_insc').innerText = "Finalizar Inscrição";
        document.getElementById('btn_cancel_edit').style.display = "none";
        toggleAtletaType('interno');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        const cat = urlParams.get('cat_id');
        if (tab) {
            openTab(null, tab);
            if (tab === 'chaves' && cat) {
                document.getElementById('select_cat_chaves').value = cat;
                loadChave(cat);
            }
        } else {
            openTab(null, 'dashboard');
        }
    });

    function confirmGerar(e) {
        const cat = document.getElementById('select_cat_chaves').value;
        if (!cat) {
            alert('Selecione uma categoria primeiro!');
            e.preventDefault();
            return;
        }
        if (!confirm('Isso irá apagar as chaves atuais desta categoria e gerar novas. Continuar?')) {
            e.preventDefault();
        }
        document.getElementById('form_cat_id_sh').value = cat;
    }

    async function loadChave(catId) {
        if (!catId) return;
        const canvas = document.getElementById('canvas_chaves');
        canvas.innerHTML = '<p style="text-align:center; width:100%;">Carregando chave...</p>';

        try {
            const resp = await fetch(`editar_competicao.php?id=<?php echo $id; ?>&ajax_chave=1&cat_id=${catId}`);
            const lutas = await resp.json();
            renderChaves(lutas);

            // Atualizar URL sem recarregar para manter estado
            const url = new URL(window.location);
            url.searchParams.set('tab', 'chaves');
            url.searchParams.set('cat_id', catId);
            window.history.replaceState({}, '', url);
        } catch (e) {
            canvas.innerHTML = '<p style="color:red;">Erro ao carregar chaves.</p>';
        }
    }

    function renderChaves(lutas) {
        const canvas = document.getElementById('canvas_chaves');
        canvas.innerHTML = '';

        if (lutas.length === 0) {
            canvas.innerHTML = '<p style="text-align:center; width:100%;">Nenhuma chave gerada para esta categoria.</p>';
            return;
        }

        // Agrupar por fase
        const fases = {};
        lutas.forEach(l => {
            if (!fases[l.fase]) fases[l.fase] = [];
            fases[l.fase].push(l);
        });

        // Ordenar fases (maior para menor: Quartas -> Semi -> Final)
        const sortedFases = Object.keys(fases).sort((a, b) => b - a);

        sortedFases.forEach(f => {
            const col = document.createElement('div');
            col.className = 'fase-col';
            col.style.display = 'flex';
            col.style.flexDirection = 'column';
            col.style.justifyContent = 'space-around';
            col.style.gap = '2rem';

            const title = document.createElement('h4');
            title.innerText = f == 1 ? 'Final' : (f == 2 ? 'Semi-Final' : (f == 4 ? 'Quartas' : `Oitavas (${f})`));
            title.style.textAlign = 'center';
            title.style.marginBottom = '1rem';
            col.appendChild(title);

            fases[f].forEach(l => {
                const card = document.createElement('div');
                card.className = 'luta-card';
                card.style.background = 'white';
                card.style.border = '1px solid var(--border)';
                card.style.borderRadius = '0.5rem';
                card.style.padding = '0.5rem';
                card.style.width = '200px';
                card.style.boxShadow = 'var(--shadow-sm)';
                card.style.position = 'relative';

                const p1_nome = l.i1_nome_int || l.i1_nome_ext || '-- Vago --';
                const p2_nome = l.i2_nome_int || l.i2_nome_ext || '-- Vago --';

                card.innerHTML = `
                    <div style="padding:0.25rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; ${l.vencedor_id == l.inscricao1_id && l.vencedor_id ? 'color:var(--success); font-weight:bold;' : ''}">
                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:150px;">${p1_nome}</span>
                        ${l.inscricao1_id && !l.vencedor_id ? `<button type="button" onclick="setVencedor(${l.id}, ${l.inscricao1_id})" style="font-size:0.6rem; padding:0 0.25rem; border:1px solid var(--border); background:#fff; cursor:pointer;">✔</button>` : ''}
                    </div>
                    <div style="padding:0.25rem; display:flex; justify-content:space-between; ${l.vencedor_id == l.inscricao2_id && l.vencedor_id ? 'color:var(--success); font-weight:bold;' : ''}">
                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:150px;">${p2_nome}</span>
                        ${l.inscricao2_id && !l.vencedor_id ? `<button type="button" onclick="setVencedor(${l.id}, ${l.inscricao2_id})" style="font-size:0.6rem; padding:0 0.25rem; border:1px solid var(--border); background:#fff; cursor:pointer;">✔</button>` : ''}
                    </div>
                `;
                col.appendChild(card);
            });
            canvas.appendChild(col);
        });
    }

    function imprimirChave() {
        const catId = document.getElementById('select_cat_chaves').value;
        if (!catId) {
            alert('Selecione uma categoria primeiro!');
            return;
        }
        const url = `imprimir.php?id=<?php echo $id; ?>&tipo=chaves&cat_id=${catId}`;
        window.open(url, '_blank');
    }

    async function setPesagemStatus(inscId, status) {
        const formData = new FormData();
        formData.append('ajax_pesagem', '1');
        formData.append('update_status', '1');
        formData.append('insc_id', inscId);
        formData.append('status', status);

        try {
            const resp = await fetch('editar_competicao.php?id=<?php echo $id; ?>', {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();
            if (res.success) {
                location.reload(); // Recarrega para atualizar visualmente os botões
            } else {
                alert('Erro ao atualizar status: ' + res.error);
            }
        } catch (e) {
            alert('Erro na conexão.');
        }
    }

    async function savePeso(inscId, peso) {
        const formData = new FormData();
        formData.append('ajax_pesagem', '1');
        formData.append('update_peso', '1');
        formData.append('insc_id', inscId);
        formData.append('peso', peso);

        try {
            const resp = await fetch('editar_competicao.php?id=<?php echo $id; ?>', {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();
            if (!res.success) {
                alert('Erro ao salvar peso: ' + res.error);
            }
        } catch (e) {
            alert('Erro na conexão.');
        }
    }

    async function saveResultado(inscId, resultado) {
        const formData = new FormData();
        formData.append('ajax_pesagem', '1'); // Reutilizando o handler de pesagem
        formData.append('update_resultado', '1');
        formData.append('insc_id', inscId);
        formData.append('resultado', resultado);

        try {
            const resp = await fetch('editar_competicao.php?id=<?php echo $id; ?>', {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();
            if (!res.success) {
                alert('Erro ao salvar resultado: ' + res.error);
            }
        } catch (e) {
            alert('Erro na conexão.');
        }
    }

    function setVencedor(lutaId, vencedorId) {
        if (!confirm('Confirmar vencedor desta luta?')) return;
        const catId = document.getElementById('select_cat_chaves').value;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `editar_competicao.php?id=<?php echo $id; ?>&tab=chaves&cat_id=${catId}`;
        form.innerHTML = `
            <input type="hidden" name="definir_vencedor" value="1">
            <input type="hidden" name="luta_id" value="${lutaId}">
            <input type="hidden" name="vencedor_id" value="${vencedorId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    function toggleSelectAllDocs(checked) {
        document.querySelectorAll('.doc-checkbox').forEach(cb => {
            if (cb.closest('.doc-row').style.display !== 'none') {
                cb.checked = checked;
            }
        });
    }

    function filtrarPesagem(catId) {
        const rows = document.querySelectorAll('.pesagem-row');
        rows.forEach(row => {
            if (catId === 'todas' || row.getAttribute('data-cat') === catId) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function imprimirPesagem() {
        const catId = document.getElementById('select_cat_pesagem').value;
        const url = `imprimir.php?id=<?php echo $id; ?>&tipo=pesagem&cat_id=${catId === 'todas' ? '' : catId}`;
        window.open(url, '_blank');
    }

    function filterDocs() {
        const nomeFilter = document.getElementById('filter_nome_docs').value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        const catId = document.getElementById('filter_cat_docs').value;
        const equipeFilter = document.getElementById('filter_equipe_docs').value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        const statusFilter = document.getElementById('filter_status_docs').value;
        const anoFilter = document.getElementById('filter_ano_docs').value;
        const pesoFilter = document.getElementById('filter_peso_docs').value;

        const rows = document.querySelectorAll('.doc-row');
        rows.forEach(row => {
            const rowNome = row.getAttribute('data-nome').normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const rowCat = row.getAttribute('data-cat');
            const rowEquipe = row.getAttribute('data-equipe').normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const rowStatus = row.getAttribute('data-status');
            const rowAno = row.getAttribute('data-ano');
            const rowPeso = row.getAttribute('data-peso');

            let visible = true;

            if (nomeFilter && !rowNome.includes(nomeFilter)) visible = false;
            if (catId !== 'todas' && rowCat !== catId) visible = false;
            if (equipeFilter && !rowEquipe.includes(equipeFilter)) visible = false;
            if (statusFilter !== 'todos' && rowStatus !== statusFilter) visible = false;
            if (anoFilter && !rowAno.includes(anoFilter)) visible = false;
            if (pesoFilter && !rowPeso.includes(pesoFilter)) visible = false;

            row.style.display = visible ? '' : 'none';
            if (!visible) {
                row.querySelector('.doc-checkbox').checked = false;
            }
        });
    }

    function printDocs(tipo) {
        const selected = Array.from(document.querySelectorAll('.doc-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            alert('Selecione ao menos um atleta para imprimir.');
            return;
        }
        const url = `imprimir.php?id=<?php echo $id; ?>&tipo=${tipo}&insc_ids=${selected.join(',')}`;
        window.open(url, '_blank');
    }

    // --- Lógica de Fotos (Drag & Drop) ---
    let dropArea = document.getElementById('drop-area');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight(e) {
        dropArea.style.borderColor = 'var(--primary)';
        dropArea.style.background = 'rgba(var(--primary-rgb), 0.05)';
    }

    function unhighlight(e) {
        dropArea.style.borderColor = 'var(--border)';
        dropArea.style.background = 'rgba(0,0,0,0.02)';
    }

    dropArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        let dt = e.dataTransfer;
        let files = dt.files;
        handleFiles(files);

        // Vincular arquivos ao input real para o POST funcionar
        document.getElementById('fileElem').files = files;
    }

    function handleFiles(files) {
        files = [...files];
        files.forEach(previewFile);
    }

    function previewFile(file) {
        let reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onloadend = function () {
            let div = document.createElement('div');
            div.style.position = 'relative';
            div.style.aspectRatio = '1';
            div.style.borderRadius = '0.5rem';
            div.style.overflow = 'hidden';
            div.style.border = '2px solid var(--primary)';

            let img = document.createElement('img');
            img.src = reader.result;
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'cover';
            img.style.opacity = '0.6';

            div.appendChild(img);
            document.getElementById('gallery').appendChild(div);
        }
    }
</script>
<?php include 'footer.php'; ?>