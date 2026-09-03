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

// Auto-migração: garante que a tabela de delegações visitantes existe
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

// Outras unidades ativas do sistema, para o admin poder vincular a delegação a uma unidade já existente
$stmt_unidades = $pdo->prepare("SELECT id, nome, cidade, estado FROM unidades WHERE id != ? AND status = 'ativo' ORDER BY nome ASC");
$stmt_unidades->execute([$unidade_id]);
$unidades_disponiveis = $stmt_unidades->fetchAll();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unidade_vinculada_id = $_POST['unidade_vinculada_id'] ?: null;
    $nome = trim($_POST['nome'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $responsavel_nome = trim($_POST['responsavel_nome'] ?? '');
    $responsavel_telefone = trim($_POST['responsavel_telefone'] ?? '');
    $responsavel_email = trim($_POST['responsavel_email'] ?? '');

    // Se escolheu uma unidade já existente, usa o nome/cidade dela automaticamente
    if ($unidade_vinculada_id) {
        $stmt_u = $pdo->prepare("SELECT nome, cidade, estado FROM unidades WHERE id = ?");
        $stmt_u->execute([$unidade_vinculada_id]);
        $u = $stmt_u->fetch();
        if ($u) {
            $nome = $u['nome'];
            $cidade = $cidade ?: $u['cidade'];
            $estado = $estado ?: $u['estado'];
        }
    }

    if (!$nome) {
        $erro = "Informe o nome da academia/delegação ou selecione uma unidade existente.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO delegacoes_visitantes (unidade_id, nome, unidade_vinculada_id, cidade, estado, responsavel_nome, responsavel_telefone, responsavel_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$unidade_id, $nome, $unidade_vinculada_id, $cidade, $estado, $responsavel_nome, $responsavel_telefone, $responsavel_email]);
        header('Location: editar_delegacao.php?id=' . $pdo->lastInsertId() . '&sucesso=criada');
        exit;
    }
}

$custom_title = "Nova Delegação Visitante";
include 'header.php';
?>

<div class="dashboard-section-sq">
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
            Nova Delegação Visitante
        </h1>
        <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
            Cadastre uma academia/delegação nova, ou escolha uma unidade já existente no sistema
        </p>
    </div>

    <?php if ($erro): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:1rem 1.5rem; margin-bottom:20px;"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div style="background: white; border: 1px solid var(--border-color); padding: 2rem; max-width: 640px;">
        <form method="POST" id="formDelegacao">
            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Unidade já cadastrada no sistema (opcional)</label>
                <select name="unidade_vinculada_id" id="unidadeVinculada" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                    <option value="">— Nenhuma, é uma academia nova/externa —</option>
                    <?php foreach ($unidades_disponiveis as $u): ?>
                        <option value="<?php echo (int) $u['id']; ?>">
                            <?php echo htmlspecialchars($u['nome']); ?><?php echo $u['cidade'] ? ' (' . htmlspecialchars($u['cidade']) . ($u['estado'] ? '/' . htmlspecialchars($u['estado']) : '') . ')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:var(--fs-xs); color:var(--text-muted); margin-top:6px;">
                    Se a academia visitante já usa o SHIAI PRO, selecione-a aqui para reaproveitar os dados e poder escolher as turmas dela depois.
                </div>
            </div>

            <div style="margin-bottom: 20px;" id="campoNome">
                <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Nome da academia/delegação <span style="color:#ef4444;">*</span></label>
                <input type="text" name="nome" id="campoNomeInput" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);" placeholder="Ex: Academia Judô Norte">
            </div>

            <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom: 20px;">
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Cidade</label>
                    <input type="text" name="cidade" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">UF</label>
                    <input type="text" name="estado" maxlength="2" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm); text-transform:uppercase;">
                </div>
            </div>

            <h3 style="font-size:var(--fs-base); font-weight:800; margin: 25px 0 15px; text-transform:uppercase;">Responsável / Contato</h3>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom: 20px;">
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Nome do responsável</label>
                    <input type="text" name="responsavel_nome" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Telefone</label>
                    <input type="text" name="responsavel_telefone" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
            </div>
            <div style="margin-bottom: 30px;">
                <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">E-mail</label>
                <input type="email" name="responsavel_email" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-sq" style="width:auto; padding:12px 30px;">Salvar Delegação</button>
                <a href="delegacoes.php" class="btn-sq-light" style="width:auto; padding:12px 30px;">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    var selectUnidade = document.getElementById('unidadeVinculada');
    var campoNome = document.getElementById('campoNome');
    var campoNomeInput = document.getElementById('campoNomeInput');

    function alternarCampoNome() {
        if (selectUnidade.value) {
            campoNome.style.display = 'none';
            campoNomeInput.removeAttribute('required');
        } else {
            campoNome.style.display = 'block';
            campoNomeInput.setAttribute('required', 'required');
        }
    }
    selectUnidade.addEventListener('change', alternarCampoNome);
    alternarCampoNome();
</script>

<?php include 'footer.php'; ?>
