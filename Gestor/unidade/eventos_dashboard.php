<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// 1. OFICIAIS
$stmt_oficiais = $pdo->prepare("SELECT nome, data_inicio, localizacao, status FROM competicoes_oficiais WHERE unidade_id = ? ORDER BY data_inicio DESC LIMIT 3");
$stmt_oficiais->execute([$unidade_id]);
$ofi_list = $stmt_oficiais->fetchAll();

// 2. TORNEIOS (Internos)
$stmt_torneios = $pdo->prepare("SELECT nome, data_evento, localizacao, status FROM competicoes WHERE unidade_id = ? ORDER BY data_evento DESC LIMIT 3");
$stmt_torneios->execute([$unidade_id]);
$tor_list = $stmt_torneios->fetchAll();

// 3. EXAMES DE FAIXAS
$stmt_grad = $pdo->prepare("SELECT titulo, data_evento, local, status FROM eventos_graduacao WHERE unidade_id = ? ORDER BY data_evento DESC LIMIT 3");
$stmt_grad->execute([$unidade_id]);
$grad_list = $stmt_grad->fetchAll();

// 4. VISITANTES (inscrições públicas sem vínculo de aluno)
try {
    $stmt_vis = $pdo->prepare("SELECT COUNT(*) FROM eventos_visitantes WHERE unidade_id = ?");
    $stmt_vis->execute([$unidade_id]);
    $total_visitantes = (int) $stmt_vis->fetchColumn();
} catch (Exception $e) {
    $total_visitantes = 0;
}

// Estatísticas Públicas
$total_oficiais = count($ofi_list);
$total_torneios = count($tor_list);
$total_grad = count($grad_list);

$custom_title = "Painel de Eventos";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Gestão de Eventos
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Organize competições e exames de graduação da sua unidade.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="novo_evento_faixa.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-medal" style="margin-right: 8px;"></i> Novo Exame
            </a>
            <a href="nova_competicao.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-trophy" style="margin-right: 8px;"></i> Criar Evento
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="eventos_dashboard.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'eventos_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
    <a href="competicoes_oficiais.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'competicoes_oficiais.php') ? 'active' : ''; ?>">Oficiais</a>
    <a href="competicoes.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'competicoes.php') ? 'active' : ''; ?>">Torneios</a>
    <a href="faixas.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'faixas.php') ? 'active' : ''; ?>">Exames</a>
    <a href="eventos_visitantes.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'eventos_visitantes.php') ? 'active' : ''; ?>">Visitantes</a>
    <a href="delegacoes.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'delegacoes.php') ? 'active' : ''; ?>">Delegações</a>
    <a href="relatorios_eventos.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'relatorios_eventos.php') ? 'active' : ''; ?>">Relatórios</a>
