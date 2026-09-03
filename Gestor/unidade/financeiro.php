<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('financeiro_visualizar')) {
    header('Location: administrativo_dashboard.php?erro=sem_permissao');
    exit;
}

// Lógica de POST - Novo Lançamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_lancamento'])) {
    if (!temPermissaoModulo('financeiro_criar')) {
        header("Location: financeiro.php?erro=sem_permissao");
        exit;
    }
    $tipo = $_POST['tipo'];
    $descr_base = $_POST['descricao'];
    $valor = str_replace(['.', ','], ['', '.'], $_POST['valor']);
    $venc_base = $_POST['data_vencimento'];
    $status_inicial = $_POST['status'] ?? 'pendente';
    $cat = $_POST['categoria_id'] ?: null;

    if (!empty($_POST['nova_categoria_nome'])) {
        $stmt_cat = $pdo->prepare("INSERT INTO financeiro_categorias (unidade_id, nome, tipo) VALUES (?, ?, ?)");
        $stmt_cat->execute([$unidade_id, $_POST['nova_categoria_nome'], $tipo]);
        $cat = $pdo->lastInsertId();
    }
    
    $recorrencia = $_POST['recorrencia'] ?? 'nenhuma';
    $num_repeticoes = (int)($_POST['num_repeticoes'] ?? 1);
    $acad_id_post = $_POST['academia_id_post'] ?: null;
    $num_repeticoes = max(1, $num_repeticoes);

    $valor_total = (float)$valor;
    $valor_parcela = $valor_total;
    if ($recorrencia == 'parcelado_mensal') {
        $valor_parcela = floor(($valor_total / $num_repeticoes) * 100) / 100;
        $recorrencia = 'mensal';
    }

    $parent_id = null;
    for ($i = 0; $i < $num_repeticoes; $i++) {
        $data_obj = new DateTime($venc_base);
        if ($recorrencia == 'mensal') $data_obj->modify("+$i month");
        elseif ($recorrencia == 'quinzenal') { $days = $i * 15; $data_obj->modify("+$days day"); }
        elseif ($recorrencia == 'semanal') { $days = $i * 7; $data_obj->modify("+$days day"); }
        elseif ($recorrencia == 'anual') $data_obj->modify("+$i year");
        
        $venc_atual = $data_obj->format('Y-m-d');
        $descr = ($num_repeticoes > 1) ? "$descr_base (" . ($i+1) . "/$num_repeticoes)" : $descr_base;
        $valor_final = ($i == $num_repeticoes - 1 && ($_POST['recorrencia'] ?? '') == 'parcelado_mensal') ? 
                      round($valor_total - ($valor_parcela * ($num_repeticoes - 1)), 2) : $valor_parcela;

        $stmt = $pdo->prepare("INSERT INTO financeiro_lancamentos 
            (unidade_id, academia_id, categoria_id, tipo, descricao, valor, data_vencimento, status, recorrencia, total_parcelas, parcela_atual, parent_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $unidade_id, $acad_id_post, $cat, $tipo, $descr, $valor_final, $venc_atual, 
            ($i == 0 ? $status_inicial : 'pendente'), $recorrencia, $num_repeticoes, ($i + 1), $parent_id
        ]);
        if ($i == 0) $parent_id = $pdo->lastInsertId();
    }
    header("Location: financeiro.php"); exit;
}

