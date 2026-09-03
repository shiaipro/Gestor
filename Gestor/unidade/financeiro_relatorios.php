<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!moduloAtivo('financeiro')) {
    header('Location: administrativo_dashboard.php');
    exit;
}

$mes_atual = isset($_GET['mes']) ? str_pad($_GET['mes'], 2, '0', STR_PAD_LEFT) : date('m');
$ano_atual = isset($_GET['ano']) ? $_GET['ano'] : date('Y');

// Lógica de Navegação de Data
$data_nav = new DateTime("$ano_atual-$mes_atual-01");
$prev_month = (clone $data_nav)->modify('-1 month');
$next_month = (clone $data_nav)->modify('+1 month');

$prev_url = "financeiro_relatorios.php?" . http_build_query(array_merge($_GET, ['mes' => $prev_month->format('m'), 'ano' => $prev_month->format('Y')]));
$next_url = "financeiro_relatorios.php?" . http_build_query(array_merge($_GET, ['mes' => $next_month->format('m'), 'ano' => $next_month->format('Y')]));

$meses_nomes = ['01' => 'Janeiro','02' => 'Fevereiro','03' => 'Março','04' => 'Abril','05' => 'Maio','06' => 'Junho','07' => 'Julho','08' => 'Agosto','09' => 'Setembro','10' => 'Outubro','11' => 'Novembro','12' => 'Dezembro'];

$academia_id_filtro = $_GET['academia_id'] ?? null;
$where_m_rel = $academia_id_filtro ? " AND academia_id = ? " : "";
$where_l_rel = $academia_id_filtro ? " AND l.academia_id = ? " : "";

$rel = $pdo->prepare("
    SELECT 
        (SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE unidade_id = ? $where_m_rel AND status = 'pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) +
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos l WHERE l.unidade_id = ? $where_l_rel AND l.status = 'pago' AND l.tipo = 'receita' AND MONTH(l.data_pagamento) = ? AND YEAR(l.data_pagamento) = ?) as total_receitas,
        
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos l WHERE l.unidade_id = ? $where_l_rel AND l.status = 'pago' AND l.tipo = 'despesa' AND MONTH(l.data_pagamento) = ? AND YEAR(l.data_pagamento) = ?) as total_despesas,

        (SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE unidade_id = ? $where_m_rel AND status = 'pendente' AND MONTH(data_vencimento) = ? AND YEAR(data_vencimento) = ? AND aluno_id IN (SELECT id FROM alunos WHERE status != 'inativo')) +
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos l WHERE l.unidade_id = ? $where_l_rel AND l.status = 'pendente' AND l.tipo = 'receita' AND MONTH(l.data_vencimento) = ? AND YEAR(l.data_vencimento) = ?) as prev_receitas,

        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos l WHERE l.unidade_id = ? $where_l_rel AND l.status = 'pendente' AND l.tipo = 'despesa' AND MONTH(l.data_vencimento) = ? AND YEAR(l.data_vencimento) = ?) as prev_despesas
");

$p_exec = [];
for($i=0; $i<6; $i++) {
    $p_exec[] = $unidade_id;
    if($academia_id_filtro) $p_exec[] = $academia_id_filtro;
    $p_exec[] = $mes_atual;
    $p_exec[] = $ano_atual;
}

$rel->execute($p_exec);
$data_rel = $rel->fetch();

$saldo_real = $data_rel['total_receitas'] - $data_rel['total_despesas'];

