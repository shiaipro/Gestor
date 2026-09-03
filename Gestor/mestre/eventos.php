<?php
require_once '../config.php';
$custom_title = "Eventos";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <h2 class="section-title-sq">Eventos Central</h2>
    <p style="color: var(--text-muted); margin-bottom: 30px;">Coordenação de grandes eventos de rede e competições oficiais.</p>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
        <div class="stat-card-square">
            <div class="label">Grandes Eventos</div>
            <div class="value">0</div>
            <a href="#" class="link">Planejamento</a>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
