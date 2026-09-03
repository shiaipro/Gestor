<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('rh_visualizar')) {
    header('Location: administrativo_dashboard.php?erro=sem_permissao');
    exit;
}

// 1. Auto-Migração para Equipe
try {
    $pdo->query("SELECT acesso_sistema FROM unidade_equipe LIMIT 1");
}
catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE unidade_equipe ADD COLUMN foto VARCHAR(255) AFTER nome");
    } catch (Exception $e_foto) {}

    try {
        $pdo->exec("ALTER TABLE unidade_equipe ADD COLUMN usuario_id INT NULL AFTER unidade_id");
        $pdo->exec("ALTER TABLE unidade_equipe ADD COLUMN nivel_acesso VARCHAR(50) NULL AFTER funcao");
        $pdo->exec("ALTER TABLE unidade_equipe ADD COLUMN acesso_sistema TINYINT(1) DEFAULT 0 AFTER status");
    } catch (Exception $e_mig) {
        $sql = "CREATE TABLE IF NOT EXISTS unidade_equipe (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidade_id INT NOT NULL,
            usuario_id INT NULL,
            nome VARCHAR(100) NOT NULL,
            foto VARCHAR(255) DEFAULT NULL,
            funcao VARCHAR(100) NOT NULL,
            nivel_acesso VARCHAR(50) NULL,
            telefone VARCHAR(20),
            email VARCHAR(100),
            status ENUM('ativo', 'inativo') DEFAULT 'ativo',
            acesso_sistema TINYINT(1) DEFAULT 0,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
}

// Exclusão
if (isset($_POST['excluir_id'])) {
    if (!temPermissaoModulo('rh_remover')) {
        header('Location: equipe.php?erro=sem_permissao');
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM unidade_equipe WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$_POST['excluir_id'], $unidade_id]);
    $mensagem = "Colaborador removido com sucesso!";
}

// Busca
$stmt = $pdo->prepare("SELECT * FROM unidade_equipe WHERE unidade_id = ? ORDER BY nome ASC");
$stmt->execute([$unidade_id]);
$equipe = $stmt->fetchAll();

$custom_title = "Gestão de Equipe";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Recursos Humanos
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Gestão de colaboradores e instrutores
            </p>
        </div>

    
        
        <?php if (temPermissaoModulo('rh_criar')): ?>
        <div style="display: flex; gap: 10px;">
            <a href="novo_membro.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-user-plus" style="margin-right: 8px;"></i> Novo Colaborador
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="alunos.php" class="tab-item-sq">Alunos</a>
    <a href="equipe.php" class="tab-item-sq active">RH</a>
    <a href="academias.php" class="tab-item-sq">Unidades</a>
    <a href="turmas.php" class="tab-item-sq">Turmas</a>
    <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
    <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
</div>

    

    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'sem_permissao'): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> ACESSO NEGADO: Você não tem permissão para realizar esta ação.
        </div>
    <?php endif; ?>

    <?php if (isset($mensagem)): ?>
        <div style="background: var(--primary-green); color: #fff; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <div class="dashboard-container" style="padding: 0; border: 1px solid var(--border-color);">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; width: 100px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Perfil</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Colaborador / Contato</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Função</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Status</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right; letter-spacing: 0.1em;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipe as $m): ?>
                    <tr>
                        <td style="padding: 20px 30px;">
                            <?php if ($m['foto']): ?>
                                <div style="width: 60px; height: 60px; border: 1px solid var(--border-color); overflow: hidden; background: #fafafa;">
                                    <img src="<?php echo getUploadURL('equipe', $m['foto']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            <?php else: ?>
                                <div style="width: 60px; height: 60px; background: #fafafa; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: #08153a;">
                                    <i class="fa-solid fa-user" style="font-size: 20px;"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;">
                                <?php echo htmlspecialchars($m['nome']); ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; margin-top: 5px; text-transform: uppercase; display: flex; align-items: center; gap: 10px;">
                                <span><i class="fa-solid fa-envelope" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($m['email']); ?></span>
                                <?php if ($m['acesso_sistema']): ?>
                                    <span style="color: var(--primary-green); font-size: 10px;" title="Tem acesso ao sistema">
                                        <i class="fa-solid fa-shield-halved"></i> ACESSO ATIVO
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 20px 30px;">
                            <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-start;">
                                <span style="background: #08153a; color: #fff; padding: 4px 12px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                                    <?php echo htmlspecialchars($m['funcao']); ?>
                                </span>
                            </div>
                        </td>
                        <td style="padding: 20px 30px;">
                            <span style="background: <?php echo $m['status'] == 'ativo' ? 'var(--primary-green)' : '#ef4444'; ?>; color: #fff; padding: 4px 12px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                                <?php echo $m['status']; ?>
                            </span>
                        </td>
                        <td style="padding: 20px 30px; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                <?php if (temPermissaoModulo('rh_editar')): ?>
                                <a href="editar_membro.php?id=<?php echo $m['id']; ?>" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Editar">
                                    <i class="fa-solid fa-pen-nib" style="font-size: var(--fs-sm);"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (temPermissaoModulo('rh_remover')): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este colaborador permanentemente?')">
                                    <input type="hidden" name="excluir_id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; color: #ef4444;">
                                        <i class="fa-solid fa-trash-can" style="font-size: var(--fs-sm);"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($equipe)): ?>
                    <tr><td colspan="5" style="padding: 80px; text-align: center; color: var(--text-muted); font-weight: 900;">NENHUM COLABORADOR REGISTRADO.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>