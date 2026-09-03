<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (isset($_GET['delete'])) {
    $planId = $_GET['delete'];
    $pdo->prepare("DELETE FROM planos WHERE id = ? AND unidade_id = ?")->execute([$planId, $unidade_id]);
    header('Location: planos.php');
    exit;
}

$custom_title = "Gestão de Planos";
include 'header.php';

$stmt_planos = $pdo->prepare("SELECT * FROM planos WHERE unidade_id = ? ORDER BY ativo DESC, nome ASC");
$stmt_planos->execute([$unidade_id]);
$lista = $stmt_planos->fetchAll();
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Catálogo de Planos
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Configuração de serviços e mensalidades
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="novo_plano.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Novo Plano
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="alunos.php" class="tab-item-sq">Alunos</a>
    <a href="equipe.php" class="tab-item-sq">RH</a>
    <a href="academias.php" class="tab-item-sq">Unidades</a>
    <a href="turmas.php" class="tab-item-sq">Turmas</a>
    <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
    <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
</div>

    

    <!-- Listagem Square -->
    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Identificação do Plano</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Valor Mensal</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Recorrência</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Status</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right; letter-spacing: 0.1em;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $p): ?>
                    <tr>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;">
                                <?php echo htmlspecialchars($p['nome']); ?>
                            </div>
                            <?php if ($p['descricao']): ?>
                                <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 5px; text-transform: uppercase;">
                                    <?php echo htmlspecialchars(mb_strimwidth($p['descricao'], 0, 80, "...")); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base);">
                                R$ <?php echo number_format($p['valor'], 2, ',', '.'); ?>
                            </div>
                        </td>
                        <td style="padding: 20px 30px;">
                            <span style="background: #08153a; color: #fff; padding: 4px 12px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                                <?php echo $p['frequencia']; ?>
                            </span>
                        </td>
                        <td style="padding: 20px 30px;">
                            <span style="background: <?php echo $p['ativo'] ? 'var(--primary-green)' : '#94a3b8'; ?>; color: #fff; padding: 4px 12px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                                <?php echo $p['ativo'] ? 'ATIVO' : 'INATIVO'; ?>
                            </span>
                        </td>
                        <td style="padding: 20px 30px; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                <a href="editar_plano.php?id=<?php echo $p['id']; ?>" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Editar">
                                    <i class="fa-solid fa-pen-nib" style="font-size: var(--fs-sm);"></i>
                                </a>
                                <a href="planos.php?delete=<?php echo $p['id']; ?>" onclick="return confirm('ATENÇÃO: A exclusão do plano pode afetar novos contratos. Confirmar?')" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; color: #ef4444;" title="Excluir">
                                    <i class="fa-solid fa-trash-can" style="font-size: var(--fs-sm);"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($lista)): ?>
                    <tr><td colspan="5" style="padding: 80px; text-align: center; color: var(--text-muted); font-weight: 900;">NENHUM PLANO CADASTRADO.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>