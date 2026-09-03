<?php
require_once '../config.php';

$id = $_GET['id'] ?? null;
$unidade_id = getUnidadeId();

if (!$id) {
    header('Location: alunos.php');
    exit;
}

if (!temPermissaoModulo('alunos_editar')) {
    header('Location: alunos.php?erro=sem_permissao');
    exit;
}

// Auto-migration: Garantir coluna responsavel_email
try {
    $pdo->query("SELECT responsavel_email FROM alunos LIMIT 1");
} catch (Exception $e) {
    $pdo->query("ALTER TABLE alunos ADD responsavel_email VARCHAR(255) AFTER responsavel_telefone");
}

// Auto-migration: Garantir coluna complemento (turma da escola)
try {
    $pdo->query("SELECT complemento FROM alunos LIMIT 1");
} catch (Exception $e) {
    $pdo->query("ALTER TABLE alunos ADD complemento VARCHAR(150) AFTER academia_id");
}

garantirColunaTipoCadastroAluno($pdo);

// Buscar dados do aluno
$stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$aluno = $stmt->fetch();

if (!$aluno) {
    header('Location: alunos.php');
    exit;
}

// Buscar faixas da unidade
$stmt_grads = $pdo->prepare("SELECT nome, grau FROM unidade_graduacoes WHERE unidade_id = ? ORDER BY ordem ASC, nome ASC");
$stmt_grads->execute([$unidade_id]);
$graduacoes_db = $stmt_grads->fetchAll(PDO::FETCH_ASSOC);

if (empty($graduacoes_db)) {
    $graduacoes_list = [];
    foreach (['Branca', 'Cinza', 'Amarela', 'Laranja', 'Verde', 'Azul', 'Roxa', 'Marrom', 'Preta'] as $f) {
        $graduacoes_list[] = ['nome' => $f, 'grau' => ''];
    }
} else {
    $graduacoes_list = $graduacoes_db;
}

$mensagem = '';
$erro = '';

// ──────────────────────────────────────────────────────────
// EXCLUIR ALUNO — precisa rodar ANTES do include header.php
// para que o header('Location:') funcione sem erros
// ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_aluno'])) {
    $codigo_confirmacao = trim($_POST['codigo_confirmacao'] ?? '');
    if ($codigo_confirmacao == $id) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM turma_alunos WHERE aluno_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM mensalidades WHERE aluno_id = ? AND unidade_id = ?")->execute([$id, $unidade_id]);
            $pdo->prepare("DELETE FROM competicao_inscricoes WHERE aluno_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM alunos WHERE id = ? AND unidade_id = ?")->execute([$id, $unidade_id]);
            $pdo->commit();
            header('Location: alunos.php?msg=aluno_excluido');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao excluir aluno: " . $e->getMessage();
        }
    } else {
        $erro = "Código incorreto. O aluno NÃO foi excluído.";
    }
}
// ──────────────────────────────────────────────────────────

$custom_title = "Editar Aluno";
$custom_subtitle = "Gerencie o perfil completo, turmas e financeiro deste aluno.";
$back_link = "alunos.php";
$back_text = "Voltar para Listagem";
include 'header.php';

