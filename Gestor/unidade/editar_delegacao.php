<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!moduloAtivo('competicoes') || !temPermissaoModulo('eventos')) {
    header('Location: delegacoes.php?erro=sem_permissao');
    exit;
}

// Auto-migração (mesmas tabelas de delegacoes.php)
try {
    $pdo->query("SELECT 1 FROM delegacoes_visitantes LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS delegacoes_visitantes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        unidade_vinculada_id INT DEFAULT NULL,
        cidade VARCHAR(100) DEFAULT NULL,
        estado VARCHAR(2) DEFAULT NULL,
        responsavel_nome VARCHAR(255) DEFAULT NULL,
        responsavel_telefone VARCHAR(30) DEFAULT NULL,
        responsavel_email VARCHAR(255) DEFAULT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE,
        FOREIGN KEY (unidade_vinculada_id) REFERENCES unidades(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
try {
    $pdo->query("SELECT 1 FROM delegacoes_turmas LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS delegacoes_turmas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delegacao_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        turma_vinculada_id INT DEFAULT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (delegacao_id) REFERENCES delegacoes_visitantes(id) ON DELETE CASCADE,
        FOREIGN KEY (turma_vinculada_id) REFERENCES turmas(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM delegacoes_visitantes WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$delegacao = $stmt->fetch();

if (!$delegacao) {
    header('Location: delegacoes.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['salvar_dados'])) {
        $nome = trim($_POST['nome'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $responsavel_nome = trim($_POST['responsavel_nome'] ?? '');
        $responsavel_telefone = trim($_POST['responsavel_telefone'] ?? '');
        $responsavel_email = trim($_POST['responsavel_email'] ?? '');

        if ($nome) {
            $stmt_upd = $pdo->prepare("UPDATE delegacoes_visitantes SET nome = ?, cidade = ?, estado = ?, responsavel_nome = ?, responsavel_telefone = ?, responsavel_email = ? WHERE id = ? AND unidade_id = ?");
            $stmt_upd->execute([$nome, $cidade, $estado, $responsavel_nome, $responsavel_telefone, $responsavel_email, $id, $unidade_id]);
            header('Location: editar_delegacao.php?id=' . $id . '&sucesso=atualizada');
            exit;
        }
        $erro = "Informe o nome da academia/delegação.";
    } elseif (isset($_POST['adicionar_turma'])) {
        $nome_turma = trim($_POST['nome_turma'] ?? '');

        if ($nome_turma) {
            $stmt_ins = $pdo->prepare("INSERT INTO delegacoes_turmas (delegacao_id, nome) VALUES (?, ?)");
            $stmt_ins->execute([$id, $nome_turma]);
        }
        header('Location: editar_delegacao.php?id=' . $id);
        exit;
    } elseif (isset($_POST['remover_turma'])) {
        $turma_id_remover = (int) ($_POST['turma_id'] ?? 0);
        $stmt_del = $pdo->prepare("DELETE FROM delegacoes_turmas WHERE id = ? AND delegacao_id = ?");
        $stmt_del->execute([$turma_id_remover, $id]);
        header('Location: editar_delegacao.php?id=' . $id);
        exit;
    }
}

// Recarrega (pode ter mudado após POST sem redirect, ex: erro de validação)
$stmt = $pdo->prepare("SELECT * FROM delegacoes_visitantes WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$delegacao = $stmt->fetch();

$stmt_turmas_deleg = $pdo->prepare("SELECT * FROM delegacoes_turmas WHERE delegacao_id = ? ORDER BY nome ASC");
$stmt_turmas_deleg->execute([$id]);
$turmas_delegacao = $stmt_turmas_deleg->fetchAll();

$custom_title = "Editar Delegação";
include 'header.php';
?>

<div class="dashboard-section-sq">
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
            <?php echo htmlspecialchars($delegacao['nome']); ?>
        </h1>
        <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
            <a href="delegacoes.php" style="color:inherit;">&larr; Voltar para Delegações</a>
        </p>
    </div>

    <?php if ($erro): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:1rem 1.5rem; margin-bottom:20px;"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['sucesso'])): ?>
        <div style="background:#dcfce7; color:#166534; padding:1rem 1.5rem; margin-bottom:20px;">Delegação atualizada com sucesso.</div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px; align-items:start;">

        <!-- Dados da delegação -->
        <div style="background: white; border: 1px solid var(--border-color); padding: 2rem;">
            <h3 style="font-size:var(--fs-base); font-weight:800; margin: 0 0 20px; text-transform:uppercase;">Dados da Delegação</h3>

            <?php if ($delegacao['unidade_vinculada_id']): ?>
                <div style="background:var(--gray-50, #f9fafb); padding:12px 16px; margin-bottom:20px; font-size:var(--fs-sm); border-left:3px solid var(--primary-green);">
                    <i class="fa-solid fa-link" style="margin-right:6px;"></i> Vinculada a uma unidade já existente no sistema — nome e turmas são sincronizados com ela.
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Nome</label>
                    <input type="text" name="nome" value="<?php echo htmlspecialchars($delegacao['nome']); ?>" required
                        style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
                <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom: 16px;">
                    <div>
                        <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Cidade</label>
                        <input type="text" name="cidade" value="<?php echo htmlspecialchars($delegacao['cidade'] ?? ''); ?>"
                            style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                    </div>
                    <div>
                        <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">UF</label>
                        <input type="text" name="estado" maxlength="2" value="<?php echo htmlspecialchars($delegacao['estado'] ?? ''); ?>"
                            style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm); text-transform:uppercase;">
                    </div>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Responsável</label>
                    <input type="text" name="responsavel_nome" value="<?php echo htmlspecialchars($delegacao['responsavel_nome'] ?? ''); ?>"
                        style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom: 24px;">
                    <div>
                        <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Telefone</label>
                        <input type="text" name="responsavel_telefone" value="<?php echo htmlspecialchars($delegacao['responsavel_telefone'] ?? ''); ?>"
                            style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                    </div>
                    <div>
                        <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">E-mail</label>
                        <input type="email" name="responsavel_email" value="<?php echo htmlspecialchars($delegacao['responsavel_email'] ?? ''); ?>"
                            style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                    </div>
                </div>
                <button type="submit" name="salvar_dados" value="1" class="btn-sq" style="width:auto; padding:12px 30px;">Salvar Dados</button>
            </form>
        </div>

        <!-- Turmas visitantes -->
        <div style="background: white; border: 1px solid var(--border-color); padding: 2rem;">
            <h3 style="font-size:var(--fs-base); font-weight:800; margin: 0 0 20px; text-transform:uppercase;">Turmas / Equipes Visitantes</h3>

            <form method="POST" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:flex-end;">
                <div style="flex:1; min-width:200px;">
                    <label style="display:block; font-weight:700; font-size:var(--fs-xs); margin-bottom:6px;">Nome da turma/equipe</label>
                    <input type="text" name="nome_turma" id="inputNomeTurma" placeholder="Ex: Sub-14 Masculino"
                        style="width:100%; padding:10px 14px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
                <button type="submit" name="adicionar_turma" value="1" class="btn-sq-light" style="width:auto; padding:10px 20px;">
                    <i class="fa-solid fa-plus" style="margin-right:6px;"></i> Adicionar
                </button>
            </form>

            <?php if (empty($turmas_delegacao)): ?>
                <p style="color: var(--text-muted); font-size: var(--fs-sm);">Nenhuma turma/equipe cadastrada ainda.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php foreach ($turmas_delegacao as $t): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#f8fafc; border:1px solid var(--border-color);">
                            <span style="font-weight:700; font-size:var(--fs-sm);">
                                <?php echo htmlspecialchars($t['nome']); ?>
                                <?php if ($t['turma_vinculada_id']): ?>
                                    <i class="fa-solid fa-link" style="margin-left:6px; color:var(--primary-green); font-size:11px;" title="Vinculada a turma existente"></i>
                                <?php endif; ?>
                            </span>
                            <form method="POST" onsubmit="return confirm('Remover essa turma/equipe da delegação?');">
                                <input type="hidden" name="turma_id" value="<?php echo (int) $t['id']; ?>">
                                <button type="submit" name="remover_turma" value="1" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:var(--fs-sm);">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>