// Lógica de POST - Baixar Lançamento (Receita)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['baixar_lancamento'])) {
    if (!temPermissaoModulo('financeiro_editar')) {
        header("Location: financeiro.php?erro=sem_permissao");
        exit;
    }
    $id = $_POST['lancamento_id'];
    $origem = $_POST['origem'] ?? 'lancamento';
    $forma = $_POST['forma_pagamento'] ?? 'dinheiro';
    $data_pago = $_POST['data_pagamento'] ?: date('Y-m-d');
    
    if ($origem === 'mensalidade') {
        $stmt = $pdo->prepare("UPDATE mensalidades SET status = 'pago', forma_pagamento = ?, data_pagamento = ? WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$forma, $data_pago, $id, $unidade_id]);
        registrarComissoesMensalidade($id, $unidade_id);
    } else {
        $stmt = $pdo->prepare("UPDATE financeiro_lancamentos SET status = 'pago', forma_pagamento = ?, data_pagamento = ? WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$forma, $data_pago, $id, $unidade_id]);
    }
    header("Location: financeiro.php"); exit;
}

// Lógica de POST - Editar Lançamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_lancamento'])) {
    if (!temPermissaoModulo('financeiro_editar')) {
        header("Location: financeiro.php?erro=sem_permissao");
        exit;
    }
    $id = $_POST['lancamento_id'];
    $descr = $_POST['descricao'];
    $valor = str_replace(['.', ','], ['', '.'], $_POST['valor']);
    $venc = $_POST['data_vencimento'];
    $cat = $_POST['categoria_id'] ?: null;
    $acad = $_POST['academia_id_post'] ?: null;
    $aplicar_edicao = $_POST['aplicar_edicao'] ?? 'atual';

    if (!empty($_POST['nova_categoria_nome'])) {
        $stmt_cat = $pdo->prepare("INSERT INTO financeiro_categorias (unidade_id, nome, tipo) VALUES (?, ?, ?)");
        $stmt_cat->execute([$unidade_id, $_POST['nova_categoria_nome'], 'misto']);
        $cat = $pdo->lastInsertId();
    }

    // A parcela editada sempre recebe todos os campos exatamente como digitado
    $stmt = $pdo->prepare("UPDATE financeiro_lancamentos SET descricao = ?, valor = ?, data_vencimento = ?, categoria_id = ?, academia_id = ? WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$descr, $valor, $venc, $cat, $acad, $id, $unidade_id]);

    // Demais parcelas da série (passadas/futuras) recebem apenas Valor, Categoria e Academia,
    // preservando a descrição (numeração "x/y") e o vencimento próprios de cada parcela
    if ($aplicar_edicao === 'passadas' || $aplicar_edicao === 'futuras') {
        $stmt_check = $pdo->prepare("SELECT id, parent_id, parcela_atual FROM financeiro_lancamentos WHERE id = ? AND unidade_id = ?");
        $stmt_check->execute([$id, $unidade_id]);
        $item = $stmt_check->fetch();

        if ($item) {
            $parent_ref = $item['parent_id'] ?: $item['id'];
            $operador = ($aplicar_edicao === 'passadas') ? '<=' : '>=';
            $stmt_serie = $pdo->prepare("UPDATE financeiro_lancamentos SET valor = ?, categoria_id = ?, academia_id = ? WHERE (id = ? OR parent_id = ?) AND unidade_id = ? AND id != ? AND parcela_atual $operador ?");
            $stmt_serie->execute([$valor, $cat, $acad, $parent_ref, $parent_ref, $unidade_id, $id, $item['parcela_atual']]);
        }
    }
    header("Location: financeiro.php"); exit;
}

// Lógica de POST - Excluir Lançamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_lancamento'])) {
    if (!temPermissaoModulo('financeiro_remover')) {
        header("Location: financeiro.php?erro=sem_permissao");
        exit;
    }
    $id = $_POST['lancamento_id'];
    $escopo = $_POST['excluir_escopo'] ?? 'esta';
    $stmt_check = $pdo->prepare("SELECT id, parent_id, parcela_atual FROM financeiro_lancamentos WHERE id = ? AND unidade_id = ?");
    $stmt_check->execute([$id, $unidade_id]);
    $item = $stmt_check->fetch();
    if ($item) {
        if ($escopo === 'todos') {
            $parent_ref = $item['parent_id'] ?: $item['id'];
            $pdo->prepare("DELETE FROM financeiro_lancamentos WHERE (id = ? OR parent_id = ?) AND unidade_id = ?")->execute([$parent_ref, $parent_ref, $unidade_id]);
        } elseif ($escopo === 'futuros') {
            $parent_ref = $item['parent_id'] ?: $item['id'];
            $pdo->prepare("DELETE FROM financeiro_lancamentos WHERE (id = ? OR parent_id = ?) AND unidade_id = ? AND parcela_atual >= ?")->execute([$parent_ref, $parent_ref, $unidade_id, $item['parcela_atual']]);
        } else {
            $pdo->prepare("DELETE FROM financeiro_lancamentos WHERE id = ? AND unidade_id = ?")->execute([$id, $unidade_id]);
        }
    }
    header("Location: financeiro.php"); exit;
}

// Lógica de POST - Categorias
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_categoria'])) {
    if (!temPermissaoModulo('financeiro_criar')) {
        header("Location: financeiro.php?erro=sem_permissao");
        exit;
    }
    $pdo->prepare("INSERT INTO financeiro_categorias (unidade_id, nome, tipo) VALUES (?, ?, ?)")->execute([$unidade_id, $_POST['nome'], $_POST['tipo'] ?? 'receita']);
    header("Location: financeiro.php"); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_categoria'])) {
    if (!temPermissaoModulo('financeiro_remover')) {
        header("Location: financeiro.php?erro=sem_permissao");
        exit;
    }
    $pdo->prepare("DELETE FROM financeiro_categorias WHERE id = ? AND unidade_id = ?")->execute([$_POST['categoria_id'], $unidade_id]);
    header("Location: financeiro.php"); exit;
}

$custom_title = "Financeiro: Receitas";
include 'header.php';
?>

<?php
$mes_atual = isset($_GET['mes']) ? str_pad($_GET['mes'], 2, '0', STR_PAD_LEFT) : date('m');
$ano_atual = isset($_GET['ano']) ? $_GET['ano'] : date('Y');
$academia_id_filtro = $_GET['academia_id'] ?? null;

$data_nav = new DateTime("$ano_atual-$mes_atual-01");
$prev_month = (clone $data_nav)->modify('-1 month');
$next_month = (clone $data_nav)->modify('+1 month');

$prev_url = "financeiro.php?" . http_build_query(array_merge($_GET, ['mes' => $prev_month->format('m'), 'ano' => $prev_month->format('Y')]));
$next_url = "financeiro.php?" . http_build_query(array_merge($_GET, ['mes' => $next_month->format('m'), 'ano' => $next_month->format('Y')]));

$meses_nomes = ['01' => 'Janeiro','02' => 'Fevereiro','03' => 'Março','04' => 'Abril','05' => 'Maio','06' => 'Junho','07' => 'Julho','08' => 'Agosto','09' => 'Setembro','10' => 'Outubro','11' => 'Novembro','12' => 'Dezembro'];

// Filtro de Academia
$where_m_f = $academia_id_filtro ? " AND m.academia_id = ? " : "";
$where_l_f = $academia_id_filtro ? " AND l.academia_id = ? " : "";
$where_stats_f = $academia_id_filtro ? " AND academia_id = ? " : "";

