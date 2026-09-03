<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Estatísticas de Marketing (Posts, Campanhas, etc)
try {
    $stmt_posts = $pdo->prepare("SELECT COUNT(*) FROM cms_posts WHERE unidade_id = ?");
    $stmt_posts->execute([$unidade_id]);
    $total_posts = $stmt_posts->fetchColumn() ?: 0;
}
catch (Exception $e) {
    $total_posts = 0;
}

try {
    $stmt_campanhas = $pdo->prepare("SELECT COUNT(*) FROM marketing_campanhas WHERE unidade_id = ?");
    $stmt_campanhas->execute([$unidade_id]);
    $total_campanhas = $stmt_campanhas->fetchColumn() ?: 0;
}
catch (Exception $e) {
    $total_campanhas = 0;
}

// Simulação de alcance e engajamento
$alcance_social = 14500;
$cliques_campanhas = 1240;

$custom_title = "Painel de Marketing";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Marketing & Presença
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Gerencie sua marca, conteúdos e alcance digital da unidade.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="novo_post.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-file-pen" style="margin-right: 8px;"></i> Novo Post
            </a>
            <a href="nova_campanha.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-bullhorn" style="margin-right: 8px;"></i> Criar Campanha
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="marketing_dashboard.php" class="tab-item-sq active">Dashboard</a>
    <a href="marketing_campanhas.php" class="tab-item-sq">Campanhas</a>
    <a href="marketing_social_media.php" class="tab-item-sq">Social Media</a>
    <a href="marketing_agenda.php" class="tab-item-sq">Offline</a>
</div>

    

    <!-- Métricas em Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px;">
        <!-- Posts -->
        <div class="stat-card-square">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Conteúdos Publicados</span>
                <i class="fa-solid fa-file-lines" style="color: var(--primary-green); opacity: 0.5;"></i>
            </div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo $total_posts; ?></div>
            <div style="margin-top: 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--primary-green); display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-arrow-up"></i>
                <span>+3 este mês</span>
            </div>
        </div>

        <!-- Campanhas -->
        <div class="stat-card-square">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Campanhas Ativas</span>
                <i class="fa-solid fa-tower-broadcast" style="color: var(--primary-green); opacity: 0.5;"></i>
            </div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo $total_campanhas; ?></div>
            <div style="margin-top: 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-check-double"></i>
                <span>Performance estável</span>
            </div>
        </div>

        <!-- Alcance -->
        <div class="stat-card-square">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Alcance Social</span>
                <i class="fa-solid fa-users-viewfinder" style="color: var(--primary-green); opacity: 0.5;"></i>
            </div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;">14.5k</div>
            <div style="margin-top: 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--primary-green); display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-chart-line"></i>
                <span>Crescimento de 12%</span>
            </div>
        </div>
    </div>

</section>

<?php include 'footer.php'; ?>