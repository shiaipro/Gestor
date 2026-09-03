<?php
require_once '../config.php';
$custom_title = "Comercial";
include 'header.php';
?>



<section class="dashboard-section-sq" style="padding-top: 0;">
    <h2 class="section-title-sq">Departamento Comercial</h2>
    <p style="color: var(--text-muted); margin-bottom: 30px;">Gestão de vendas, contratos e prospecção de alunos.</p>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="comercial_dashboard.php" class="tab-item-sq active">Início</a>
        <a href="crm_vendas.php" class="tab-item-sq">Vendas</a>
        <a href="crm_pos_vendas.php" class="tab-item-sq">Pós Vendas</a>
        <a href="marketing_leads.php" class="tab-item-sq">CRM</a>
        <a href="mensagens.php" class="tab-item-sq">Mensagens</a>
        <a href="marketing_campanhas.php?menu=comercial" class="tab-item-sq">Funis de Vendas</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
        <div class="stat-card-square">
            <div class="label">Novos Contratos (Mês)</div>
            <div class="value">0</div>
            <a href="#" class="link">Ver mais</a>
        </div>
        <div class="stat-card-square">
            <div class="label">Taxa de Conversão</div>
            <div class="value">0%</div>
            <a href="#" class="link">Analisar</a>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