// Resumo
$stats_sql = "
    SELECT 
        (SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE unidade_id = ? $where_stats_f AND status = 'pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) +
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos WHERE unidade_id = ? $where_stats_f AND status = 'pago' AND tipo = 'receita' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) as total_pago,
        
        (SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE unidade_id = ? $where_stats_f AND status = 'pendente' AND data_vencimento >= CURDATE() AND MONTH(data_vencimento) = ? AND YEAR(data_vencimento) = ? AND aluno_id IN (SELECT id FROM alunos WHERE status != 'inativo')) +
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos WHERE unidade_id = ? $where_stats_f AND status = 'pendente' AND tipo = 'receita' AND data_vencimento >= CURDATE() AND MONTH(data_vencimento) = ? AND YEAR(data_vencimento) = ?) as total_pendente,
        
        (SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE unidade_id = ? $where_stats_f AND (status = 'atrasado' OR (status = 'pendente' AND data_vencimento < CURDATE())) AND MONTH(data_vencimento) = ? AND YEAR(data_vencimento) = ? AND aluno_id IN (SELECT id FROM alunos WHERE status != 'inativo')) +
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos WHERE unidade_id = ? $where_stats_f AND (status = 'pendente' AND data_vencimento < CURDATE()) AND tipo = 'receita' AND MONTH(data_vencimento) = ? AND YEAR(data_vencimento) = ?) as total_atrasado
";
$stats = $pdo->prepare($stats_sql);

$p_stats = [];
for($i=0; $i<6; $i++){
    $p_stats[] = $unidade_id;
    if($academia_id_filtro) $p_stats[] = $academia_id_filtro;
    $p_stats[] = $mes_atual;
    $p_stats[] = $ano_atual;
}
$stats->execute($p_stats);
$resumo = $stats->fetch();

