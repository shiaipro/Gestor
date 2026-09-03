<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Estatísticas de Backoffice
$stmt_equipe = $pdo->prepare("SELECT COUNT(*) FROM unidade_equipe WHERE unidade_id = ? AND status = 'ativo'");
$stmt_equipe->execute([$unidade_id]);
$total_equipe = $stmt_equipe->fetchColumn() ?: 0;

$stmt_planos = $pdo->prepare("SELECT COUNT(*) FROM planos WHERE unidade_id = ? AND ativo = 1");
$stmt_planos->execute([$unidade_id]);
$total_planos = $stmt_planos->fetchColumn() ?: 0;

$stmt_academias = $pdo->prepare("SELECT COUNT(*) FROM academias WHERE unidade_id = ? AND status = 'ativo'");
$stmt_academias->execute([$unidade_id]);
$total_academias = $stmt_academias->fetchColumn() ?: 0;

$progresso = 0;
$onboarding_completo = true;

$custom_title = "Administrativo - SHIAIPRO";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Gestão Administrativa
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Configurações de estrutura e RH
            </p>
        </div>

    
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar Operações
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="administrativo_dashboard.php" class="tab-item-sq active">Dashboard</a>
        <a href="alunos.php" class="tab-item-sq">Alunos</a>
        <a href="equipe.php" class="tab-item-sq">RH</a>
        <a href="academias.php" class="tab-item-sq">Unidades</a>
        <a href="turmas.php" class="tab-item-sq">Turmas</a>
        <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
        <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
    </div>

    <!-- Cards de Estrutura -->
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; margin-bottom: 50px;">
        <div class="dashboard-container" style="padding: 40px; background: #fff;">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 10px;">Corpo Docente</div>
            <div style="font-size: 52px; font-weight: 900; color: #08153a; line-height: 1;"><?php echo str_pad($total_equipe, 2, '0', STR_PAD_LEFT); ?></div>
            <a href="equipe.php" style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-decoration: none; display: block; margin-top: 15px; text-transform: uppercase; letter-spacing: 0.05em;">GERENCIAR RH →</a>
        </div>

        <div class="dashboard-container" style="padding: 40px; background: #fff;">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 10px;">Unidades Ativas</div>
            <div style="font-size: 52px; font-weight: 900; color: #08153a; line-height: 1;"><?php echo str_pad($total_academias, 2, '0', STR_PAD_LEFT); ?></div>
            <a href="academias.php" style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-decoration: none; display: block; margin-top: 15px; text-transform: uppercase; letter-spacing: 0.05em;">CONFIGURAR PONTOS →</a>
        </div>
    </div>

</section>

<?php include 'footer.php'; ?>