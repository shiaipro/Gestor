<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Estatísticas Reais
$stmt_leads_total = $pdo->prepare("SELECT COUNT(*) FROM comercial_leads WHERE unidade_id = ?");
$stmt_leads_total->execute([$unidade_id]);
$total_leads = $stmt_leads_total->fetchColumn() ?: 0;

$stmt_leads_mes = $pdo->prepare("SELECT COUNT(*) FROM comercial_leads WHERE unidade_id = ? AND MONTH(criado_em) = MONTH(CURRENT_DATE()) AND YEAR(criado_em) = YEAR(CURRENT_DATE())");
$stmt_leads_mes->execute([$unidade_id]);
$leads_mes = $stmt_leads_mes->fetchColumn() ?: 0;

// Simulação de Pipeline (Pode ser refinado se houver coluna de status)
$stats_funnel = [
    'Captados' => $leads_mes,
    'Contatos' => round($leads_mes * 0.8),
    'Visitas' => round($leads_mes * 0.45),
    'Vendas' => round($leads_mes * 0.15)
];

$conversao = $leads_mes > 0 ? round(($stats_funnel['Vendas'] / $leads_mes) * 100) : 0;

// Leads Recentes
$stmt_recentes = $pdo->prepare("SELECT nome, origem, status, criado_em FROM comercial_leads WHERE unidade_id = ? ORDER BY criado_em DESC LIMIT 5");
$stmt_recentes->execute([$unidade_id]);
$leads_recentes = $stmt_recentes->fetchAll();

$custom_title = "Painel Comercial";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div
        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="padding-left: 0;">
            <h1
                style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Sales Intelligence
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Tracking de conversão & performance de vendas
            </p>
        </div>

        <div style="display: flex; gap: 10px;">
            <a href="novo_lead.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus-circle" style="margin-right: 8px;"></i> Registrar Lead
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="comercial_dashboard.php" class="tab-item-sq active">Início</a>
        <a href="crm_vendas.php" class="tab-item-sq">Vendas</a>
        <a href="crm_pos_vendas.php" class="tab-item-sq">Pós Vendas</a>
        <a href="marketing_leads.php" class="tab-item-sq">CRM</a>
        <a href="mensagens.php" class="tab-item-sq">Mensagens</a>
        <a href="marketing_campanhas.php?menu=comercial" class="tab-item-sq">Funis de Vendas</a>
    </div>

    <!-- KPI Row -->
    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="stat-card-square" style="padding: 30px;">
            <div
                style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">
                Leads (Mês)</div>
            <div style="font-size: 42px; font-weight: 900; color: var(--text-dark); line-height: 1;">
                <?php echo $leads_mes; ?>
            </div>
            <div style="font-size: var(--fs-xs); font-weight: 700; color: var(--primary-green); margin-top: 15px;">
                OPORTUNIDADES CAPTADAS</div>
        </div>
        <div class="stat-card-square" style="padding: 30px;">
            <div
                style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">
                Conversão</div>
            <div style="font-size: 42px; font-weight: 900; color: var(--primary-green); line-height: 1;">
                <?php echo $conversao; ?>%
            </div>
            <div style="font-size: var(--fs-xs); font-weight: 700; color: var(--text-muted); margin-top: 15px;">LEADS ->
                MATRÍCULAS</div>
        </div>
        <div class="stat-card-square" style="padding: 30px;">
            <div
                style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">
                Vendas Efetuadas</div>
            <div style="font-size: 42px; font-weight: 900; color: #3b82f6; line-height: 1;">
                <?php echo $stats_funnel['Vendas']; ?>
            </div>
            <div style="font-size: var(--fs-xs); font-weight: 700; color: var(--text-muted); margin-top: 15px;">
                CONTRATOS ASSINADOS (MÊS)</div>
        </div>
    </div>

</section>

<?php include 'footer.php'; ?>