// Dados consolidado
$stmt_m = $pdo->prepare(" SELECT a.nome_completo as titulo, a.responsavel_nome as responsavel_nome, acad.nome as academia_nome, 'Mensalidade Aluno' as sub, m.valor, m.data_vencimento, m.data_pagamento, m.status, m.id as id, m.aluno_id as aluno_id, 'mensalidade' as origem, m.academia_id, 0 as categoria_id, 'nenhuma' as recorrencia, NULL as parent_id
    FROM mensalidades m JOIN alunos a ON m.aluno_id=a.id LEFT JOIN academias acad ON m.academia_id = acad.id
    WHERE m.unidade_id=? $where_m_f
    AND (
        (m.status = 'pago' AND MONTH(m.data_pagamento)=? AND YEAR(m.data_pagamento)=?) OR
        (m.status != 'pago' AND MONTH(m.data_vencimento)=? AND YEAR(m.data_vencimento)=? AND a.status != 'inativo')
    ) ");

$p_m = [$unidade_id];
if($academia_id_filtro) $p_m[] = $academia_id_filtro;
array_push($p_m, $mes_atual, $ano_atual, $mes_atual, $ano_atual);
$stmt_m->execute($p_m);
$mensalidades = $stmt_m->fetchAll();

$stmt_l = $pdo->prepare(" SELECT l.descricao as titulo, NULL as responsavel_nome, acad.nome as academia_nome, c.nome as sub, l.valor, l.data_vencimento, l.data_pagamento, l.status, l.id, 'lancamento' as origem, l.academia_id, l.categoria_id, l.recorrencia, l.parent_id 
    FROM financeiro_lancamentos l LEFT JOIN financeiro_categorias c ON l.categoria_id=c.id LEFT JOIN academias acad ON l.academia_id = acad.id
    WHERE l.unidade_id=? AND l.tipo='receita' $where_l_f
    AND (
        (l.status = 'pago' AND MONTH(l.data_pagamento)=? AND YEAR(l.data_pagamento)=?) OR
        (l.status != 'pago' AND MONTH(l.data_vencimento)=? AND YEAR(l.data_vencimento)=?)
    ) ");

$p_l = [$unidade_id];
if($academia_id_filtro) $p_l[] = $academia_id_filtro;
array_push($p_l, $mes_atual, $ano_atual, $mes_atual, $ano_atual);
$stmt_l->execute($p_l);
$lancamentosExtra = $stmt_l->fetchAll();

$financeiro_data = array_merge($mensalidades, $lancamentosExtra);

$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$status_filtro = isset($_GET['status_filtro']) ? trim($_GET['status_filtro']) : '';
$categoria_id_filtro = isset($_GET['categoria_id_filtro']) ? trim($_GET['categoria_id_filtro']) : '';

if ($busca !== '') {
    $financeiro_data = array_filter($financeiro_data, function($item) use ($busca) {
        return stripos($item['titulo'], $busca) !== false 
            || stripos($item['sub'], $busca) !== false
            || (!empty($item['responsavel_nome']) && stripos($item['responsavel_nome'], $busca) !== false);
    });
}
if ($status_filtro !== '') {
    $financeiro_data = array_filter($financeiro_data, function($item) use ($status_filtro) {
        $is_atrasado = ($item['status'] === 'pendente' && $item['data_vencimento'] < date('Y-m-d'));
        if ($status_filtro === 'pago') {
            return $item['status'] === 'pago';
        } elseif ($status_filtro === 'atrasado') {
            return $is_atrasado || $item['status'] === 'atrasado';
        } elseif ($status_filtro === 'pendente') {
            return $item['status'] === 'pendente' && !$is_atrasado;
        }
        return true;
    });
}
if ($categoria_id_filtro !== '') {
    $financeiro_data = array_filter($financeiro_data, function($item) use ($categoria_id_filtro) {
        return (int)$item['categoria_id'] === (int)$categoria_id_filtro;
    });
}

$sort_col = $_GET['sort'] ?? 'titulo';
$sort_dir = $_GET['dir'] ?? 'asc';

usort($financeiro_data, function($a, $b) use ($sort_col, $sort_dir) {
    $valA = $a[$sort_col] ?? '';
    $valB = $b[$sort_col] ?? '';

    // Tratar valores numéricos ou strings
    if ($sort_col === 'valor') {
        $cmp = ($valA == $valB) ? 0 : (($valA < $valB) ? -1 : 1);
    } else {
        $cmp = strcasecmp((string)$valA, (string)$valB);
    }

    return ($sort_dir === 'desc') ? -$cmp : $cmp;
});

$categorias_exec = $pdo->prepare("SELECT * FROM financeiro_categorias WHERE unidade_id = ?");
$categorias_exec->execute([$unidade_id]);
$categorias_exec = $categorias_exec->fetchAll();

$stmt_acads = $pdo->prepare("SELECT id, nome FROM academias WHERE unidade_id = ? ORDER BY nome ASC");
$stmt_acads->execute([$unidade_id]);
$acads_filtro = $stmt_acads->fetchAll();
?>

<style>
.modal-sq { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; backdrop-filter: blur(4px); }
.modal-content-sq { background:#fff; border: 1px solid #08153a; width:95%; max-width:550px; padding:40px; }
.form-group-sq { margin-bottom: 20px; }
.form-group-sq label { display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em; }
.form-group-sq input, .form-group-sq select, .form-group-sq textarea { width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700; transition: border-color 0.2s; }
.form-group-sq input:focus { border-color: var(--primary-green); outline:none; }
</style>

<section class="dashboard-section-sq">
    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'sem_permissao'): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> ACESSO NEGADO: Você não tem permissão para realizar esta ação.
        </div>
    <?php endif; ?>

    <!-- Cabeçalho da Página -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="border-left: 8px solid var(--primary-green); padding-left: 20px;">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Contas a Receber
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Gestão de mensalidades, vendas e fluxos de entrada.
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <?php if (temPermissaoModulo('financeiro_criar')): ?>
            <button onclick="document.getElementById('modal_receita').style.display='flex'" class="btn-sq" style="padding: 12px 25px; background: var(--primary-green); border-color: var(--primary-green);">
                <i class="fa-solid fa-plus-square" style="margin-right: 8px;"></i> Nova Receita
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navegação por Abas (Globais) -->
    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
        <a href="alunos.php" class="tab-item-sq">Alunos</a>
        <a href="equipe.php" class="tab-item-sq">RH</a>
        <a href="academias.php" class="tab-item-sq">Unidades</a>
        <a href="turmas.php" class="tab-item-sq">Turmas</a>
        <a href="financeiro.php" class="tab-item-sq active">Financeiro</a>
        <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
    </div>

    <!-- Navegação Interna (Financeiro) -->
    <div class="nav-financeiro-sq" style="margin-bottom: 40px;">
        <a href="financeiro.php" class="btn-nav-financeiro-sq active">RECEITAS</a>
        <a href="financeiro_pagar.php" class="btn-nav-financeiro-sq">DESPESAS</a>
        <a href="financeiro_relatorios.php" class="btn-nav-financeiro-sq">RELATÓRIOS</a>
        <button onclick="document.getElementById('modal_categorias').style.display='flex'" class="btn-nav-financeiro-sq">CATEGORIAS</button>
    </div>

    <!-- Cards de Balanço (Métricas) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px;">
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">Liquidado no Mês</div>
            <div class="value" style="font-size: 32px; font-weight: 900; color: var(--primary-green);">R$ <?php echo number_format($resumo['total_pago'] ?: 0, 2, ',', '.'); ?></div>
            <div style="font-size: var(--fs-sm); color: var(--text-muted); margin-top: 10px; font-weight: 700;">FLUXO DE CAIXA REALIZADO</div>
        </div>
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">Previsão (Aberto)</div>
            <div class="value" style="font-size: 32px; font-weight: 900; color: #3b82f6;">R$ <?php echo number_format($resumo['total_pendente'] ?: 0, 2, ',', '.'); ?></div>
            <div style="font-size: var(--fs-sm); color: var(--text-muted); margin-top: 10px; font-weight: 700;">AGUARDANDO VENCIMENTO</div>
        </div>
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">Vencido</div>
            <div class="value" style="font-size: 32px; font-weight: 900; color: #ef4444;">R$ <?php echo number_format($resumo['total_atrasado'] ?: 0, 2, ',', '.'); ?></div>
            <div style="font-size: var(--fs-sm); color: var(--text-muted); margin-top: 10px; font-weight: 700;">INADIMPLÊNCIA CRÍTICA</div>
        </div>
    </div>

    <!-- Navegação de Período e Filtros de Busca -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 20px;">
        <div style="display: flex; align-items: center; background: #fafafa; border: 1px solid var(--border-color);">
            <a href="<?php echo $prev_url; ?>" class="btn-sq-light" style="width: 45px; height: 45px; padding:0; display:flex; align-items:center; justify-content:center; border:none;"><i class="fa-solid fa-chevron-left"></i></a>
            <div style="padding: 0 25px; font-size: var(--fs-sm); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo $meses_nomes[$mes_atual]; ?> <?php echo $ano_atual; ?></div>
            <a href="<?php echo $next_url; ?>" class="btn-sq-light" style="width: 45px; height: 45px; padding:0; display:flex; align-items:center; justify-content:center; border:none;"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>

    <div style="background: #fafafa; border: 1px solid var(--border-color); padding: 20px; margin-bottom: 30px;">
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end;">
            <input type="hidden" name="mes" value="<?php echo $mes_atual; ?>">
            <input type="hidden" name="ano" value="<?php echo $ano_atual; ?>">
            
            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase;">Buscar</label>
                <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Cliente ou descrição..." style="height: 45px; background: #fff; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-xs); font-weight: 700; width: 100%;">
            </div>

            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase;">Status</label>
                <select name="status_filtro" style="height: 45px; background: #fff; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; width: 100%;">
                    <option value="">Todos</option>
                    <option value="pago" <?php echo $status_filtro === 'pago' ? 'selected' : ''; ?>>Pago</option>
                    <option value="pendente" <?php echo $status_filtro === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="atrasado" <?php echo $status_filtro === 'atrasado' ? 'selected' : ''; ?>>Vencido</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase;">Categoria</label>
                <select name="categoria_id_filtro" style="height: 45px; background: #fff; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; width: 100%;">
                    <option value="">Todas</option>
                    <?php foreach($categorias_exec as $c): if($c['tipo'] != 'despesa'): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $categoria_id_filtro == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nome']); ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase;">Academia</label>
                <select name="academia_id" style="height: 45px; background: #fff; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; width: 100%;">
                    <option value="">Todas</option>
                    <?php foreach ($acads_filtro as $acad): ?>
                        <option value="<?php echo $acad['id']; ?>" <?php echo $academia_id_filtro == $acad['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($acad['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px; height: 45px;">
                <button type="submit" class="btn-sq" style="flex: 1; height: 100%; display: flex; align-items: center; justify-content: center; font-size: var(--fs-xs); gap: 5px; background: var(--primary-green); border-color: var(--primary-green);">
                    <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                </button>
                <?php if ($busca !== '' || $status_filtro !== '' || $categoria_id_filtro !== '' || $academia_id_filtro): ?>
                    <a href="financeiro.php?mes=<?php echo $mes_atual; ?>&ano=<?php echo $ano_atual; ?>" class="btn-sq-light" style="width: 45px; height: 100%; display: flex; align-items: center; justify-content: center; border-color: var(--border-color); color: #ef4444; text-decoration: none;" title="Limpar Filtros">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <?php
                    // Helper para gerar o link e o ícone de ordenação
                    function renderHeaderSort($label, $col, $current_sort, $current_dir) {
                        $next_dir = ($current_sort === $col && $current_dir === 'asc') ? 'desc' : 'asc';
                        $params = array_merge($_GET, ['sort' => $col, 'dir' => $next_dir]);
                        $link = '?' . http_build_query($params);
                        
                        $icon = '<i class="fa-solid fa-sort" style="margin-left: 8px; opacity: 0.3;"></i>';
                        if ($current_sort === $col) {
                            $icon = ($current_dir === 'asc') 
                                ? '<i class="fa-solid fa-arrow-up-wide-short" style="margin-left: 8px; color: var(--primary-green);"></i>' 
                                : '<i class="fa-solid fa-arrow-down-wide-short" style="margin-left: 8px; color: var(--primary-green);"></i>';
                        }
                        
                        return '<a href="' . $link . '" style="text-decoration: none; color: inherit; display: inline-flex; align-items: center;">' . $label . $icon . '</a>';
                    }
                    ?>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);"><?php echo renderHeaderSort('Alunos', 'titulo', $sort_col, $sort_dir); ?></th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);"><?php echo renderHeaderSort('Vencimento', 'data_vencimento', $sort_col, $sort_dir); ?></th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);"><?php echo renderHeaderSort('Valor', 'valor', $sort_col, $sort_dir); ?></th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);"><?php echo renderHeaderSort('Status', 'status', $sort_col, $sort_dir); ?></th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($financeiro_data as $i): ?>
                    <tr>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;"><?php echo htmlspecialchars($i['titulo']); ?></div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-top: 3px;">
                                <?php echo htmlspecialchars($i['sub']); ?> 
                                <?php if($i['academia_nome']): ?> • <span style="color: var(--primary-green);"><?php echo htmlspecialchars($i['academia_nome']); ?></span><?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 15px 30px; font-size: var(--fs-sm); font-weight: 800; color: var(--text-dark);">
                            <?php echo date('d/m/Y', strtotime($i['data_vencimento'])); ?>
                        </td>
                        <td style="padding: 15px 30px; font-size: var(--fs-base); font-weight: 900; color: var(--text-dark);">
                            R$ <?php echo number_format($i['valor'], 2, ',', '.'); ?>
                        </td>
                        <td style="padding: 15px 30px;">
                            <?php 
                            $is_atrasado = ($i['status'] === 'atrasado' || ($i['status'] === 'pendente' && $i['data_vencimento'] < date('Y-m-d')));
                            if ($i['status'] === 'pago'): ?>
                                <span style="background: var(--primary-green); color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">PAGO</span>
                            <?php elseif ($is_atrasado): ?>
                                <span style="background: #ef4444; color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">VENCIDO</span>
                            <?php else: ?>
                                <span style="background: #3b82f6; color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">PENDENTE</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px; text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <?php if(($i['status'] == 'pendente' || $i['status'] == 'atrasado') && temPermissaoModulo('financeiro_editar')): ?>
                                    <button onclick="abrirModalBaixa(<?php echo $i['id']; ?>, '<?php echo $i['origem']; ?>')" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; color: var(--primary-green); border-color: var(--primary-green);" title="Confirmar Recebimento"><i class="fa-solid fa-check"></i></button>
                                <?php endif; ?>
                                
                                <?php if($i['status'] === 'pago'): ?>
                                    <a href="recibo.php?id=<?php echo $i['id']; ?>&origem=<?php echo $i['origem']; ?>" target="_blank" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; color: #3b82f6; border-color: #3b82f6;" title="Gerar Recibo"><i class="fa-solid fa-file-invoice"></i></a>
                                <?php endif; ?>
                                

                                <?php if($i['origem'] == 'lancamento'): ?>
                                    <?php if (temPermissaoModulo('financeiro_editar')): ?>
                                    <button onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($i)); ?>)" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0;" title="Editar"><i class="fa-solid fa-edit"></i></button>
                                    <?php endif; ?>
                                    <?php if (temPermissaoModulo('financeiro_remover')): ?>
                                    <button onclick="abrirModalExcluir(<?php echo $i['id']; ?>, '<?php echo $i['recorrencia']; ?>')" class="btn-sq-danger" style="width: 35px; height: 35px; padding: 0;" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="editar_aluno.php?id=<?php echo $i['aluno_id']; ?>" class="btn-sq-light" style="height: 35px; font-size: var(--fs-xs); padding: 0 15px; display: flex; align-items: center;" title="Ficha do Aluno">FICHA</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($financeiro_data)): ?>
                    <tr><td colspan="5" style="padding: 80px; text-align: center; color: var(--text-muted); font-weight: 600;">NENHUMA RECEITA PARA ESTE PERÍODO.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<!-- MODAIS -->

