<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Processar Exclusão
if (isset($_POST['excluir_id'])) {
    $stmt = $pdo->prepare("DELETE FROM comercial_leads WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$_POST['excluir_id'], $unidade_id]);
    $mensagem = "Lead removido com sucesso!";
}

// Buscar Leads
$stmt = $pdo->prepare("SELECT * FROM comercial_leads WHERE unidade_id = ? ORDER BY criado_em DESC");
$stmt->execute([$unidade_id]);
$leads = $stmt->fetchAll();

// Contagens para o Dashboard
$contagem = [
    'novo' => 0,
    'em_contato' => 0,
    'agendado' => 0,
    'fechado' => 0,
    'perdido' => 0
];
foreach ($leads as $l) {
    $contagem[$l['status']]++;
}

$custom_title = "CRM de Vendas";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Pipeline de Vendas
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Gerencie suas oportunidades e converta prospectos em alunos.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="vendas_kanban.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-columns-look" style="margin-right: 8px;"></i> Visualizar Kanban
            </a>
            <a href="novo_lead.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-user-plus" style="margin-right: 8px;"></i> Novo Lead
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="comercial_dashboard.php" class="tab-item-sq">Início</a>
    <a href="crm_vendas.php" class="tab-item-sq active">Vendas</a>
    <a href="crm_pos_vendas.php" class="tab-item-sq">Pós Vendas</a>
    <a href="marketing_leads.php" class="tab-item-sq">CRM</a>
    <a href="mensagens.php" class="tab-item-sq">Mensagens</a>
    <a href="marketing_campanhas.php?menu=comercial" class="tab-item-sq">Funis de Vendas</a>
</div>

    

    <!-- Dashboard de Funil Mini -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 40px;">
        <?php
        $status_config = [
            'novo' => ['label' => 'Novos', 'cor' => '#3b82f6', 'icon' => 'fa-star'],
            'em_contato' => ['label' => 'Em Contato', 'cor' => '#f59e0b', 'icon' => 'fa-comments'],
            'agendado' => ['label' => 'Agendado', 'cor' => '#8b5cf6', 'icon' => 'fa-calendar-check'],
            'fechado' => ['label' => 'Fechado', 'cor' => 'var(--primary-green)', 'icon' => 'fa-crown'],
            'perdido' => ['label' => 'Perdido', 'cor' => '#ef4444', 'icon' => 'fa-thumbs-down']
        ];
        foreach ($status_config as $s_key => $s_val): ?>
            <div class="stat-card-square" style="padding: 25px; border-bottom: 4px solid <?php echo $s_val['cor']; ?>;">
                <div style="color: <?php echo $s_val['cor']; ?>; font-size: var(--fs-base); margin-bottom: 12px;">
                    <i class="fa-solid <?php echo $s_val['icon']; ?>"></i>
                </div>
                <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo $contagem[$s_key]; ?></div>
                <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-top: 12px; letter-spacing: 0.1em;">
                    <?php echo $s_val['label']; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (isset($mensagem)): ?>
        <div style="background: #E8F5E9; color: #2E7D32; padding: 20px; border: 1px solid #C8E6C9; margin-bottom: 30px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-check"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Prospecto</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Origem / Canal</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Status Atual</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right; letter-spacing: 0.05em;">Gerenciar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $l):
                    $cfg = $status_config[$l['status']];
                    ?>
                    <tr>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;">
                                <?php echo htmlspecialchars($l['nome']); ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 3px;">
                                <i class="fa-solid fa-phone" style="font-size: var(--fs-xs); margin-right: 5px; opacity: 0.5;"></i> <?php echo htmlspecialchars($l['telefone']); ?>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <span style="background: #1e293b; color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">
                                <?php echo htmlspecialchars($l['origem'] ?: 'DIRETO'); ?>
                            </span>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 8px; height: 8px; background: <?php echo $cfg['cor']; ?>;"></div>
                                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.05em;">
                                    <?php echo $cfg['label']; ?>
                                </span>
                            </div>
                        </td>
                        <td style="padding: 15px 30px; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                <a href="editar_lead.php?id=<?php echo $l['id']; ?>" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Editar Lead">
                                    <i class="fa-solid fa-user-pen" style="font-size: var(--fs-sm);"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este lead definitivamente?')">
                                    <input type="hidden" name="excluir_id" value="<?php echo $l['id']; ?>">
                                    <button type="submit" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; color: #ef4444;">
                                        <i class="fa-solid fa-trash-can" style="font-size: var(--fs-sm);"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="4" style="padding: 100px; text-align: center; color: var(--text-muted); font-weight: 600;">
                            <i class="fa-solid fa-users-viewfinder" style="font-size: 48px; opacity: 0.1; display: block; margin-bottom: 20px;"></i>
                            Nenhum lead encontrado no pipeline.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>