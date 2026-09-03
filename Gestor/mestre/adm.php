<?php
require_once '../config.php';
$custom_title = "Administrativo";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <h2 class="section-title-sq">Adm Central</h2>
    <p style="color: var(--text-muted); margin-bottom: 30px;">Gestão administrativa global da rede.</p>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
        <div class="stat-card-square">
            <div class="label">Processos Globais</div>
            <div class="value">0</div>
            <a href="#" class="link">Gerenciar</a>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