</div>

    

    <!-- Métricas em Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px;">
        <div class="stat-card-square">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.05em;">Eventos Oficiais</div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark);"><?php echo count($ofi_list); ?></div>
            <div style="margin-top: 15px; font-size: var(--fs-xs); color: var(--primary-green); font-weight: 700;">Calendário Externo</div>
        </div>
        <div class="stat-card-square">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.05em;">Torneios Internos</div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark);"><?php echo count($tor_list); ?></div>
            <div style="margin-top: 15px; font-size: var(--fs-xs); color: var(--primary-green); font-weight: 700;">Gestão da Unidade</div>
        </div>
        <div class="stat-card-square">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.05em;">Exames de Faixa</div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark);"><?php echo count($grad_list); ?></div>
            <div style="margin-top: 15px; font-size: var(--fs-xs); color: var(--primary-green); font-weight: 700;">Graduações Agendadas</div>
        </div>
        <div class="stat-card-square">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.05em;">Visitantes</div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark);"><?php echo $total_visitantes; ?></div>
            <div style="margin-top: 15px; font-size: var(--fs-xs); color: var(--primary-green); font-weight: 700;"><a href="eventos_visitantes.php" style="color:inherit; text-decoration:none;">Ver lista</a></div>
        </div>
    </div>

    <!-- Layout em Grid para Listas -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Card: Oficiais -->
        <div class="dashboard-container" style="padding: 0;">
            <div style="padding: 25px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: var(--fs-base); font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 0.02em;">Competições Oficiais</h3>
                <a href="competicoes_oficiais.php" style="font-size: var(--fs-xs); color: var(--primary-green); font-weight: 800; text-decoration: none;">VER TUDO</a>
            </div>
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table class="table-sq" style="width: 100%;">
                <?php if (empty($ofi_list)): ?>
                    <tr><td style="padding: 40px; text-align: center; color: var(--text-muted);">Nenhum evento oficial agendado.</td></tr>
                <?php else: foreach ($ofi_list as $ofi): ?>
                    <tr>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 800; color: var(--text-dark); font-size: var(--fs-base);"><?php echo htmlspecialchars($ofi['nome']); ?></div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600; margin-top: 2px;"><?php echo htmlspecialchars($ofi['localizacao']); ?></div>
                        </td>
                        <td style="padding: 20px 30px; text-align: right;">
                            <div style="font-weight: 900; font-size: var(--fs-sm); color: var(--text-dark);"><?php echo date('d/m/Y', strtotime($ofi['data_inicio'])); ?></div>
                            <span class="badge-sq <?php echo $ofi['status'] == 'aberto' ? 'badge-ativo' : 'badge-inativo'; ?>" style="margin-top: 5px;">
                                <?php echo strtoupper($ofi['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
            </div>
        </div>

        <!-- Card: Torneios -->
        <div class="dashboard-container" style="padding: 0;">
            <div style="padding: 25px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: var(--fs-base); font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 0.02em;">Torneios Internos</h3>
                <a href="competicoes.php" style="font-size: var(--fs-xs); color: var(--primary-green); font-weight: 800; text-decoration: none;">VER TUDO</a>
            </div>
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table class="table-sq" style="width: 100%;">
                <?php if (empty($tor_list)): ?>
                    <tr><td style="padding: 40px; text-align: center; color: var(--text-muted);">Nenhum torneio cadastrado.</td></tr>
                <?php else: foreach ($tor_list as $tor): ?>
                    <tr>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 800; color: var(--text-dark); font-size: var(--fs-base);"><?php echo htmlspecialchars($tor['nome']); ?></div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600; margin-top: 2px;"><?php echo htmlspecialchars($tor['localizacao']); ?></div>
                        </td>
                        <td style="padding: 20px 30px; text-align: right;">
                            <div style="font-weight: 900; font-size: var(--fs-sm); color: var(--text-dark);"><?php echo date('d/m/Y', strtotime($tor['data_evento'])); ?></div>
                            <span class="badge-sq <?php echo $tor['status'] == 'aberto' ? 'badge-ativo' : 'badge-inativo'; ?>" style="margin-top: 5px;">
                                <?php echo strtoupper($tor['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
            </div>
        </div>
    </div>

    <!-- Exames de Faixa (Largura Total) -->
    <div class="dashboard-container" style="padding: 0; margin-top: 30px;">
        <div style="padding: 25px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: var(--fs-base); font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 0.02em;">Últimos Exames de Faixas</h3>
            <a href="faixas.php" style="font-size: var(--fs-xs); color: var(--primary-green); font-weight: 800; text-decoration: none;">GERENCIAR TODOS</a>
        </div>
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%;">
            <?php if (empty($grad_list)): ?>
                <tr><td style="padding: 40px; text-align: center; color: var(--text-muted);">Nenhum exame agendado.</td></tr>
            <?php else: foreach ($grad_list as $grd): ?>
                <tr>
                    <td style="padding: 20px 30px;">
                        <div style="font-weight: 800; color: var(--text-dark); font-size: var(--fs-base);"><?php echo htmlspecialchars($grd['titulo']); ?></div>
                        <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600; margin-top: 2px;"><i class="fa-solid fa-location-dot" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($grd['local']); ?></div>
                    </td>
                    <td style="padding: 20px 30px; text-align: right;">
                        <div style="font-weight: 900; font-size: var(--fs-sm); color: var(--text-dark);"><?php echo date('d/m/Y', strtotime($grd['data_evento'])); ?></div>
                        <span class="badge-sq <?php echo $grd['status'] == 'agendado' ? 'badge-ativo' : 'badge-inativo'; ?>" style="margin-top: 5px;">
                            <?php echo strtoupper($grd['status']); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>