// Quantidade de alunos pagos e pendentes
$stmt_qtd_alunos = $pdo->prepare("
    SELECT 
        (SELECT COUNT(DISTINCT aluno_id) FROM mensalidades WHERE unidade_id = ? $where_m_rel AND status = 'pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) as alunos_pagos,
        (SELECT COUNT(DISTINCT aluno_id) FROM mensalidades WHERE unidade_id = ? $where_m_rel AND status = 'pendente' AND MONTH(data_vencimento) = ? AND YEAR(data_vencimento) = ? AND aluno_id IN (SELECT id FROM alunos WHERE status != 'inativo')) as alunos_pendentes
");
$p_qtd = [$unidade_id];
if ($academia_id_filtro) $p_qtd[] = $academia_id_filtro;
$p_qtd[] = $mes_atual;
$p_qtd[] = $ano_atual;
$p_qtd[] = $unidade_id;
if ($academia_id_filtro) $p_qtd[] = $academia_id_filtro;
$p_qtd[] = $mes_atual;
$p_qtd[] = $ano_atual;

$stmt_qtd_alunos->execute($p_qtd);
$qtd_alunos = $stmt_qtd_alunos->fetch();

// Histórico 6 Meses
$historico_labels = []; $hist_rec = []; $hist_desp = [];
$h_date = (clone $data_nav)->modify('-5 months');

$stmt_h = $pdo->prepare("
    SELECT 
        (SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE unidade_id = ? $where_m_rel AND status = 'pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) +
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos l WHERE l.unidade_id = ? $where_l_rel AND l.status = 'pago' AND l.tipo = 'receita' AND MONTH(l.data_pagamento) = ? AND YEAR(l.data_pagamento) = ?) as r,
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos l WHERE l.unidade_id = ? $where_l_rel AND l.status = 'pago' AND l.tipo = 'despesa' AND MONTH(l.data_pagamento) = ? AND YEAR(l.data_pagamento) = ?) as d
");

for($i=0; $i<6; $i++){
    $m_ = $h_date->format('m'); $a_ = $h_date->format('Y');
    
    $p_h = [];
    // Receitas Alunos
    $p_h[] = $unidade_id; if($academia_id_filtro) $p_h[] = $academia_id_filtro; $p_h[] = $m_; $p_h[] = $a_;
    // Receitas Lancamentos
    $p_h[] = $unidade_id; if($academia_id_filtro) $p_h[] = $academia_id_filtro; $p_h[] = $m_; $p_h[] = $a_;
    // Despesas Lancamentos
    $p_h[] = $unidade_id; if($academia_id_filtro) $p_h[] = $academia_id_filtro; $p_h[] = $m_; $p_h[] = $a_;

    $stmt_h->execute($p_h);
    $row = $stmt_h->fetch();
    $historico_labels[] = substr($meses_nomes[$m_], 0, 3) . '/' . substr($a_, 2);
    $hist_rec[] = (float)$row['r'];
    $hist_desp[] = (float)$row['d'];
    $h_date->modify('+1 month');
}

$custom_title = "Relatório Financeiro";
include 'header.php';
?>




<section class="dashboard-section-sq">
    <!-- Cabeçalho da Página -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="border-left: 8px solid #08153a; padding-left: 20px;">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                DRE & Performance
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Análise consolidada de saúde financeira e projeções de caixa.
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="imprimir_financeiro.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="btn-sq" style="padding: 12px 25px; background: #08153a; border-color: #08153a; text-decoration: none;">
                <i class="fa-solid fa-file-pdf" style="margin-right: 8px;"></i> Exportar Relatório
            </a>
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
        <a href="financeiro.php" class="btn-nav-financeiro-sq">RECEITAS</a>
        <a href="financeiro_pagar.php" class="btn-nav-financeiro-sq">DESPESAS</a>
        <a href="financeiro_relatorios.php" class="btn-nav-financeiro-sq active">RELATÓRIO</a>
    </div>

    <!-- Filtros de Período -->
<?php
    $stmt_acads = $pdo->prepare("SELECT id, nome FROM academias WHERE unidade_id = ? ORDER BY nome ASC");
    $stmt_acads->execute([$unidade_id]);
    $acads_filtro = $stmt_acads->fetchAll();
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="display: flex; align-items: center; background: #fafafa; border: 1px solid var(--border-color);">
            <a href="<?php echo $prev_url; ?>" class="btn-sq-light" style="width: 50px; height: 50px; padding:0; display:flex; align-items:center; justify-content:center; border:none;"><i class="fa-solid fa-chevron-left"></i></a>
            <div style="padding: 0 30px; font-size: var(--fs-base); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;"><?php echo $meses_nomes[$mes_atual]; ?> <?php echo $ano_atual; ?></div>
            <a href="<?php echo $next_url; ?>" class="btn-sq-light" style="width: 50px; height: 50px; padding:0; display:flex; align-items:center; justify-content:center; border:none;"><i class="fa-solid fa-chevron-right"></i></a>
        </div>

        <form method="GET" style="display: flex; gap: 10px;">
            <input type="hidden" name="mes" value="<?php echo $mes_atual; ?>">
            <input type="hidden" name="ano" value="<?php echo $ano_atual; ?>">
            <select name="academia_id" onchange="this.form.submit()" style="height: 50px; background: #fff; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase;">
                <option value="">FILTRAR POR ACADEMIA</option>
                <?php foreach ($acads_filtro as $acad): ?>
                    <option value="<?php echo $acad['id']; ?>" <?php echo $academia_id_filtro == $acad['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($acad['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Cards DRE (Métricas Reais) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin-bottom: 40px;">
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">Receita Realizada</div>
            <div class="value" style="font-size: 32px; font-weight: 900; color: var(--primary-green);">R$ <?php echo number_format($data_rel['total_receitas'], 2, ',', '.'); ?></div>
            <div style="font-size: var(--fs-sm); color: var(--text-muted); margin-top: 10px; font-weight: 700;">FATURAMENTO LÍQUIDO NO MÊS</div>
        </div>
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">Despesa Efetivada</div>
            <div class="value" style="font-size: 32px; font-weight: 900; color: #ef4444;">R$ <?php echo number_format($data_rel['total_despesas'], 2, ',', '.'); ?></div>
            <div style="font-size: var(--fs-sm); color: var(--text-muted); margin-top: 10px; font-weight: 700;">CUSTOS TOTAIS PAGOS</div>
        </div>
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">Saldo Operacional</div>
            <div class="value" style="font-size: 32px; font-weight: 900; color: #08153a;">R$ <?php echo number_format($saldo_real, 2, ',', '.'); ?></div>
            <div style="font-size: var(--fs-sm); color: <?php echo $saldo_real >= 0 ? 'var(--primary-green)' : '#ef4444'; ?>; margin-top: 10px; font-weight: 700;">LUCRO/PREJUÍZO DO PERÍODO</div>
        </div>
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">Alunos Pagos / Pendentes</div>
            <div class="value" style="font-size: 32px; font-weight: 900; color: #08153a;">
                <span style="color: var(--primary-green);"><?php echo $qtd_alunos['alunos_pagos']; ?></span> <span style="color: var(--text-muted); font-size: 20px; font-weight: 500;">/</span> <span style="color: #ef4444;"><?php echo $qtd_alunos['alunos_pendentes']; ?></span>
            </div>
            <div style="font-size: var(--fs-sm); color: var(--text-muted); margin-top: 10px; font-weight: 700;">PAGOS VS EM ABERTO NO MÊS</div>
        </div>
    </div>

    <!-- Área de Análise Gráfica -->
    <div style="display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 25px; margin-bottom: 40px; align-items: stretch;">
        <div class="dashboard-container" style="padding: 40px; display: flex; flex-direction: column;">
            <div style="border-left: 6px solid #08153a; padding-left: 20px; margin-bottom: 40px;">
                <h3 style="font-size: var(--fs-base); font-weight: 900; text-transform: uppercase; margin: 0; letter-spacing: 0.05em;">Performance Semestral</h3>
                <p style="font-size: var(--fs-xs); color: var(--text-muted); margin: 5px 0 0 0; font-weight: 700;">COMPARAÇÃO DE FLUXO DE CAIXA (RECEITAS VS DESPESAS)</p>
            </div>
            <div style="flex: 1; min-height: 350px;">
                <canvas id="chartHistorico"></canvas>
            </div>
        </div>

        <div class="dashboard-container" style="padding: 40px; background: #fafafa; border: 1px solid var(--border-color);">
            <div style="border-left: 6px solid #08153a; padding-left: 20px; margin-bottom: 40px;">
                <h3 style="font-size: var(--fs-base); font-weight: 900; text-transform: uppercase; margin: 0; letter-spacing: 0.05em;">Projeção Mensal</h3>
                <p style="font-size: var(--fs-xs); color: var(--text-muted); margin: 5px 0 0 0; font-weight: 700;">EXPECTATIVA DE FECHAMENTO (ACUMULADO + PENDENTE)</p>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="padding: 25px; background: #fff; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 10px; font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.1em;">A Receber Pendente</div>
                        <div style="font-size: 22px; font-weight: 900; color: var(--primary-green);">R$ <?php echo number_format($data_rel['prev_receitas'], 2, ',', '.'); ?></div>
                    </div>
                    <i class="fa-solid fa-arrow-trend-up" style="font-size: 24px; color: var(--primary-green); opacity: 0.3;"></i>
                </div>

                <div style="padding: 25px; background: #fff; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 10px; font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.1em;">A Pagar Agendado</div>
                        <div style="font-size: 22px; font-weight: 900; color: #ef4444;">R$ <?php echo number_format($data_rel['prev_despesas'], 2, ',', '.'); ?></div>
                    </div>
                    <i class="fa-solid fa-arrow-trend-down" style="font-size: 24px; color: #ef4444; opacity: 0.3;"></i>
                </div>

                <div style="padding: 30px; background: #08153a; color: #fff; margin-top: 10px; position: relative; overflow: hidden;">
                    <div style="position: relative; z-index: 2;">
                        <div style="font-size: 10px; font-weight: 900; color: #c99742; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.1em;">Expectativa de Saldo</div>
                        <div style="font-size: 28px; font-weight: 900;">R$ <?php echo number_format($saldo_real + $data_rel['prev_receitas'] - $data_rel['prev_despesas'], 2, ',', '.'); ?></div>
                    </div>
                    <i class="fa-solid fa-chart-line" style="position: absolute; right: -10px; bottom: -10px; font-size: 80px; color: rgba(255,255,255,0.05);"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('chartHistorico').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($historico_labels); ?>,
            datasets: [
                {
                    label: 'RECEITAS',
                    data: <?php echo json_encode($hist_rec); ?>,
                    backgroundColor: '#c99742',
                    borderRadius: 0,
                    barPercentage: 0.6
                },
                {
                    label: 'DESPESAS',
                    data: <?php echo json_encode($hist_desp); ?>,
                    backgroundColor: '#ef4444',
                    borderRadius: 0,
                    barPercentage: 0.6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { family: 'Outfit', size: 11, weight: '900' }, usePointStyle: true, boxWidth: 8, padding: 20 }
                },
                tooltip: { backgroundColor: '#08153a', titleFont: { size: 12, weight: '900' }, bodyFont: { size: 12, weight: '700' }, padding: 15, displayColors: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f1f1', drawBorder: false }, ticks: { font: { weight: '700' }, callback: function(value) { return 'R$ ' + value.toLocaleString('pt-BR'); } } },
                x: { grid: { display: false }, ticks: { font: { weight: '800', size: 10 } } }
            }
        }
    });
</script>

<?php include 'footer.php'; ?>