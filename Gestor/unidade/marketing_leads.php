<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Buscar Leads do CRM para Análise
$stmt = $pdo->prepare("SELECT origem, status, COUNT(*) as total FROM comercial_leads WHERE unidade_id = ? GROUP BY origem, status");
$stmt->execute([$unidade_id]);
$stats = $stmt->fetchAll();

// Processar dados para o Dashboard
$por_origem = [];
$por_status = [];
$total_leads = 0;

foreach ($stats as $s) {
    $origem = $s['origem'] ?: 'DIRETO / NÃO INFORMADA';
    $por_origem[$origem] = ($por_origem[$origem] ?? 0) + $s['total'];
    $por_status[$s['status']] = ($por_status[$s['status']] ?? 0) + $s['total'];
    $total_leads += $s['total'];
}

arsort($por_origem);

$custom_title = "Análise Comercial";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Inteligência Comercial
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Analise a eficiência dos seus canais de captação e conversão.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="crm_vendas.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-right" style="margin-right: 8px;"></i> Ir para Funil
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="comercial_dashboard.php" class="tab-item-sq">Início</a>
    <a href="crm_vendas.php" class="tab-item-sq">Vendas</a>
    <a href="crm_pos_vendas.php" class="tab-item-sq">Pós Vendas</a>
    <a href="marketing_leads.php" class="tab-item-sq active">CRM</a>
    <a href="mensagens.php" class="tab-item-sq">Mensagens</a>
    <a href="marketing_campanhas.php?menu=comercial" class="tab-item-sq">Funis de Vendas</a>
</div>

    

    <!-- Stats Principais Square -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px;">
        <div class="stat-card-square" style="padding: 30px; border-left: 6px solid #1e293b;">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">Volume de Captação</div>
            <div style="font-size: 42px; font-weight: 900; color: var(--text-dark);"><?php echo $total_leads; ?></div>
            <div style="font-size: var(--fs-xs); font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-top: 10px;">Leads Totais</div>
        </div>
        
        <div class="stat-card-square" style="padding: 30px; border-left: 6px solid var(--primary-green);">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">Conversão Final</div>
            <?php
            $fechados = $por_status['fechado'] ?? 0;
            $taxa = $total_leads > 0 ? round(($fechados / $total_leads) * 100, 1) : 0;
            ?>
            <div style="font-size: 42px; font-weight: 900; color: var(--primary-green);"><?php echo $taxa; ?><span style="font-size: 20px;">%</span></div>
            <div style="font-size: var(--fs-xs); font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-top: 10px;">Matrículas Efetuadas</div>
        </div>

        <div class="stat-card-square" style="padding: 30px; border-left: 6px solid #f59e0b;">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.1em;">Hot Leads</div>
            <?php
            $aberto = ($por_status['novo'] ?? 0) + ($por_status['em_contato'] ?? 0) + ($por_status['agendado'] ?? 0);
            ?>
            <div style="font-size: 42px; font-weight: 900; color: #f59e0b;"><?php echo $aberto; ?></div>
            <div style="font-size: var(--fs-xs); font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-top: 10px;">Em Negociação</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 30px;">
        <!-- Ranking de Origens Square -->
        <div class="dashboard-container" style="padding: 35px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h3 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Performance por Canal</h3>
            </div>
            <div style="display: grid; gap: 25px;">
                <?php foreach ($por_origem as $origem => $qtd):
                    $pct = $total_leads > 0 ? round(($qtd / $total_leads) * 100) : 0;
                    ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: var(--fs-sm); font-weight: 900; margin-bottom: 10px; text-transform: uppercase;">
                            <span><?php echo $origem; ?></span>
                            <span style="color: var(--text-dark);"><?php echo $qtd; ?> <span style="color: var(--text-muted); opacity: 0.5;">(<?php echo $pct; ?>%)</span></span>
                        </div>
                        <div style="height: 6px; background: #f1f5f9; position: relative;">
                            <div style="width: <?php echo $pct; ?>%; height: 100%; background: #1e293b;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($por_origem)): ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 40px; border: 1px dashed var(--border-color);">
                        <i class="fa-solid fa-filter" style="font-size: 40px; opacity: 0.1; display: block; margin-bottom: 15px;"></i>
                        Aguardando dados de captação...
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Funil Estrutural Square -->
        <div class="dashboard-container" style="padding: 35px; background: #fafafa;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h3 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Pipeline Estrutural</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 10px; align-items: center;">
                <div style="width: 100%; height: 50px; background: #08153a; color: white; display: flex; align-items: center; justify-content: center; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">
                    TRIAGEM (<?php echo $por_status['novo'] ?? 0; ?>)
                </div>
                <div style="width: 85%; height: 50px; background: #334155; color: white; display: flex; align-items: center; justify-content: center; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">
                    CONTATO (<?php echo $por_status['em_contato'] ?? 0; ?>)
                </div>
                <div style="width: 70%; height: 50px; background: #64748b; color: white; display: flex; align-items: center; justify-content: center; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">
                    VISITA (<?php echo $por_status['agendado'] ?? 0; ?>)
                </div>
                <div style="width: 55%; height: 60px; background: var(--primary-green); color: white; display: flex; align-items: center; justify-content: center; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border: 2px solid #08153a;">
                    MATRÍCULA (<?php echo $por_status['fechado'] ?? 0; ?>)
                </div>
            </div>
            <div style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid var(--border-color); text-align: center;">
                <p style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; text-transform: uppercase; line-height: 1.6; margin: 0;">
                    <i class="fa-solid fa-chart-pie" style="color: var(--primary-green); margin-right: 5px;"></i>
                    Mantenha o funil sempre atualizado para métricas precisas.
                </p>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>