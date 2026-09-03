<?php
require_once '../config.php';

$unidade_id = getUnidadeId();

if (!moduloAtivo('competicoes')) {
    echo "<div class='alert alert-danger' style='margin:2rem; padding:1.5rem; background:#fee2e2; color:#991b1b; border-radius:0px;'>Acesso Negado: O módulo de Eventos não está ativo para o seu plano.</div>";
    include 'footer.php';
    exit;
}

// Buscar competições da unidade
$stmt = $pdo->prepare("SELECT * FROM competicoes WHERE unidade_id = ? ORDER BY data_evento DESC");
$stmt->execute([$unidade_id]);
$competicoes = $stmt->fetchAll();

$custom_title = "Torneios Internos";
include 'header.php';
?>

<div class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Torneios Internos
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Organização e inscrições de competições internas
            </p>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <a href="nova_competicao.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-trophy" style="margin-right: 8px;"></i> Novo Torneio
            </a>
        </div>
    </div>

    <!-- Menu Especial do Departamento -->
    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="eventos_dashboard.php" class="tab-item-sq">Dashboard</a>
        <a href="competicoes_oficiais.php" class="tab-item-sq">Oficiais</a>
        <a href="competicoes.php" class="tab-item-sq active">Torneios</a>
        <a href="faixas.php" class="tab-item-sq">Exames</a>
        <a href="eventos_visitantes.php" class="tab-item-sq">Visitantes</a>
        <a href="delegacoes.php" class="tab-item-sq">Delegações</a>
        <a href="relatorios_eventos.php" class="tab-item-sq">Relatórios</a>
    </div>


    <div style="background: white; border: 1px solid var(--border-color); padding: 0; margin-bottom: 30px;">

        <?php if (empty($competicoes)): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 5rem 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.1;"><i class="fa-solid fa-trophy"></i></div>
                <p style="font-weight: 600; font-size: 1rem;">Nenhuma competição cadastrada no momento.</p>
                <p style="font-size: 0.85rem; margin-top: 0.5rem;">Organize seus eventos e acompanhe as inscrições dos alunos.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Evento</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Data</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Local</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Status</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase; text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competicoes as $c): ?>
                            <tr style="border-bottom: 1px solid var(--border-color); transition: all 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 20px 30px;">
                                    <div style="font-weight: 800; color: var(--dark-main); font-size: var(--fs-base);">
                                        <?php echo htmlspecialchars($c['nome']); ?>
                                    </div>
                                </td>
                                <td style="padding: 20px;">
                                    <span style="font-size: var(--fs-sm); font-weight: 700; color: var(--text-muted);">
                                        <i class="fa-regular fa-calendar" style="margin-right: 5px; opacity: 0.5;"></i>
                                        <?php echo date('d/m/Y', strtotime($c['data_evento'])); ?>
                                    </span>
                                </td>
                                <td style="padding: 20px;">
                                    <span style="font-size: var(--fs-sm); color: var(--dark-main); font-weight: 700;">
                                        <i class="fa-solid fa-location-dot" style="margin-right: 8px; color: var(--text-muted);"></i>
                                        <?php echo htmlspecialchars($c['localizacao'] ?: '--'); ?>
                                    </span>
                                </td>
                                <td style="padding: 20px;">
                                    <span class="badge-sq <?php echo $c['status'] === 'aberto' ? 'badge-ativo' : 'badge-inativo'; ?>" style="font-size: var(--fs-xs);">
                                        <?php echo strtoupper($c['status']); ?>
                                    </span>
                                </td>
                                <td style="padding: 20px 30px; text-align: right;">
                                    <a href="editar_competicao.php?id=<?php echo $c['id']; ?>" class="btn-sq-outline" style="padding: 8px 15px; font-size: var(--fs-xs); text-decoration: none;">
                                        Gerenciar / Inscritos
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>