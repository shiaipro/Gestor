<?php
require_once '../config.php';
$custom_title = "Eventos";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <h2 class="section-title-sq">Departamento de Eventos</h2>
    <p style="color: var(--text-muted); margin-bottom: 30px;">Planejamento e execução de competições, exames e seminários.</p>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
        <div class="stat-card-square">
            <div class="label">Próximos Eventos</div>
            <div class="value">0</div>
            <a href="eventos_dashboard.php" class="link">Calendário</a>
        </div>
        <div class="stat-card-square">
            <div class="label">Inscritos Totais</div>
            <div class="value">0</div>
            <a href="#" class="link">Ver lista</a>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
