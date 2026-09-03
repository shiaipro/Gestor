<?php
require_once '../config.php';
$custom_title = "Administrativo";
include 'header.php';
?>

<div style="padding: 2rem 2rem 0 2rem; background: transparent;">
    

    <div class="greet-header" style="border-left: 6px solid #22c55e; padding-left: 20px;">
        <h1 style="font-size: 1.8rem; font-weight: 800; color: #1e293b; margin-bottom: 0.5rem; letter-spacing: -0.02em;">Departamento Administrativo</h1>
        <p style="color: #64748b; font-size: 1rem; margin: 0;">Funções administrativas e operacionais.</p>
    </div>
</div>

<section class="dashboard-section-sq" style="padding-top: 0;">

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="alunos.php" class="tab-item-sq">Alunos</a>
    <a href="equipe.php" class="tab-item-sq">RH</a>
    <a href="academias.php" class="tab-item-sq">Unidades</a>
    <a href="turmas.php" class="tab-item-sq">Turmas</a>
    <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
    <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
</div>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
        <div class="stat-card-square">
            <div class="label">Financeiro Retido</div>
            <div class="value">R$ 0,00</div>
            <a href="financeiro.php" class="link">Ver detalhes</a>
        </div>
        <div class="stat-card-square">
            <div class="label">Pendências Doc</div>
            <div class="value">0</div>
            <a href="#" class="link">Ver todos</a>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