// Processamento de POSTs baseados em ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Salvar Dados Pessoais e Foto
    if (isset($_POST['salvar_dados'])) {
        $nome_completo = $_POST['nome_completo'] ?? '';
        $email_pessoal = $_POST['email_pessoal'] ?? '';
        $cpf = $_POST['cpf'] ?? '';
        $rg = $_POST['rg'] ?? '';
        $data_nascimento = $_POST['data_nascimento'] ?: null;
        $genero = $_POST['genero'] ?: null;
        $telefone = $_POST['telefone'] ?? '';

        // Endereço
        $cep = $_POST['cep'] ?? '';
        $endereco = $_POST['endereco'] ?? '';
        $endereco_numero = $_POST['endereco_numero'] ?? '';
        $endereco_complemento = $_POST['endereco_complemento'] ?? '';
        $bairro = $_POST['bairro'] ?? '';
        $cidade = $_POST['cidade'] ?? '';
        $estado = $_POST['estado'] ?? '';

        // Responsável
        $responsavel_nome = $_POST['responsavel_nome'] ?? '';
        $responsavel_cpf = $_POST['responsavel_cpf'] ?? '';
        $responsavel_parentesco = $_POST['responsavel_parentesco'] ?? '';
        $responsavel_telefone = $_POST['responsavel_telefone'] ?? '';
        $responsavel_email = $_POST['responsavel_email'] ?? '';

        $faixa = $_POST['faixa'] ?? '';
        $academia_id = isset($_POST['academia_id']) ? ($_POST['academia_id'] ?: null) : ($aluno['academia_id'] ?: null);
        $complemento = isset($_POST['complemento']) ? $_POST['complemento'] : ($aluno['complemento'] ?? '');
        $data_graduacao = $_POST['data_graduacao'] ?: null;
        $status = $_POST['status'] ?? 'ativo';
        $tipo_cadastro = in_array($_POST['tipo_cadastro'] ?? '', ['fixo', 'visitante'], true) ? $_POST['tipo_cadastro'] : 'fixo';

        $turma_id = $_POST['turma_id'] ?? null;
        $valor_mensal = $_POST['valor_mensal'] ?? 0;
        $dia_vencimento = $_POST['dia_vencimento'] ?? 5;
        $qtd_parcelas = $_POST['qtd_parcelas'] ?? 0;

        // Upload de Foto
        $foto_path = $aluno['foto'];
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $novo_nome = "aluno_" . $id . "_" . time() . "." . $ext;
            
            $path = getUploadPath('alunos', $academia_id);
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $path . "/" . $novo_nome)) {
                $foto_path = $novo_nome;
            }
        }

        try {
            $sql = "UPDATE alunos SET 
                nome_completo = ?, email_pessoal = ?, cpf = ?, rg = ?, foto = ?, 
                data_nascimento = ?, genero = ?, telefone = ?, 
                cep = ?, endereco = ?, endereco_numero = ?, endereco_complemento = ?, bairro = ?, cidade = ?, estado = ?, 
                responsavel_nome = ?, responsavel_cpf = ?, responsavel_parentesco = ?, responsavel_telefone = ?, responsavel_email = ?, 
                faixa = ?, academia_id = ?, complemento = ?, data_graduacao = ?, status = ?, tipo_cadastro = ?
                WHERE id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nome_completo,
                $email_pessoal,
                $cpf,
                $rg,
                $foto_path,
                $data_nascimento,
                $genero,
                $telefone,
                $cep,
                $endereco,
                $endereco_numero,
                $endereco_complemento,
                $bairro,
                $cidade,
                $estado,
                $responsavel_nome,
                $responsavel_cpf,
                $responsavel_parentesco,
                $responsavel_telefone,
                $responsavel_email,
                $faixa,
                $academia_id,
                $complemento,
                $data_graduacao,
                $status,
                $tipo_cadastro,
                $id
            ]);
            if ($status === 'inativo') {
                $pdo->prepare("DELETE FROM turma_alunos WHERE aluno_id = ?")->execute([$id]);
            }

            // Vincular nova turma, se fornecida
            if ($turma_id) {
                $check = $pdo->prepare("SELECT 1 FROM turma_alunos WHERE aluno_id = ? AND turma_id = ?");
                $check->execute([$id, $turma_id]);
                if (!$check->fetch()) {
                    $pdo->prepare("INSERT INTO turma_alunos (aluno_id, turma_id) VALUES (?, ?)")->execute([$id, $turma_id]);
                }
            }

            // Gerar novas parcelas, se fornecido valor e quantidade
            if ($valor_mensal > 0 && $qtd_parcelas > 0) {
                $acad_id = $academia_id ?: ($aluno['academia_id'] ?: null);
                $data_base = date('Y-m-') . str_pad($dia_vencimento, 2, '0', STR_PAD_LEFT);
                for ($i = 0; $i < $qtd_parcelas; $i++) {
                    $venc_p = date('Y-m-d', strtotime("+$i month", strtotime($data_base)));
                    $pdo->prepare("INSERT INTO mensalidades (unidade_id, academia_id, aluno_id, valor, data_vencimento, status) VALUES (?, ?, ?, ?, ?, 'pendente')")
                        ->execute([$unidade_id, $acad_id, $id, $valor_mensal, $venc_p]);
                }
            }

            $mensagem = "Cadastro atualizado com sucesso!";
            $aluno = $pdo->query("SELECT * FROM alunos WHERE id = $id")->fetch(); // Recarregar
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    }

    // 2. Vincular Turmas + Academia + Complemento
    if (isset($_POST['atualizar_turmas'])) {
        $turmas = $_POST['turmas'] ?? [];
        $academia_id_turma = $_POST['academia_id_turma'] ?: null;
        $complemento_turma = $_POST['complemento'] ?? '';

        // Salvar academia e complemento no aluno
        $pdo->prepare("UPDATE alunos SET academia_id = ?, complemento = ? WHERE id = ?")
            ->execute([$academia_id_turma, $complemento_turma, $id]);

        // Atualizar vínculos de turma
        $pdo->prepare("DELETE FROM turma_alunos WHERE aluno_id = ?")->execute([$id]);
        foreach ($turmas as $t_id) {
            $pdo->prepare("INSERT INTO turma_alunos (aluno_id, turma_id) VALUES (?, ?)")->execute([$id, $t_id]);
        }
        $mensagem = "Vínculos atualizados com sucesso!";
        // Recarregar dados do aluno
        $aluno = $pdo->query("SELECT * FROM alunos WHERE id = $id")->fetch();
    }

    // 3. Financeiro (Mensalidades)
    if (isset($_POST['nova_mensalidade'])) {
        $acad_id = $aluno['academia_id'] ?: null;
        $valor = $_POST['valor'] ?? 0;
        $vencimento = $_POST['vencimento'];
        $qtd_parcelas = isset($_POST['qtd_parcelas']) ? (int)$_POST['qtd_parcelas'] : 1;

        if ($valor > 0 && $qtd_parcelas > 0) {
            for ($i = 0; $i < $qtd_parcelas; $i++) {
                $venc_p = date('Y-m-d', strtotime("+$i month", strtotime($vencimento)));
                $stmt = $pdo->prepare("INSERT INTO mensalidades (unidade_id, academia_id, aluno_id, valor, data_vencimento, status) VALUES (?, ?, ?, ?, ?, 'pendente')");
                $stmt->execute([$unidade_id, $acad_id, $id, $valor, $venc_p]);
            }
            $mensagem = "Lançamento financeiro concluído! Gerada(s) $qtd_parcelas parcela(s).";
        }
    }

    if (isset($_POST['pagar_id'])) {
        $stmt = $pdo->prepare("UPDATE mensalidades SET status = 'pago', data_pagamento = CURDATE(), forma_pagamento = ? WHERE id = ?");
        $stmt->execute([$_POST['forma_pagamento'], $_POST['pagar_id']]);
        registrarComissoesMensalidade($_POST['pagar_id'], $unidade_id);
        $mensagem = "Pagamento baixado!";
    }
    
    // 4. Editar Mensalidade
    if (isset($_POST['editar_mensalidade'])) {
        $m_id = $_POST['m_id'];
        $valor = $_POST['valor'];
        $venc = $_POST['data_vencimento'];
        $pag = $_POST['data_pagamento'] ?: null;
        $status = $_POST['status'];
        $forma = $_POST['forma_pagamento'] ?: null;
        $qtd_parcelas = isset($_POST['qtd_parcelas']) ? (int)$_POST['qtd_parcelas'] : 1;
        $aplicar_edicao = $_POST['aplicar_edicao'] ?? 'atual';

        // Vencimento original (antes da edição) serve de referência para localizar passadas/futuras
        $stmt_original = $pdo->prepare("SELECT data_vencimento FROM mensalidades WHERE id = ? AND unidade_id = ?");
        $stmt_original->execute([$m_id, $unidade_id]);
        $venc_original = $stmt_original->fetchColumn();

        $stmt = $pdo->prepare("UPDATE mensalidades SET valor = ?, data_vencimento = ?, data_pagamento = ?, status = ?, forma_pagamento = ? WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$valor, $venc, $pag, $status, $forma, $m_id, $unidade_id]);
        if ($status === 'pago') {
            registrarComissoesMensalidade($m_id, $unidade_id);
        }

        // Demais mensalidades do aluno (passadas/futuras) recebem apenas o Valor, preservando
        // vencimento/status/data de pagamento próprios de cada uma; mensalidades pagas nunca são alteradas
        if (($aplicar_edicao === 'passadas' || $aplicar_edicao === 'futuras') && $venc_original) {
            $operador = ($aplicar_edicao === 'passadas') ? '<=' : '>=';
            $stmt_serie = $pdo->prepare("UPDATE mensalidades SET valor = ? WHERE aluno_id = ? AND unidade_id = ? AND id != ? AND status != 'pago' AND data_vencimento $operador ?");
            $stmt_serie->execute([$valor, $id, $unidade_id, $m_id, $venc_original]);
        }

        // Gerar parcelas adicionais se qtd_parcelas > 1
        if ($qtd_parcelas > 1) {
            $acad_id = $aluno['academia_id'] ?: null;
            for ($i = 1; $i < $qtd_parcelas; $i++) {
                $venc_p = date('Y-m-d', strtotime("+$i month", strtotime($venc)));
                $stmt_insert = $pdo->prepare("INSERT INTO mensalidades (unidade_id, academia_id, aluno_id, valor, data_vencimento, status) VALUES (?, ?, ?, ?, ?, 'pendente')");
                $stmt_insert->execute([$unidade_id, $acad_id, $id, $valor, $venc_p]);
            }
        }

        $mensagem = "Pagamento atualizado com sucesso.";
    }

    // 5. Excluir Mensalidade
    if (isset($_POST['excluir_mensalidade'])) {
        $m_id = $_POST['m_id'];
        $escopo = $_POST['excluir_escopo'] ?? 'esta';

        if ($escopo === 'todos') {
            // A mensalidade selecionada é sempre excluída; as demais só se ainda não estiverem pagas
            $pdo->prepare("DELETE FROM mensalidades WHERE unidade_id = ? AND aluno_id = ? AND (id = ? OR status != 'pago')")
                ->execute([$unidade_id, $id, $m_id]);
        } elseif ($escopo === 'futuros') {
            $stmt_original = $pdo->prepare("SELECT data_vencimento FROM mensalidades WHERE id = ? AND unidade_id = ?");
            $stmt_original->execute([$m_id, $unidade_id]);
            $venc_original = $stmt_original->fetchColumn();
            $pdo->prepare("DELETE FROM mensalidades WHERE unidade_id = ? AND aluno_id = ? AND (id = ? OR (status != 'pago' AND data_vencimento >= ?))")
                ->execute([$unidade_id, $id, $m_id, $venc_original]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM mensalidades WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$m_id, $unidade_id]);
        }
        $mensagem = "Lançamento excluído com sucesso.";
    }

}

