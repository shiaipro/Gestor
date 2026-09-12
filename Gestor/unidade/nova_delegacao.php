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
        email VARCHAR(255) DEFAULT NULL,
        site VARCHAR(255) DEFAULT NULL,
        whatsapp VARCHAR(30) DEFAULT NULL,
        cidade VARCHAR(100) DEFAULT NULL,
        estado VARCHAR(2) DEFAULT NULL,
        pais VARCHAR(100) DEFAULT 'Brasil',
        responsavel_nome VARCHAR(255) DEFAULT NULL,
        responsavel_telefone VARCHAR(30) DEFAULT NULL,
        responsavel_email VARCHAR(255) DEFAULT NULL,
        tecnico_nome VARCHAR(255) DEFAULT NULL,
        tecnico_telefone VARCHAR(30) DEFAULT NULL,
        financeiro_nome VARCHAR(255) DEFAULT NULL,
        financeiro_telefone VARCHAR(30) DEFAULT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE,
        FOREIGN KEY (unidade_vinculada_id) REFERENCES unidades(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
// Automigração: novos campos de contato completo da academia (email, site, whatsapp, país, técnico, financeiro)
$col_check_deleg = $pdo->query("SHOW COLUMNS FROM delegacoes_visitantes LIKE 'email'")->fetch();
if (!$col_check_deleg) {
    $pdo->exec("ALTER TABLE delegacoes_visitantes
        ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER unidade_vinculada_id,
        ADD COLUMN site VARCHAR(255) DEFAULT NULL AFTER email,
        ADD COLUMN whatsapp VARCHAR(30) DEFAULT NULL AFTER site,
        ADD COLUMN pais VARCHAR(100) DEFAULT 'Brasil' AFTER estado,
        ADD COLUMN tecnico_nome VARCHAR(255) DEFAULT NULL AFTER responsavel_email,
        ADD COLUMN tecnico_telefone VARCHAR(30) DEFAULT NULL AFTER tecnico_nome,
        ADD COLUMN financeiro_nome VARCHAR(255) DEFAULT NULL AFTER tecnico_telefone,
        ADD COLUMN financeiro_telefone VARCHAR(30) DEFAULT NULL AFTER financeiro_nome
    ");
}

// Automigração: convidados (academias autorizadas a inscrever alunos) de cada competição
try {
    $pdo->query("SELECT 1 FROM competicao_convidados LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS competicao_convidados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        competicao_id INT NOT NULL,
        delegacao_id INT NOT NULL,
        observacao VARCHAR(255) DEFAULT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_competicao_delegacao (competicao_id, delegacao_id),
        FOREIGN KEY (competicao_id) REFERENCES competicoes(id) ON DELETE CASCADE,
        FOREIGN KEY (delegacao_id) REFERENCES delegacoes_visitantes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Outras unidades ativas do sistema, para o admin poder vincular a delegação a uma unidade já existente
$stmt_unidades = $pdo->prepare("SELECT id, nome, cidade, estado FROM unidades WHERE id != ? AND status = 'ativo' ORDER BY nome ASC");
$stmt_unidades->execute([$unidade_id]);
$unidades_disponiveis = $stmt_unidades->fetchAll();

// Se veio de "Academias Convidadas" de um evento específico, ao salvar já convida a academia e volta para lá
$evento_id = (int) ($_GET['evento_id'] ?? $_POST['evento_id'] ?? 0);
if ($evento_id) {
    $stmt_evt = $pdo->prepare("SELECT id FROM competicoes WHERE id = ? AND unidade_id = ?");
    $stmt_evt->execute([$evento_id, $unidade_id]);
    if (!$stmt_evt->fetch()) {
        $evento_id = 0;
    }
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unidade_vinculada_id = $_POST['unidade_vinculada_id'] ?: null;
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $site = trim($_POST['site'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $pais = trim($_POST['pais'] ?? '') ?: 'Brasil';
    $responsavel_nome = trim($_POST['responsavel_nome'] ?? '');
    $responsavel_telefone = trim($_POST['responsavel_telefone'] ?? '');
    $responsavel_email = trim($_POST['responsavel_email'] ?? '');
    $tecnico_nome = trim($_POST['tecnico_nome'] ?? '');
    $tecnico_telefone = trim($_POST['tecnico_telefone'] ?? '');
    $financeiro_nome = trim($_POST['financeiro_nome'] ?? '');
    $financeiro_telefone = trim($_POST['financeiro_telefone'] ?? '');

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
        $stmt = $pdo->prepare("INSERT INTO delegacoes_visitantes
            (unidade_id, nome, unidade_vinculada_id, email, site, whatsapp, cidade, estado, pais,
             responsavel_nome, responsavel_telefone, responsavel_email,
             tecnico_nome, tecnico_telefone, financeiro_nome, financeiro_telefone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $unidade_id, $nome, $unidade_vinculada_id, $email, $site, $whatsapp, $cidade, $estado, $pais,
            $responsavel_nome, $responsavel_telefone, $responsavel_email,
            $tecnico_nome, $tecnico_telefone, $financeiro_nome, $financeiro_telefone
        ]);
        $nova_delegacao_id = $pdo->lastInsertId();

        if ($evento_id) {
            $pdo->prepare("INSERT IGNORE INTO competicao_convidados (competicao_id, delegacao_id) VALUES (?, ?)")
                ->execute([$evento_id, $nova_delegacao_id]);
            header('Location: editar_competicao.php?id=' . $evento_id . '&tab=info&sucesso=convidada');
            exit;
        }

        header('Location: editar_delegacao.php?id=' . $nova_delegacao_id . '&sucesso=criada');
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

    <div style="background: white; border: 1px solid var(--border-color); padding: 2rem; max-width: 720px;">
        <form method="POST" id="formDelegacao">
            <?php if ($evento_id): ?>
                <input type="hidden" name="evento_id" value="<?php echo (int) $evento_id; ?>">
                <div style="background:var(--gray-50, #f9fafb); padding:12px 16px; margin-bottom:20px; font-size:var(--fs-sm); border-left:3px solid var(--primary-green);">
                    <i class="fa-solid fa-circle-info" style="margin-right:6px;"></i> Ao salvar, esta academia já será convidada para o evento e você voltará para lá.
                </div>
            <?php endif; ?>

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

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom: 20px;">
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">E-mail da Academia</label>
                    <input type="email" name="email" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);" placeholder="contato@academia.com.br">
                </div>
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Site</label>
                    <input type="text" name="site" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);" placeholder="www.academia.com.br">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Contato (Celular/WhatsApp)</label>
                <input type="text" name="whatsapp" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);" placeholder="(00) 00000-0000">
            </div>

            <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:16px; margin-bottom: 20px;">
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Cidade</label>
                    <input type="text" name="cidade" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">UF</label>
                    <input type="text" name="estado" maxlength="2" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm); text-transform:uppercase;">
                </div>
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">País</label>
                    <input type="text" name="pais" value="Brasil" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
            </div>

            <h3 style="font-size:var(--fs-base); font-weight:800; margin: 25px 0 15px; text-transform:uppercase;">Responsável</h3>
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
                <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">E-mail do responsável</label>
                <input type="email" name="responsavel_email" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
            </div>

            <h3 style="font-size:var(--fs-base); font-weight:800; margin: 25px 0 15px; text-transform:uppercase;">Técnico</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom: 30px;">
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Nome do técnico</label>
                    <input type="text" name="tecnico_nome" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Telefone</label>
                    <input type="text" name="tecnico_telefone" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
            </div>

            <h3 style="font-size:var(--fs-base); font-weight:800; margin: 25px 0 15px; text-transform:uppercase;">Financeiro</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom: 30px;">
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Nome do responsável financeiro</label>
                    <input type="text" name="financeiro_nome" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
                <div>
                    <label style="display:block; font-weight:700; font-size:var(--fs-sm); margin-bottom:8px;">Telefone</label>
                    <input type="text" name="financeiro_telefone" style="width:100%; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-sq" style="width:auto; padding:12px 30px;">Salvar Delegação</button>
                <a href="<?php echo $evento_id ? 'editar_competicao.php?id=' . $evento_id . '&tab=info' : 'delegacoes.php'; ?>" class="btn-sq-light" style="width:auto; padding:12px 30px;">Cancelar</a>
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
