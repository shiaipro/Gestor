<?php
require_once '../config.php';

$unidade_id = getUnidadeId();

if (!moduloAtivo('competicoes')) {
    echo "<div class='alert alert-danger' style='margin:2rem; padding:1.5rem; background:#fee2e2; color:#991b1b; border-radius:0px;'>Acesso Negado: O módulo de Eventos não está ativo para o seu plano.</div>";
    include 'footer.php';
    exit;
}

// Auto-migração: cadastro de academias/delegações visitantes (reutilizável em vários eventos)
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

$busca = trim($_GET['busca'] ?? '');

$sql = "SELECT d.*, u.nome AS unidade_vinculada_nome,
               (SELECT COUNT(*) FROM delegacoes_turmas dt WHERE dt.delegacao_id = d.id) AS total_turmas
        FROM delegacoes_visitantes d
        LEFT JOIN unidades u ON u.id = d.unidade_vinculada_id
        WHERE d.unidade_id = ?" .
        ($busca ? " AND d.nome LIKE ?" : "") .
        " ORDER BY d.nome ASC";

$params = [$unidade_id];
if ($busca) { $params[] = '%' . $busca . '%'; }

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$delegacoes = $stmt->fetchAll();

$custom_title = "Delegações Visitantes";
include 'header.php';
?>

<div class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Delegações Visitantes
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Academias e turmas convidadas para participar dos seus eventos
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="nova_delegacao.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-people-group" style="margin-right: 8px;"></i> Nova Delegação
            </a>
        </div>
    </div>

    <?php if (($_GET['sucesso'] ?? '') === 'removida'): ?>
        <div style="background:#dcfce7; color:#166534; padding:1rem 1.5rem; margin-bottom:20px;">Delegação removida com sucesso.</div>
    <?php endif; ?>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="eventos_dashboard.php" class="tab-item-sq">Dashboard</a>
        <a href="competicoes_oficiais.php" class="tab-item-sq">Oficiais</a>
        <a href="competicoes.php" class="tab-item-sq">Torneios</a>
        <a href="faixas.php" class="tab-item-sq">Exames</a>
        <a href="eventos_visitantes.php" class="tab-item-sq">Visitantes</a>
        <a href="delegacoes.php" class="tab-item-sq active">Delegações</a>
        <a href="relatorios_eventos.php" class="tab-item-sq">Relatórios</a>
    </div>

    <form method="GET" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
        <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar academia/delegação..."
            style="flex:1; min-width:220px; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
        <button type="submit" class="btn-sq" style="width:auto; padding:12px 25px;">Buscar</button>
        <?php if ($busca): ?>
            <a href="delegacoes.php" class="btn-sq-light" style="width:auto; padding:12px 25px;">Limpar</a>
        <?php endif; ?>
    </form>

    <div style="background: white; border: 1px solid var(--border-color); padding: 0; margin-bottom: 30px;">
        <?php if (empty($delegacoes)): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 5rem 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.1;"><i class="fa-solid fa-people-group"></i></div>
                <p style="font-weight: 600; font-size: 1rem;">Nenhuma delegação visitante cadastrada.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Academia / Delegação</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Cidade</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Responsável</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Turmas</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase; text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($delegacoes as $d): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 18px 20px;">
                                    <div style="font-weight: 800; color: var(--text-dark);"><?php echo htmlspecialchars($d['nome']); ?></div>
                                    <?php if ($d['unidade_vinculada_nome']): ?>
                                        <div style="font-size: var(--fs-xs); color: var(--primary-green); font-weight: 700;">
                                            <i class="fa-solid fa-link" style="margin-right:4px;"></i> Vinculada a <?php echo htmlspecialchars($d['unidade_vinculada_nome']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 18px 20px; font-size: var(--fs-sm);"><?php echo htmlspecialchars(trim(($d['cidade'] ?: '') . ($d['estado'] ? ' - ' . $d['estado'] : '')) ?: '—'); ?></td>
                                <td style="padding: 18px 20px; font-size: var(--fs-sm);">
                                    <?php if ($d['responsavel_nome']): ?>
                                        <div><?php echo htmlspecialchars($d['responsavel_nome']); ?></div>
                                        <div style="color: var(--text-muted); font-size: var(--fs-xs);"><?php echo htmlspecialchars($d['responsavel_telefone']); ?></div>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td style="padding: 18px 20px; font-size: var(--fs-sm);"><?php echo (int) $d['total_turmas']; ?></td>
                                <td style="padding: 18px 20px; text-align: right; white-space: nowrap;">
                                    <a href="editar_delegacao.php?id=<?php echo (int) $d['id']; ?>" class="btn-sq-light" style="width:auto; padding:8px 16px; font-size:var(--fs-xs);">
                                        <i class="fa-solid fa-pen" style="margin-right:6px;"></i> Gerenciar
                                    </a>
                                    <form method="POST" action="editar_delegacao.php?id=<?php echo (int) $d['id']; ?>" style="display:inline;"
                                        onsubmit="return confirm('Remover permanentemente esta academia/delegação? Ela também será removida dos convites de eventos e as turmas cadastradas serão apagadas.');">
                                        <button type="submit" name="remover_delegacao" value="1" style="width:auto; padding:8px 12px; font-size:var(--fs-xs); background:#fee2e2; color:#991b1b; border:1px solid #fecaca; cursor:pointer; margin-left:6px;">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
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