// Queries de Exibição
$turmas_aluno = $pdo->prepare("SELECT turma_id FROM turma_alunos WHERE aluno_id = ?");
$turmas_aluno->execute([$id]);
$turmas_aluno = $turmas_aluno->fetchAll(PDO::FETCH_COLUMN);

$todas_turmas = $pdo->prepare("SELECT id, nome, preco_mensal, horario, academia_id FROM turmas WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
$todas_turmas->execute([$unidade_id]);
$todas_turmas = $todas_turmas->fetchAll();

$financeiro = $pdo->prepare("SELECT * FROM mensalidades WHERE aluno_id = ? ORDER BY data_vencimento DESC");
$financeiro->execute([$id]);
$financeiro = $financeiro->fetchAll();

$competicoes = $pdo->prepare("
    SELECT ci.*, c.nome as comp_nome, c.data_evento 
    FROM competicao_inscricoes ci 
    JOIN competicoes c ON ci.competicao_id = c.id 
    WHERE ci.aluno_id = ? 
    ORDER BY c.data_evento DESC
");
$competicoes->execute([$id]);
$competicoes = $competicoes->fetchAll();

// Exames de Faixa do aluno
$exames_faixa = [];
try {
    $stmt_ef = $pdo->prepare("
        SELECT ei.*, eg.titulo as evento_titulo, eg.data_evento, eg.local, eg.taxa
        FROM eventos_graduacao_inscricoes ei
        JOIN eventos_graduacao eg ON eg.id = ei.evento_id
        WHERE ei.aluno_id = ?
        ORDER BY eg.data_evento DESC
    ");
    $stmt_ef->execute([$id]);
    $exames_faixa = $stmt_ef->fetchAll();
} catch (Exception $e) { $exames_faixa = []; }

$academias_disponiveis = $pdo->prepare("SELECT id, nome FROM academias WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
$academias_disponiveis->execute([$unidade_id]);
$academias_disponiveis = $academias_disponiveis->fetchAll();

?>
<style>
    .tab-btn-inner.active {
        background: #133080 !important;
        color: var(--primary-green) !important;
    }
    .tab-btn-inner:not(.active):hover {
        background: #eee;
    }
    .tab-content-aluno { display: none; }
    .tab-content-aluno.active { display: block; animation: fadeInSq 0.3s ease; }
    @keyframes fadeInSq { from { opacity: 0; } to { opacity: 1; } }
</style>



<section class="dashboard-section-sq">
    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
        <a href="alunos.php" class="tab-item-sq active">Alunos</a>
        <a href="equipe.php" class="tab-item-sq">RH</a>
        <a href="academias.php" class="tab-item-sq">Unidades</a>
        <a href="turmas.php" class="tab-item-sq">Turmas</a>
        <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
        <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
    </div>

    <!-- Cabeçalho de Perfil Premium -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; flex-wrap: wrap; gap: 20px; background: #fff; padding: 30px; border: 1px solid var(--border-color); border-left: 10px solid var(--primary-green);">
        <div style="display: flex; align-items: center; gap: 25px;">
            <div style="position: relative;">
                <img src="<?php echo $aluno['foto'] ? getUploadURL('alunos', $aluno['foto'], $aluno['academia_id']) : 'https://ui-avatars.com/api/?name=' . urlencode($aluno['nome_completo']) . '&background=133080&color=c99742'; ?>" 
                     style="width: 100px; height: 100px; object-fit: cover; border: 1px solid var(--border-color);">
                <div style="position: absolute; bottom: -3px; right: -3px; width: 20px; height: 20px; background: var(--primary-green); border: 3px solid #fff; border-radius: 0;"></div>

    
            </div>
            <div>
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 3px;">Aluno Perfil</span>
                <h1 style="font-size: 1.8rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                    <?php echo htmlspecialchars($aluno['nome_completo']); ?>
                </h1>
                <div style="display: flex; align-items: center; gap: 12px; margin-top: 5px;">
                    <p style="color: var(--text-muted); font-size: var(--fs-sm); margin: 0; font-weight: 700; text-transform: uppercase;">
                        <?php echo $aluno['faixa']; ?> • ID: #<?php echo $id; ?>
                    </p>
                    <?php if (!empty($aluno['responsavel_nome'])): ?>
                        <p style="color: var(--primary-green); font-size: var(--fs-xs); margin: 5px 0 0 0; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                            Responsável: <?php echo htmlspecialchars($aluno['responsavel_nome']); ?>
                        </p>
                    <?php endif; ?>
                    <span style="display: inline-block; padding: 3px 10px; font-size: var(--fs-xs); font-weight: 900; background: #133080; color: var(--primary-green); text-transform: uppercase;">
                        <?php echo $aluno['status']; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="alunos.php" class="btn-sq-light" style="width: auto; padding: 10px 20px; font-size: var(--fs-sm);">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
            <button type="button" onclick="document.getElementById('modal_excluir_aluno').style.display='flex'" 
                style="width: auto; padding: 10px 20px; font-size: var(--fs-sm); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; border: 2px solid #ef4444; background: transparent; color: #ef4444; display: flex; align-items: center; gap: 8px; transition: all 0.2s;"
                onmouseover="this.style.background='#ef4444'; this.style.color='#fff';"
                onmouseout="this.style.background='transparent'; this.style.color='#ef4444';">
                <i class="fa-solid fa-trash-can"></i> Excluir Aluno
            </button>
        </div>
    </div>

    

    <?php if ($mensagem): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px 25px; border-left: 4px solid var(--primary-green); margin-bottom: 30px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 15px 25px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

<div>
    <!-- Coluna Principal (Conteúdo) -->
    <div>
        <!-- Tabs Secundárias Internas -->
        <div style="display: flex; gap: 5px; margin-bottom: 25px; background: #fafafa; padding: 5px; border: 1px solid var(--border-color);">
            <button class="tab-btn-inner active" onclick="openTab(event, 'dados')" style="flex: 1; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s;">DADOS</button>
            <button class="tab-btn-inner" onclick="openTab(event, 'turmas')" style="flex: 1; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s;">TURMAS</button>
            <button class="tab-btn-inner" onclick="openTab(event, 'financeiro')" style="flex: 1; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s;">FINANCEIRO</button>
            <button class="tab-btn-inner" onclick="openTab(event, 'competicoes')" style="flex: 1; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s;">COMPETIÇÕES</button>
            <button class="tab-btn-inner" onclick="openTab(event, 'exames_faixa')" style="flex: 1; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fa-solid fa-medal" style="font-size:11px;"></i>EXAME DE FAIXAS</button>
        </div>

<!-- IMask para Máscaras de Input -->
<script src="https://unpkg.com/imask"></script>

<!-- TAB DADOS -->
<div id="dados" class="tab-content-aluno active">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="salvar_dados" value="1">

        <div class="dashboard-container" style="padding: 40px; margin-bottom: 30px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Informações de Cadastro</h2>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px;">
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Nome Completo</label>
                    <input type="text" name="nome_completo" value="<?php echo htmlspecialchars($aluno['nome_completo']); ?>" 
                        style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark); text-transform: uppercase;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">E-mail Pessoal</label>
                    <input type="email" name="email_pessoal" value="<?php echo htmlspecialchars($aluno['email_pessoal']); ?>" 
                        style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Telefone/WhatsApp</label>
                    <input type="text" name="telefone" value="<?php echo htmlspecialchars($aluno['telefone']); ?>" 
                        style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Data Nascimento</label>
                    <input type="date" name="data_nascimento" id="data_nascimento" value="<?php echo $aluno['data_nascimento']; ?>" onchange="verificarIdade(this.value)" 
                        style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">CPF</label>
                    <input type="text" name="cpf" value="<?php echo htmlspecialchars($aluno['cpf']); ?>" 
                        style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">RG</label>
                    <input type="text" name="rg" value="<?php echo htmlspecialchars($aluno['rg']); ?>" 
                        style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Gênero</label>
                    <select name="genero" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                        <option value="masculino" <?php echo $aluno['genero'] == 'masculino' ? 'selected' : ''; ?>>MASCULINO</option>
                        <option value="feminino" <?php echo $aluno['genero'] == 'feminino' ? 'selected' : ''; ?>>FEMININO</option>
                        <option value="outro" <?php echo $aluno['genero'] == 'outro' ? 'selected' : ''; ?>>OUTRO</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Nome do Responsável</label>
                    <input type="text" name="responsavel_nome" id="responsavel_nome" class="form-control" value="<?php echo htmlspecialchars($aluno['responsavel_nome']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700; text-transform: uppercase;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">CPF do Responsável</label>
                    <input type="text" name="responsavel_cpf" id="responsavel_cpf" class="form-control" value="<?php echo htmlspecialchars($aluno['responsavel_cpf']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Parentesco</label>
                    <input type="text" name="responsavel_parentesco" id="responsavel_parentesco" class="form-control" value="<?php echo htmlspecialchars($aluno['responsavel_parentesco']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Telefone do Responsável</label>
                    <input type="text" name="responsavel_telefone" id="responsavel_telefone" class="form-control" value="<?php echo htmlspecialchars($aluno['responsavel_telefone']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">E-mail do Responsável</label>
                    <input type="email" name="responsavel_email" id="responsavel_email" class="form-control" value="<?php echo htmlspecialchars($aluno['responsavel_email'] ?? ''); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
                
            </div>
        </div>

        <!-- SEÇÃO 2.5: GRADUAÇÃO -->
        <div class="dashboard-container" style="padding: 40px; margin-bottom: 30px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Graduação</h2>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:25px;">
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Faixa</label>
                    <select name="faixa" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                        <option value="">— Selecionar —</option>
                        <?php foreach ($graduacoes_list as $g_opt): 
                            $display_val = $g_opt['nome'];
                            if (!empty($g_opt['grau'])) {
                                $display_val .= ' - ' . $g_opt['grau'];
                            }
                        ?>
                            <option value="<?php echo htmlspecialchars($display_val); ?>" <?php echo $aluno['faixa'] == $display_val ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(strtoupper($display_val)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Data de Graduação</label>
                    <input type="date" name="data_graduacao" value="<?php echo $aluno['data_graduacao'] ?? ''; ?>" style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Status do Aluno</label>
                    <select name="status" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                        <option value="ativo"  <?php echo ($aluno['status'] ?? '') == 'ativo'  ? 'selected' : ''; ?>>ATIVO</option>
                        <option value="inativo" <?php echo ($aluno['status'] ?? '') == 'inativo' ? 'selected' : ''; ?>>INATIVO</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Tipo de Cadastro</label>
                    <select name="tipo_cadastro" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                        <option value="fixo" <?php echo ($aluno['tipo_cadastro'] ?? 'fixo') == 'fixo' ? 'selected' : ''; ?>>ALUNO FIXO</option>
                        <option value="visitante" <?php echo ($aluno['tipo_cadastro'] ?? 'fixo') == 'visitante' ? 'selected' : ''; ?>>VISITANTE (SÓ EXAME)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- SEÇÃO 3: ENDEREÇO -->
        <div class="dashboard-container" style="padding: 40px; margin-bottom: 30px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Endereço & Localização</h2>
            </div>
            <div style="display:grid; grid-template-columns: 180px 1fr; gap:25px; margin-bottom:25px;">
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">CEP</label>
                    <input type="text" name="cep" id="cep" class="form-control" value="<?php echo htmlspecialchars($aluno['cep']); ?>" onblur="buscarCEP(this.value)" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Logradouro</label>
                    <input type="text" name="endereco" id="endereco" class="form-control" value="<?php echo htmlspecialchars($aluno['endereco']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 100px 1fr 1fr; gap:25px; margin-bottom:25px;">
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Nº</label>
                    <input type="text" name="endereco_numero" class="form-control" value="<?php echo htmlspecialchars($aluno['endereco_numero']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Complemento</label>
                    <input type="text" name="endereco_complemento" class="form-control" value="<?php echo htmlspecialchars($aluno['endereco_complemento']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Bairro</label>
                    <input type="text" name="bairro" id="bairro" class="form-control" value="<?php echo htmlspecialchars($aluno['bairro']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 100px; gap:25px;">
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control" value="<?php echo htmlspecialchars($aluno['cidade']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">UF</label>
                    <input type="text" name="estado" id="estado" class="form-control" value="<?php echo htmlspecialchars($aluno['estado']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                </div>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn-sq" style="width:100%; padding: 20px; font-size: var(--fs-base);">SALVAR ALTERAÇÕES CADASTRAIS</button>
        </div>
    </form>
</div>

<!-- TAB TURMAS -->
<div id="turmas" class="tab-content-aluno">
    <form method="POST">
        <input type="hidden" name="atualizar_turmas" value="1">

        <!-- Academia & Complemento -->
        <div class="dashboard-container" style="padding: 40px; margin-bottom: 25px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Academia & Complemento</h2>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px;">
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Academia / Unidade</label>
                    <select name="academia_id_turma" id="academia_id_turma" onchange="filtrarTurmasEdit()" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                        <option value="">— Selecionar Academia —</option>
                        <?php foreach ($academias_disponiveis as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $aluno['academia_id'] == $a['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Complemento <span style="font-weight:500; text-transform:none; font-size:0.7rem;">(ex: turma, série, ano escolar)</span></label>
                    <input type="text" name="complemento" value="<?php echo htmlspecialchars($aluno['complemento'] ?? ''); ?>" placeholder="Ex: 5º Ano A, 2ª Série B..." style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                </div>
            </div>
        </div>

        <!-- Vínculo de Turmas -->
        <div class="dashboard-container" style="padding: 40px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Vínculo de Turmas</h2>
                <p style="font-size: var(--fs-xs); color: var(--text-muted); margin: 6px 0 0 0; font-weight:600;">Selecione a academia acima para filtrar as turmas disponíveis.</p>
            </div>
            <div id="lista-turmas" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px,1fr)); gap:15px;">
                <?php foreach ($todas_turmas as $t): ?>
                    <label data-academia="<?php echo $t['academia_id']; ?>" style="display: flex; align-items: flex-start; gap: 15px; padding: 20px; background: #fafafa; border: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s;">
                        <input type="checkbox" name="turmas[]" value="<?php echo $t['id']; ?>" data-preco="<?php echo $t['preco_mensal']; ?>" <?php echo in_array($t['id'], $turmas_aluno) ? 'checked' : ''; ?> onchange="atualizarPrecoEdit(this)" style="width: 20px; height: 20px; accent-color: var(--primary-green); margin-top: 2px;">
                        <div>
                            <span style="font-size: var(--fs-sm); font-weight: 900; color: var(--text-dark); display: block; text-transform: uppercase;"><?php echo htmlspecialchars($t['nome']); ?></span>
                            <span style="font-size: var(--fs-xs); font-weight: 700; color: var(--primary-green);"><?php echo $t['horario'] ? date('H:i', strtotime($t['horario'])) : ''; ?></span>
                        </div>
                    </label>
                <?php endforeach; ?>
                <?php if (empty($todas_turmas)): ?>
                    <p style="color: var(--text-muted); font-size: var(--fs-sm); font-weight:600;">Nenhuma turma cadastrada nesta unidade.</p>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-sq" style="width:100%; height: 50px; margin-top: 30px;">SALVAR VÍNCULOS</button>
        </div>
    </form>
</div>

<!-- TAB FINANCEIRO -->
<div id="financeiro" class="tab-content-aluno">
    <div style="display:grid; grid-template-columns: 1fr 300px; gap:30px;">
        <div class="dashboard-container" style="padding: 40px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Histórico Financeiro</h2>
            </div>
            <table style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #133080;">
                        <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Vencimento</th>
                        <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Valor</th>
                        <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Status</th>
                        <th style="padding: 15px 10px; text-align: right; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($financeiro as $f): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px 10px; font-size: var(--fs-sm); font-weight: 800;"><?php echo date('d/m/y', strtotime($f['data_vencimento'])); ?></td>
                            <td style="padding: 15px 10px; font-size: var(--fs-sm); font-weight: 900;">R$ <?php echo number_format($f['valor'], 2, ',', '.'); ?></td>
                            <td style="padding: 15px 10px;">
                                <span style="display: inline-block; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; background: <?php echo $f['status'] == 'pago' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $f['status'] == 'pago' ? '#166534' : '#991b1b'; ?>;">
                                    <?php echo $f['status']; ?>
                                </span>
                            </td>
                            <td style="padding: 15px 10px; text-align: right;">
                                    <div style="display:flex; gap:5px; justify-content: flex-end; align-items: center;">
                                        <?php if ($f['status'] != 'pago'): ?>
                                            <form method="POST" style="display:flex; gap:5px;">
                                                <input type="hidden" name="pagar_id" value="<?php echo $f['id']; ?>">
                                                <select name="forma_pagamento" style="height: 30px; font-size: var(--fs-xs); font-weight: 900; border: 1px solid var(--border-color); background: #fafafa; text-transform: uppercase;">
                                                    <option value="pix">PIX</option>
                                                    <option value="dinheiro">DINHEIRO</option>
                                                </select>
                                                <button type="submit" class="btn-sq" style="height: 30px; font-size: var(--fs-xs); padding: 0 10px;">BAIXA</button>
                                            </form>
                                        <?php endif; ?>
                                        <button onclick='abrirModalEditarPagamento(<?php echo json_encode($f); ?>)' class="btn-sq-light" style="width: 30px; height: 30px; padding: 0;" title="Editar"><i class="fa-solid fa-edit"></i></button>
                                    </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="dashboard-container" style="padding: 30px; height: fit-content; background: #fafafa; border: 1px solid var(--border-color);">
            <div style="border-left: 4px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                <h2 style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Novo Lançamento</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="nova_mensalidade" value="1">
                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Valor da Parcela</label>
                    <input type="number" step="0.01" name="valor" required 
                        style="width:100%; height:50px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:900; color:var(--text-dark);">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Qtd Parcelas</label>
                    <input type="number" name="qtd_parcelas" value="1" min="1" required 
                        style="width:100%; height:50px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:900; color:var(--text-dark);">
                </div>
                <div style="margin-bottom: 25px;">
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Vencimento (1ª Parcela)</label>
                    <input type="date" name="vencimento" required 
                        style="width:100%; height:50px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                </div>
                <button type="submit" class="btn-sq" style="width:100%; height:60px; font-size: var(--fs-sm);">
                    <i class="fa-solid fa-plus-circle" style="margin-right:8px;"></i>GERAR AGORA
                </button>
            </form>
        </div>
    </div>
</div>

<!-- TAB COMPETICOES -->
<div id="competicoes" class="tab-content-aluno">
    <div class="dashboard-container" style="padding: 40px;">
        <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
            <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Histórico de Competições</h2>
        </div>
        <div class="table-responsive">
            <table style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #133080;">
                        <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Evento</th>
                        <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Data</th>
                        <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Categoria</th>
                        <th style="padding: 15px 10px; text-align: right; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($competicoes)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding:40px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Nenhuma competição registrada.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($competicoes as $c): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px 10px; font-size: var(--fs-sm); font-weight: 900; text-transform: uppercase;"><?php echo $c['comp_nome']; ?></td>
                            <td style="padding: 15px 10px; font-size: var(--fs-sm); font-weight: 800; color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($c['data_evento'])); ?></td>
                            <td style="padding: 15px 10px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase;"><?php echo $c['categoria']; ?></td>
                            <td style="padding: 15px 10px; text-align: right;">
                                <span style="display: inline-block; padding: 4px 12px; font-size: var(--fs-xs); font-weight: 900; background: #133080; color: var(--primary-green); text-transform: uppercase;">
                                    <?php echo $c['resultado'] ?: 'Pendente'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div><!-- Fim Coluna Principal -->
</div><!-- Fim Layout Wrapper -->

<!-- TAB EXAMES DE FAIXA -->
<div id="exames_faixa" class="tab-content-aluno">
    <div class="dashboard-container" style="padding: 40px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:30px; flex-wrap:wrap; gap:15px;">
            <div style="border-left: 6px solid #6366f1; padding-left: 15px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1; color:#1e293b;">Exames de Faixa</h2>
                <p style="font-size: var(--fs-xs); color: var(--text-muted); margin: 6px 0 0 0; font-weight:600;">Histórico de participações em exames de graduação.</p>
            </div>
            <a href="faixas.php" class="btn-sq-outline" style="width:auto; padding:10px 20px; font-size:var(--fs-xs); display:flex; align-items:center; gap:8px; text-decoration:none;">
                <i class="fa-solid fa-medal"></i> Ver Todos os Exames
            </a>
        </div>

        <?php if (empty($exames_faixa)): ?>
            <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
                <i class="fa-solid fa-medal" style="font-size:3rem; opacity:0.1; display:block; margin-bottom:15px;"></i>
                <p style="font-weight:800; text-transform:uppercase; font-size:var(--fs-sm);">Nenhum exame de faixa registrado para este aluno.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #133080;">
                            <th style="padding:15px 12px; text-align:left; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase; color:var(--text-muted);">Evento</th>
                            <th style="padding:15px 12px; text-align:left; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase; color:var(--text-muted);">Data</th>
                            <th style="padding:15px 12px; text-align:left; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase; color:var(--text-muted);">Faixa Pretendida</th>
                            <th style="padding:15px 12px; text-align:left; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase; color:var(--text-muted);">Taxa</th>
                            <th style="padding:15px 12px; text-align:left; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase; color:var(--text-muted);">Status Pgto</th>
                            <th style="padding:15px 12px; text-align:left; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase; color:var(--text-muted);">Resultado</th>
                            <th style="padding:15px 12px; text-align:right; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase; color:var(--text-muted);">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exames_faixa as $ef): ?>
                            <tr style="border-bottom:1px solid var(--border-color); transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                <td style="padding:15px 12px;">
                                    <div style="font-weight:900; font-size:var(--fs-sm); color:#1e293b;">
                                        <?php echo htmlspecialchars($ef['evento_titulo']); ?>
                                    </div>
                                    <?php if ($ef['local']): ?>
                                        <div style="font-size:var(--fs-xs); color:var(--text-muted); margin-top:3px; font-weight:600;">
                                            <i class="fa-solid fa-location-dot" style="margin-right:4px;"></i><?php echo htmlspecialchars($ef['local']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:15px 12px; font-size:var(--fs-sm); font-weight:800;">
                                    <?php echo $ef['data_evento'] ? date('d/m/Y', strtotime($ef['data_evento'])) : '—'; ?>
                                </td>
                                <td style="padding:15px 12px;">
                                    <?php if ($ef['faixa_pretendida']): ?>
                                        <span style="display:inline-block; padding:4px 10px; background:#f0f0ff; color:#4f46e5; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;">
                                            <?php echo htmlspecialchars($ef['faixa_pretendida']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-weight:600; font-size:var(--fs-xs);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:15px 12px; font-size:var(--fs-sm); font-weight:800;">
                                    <?php echo $ef['taxa'] > 0 ? 'R$ ' . number_format($ef['taxa'], 2, ',', '.') : '—'; ?>
                                </td>
                                <td style="padding:15px 12px;">
                                    <?php
                                        $pago = $ef['taxa_paga'] ?? 0;
                                        $taxa_val = (float)($ef['taxa'] ?? 0);
                                        if ($taxa_val <= 0) {
                                            echo '<span style="font-size:var(--fs-xs); font-weight:700; color:var(--text-muted);">Isento</span>';
                                        } elseif ($pago) {
                                            echo '<span style="display:inline-block; padding:4px 10px; background:#dcfce7; color:#166534; font-size:var(--fs-xs); font-weight:900;">PAGO</span>';
                                        } else {
                                            echo '<span style="display:inline-block; padding:4px 10px; background:#fee2e2; color:#991b1b; font-size:var(--fs-xs); font-weight:900;">PENDENTE</span>';
                                        }
                                    ?>
                                </td>
                                <td style="padding:15px 12px;">
                                    <?php
                                        $res = $ef['resultado'] ?? '';
                                        if ($res === 'aprovado') {
                                            echo '<span style="display:inline-block; padding:4px 12px; background:#133080; color:#a3e635; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;"><i class="fa-solid fa-check" style="margin-right:5px;"></i>APROVADO</span>';
                                        } elseif ($res === 'reprovado') {
                                            echo '<span style="display:inline-block; padding:4px 12px; background:#fee2e2; color:#991b1b; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;"><i class="fa-solid fa-xmark" style="margin-right:5px;"></i>REPROVADO</span>';
                                        } else {
                                            echo '<span style="display:inline-block; padding:4px 12px; background:#f1f5f9; color:#64748b; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;">PENDENTE</span>';
                                        }
                                    ?>
                                </td>
                                <td style="padding:15px 12px; text-align:right;">
                                    <a href="editar_evento_faixa.php?id=<?php echo $ef['evento_id']; ?>" 
                                       class="btn-sq-outline" style="padding:6px 14px; font-size:var(--fs-xs); text-decoration:none;">
                                        Ver Exame
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

</section>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- MODAL: EXCLUIR ALUNO                                    -->
<!-- ═══════════════════════════════════════════════════════ -->
<div id="modal_excluir_aluno" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:3000; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
    <div style="background:#fff; border: 2px solid #ef4444; width:95%; max-width:460px; padding:40px; position:relative;">
        <div style="width:60px; height:60px; background:#fee2e2; display:flex; align-items:center; justify-content:center; margin-bottom:25px;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:28px; color:#ef4444;"></i>
        </div>
        <h2 style="font-size:1.2rem; font-weight:900; text-transform:uppercase; margin:0 0 10px 0; color:#1e293b;">
            Excluir Aluno
        </h2>
        <p style="font-size:var(--fs-sm); color:var(--text-muted); margin:0 0 20px 0; font-weight:600; line-height:1.6;">
            Esta ação é <strong style="color:#ef4444;">permanente e irreversível</strong>. Todos os vínculos de turmas, mensalidades e competições serão removidos.
        </p>
        <p style="font-size:var(--fs-xs); color:#64748b; font-weight:700; margin:0 0 12px 0; text-transform:uppercase; letter-spacing:0.05em;">
            Para confirmar, digite o código do aluno:
        </p>
        <p style="font-size:1.5rem; font-weight:900; color:#ef4444; background:#fee2e2; padding:12px 15px; margin-bottom:20px; text-align:center; letter-spacing:0.15em;">
            #<?php echo $id; ?>
        </p>
        <form method="POST">
            <input type="hidden" name="excluir_aluno" value="1">
            <div style="margin-bottom:20px;">
                <input type="number" name="codigo_confirmacao" id="input_cod_confirmacao"
                    placeholder="Digite o número acima..."
                    oninput="verificarCodigoExclusao(this.value)"
                    style="width:100%; height:55px; border:2px solid #ef4444; padding:0 15px; font-size:1.4rem; font-weight:900; text-align:center; outline:none; color:#1e293b; background:#fff; letter-spacing:0.1em;"
                    autocomplete="off">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <button type="submit" id="btn_confirmar_exclusao" disabled
                    style="height:50px; font-size:var(--fs-sm); font-weight:900; text-transform:uppercase; letter-spacing:0.05em; cursor:not-allowed; border:none; background:#d1d5db; color:#9ca3af; transition:all 0.2s;">
                    <i class="fa-solid fa-trash-can" style="margin-right:8px;"></i>EXCLUIR
                </button>
                <button type="button" onclick="fecharModalExcluir()"
                    style="height:50px; font-size:var(--fs-sm); font-weight:900; text-transform:uppercase; cursor:pointer; border:2px solid var(--border-color); background:transparent; color:var(--text-muted); transition:all 0.2s;">
                    CANCELAR
                </button>
            </div>
        </form>
    </div>
</div>



<!-- Modal Editar Pagamento -->
<div id="modal_editar_pagamento" class="modal-sq" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div class="modal-content-sq" style="background:#fff; border: 1px solid #08153a; width:95%; max-width:500px; padding:40px;">
        <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin-bottom: 30px; border-left: 5px solid #133080; padding-left: 15px;">Editar Pagamento</h2>
        <form method="POST">
            <input type="hidden" name="editar_mensalidade" value="1">
            <input type="hidden" name="m_id" id="edit_m_id">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div class="form-group-sq">
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Valor (R$)</label>
                    <input type="number" step="0.01" name="valor" id="edit_m_valor" required style="width:100%; height:45px; border:1px solid var(--border-color); padding:0 15px; font-weight:700;">
                </div>
                <div class="form-group-sq">
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Status</label>
                    <select name="status" id="edit_m_status" required style="width:100%; height:45px; border:1px solid var(--border-color); padding:0 15px; font-weight:700;">
                        <option value="pendente">PENDENTE</option>
                        <option value="pago">PAGO</option>
                        <option value="cancelado">CANCELADO</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:15px;">
                <div class="form-group-sq">
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Vencimento</label>
                    <input type="date" name="data_vencimento" id="edit_m_vencimento" required style="width:100%; height:45px; border:1px solid var(--border-color); padding:0 15px; font-weight:700;">
                </div>
                <div class="form-group-sq">
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Data Pagto</label>
                    <input type="date" name="data_pagamento" id="edit_m_pagamento" style="width:100%; height:45px; border:1px solid var(--border-color); padding:0 15px; font-weight:700;">
                </div>
            </div>

            <div class="form-group-sq" style="margin-top:15px;">
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Meio de Pagamento</label>
                <select name="forma_pagamento" id="edit_m_forma" style="width:100%; height:45px; border:1px solid var(--border-color); padding:0 15px; font-weight:700;">
                    <option value="">Nenhum</option>
                    <option value="pix">PIX</option>
                    <option value="dinheiro">DINHEIRO</option>
                    <option value="cartao">CARTÃO CRÉDITO/DÉBITO</option>
                    <option value="transferencia">TRANSFERÊNCIA</option>
                </select>
            </div>

            <div class="form-group-sq" style="margin-top:15px;">
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Qtd Parcelas (Gerar a partir deste vencimento)</label>
                <input type="number" name="qtd_parcelas" id="edit_m_qtd_parcelas" value="1" min="1" required style="width:100%; height:45px; border:1px solid var(--border-color); padding:0 15px; font-weight:700;">
            </div>

            <div style="margin-top:15px; padding: 15px; background: #f8fafc; border: 1px solid var(--border-color);">
                <p style="font-size: var(--fs-xs); font-weight: 900; margin-bottom: 10px; text-transform: uppercase; color: var(--text-muted);">Aplicar o Valor também em:</p>
                <label style="display:flex; align-items:center; gap:10px; font-size: var(--fs-xs); cursor:pointer;"><input type="radio" name="aplicar_edicao" value="atual" checked> APENAS ESTA MENSALIDADE (ATUAL)</label>
                <label style="display:flex; align-items:center; gap:10px; font-size: var(--fs-xs); cursor:pointer; margin-top:10px;"><input type="radio" name="aplicar_edicao" value="passadas"> ESTA E AS PENDENTES ANTERIORES</label>
                <label style="display:flex; align-items:center; gap:10px; font-size: var(--fs-xs); cursor:pointer; margin-top:10px;"><input type="radio" name="aplicar_edicao" value="futuras"> ESTA E AS PENDENTES FUTURAS</label>
                <p style="font-size: 0.65rem; color: var(--text-muted); margin: 10px 0 0; text-transform: none;">Mensalidades já pagas nunca são alteradas.</p>
            </div>

            <div style="margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button type="submit" class="btn-sq">SALVAR</button>
                <button type="button" onclick="document.getElementById('modal_editar_pagamento').style.display='none'" class="btn-sq-light">CANCELAR</button>
            </div>
        </form>

        <form method="POST" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color);" onsubmit="return confirm('Tem certeza que deseja excluir?')">
            <input type="hidden" name="excluir_mensalidade" value="1">
            <input type="hidden" name="m_id" id="delete_m_id">
            <div style="margin-bottom: 12px;">
                <label style="display:flex; align-items:center; gap:8px; font-size: var(--fs-xs); cursor:pointer;"><input type="radio" name="excluir_escopo" value="esta" checked> EXCLUIR APENAS ESTA</label>
                <label style="display:flex; align-items:center; gap:8px; font-size: var(--fs-xs); cursor:pointer; margin-top:6px;"><input type="radio" name="excluir_escopo" value="futuros"> ESTA E AS PENDENTES FUTURAS</label>
                <label style="display:flex; align-items:center; gap:8px; font-size: var(--fs-xs); cursor:pointer; margin-top:6px;"><input type="radio" name="excluir_escopo" value="todos"> TODAS AS PENDENTES DO ALUNO</label>
            </div>
            <button type="submit" class="btn-sq-danger" style="font-size: var(--fs-xs);">Excluir Lançamento</button>
        </form>
    </div>
</div>

<script>
    function filtrarTurmasEdit() {
        // Suporta tanto o select academia_id_turma (aba Turmas) quanto academia_id (aba Dados)
        const selAcad = document.getElementById('academia_id_turma') || document.getElementById('academia_id');
        const academiaId = selAcad ? selAcad.value : '';

        // Filtrar cards de turma na aba Turmas — apenas mostra/oculta, nunca desmarca
        const listaTurmas = document.getElementById('lista-turmas');
        if (listaTurmas) {
            const labels = listaTurmas.querySelectorAll('label[data-academia]');
            labels.forEach(label => {
                const labAcad = label.getAttribute('data-academia');
                label.style.display = (!academiaId || labAcad == academiaId) ? 'flex' : 'none';
            });
        }
    }

    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content-aluno");
        for (i = 0; i < tabcontent.length; i++) tabcontent[i].classList.remove("active");
        tablinks = document.getElementsByClassName("tab-btn-inner");
        for (i = 0; i < tablinks.length; i++) tablinks[i].classList.remove("active");
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }

    function atualizarPrecoEdit(cb) {
        if (cb.checked) {
            const preco = cb.getAttribute('data-preco');
            if (preco) document.getElementById('valor_mensal_edit').value = preco;
        }
    }

    function atualizarPreco(select) {
        const selectedOption = select.options[select.selectedIndex];
        const preco = selectedOption.getAttribute('data-preco');
        if (preco) document.getElementById('valor_mensal').value = preco;
    }

    function abrirModalEditarPagamento(f) {
        document.getElementById('edit_m_id').value = f.id;
        document.getElementById('delete_m_id').value = f.id;
        document.getElementById('edit_m_valor').value = f.valor;
        document.getElementById('edit_m_status').value = f.status;
        document.getElementById('edit_m_vencimento').value = f.data_vencimento;
        document.getElementById('edit_m_pagamento').value = f.data_pagamento || '';
        document.getElementById('edit_m_forma').value = f.forma_pagamento || '';
        document.querySelector('input[name="aplicar_edicao"][value="atual"]').checked = true;
        document.querySelector('input[name="excluir_escopo"][value="esta"]').checked = true;
        document.getElementById('edit_m_qtd_parcelas').value = 1;
        document.getElementById('modal_editar_pagamento').style.display = 'flex';
    }

    function verificarIdade(dataNasc) {
        if (!dataNasc) return;
        const hoje = new Date();
        const nasc = new Date(dataNasc);
        let idade = hoje.getFullYear() - nasc.getFullYear();
        const m = hoje.getMonth() - nasc.getMonth();
        if (m < 0 || (m === 0 && hoje.getDate() < nasc.getDate())) idade--;

        const secaoResp = document.getElementById('secao-responsavel');
        if (secaoResp) {
            secaoResp.style.display = (idade < 18) ? 'block' : 'none';
        }
    }

    async function buscarCEP(cep) {
        cep = cep.replace(/\D/g, '');
        if (cep.length !== 8) return;
        try {
            const resp = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await resp.json();
            if (!data.erro) {
                document.getElementById('endereco').value = data.logradouro;
                document.getElementById('bairro').value = data.bairro;
                document.getElementById('cidade').value = data.localidade;
                document.getElementById('estado').value = data.uf;
            }
        } catch (e) { }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof IMask !== 'undefined') {
            const maskOptions = {
                cpf: { mask: '000.000.000-00' },
                cep: { mask: '00000-000' },
                telefone: { mask: '(00) 00000-0000' }
            };

            document.querySelectorAll('input[name="cpf"], input[name="responsavel_cpf"]').forEach(el => IMask(el, maskOptions.cpf));
            document.querySelectorAll('input[name="cep"]').forEach(el => IMask(el, maskOptions.cep));
            document.querySelectorAll('input[name="telefone"], input[name="responsavel_telefone"]').forEach(el => IMask(el, maskOptions.telefone));
        }

        const d = document.getElementById('data_nascimento').value;
        if (d) verificarIdade(d);
        filtrarTurmasEdit();
    });

    // ── Validação de código numérico para excluir aluno ───────────────────
    const _idAluno = <?php echo (int)$id; ?>;

    function verificarCodigoExclusao(valor) {
        const btn = document.getElementById('btn_confirmar_exclusao');
        const match = parseInt(valor, 10) === _idAluno;
        btn.disabled = !match;
        btn.style.background = match ? '#ef4444' : '#d1d5db';
        btn.style.color      = match ? '#fff'     : '#9ca3af';
        btn.style.cursor     = match ? 'pointer'  : 'not-allowed';
    }

    function fecharModalExcluir() {
        document.getElementById('modal_excluir_aluno').style.display = 'none';
        const inp = document.getElementById('input_cod_confirmacao');
        if (inp) inp.value = '';
        const btn = document.getElementById('btn_confirmar_exclusao');
        if (btn) {
            btn.disabled = true;
            btn.style.background = '#d1d5db';
            btn.style.color      = '#9ca3af';
            btn.style.cursor     = 'not-allowed';
        }
    }
</script>

<?php include 'footer.php'; ?>