<?php
require_once '../config.php';
$custom_title = "Marketing";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <h2 class="section-title-sq">Marketing Central</h2>
    <p style="color: var(--text-muted); margin-bottom: 30px;">Gestão da marca global e campanhas de rede.</p>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
        <div class="stat-card-square">
            <div class="label">Templates Globais</div>
            <div class="value">0</div>
            <a href="#" class="link">Biblioteca</a>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