<!-- Modal Nova Receita -->
<div id="modal_receita" class="modal-sq">
    <div class="modal-content-sq">
        <h2 style="font-size: 1.2rem; font-weight: 900; text-transform: uppercase; margin-bottom: 30px; border-left: 5px solid #08153a; padding-left: 15px;">Nova Receita Avulsa</h2>
        <form method="POST">
            <input type="hidden" name="novo_lancamento" value="1">
            <input type="hidden" name="tipo" value="receita">
            <div class="form-group-sq">
                <label>Descrição</label>
                <input type="text" name="descricao" required placeholder="EX: VENDA DE UNIFORME">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group-sq">
                    <label>Valor (R$)</label>
                    <input type="text" name="valor" required placeholder="0,00" class="money-mask">
                </div>
                <div class="form-group-sq">
                    <label>Data de Vencimento</label>
                    <input type="date" name="data_vencimento" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group-sq">
                    <label>Academia / Unidade</label>
                    <select name="academia_id_post">
                        <option value="">GERAL (UNIDADE)</option>
                        <?php foreach ($acads_filtro as $acad): ?>
                            <option value="<?php echo $acad['id']; ?>"><?php echo htmlspecialchars($acad['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-sq">
                    <label>Categoria</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="categoria_id" id="cat_select_receita" style="flex: 1;">
                            <option value="">Selecione...</option>
                            <?php foreach($categorias_exec as $c): if($c['tipo'] != 'despesa'): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
                            <?php endif; endforeach; ?>
                        </select>
                        <input type="text" name="nova_categoria_nome" id="cat_input_receita" placeholder="Nova Categoria..." style="display: none; flex: 1; text-transform: uppercase;">
                        <button type="button" onclick="var sel = document.getElementById('cat_select_receita'); var inp = document.getElementById('cat_input_receita'); if(sel.style.display !== 'none') { sel.style.display = 'none'; inp.style.display = 'block'; inp.focus(); sel.value = ''; this.innerHTML = '<i class=\'fa-solid fa-xmark\'></i>'; this.style.color = '#ef4444'; } else { sel.style.display = 'block'; inp.style.display = 'none'; inp.value = ''; this.innerHTML = '<i class=\'fa-solid fa-plus\'></i>'; this.style.color = 'var(--primary-green)'; }" class="btn-sq-light" style="width: 45px; height: 45px; padding: 0; display: flex; align-items: center; justify-content: center; color: var(--primary-green); border-color: var(--border-color);" title="Adicionar Nova Categoria">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px;">
                <div class="form-group-sq">
                    <label>Recorrência</label>
                    <select name="recorrencia" onchange="toggleRepeticoes(this.value, 'novo')">
                        <option value="nenhuma">PAGAMENTO ÚNICO</option>
                        <option value="mensal">MENSAL (VALOR FIXO)</option>
                        <option value="parcelado_mensal">PARCELADO (DIVIDIR VALOR)</option>
                    </select>
                </div>
                <div class="form-group-sq" id="group_repeticoes_novo" style="display:none;">
                    <label>Qtd. Parcelas</label>
                    <input type="number" name="num_repeticoes" value="1" min="1">
                </div>
            </div>
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" class="btn-sq" style="flex: 1;">GERAR RECEITA</button>
                <button type="button" onclick="document.getElementById('modal_receita').style.display='none'" class="btn-sq-light" style="width: 120px;">CANCELAR</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Receita/Lançamento -->
<div id="modal_editar" class="modal-sq">
    <div class="modal-content-sq">
        <h2 style="font-size: 1.2rem; font-weight: 900; text-transform: uppercase; margin-bottom: 30px; border-left: 5px solid #08153a; padding-left: 15px;">Editar Receita</h2>
        <form method="POST">
            <input type="hidden" name="editar_lancamento" value="1">
            <input type="hidden" name="lancamento_id" id="edit_id">
            <div class="form-group-sq">
                <label>Descrição</label>
                <input type="text" name="descricao" id="edit_descricao" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group-sq">
                    <label>Valor (R$)</label>
                    <input type="text" name="valor" id="edit_valor" required>
                </div>
                <div class="form-group-sq">
                    <label>Vencimento</label>
                    <input type="date" name="data_vencimento" id="edit_vencimento" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group-sq">
                    <label>Academia / Unidade</label>
                    <select name="academia_id_post" id="edit_academia">
                        <option value="">GERAL (UNIDADE)</option>
                        <?php foreach ($acads_filtro as $acad): ?>
                            <option value="<?php echo $acad['id']; ?>"><?php echo htmlspecialchars($acad['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-sq">
                    <label>Categoria</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="categoria_id" id="edit_categoria" style="flex: 1;">
                            <option value="">Selecione...</option>
                            <?php foreach($categorias_exec as $c): if($c['tipo'] != 'despesa'): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
                            <?php endif; endforeach; ?>
                        </select>
                        <input type="text" name="nova_categoria_nome" id="edit_cat_input" placeholder="Nova Categoria..." style="display: none; flex: 1; text-transform: uppercase;">
                        <button type="button" onclick="var sel = document.getElementById('edit_categoria'); var inp = document.getElementById('edit_cat_input'); if(sel.style.display !== 'none') { sel.style.display = 'none'; inp.style.display = 'block'; inp.focus(); sel.value = ''; this.innerHTML = '<i class=\'fa-solid fa-xmark\'></i>'; this.style.color = '#ef4444'; } else { sel.style.display = 'block'; inp.style.display = 'none'; inp.value = ''; this.innerHTML = '<i class=\'fa-solid fa-plus\'></i>'; this.style.color = 'var(--primary-green)'; }" class="btn-sq-light" style="width: 45px; height: 45px; padding: 0; display: flex; align-items: center; justify-content: center; color: var(--primary-green); border-color: var(--border-color);" title="Adicionar Nova Categoria">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div id="opcoes_recorrencia_edit" style="display:none; text-align: left; background: #fafafa; border: 1px solid #ddd; padding: 20px; margin: 10px 0 0;">
                <p style="font-size: var(--fs-xs); font-weight: 900; margin-bottom: 10px; text-transform: uppercase;">Lançamento Recorrente — Aplicar alteração em:</p>
                <label style="display:flex; align-items:center; gap:10px; font-size: var(--fs-xs); cursor:pointer;"><input type="radio" name="aplicar_edicao" value="atual" checked> APENAS ESTA PARCELA (ATUAL)</label>
                <label style="display:flex; align-items:center; gap:10px; font-size: var(--fs-xs); cursor:pointer; margin-top:10px;"><input type="radio" name="aplicar_edicao" value="passadas"> ESTA E AS PARCELAS PASSADAS</label>
                <label style="display:flex; align-items:center; gap:10px; font-size: var(--fs-xs); cursor:pointer; margin-top:10px;"><input type="radio" name="aplicar_edicao" value="futuras"> ESTA E AS PARCELAS FUTURAS</label>
            </div>
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" class="btn-sq" style="flex: 1;">ATUALIZAR RECEITA</button>
                <button type="button" onclick="document.getElementById('modal_editar').style.display='none'" class="btn-sq-light" style="width: 120px;">CANCELAR</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Baixar -->
<div id="modal_baixa" class="modal-sq">
    <div class="modal-content-sq" style="max-width: 400px;">
        <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin-bottom: 25px; color: var(--primary-green);">Confirmar Recebimento</h2>
        <form method="POST">
            <input type="hidden" name="baixar_lancamento" value="1">
            <input type="hidden" name="lancamento_id" id="baixa_id">
            <input type="hidden" name="origem" id="baixa_origem">
            <div class="form-group-sq">
                <label>Meio de Pagamento</label>
                <select name="forma_pagamento">
                    <option value="pix">PIX</option>
                    <option value="dinheiro">DINHEIRO</option>
                    <option value="cartao">CARTÃO CRÉDITO/DÉBITO</option>
                    <option value="transferencia">TRANSFERÊNCIA BANCÁRIA</option>
                </select>
            </div>
            <div class="form-group-sq">
                <label>Data da Liquidação</label>
                <input type="date" name="data_pagamento" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <button type="submit" class="btn-sq" style="width: 100%; height: 55px; background: var(--primary-green);">CONFIRMAR BAIXA</button>
            <button type="button" onclick="document.getElementById('modal_baixa').style.display='none'" class="btn-sq-light" style="width: 100%; margin-top: 10px; border:none;">FECHAR</button>
        </form>
    </div>
</div>

<!-- Modal Excluir -->
<div id="modal_excluir" class="modal-sq">
    <div class="modal-content-sq" style="max-width: 400px; text-align: center;">
        <i class="fa-solid fa-triangle-exclamation" style="font-size: 40px; color: #ef4444; margin-bottom: 20px;"></i>
        <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin-bottom: 10px;">Excluir Movimentação?</h2>
        <p style="font-size: var(--fs-sm); color: var(--text-muted); margin-bottom: 30px;">Esta operação não poderá ser revertida no fluxo de caixa.</p>
        <form method="POST">
            <input type="hidden" name="excluir_lancamento" value="1">
            <input type="hidden" name="lancamento_id" id="excluir_id">
            <div id="opcoes_recorrencia" style="display:none; text-align: left; background: #fafafa; border: 1px solid #ddd; padding: 20px; margin-bottom: 25px;">
                <p style="font-size: var(--fs-xs); font-weight: 900; margin-bottom: 10px; text-transform: uppercase;">Lançamento Recorrente:</p>
                <label style="display:flex; align-items:center; gap:10px; font-size: var(--fs-xs); cursor:pointer;"><input type="radio" name="excluir_escopo" value="esta" checked> APENAS ESTA PARCELA</label>
                <label style="display:flex; align-items:center; gap:10px; font-size: var(--fs-xs); cursor:pointer; margin-top:10px;"><input type="radio" name="excluir_escopo" value="futuros"> ESTA E AS FUTURAS</label>
                <label style="display:flex; align-items:center; gap:10px; font-size: var(--fs-xs); cursor:pointer; margin-top:10px;"><input type="radio" name="excluir_escopo" value="todos"> TODA A SÉRIE (TODAS AS PARCELAS)</label>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <button type="submit" class="btn-sq-danger">EXCLUIR</button>
                <button type="button" onclick="document.getElementById('modal_excluir').style.display='none'" class="btn-sq-light">CANCELAR</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Categorias -->
<div id="modal_categorias" class="modal-sq">
    <div class="modal-content-sq">
        <h2 style="font-size: 1.2rem; font-weight: 900; text-transform: uppercase; margin-bottom: 30px; border-left: 5px solid #08153a; padding-left: 15px;">Gerenciar Categorias</h2>
        <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color); margin-bottom: 30px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #fafafa;">
                    <tr>
                        <th style="padding: 10px; text-align: left; font-size: var(--fs-xs); font-weight: 800;">NOME</th>
                        <th style="padding: 10px; text-align: left; font-size: var(--fs-xs); font-weight: 800;">TIPO</th>
                        <th style="padding: 10px; text-align: right;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categorias_exec as $c): ?>
                        <tr style="">
                            <td style="padding: 10px; font-size: var(--fs-sm); font-weight: 700;"><?php echo htmlspecialchars($c['nome']); ?></td>
                            <td style="padding: 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: <?php echo $c['tipo']=='receita'?'var(--primary-green)':'#ef4444'; ?>;"><?php echo $c['tipo']; ?></td>
                            <td style="padding: 10px; text-align: right;">
                                <?php if (temPermissaoModulo('financeiro_remover')): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja excluir esta categoria?')">
                                    <input type="hidden" name="excluir_categoria" value="1">
                                    <input type="hidden" name="categoria_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" style="border:none; background:none; color:#ef4444; cursor:pointer;"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="POST">
            <input type="hidden" name="nova_categoria" value="1">
            <div style="display: grid; grid-template-columns: 1fr 120px; gap: 10px;">
                <input type="text" name="nome" placeholder="NOME DA NOVA CATEGORIA" required style="width:100%; height:45px; padding:0 15px; border:1px solid var(--border-color); font-weight:700;">
                <select name="tipo" style="height:45px; border:1px solid var(--border-color); font-weight:700;">
                    <option value="receita">RECEITA</option>
                    <option value="despesa">DESPESA</option>
                </select>
            </div>
            <?php if (temPermissaoModulo('financeiro_criar')): ?>
            <button type="submit" class="btn-sq" style="width:100%; margin-top:10px;">ADICIONAR CATEGORIA</button>
            <?php endif; ?>
            <button type="button" onclick="document.getElementById('modal_categorias').style.display='none'" class="btn-sq-light" style="width:100%; margin-top:10px; border:none;">FECHAR</button>
        </form>
    </div>
</div>

<script>
function abrirModalEditar(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_descricao').value = item.titulo;
    document.getElementById('edit_valor').value = item.valor.replace('.', ',');
    document.getElementById('edit_vencimento').value = item.data_vencimento;
    document.getElementById('edit_academia').value = item.academia_id || '';
    document.getElementById('edit_categoria').value = item.categoria_id || '';
    document.querySelector('input[name="aplicar_edicao"][value="atual"]').checked = true;
    document.getElementById('opcoes_recorrencia_edit').style.display = (item.recorrencia && item.recorrencia !== 'nenhuma') ? 'block' : 'none';
    document.getElementById('modal_editar').style.display = 'flex';
}
function abrirModalBaixa(id, origem) {
    document.getElementById('baixa_id').value = id;
    document.getElementById('baixa_origem').value = origem;
    document.getElementById('modal_baixa').style.display = 'flex';
}
function abrirModalExcluir(id, recorrencia) {
    document.getElementById('excluir_id').value = id;
    document.querySelector('input[name="excluir_escopo"][value="esta"]').checked = true;
    document.getElementById('opcoes_recorrencia').style.display = (recorrencia && recorrencia !== 'nenhuma') ? 'block' : 'none';
    document.getElementById('modal_excluir').style.display = 'flex';
}
function toggleRepeticoes(val, context) {
    document.getElementById('group_repeticoes_' + context).style.display = (val === 'nenhuma') ? 'none' : 'block';
}
// Fechar modais ao clicar fora
window.onclick = function(event) {
    if (event.target.className === 'modal-sq') {
        event.target.style.display = "none";
    }
}
</script>
</section>

<?php include 'footer.php'; ?>