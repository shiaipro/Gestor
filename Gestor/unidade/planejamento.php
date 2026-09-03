<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('planejamento_visualizar')) {
    header('Location: administrativo_dashboard.php?erro=sem_permissao');
    exit;
}

include 'header.php';

// 1. Auto-Migração para Planejamento
try {
    $pdo->query("SELECT 1 FROM unidade_planejamento LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS unidade_planejamento (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT,
        data_limite DATE,
        prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
        status ENUM('pendente', 'em_andamento', 'concluido') DEFAULT 'pendente',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

// Exclusão
if (isset($_POST['excluir_id'])) {
    if (!temPermissaoModulo('planejamento_remover')) {
        header("Location: planejamento.php?erro=sem_permissao");
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM unidade_planejamento WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$_POST['excluir_id'], $unidade_id]);
    $mensagem = "Item de planejamento removido!";
}

// Busca
$stmt = $pdo->prepare("SELECT * FROM unidade_planejamento WHERE unidade_id = ? ORDER BY data_limite ASC, prioridade DESC");
$stmt->execute([$unidade_id]);
$planos = $stmt->fetchAll();
?>



<section class="dashboard-section-sq">
    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'sem_permissao'): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> ACESSO NEGADO: Você não tem permissão para realizar esta ação.
        </div>
    <?php endif; ?>

    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Planejamento Estratégico
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Defina metas, objetivos e acompanhe o crescimento da sua unidade.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <?php if (temPermissaoModulo('planejamento_criar')): ?>
            <a href="novo_item_planejado.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus-circle" style="margin-right: 8px;"></i> Novo Objetivo
            </a>
            <?php endif; ?>
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
    <a href="planejamento.php" class="tab-item-sq active">Planejamento</a>
</div>

    

    <?php if (isset($mensagem)): ?>
        <div class="stat-card-square" style="background: var(--light-green); border: 1px solid var(--primary-green); margin-bottom: 25px; padding: 15px 25px;">
            <div style="color: var(--primary-green); font-weight: 700; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-check"></i> <?php echo $mensagem; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Grid de Objetivos -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 25px;">
        <?php foreach ($planos as $p):
            $cor_prioridade = [
                'baixa' => '#22c55e',
                'media' => '#f59e0b',
                'alta' => '#ef4444'
            ][$p['prioridade']];
            ?>
            <div class="dashboard-container" style="padding: 0; display: flex; flex-direction: column;">
                <div style="padding: 20px; border-bottom: 1px solid var(--border-color); background: #fafafa; display: flex; justify-content: space-between; align-items: center;">
                    <span style="background: <?php echo $cor_prioridade; ?>; color: #fff; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; padding: 3px 10px; letter-spacing: 0.05em;">
                        PRIORIDADE <?php echo $p['prioridade']; ?>
                    </span>
                    <div style="display: flex; gap: 10px;">
                        <?php if (temPermissaoModulo('planejamento_editar')): ?>
                        <a href="editar_item_planejado.php?id=<?php echo $p['id']; ?>" class="btn-sq-light" style="width: 30px; height: 30px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: var(--fs-sm);">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (temPermissaoModulo('planejamento_remover')): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este objetivo?')">
                            <input type="hidden" name="excluir_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn-sq-light" style="width: 30px; height: 30px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: var(--fs-sm); color: #ef4444; background: #fee2e2;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="padding: 25px; flex-grow: 1;">
                    <h3 style="font-size: 18px; font-weight: 800; color: var(--text-dark); margin: 0 0 15px 0; line-height: 1.3;">
                        <?php echo htmlspecialchars($p['titulo']); ?>
                    </h3>
                    <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; margin: 0; font-weight: 500;">
                        <?php echo nl2br(htmlspecialchars($p['descricao'])); ?>
                    </p>
                </div>

                <div style="padding: 15px 25px; background: #fdfdfd;  display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: var(--fs-xs); font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                        <i class="fa-regular fa-calendar-check"></i>
                        <?php echo date('d/m/Y', strtotime($p['data_limite'])); ?>
                    </div>
                    <?php
                    $status_style = [
                        'concluido' => ['bg' => 'var(--light-green)', 'fg' => 'var(--primary-green)'],
                        'em_andamento' => ['bg' => '#eff6ff', 'fg' => '#1d4ed8'],
                        'pendente' => ['bg' => '#fffbeb', 'fg' => '#d97706']
                    ][$p['status']] ?? ['bg' => '#f3f4f6', 'fg' => '#9ca3af'];
                    ?>
                    <span class="badge-sq" style="background: <?php echo $status_style['bg']; ?>; color: <?php echo $status_style['fg']; ?>; font-size: var(--fs-xs);">
                        <?php echo strtoupper(str_replace('_', ' ', $p['status'])); ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($planos)): ?>
            <div class="dashboard-container" style="grid-column: 1 / -1; padding: 80px 20px; text-align: center; border: 2px dashed var(--border-color); background: #fafafa;">
                <i class="fa-solid fa-chess-knight" style="font-size: 64px; color: var(--border-color); margin-bottom: 25px;"></i>
                <h4 style="font-weight: 800; color: var(--text-dark); margin-bottom: 10px;">Nenhum objetivo estratégico definido</h4>
                <p style="color: var(--text-muted);">Comece a planejar o futuro da sua unidade agora mesmo.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'footer.php'; ?>