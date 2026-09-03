<?php
require_once '../config.php';
$custom_title = "Marketing";
include 'header.php';
?>



<section class="dashboard-section-sq" style="padding-top: 0;">
    <h2 class="section-title-sq">Departamento de Marketing</h2>
    <p style="color: var(--text-muted); margin-bottom: 30px;">Gerencie campanhas, redes sociais e presença digital.</p>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="marketing_dashboard.php" class="tab-item-sq active">Dashboard</a>
        <a href="marketing_campanhas.php" class="tab-item-sq">Campanhas</a>
        <a href="marketing_social_media.php" class="tab-item-sq">Social Media</a>
        <a href="marketing_agenda.php" class="tab-item-sq">Offline</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
        <div class="stat-card-square">
            <div class="label">Campanhas Ativas</div>
            <div class="value">0</div>
            <a href="marketing_campanhas.php" class="link">Ver campanhas</a>
        </div>
        <div class="stat-card-square">
            <div class="label">Leads Gerados</div>
            <div class="value">0</div>
            <a href="marketing_leads.php" class="link">Ver leads</a>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
