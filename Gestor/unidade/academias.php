<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('unidades_visualizar')) {
    header('Location: administrativo_dashboard.php?erro=sem_permissao');
    exit;
}

// 1. Auto-Migração para Academias
try {
    $pdo->exec("ALTER TABLE academias ADD COLUMN cnpj VARCHAR(20) NULL AFTER nome");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE academias ADD COLUMN whatsapp VARCHAR(20) NULL AFTER telefone");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE academias ADD COLUMN valor DECIMAL(10,2) DEFAULT 0.00 AFTER email");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE academias ADD COLUMN recorrencia VARCHAR(50) NULL AFTER valor");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE academias ADD COLUMN dia_vencimento INT NULL AFTER recorrencia");
} catch (Exception $e) {}

// Buscar Academias
$stmt = $pdo->prepare("SELECT * FROM academias WHERE unidade_id = ? ORDER BY nome ASC");
$stmt->execute([$unidade_id]);
$academias = $stmt->fetchAll();

$custom_title = "Gestão de Academias";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'sem_permissao'): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> ACESSO NEGADO: Você não tem permissão para realizar esta ação.
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'sem_valor_configurado'): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> ERRO: Esta academia não possui valor de contrato configurado para gerar cobrança.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] === 'cobranca_gerada'): ?>
        <div style="background: #dcfce7; color: #166534; padding: 20px; border-left: 4px solid var(--primary-green); margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> Cobrança gerada com sucesso! Você pode visualizá-la no painel do Financeiro.
        </div>
    <?php endif; ?>

    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="border-left: 8px solid var(--text-dark); padding-left: 20px;">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Unidades & Academias
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Gerencie as filiais e locais de treinamento vinculados à sua unidade.
            </p>
        </div>

    
        <?php if (temPermissaoModulo('unidades_criar')): ?>
        <div style="display: flex; gap: 10px;">
            <a href="nova_academia.php" class="btn-sq" style="padding: 12px 25px;">
                <i class="fa-solid fa-plus-square" style="margin-right: 8px;"></i> Nova Academia
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="alunos.php" class="tab-item-sq">Alunos</a>
    <a href="equipe.php" class="tab-item-sq">RH</a>
    <a href="academias.php" class="tab-item-sq active">Unidades</a>
    <a href="turmas.php" class="tab-item-sq">Turmas</a>
    <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
    <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
</div>

    

    <!-- Tabela -->
    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);">Unidade / Endereço</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);">Contato</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);">Status</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($academias as $a): ?>
                    <tr>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;"><?php echo htmlspecialchars($a['nome']); ?></div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-top: 3px;">
                                <i class="fa-solid fa-location-dot" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($a['endereco']); ?>
                            </div>
                        </td>
                        <td style="padding: 20px 30px;">
                            <div style="font-size: var(--fs-sm); font-weight: 800; color: var(--text-dark);"><i class="fa-solid fa-phone" style="font-size: 10px; margin-right: 4px; color: var(--text-muted);"></i> <?php echo htmlspecialchars($a['telefone']); ?></div>
                            <?php if(!empty($a['whatsapp'])): ?>
                                <div style="font-size: var(--fs-xs); font-weight: 800; color: #16a34a; margin-top: 3px;"><i class="fa-brands fa-whatsapp" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($a['whatsapp']); ?></div>
                            <?php endif; ?>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600; margin-top: 3px;"><i class="fa-regular fa-envelope" style="font-size: 10px; margin-right: 4px;"></i> <?php echo htmlspecialchars($a['email']); ?></div>
                        </td>
                        <td style="padding: 20px 30px;">
                            <?php if ($a['status'] === 'ativo'): ?>
                                <span style="background: var(--primary-green); color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">ATIVO</span>
                            <?php else: ?>
                                <span style="background: #ef4444; color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">INATIVO</span>
                            <?php endif; ?>
                            
                            <?php if (!empty($a['recorrencia'])): ?>
                                <div style="font-size: var(--fs-xs); color: var(--text-dark); font-weight: 800; margin-top: 8px;">
                                    R$ <?php echo number_format($a['valor'], 2, ',', '.'); ?> <span style="color: var(--text-muted); font-weight: 600;">(<?php echo $a['recorrencia']; ?>)</span>
                                </div>
                                <?php if (!empty($a['dia_vencimento'])): ?>
                                    <div style="font-size: 0.65rem; color: #f59e0b; font-weight: 800; text-transform: uppercase; margin-top: 2px;">
                                        Vence dia <?php echo $a['dia_vencimento']; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 20px 30px; text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <?php if (temPermissaoModulo('unidades_editar')): ?>
                                <a href="editar_academia.php?id=<?php echo $a['id']; ?>" class="btn-sq-light" style="height: 35px; font-size: var(--fs-xs); padding: 0 15px; display: inline-flex; align-items: center; gap: 8px;">
                                    <i class="fa-solid fa-edit"></i> EDITAR
                                </a>
                                <?php endif; ?>
                                <a href="gerar_cobranca_academia.php?id=<?php echo $a['id']; ?>" class="btn-sq" style="height: 35px; font-size: var(--fs-xs); padding: 0 15px; display: inline-flex; align-items: center; gap: 8px; background: #08153a !important; color: #fff !important; text-decoration: none;" onclick="return confirm('Deseja gerar a cobrança (Receita) para esta unidade baseada no valor contratual?')">
                                    <i class="fa-solid fa-file-invoice-dollar"></i> GERAR COBRANÇA
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($academias)): ?>
                    <tr><td colspan="4" style="padding: 80px; text-align: center; color: var(--text-muted); font-weight: 600;">NENHUMA ACADEMIA CADASTRADA.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>