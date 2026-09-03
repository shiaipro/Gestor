<?php
require_once '../config.php';

if (!ehMestre()) {
    header('Location: ../login.php');
    exit;
}

// Garantir que a tabela existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT DEFAULT NULL,
        email VARCHAR(255) NOT NULL,
        nome VARCHAR(255) DEFAULT NULL,
        nivel VARCHAR(50) DEFAULT NULL,
        unidade_id INT DEFAULT NULL,
        status ENUM('sucesso','falha') NOT NULL,
        ip VARCHAR(45) NOT NULL,
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_status (status),
        INDEX idx_email (email)
    )");
} catch (PDOException $e) {}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_nivel  = $_GET['nivel'] ?? '';
$filtro_search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Construir WHERE
$where = ['1=1'];
$params = [];

if ($filtro_status === 'sucesso' || $filtro_status === 'falha') {
    $where[] = 'status = ?';
    $params[] = $filtro_status;
}
if (in_array($filtro_nivel, ['mestre', 'admin', 'gestor', 'instrutor'])) {
    $where[] = 'nivel = ?';
    $params[] = $filtro_nivel;
}
if ($filtro_search) {
    $where[] = '(email LIKE ? OR nome LIKE ? OR ip LIKE ?)';
    $s = "%{$filtro_search}%";
    array_push($params, $s, $s, $s);
}

$where_sql = implode(' AND ', $where);

// Totais e registros
$total_rows = 0;
$total_pages = 1;
$logs = [];
$stats = ['total' => 0, 'sucessos' => 0, 'falhas' => 0, 'ips_unicos' => 0];

try {
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE {$where_sql}");
    $stmt_total->execute($params);
    $total_rows = (int)$stmt_total->fetchColumn();
    $total_pages = max(1, ceil($total_rows / $per_page));

    $stmt = $pdo->prepare("
        SELECT l.*, u.nome as unidade_nome
        FROM login_logs l
        LEFT JOIN unidades u ON l.unidade_id = u.id
        WHERE {$where_sql}
        ORDER BY l.created_at DESC
        LIMIT {$per_page} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Stats do dia
    $hoje = date('Y-m-d');
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
            SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falhas,
            COUNT(DISTINCT ip) as ips_unicos
        FROM login_logs
        WHERE DATE(created_at) = ?
    ");
    $stmt_stats->execute([$hoje]);
    $stats = $stmt_stats->fetch();
} catch (PDOException $e) {}

$custom_title = "Logs de Acesso";
include 'header.php';
?>

<style>
.log-badge { display: inline-block; padding: 3px 10px; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.06em; }
.log-badge.sucesso { background: #dcfce7; color: #15803d; }
.log-badge.falha   { background: #fee2e2; color: #991b1b; }
.nivel-badge { display: inline-block; padding: 2px 8px; font-size: 11px; font-weight: 900; text-transform: uppercase; background: #f1f5f9; color: #475569; }
.nivel-badge.mestre { background: #1e1b4b; color: #fff; }
.nivel-badge.admin  { background: #08153a; color: #ffc107; }
.filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 30px; }
.filter-bar input, .filter-bar select { height: 42px; border: 1px solid var(--border-color); padding: 0 15px; font-size: 13px; font-weight: 700; background: #fff; }
.filter-bar button { height: 42px; padding: 0 20px; font-size: 13px; font-weight: 900; text-transform: uppercase; }
.ua-truncated { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; color: #94a3b8; font-size: 11px; cursor: help; }
</style>

<section class="dashboard-section-sq">

    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
        <div style="border-left: 8px solid #08153a; padding-left: 20px;">
            <h1 style="font-size: 2rem; font-weight: 900; margin: 0; text-transform: uppercase; letter-spacing: -0.03em;">
                <i class="fa-solid fa-shield-halved" style="color: #ffc107; margin-right: 12px;"></i>Logs de Acesso
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0; font-weight: 500;">
                Monitoramento de todos os acessos e tentativas de login ao sistema.
            </p>
        </div>
    </div>

    <!-- Cards de Estatísticas do Dia -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 35px;">
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.1em;">Acessos Hoje</div>
            <div class="value" style="font-size: 32px; font-weight: 900;"><?php echo $stats['total'] ?? 0; ?></div>
            <div style="font-size: var(--fs-xs); color: var(--text-muted); margin-top: 8px; font-weight: 700;"><?php echo date('d/m/Y'); ?></div>
        </div>
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.1em;">Logins Bem-sucedidos</div>
            <div class="value" style="font-size: 32px; font-weight: 900; color: #22c55e;"><?php echo $stats['sucessos'] ?? 0; ?></div>
            <div style="font-size: var(--fs-xs); color: #22c55e; margin-top: 8px; font-weight: 700;">AUTENTICADOS</div>
        </div>
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.1em;">Tentativas Falhas</div>
            <div class="value" style="font-size: 32px; font-weight: 900; color: #ef4444;"><?php echo $stats['falhas'] ?? 0; ?></div>
            <div style="font-size: var(--fs-xs); color: #ef4444; margin-top: 8px; font-weight: 700;">BLOQUEADAS</div>
        </div>
        <div class="stat-card-square">
            <div class="label" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.1em;">IPs Únicos</div>
            <div class="value" style="font-size: 32px; font-weight: 900; color: #3b82f6;"><?php echo $stats['ips_unicos'] ?? 0; ?></div>
            <div style="font-size: var(--fs-xs); color: var(--text-muted); margin-top: 8px; font-weight: 700;">ENDEREÇOS DISTINTOS</div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Buscar por e-mail, nome ou IP..." value="<?php echo htmlspecialchars($filtro_search); ?>" style="flex: 1; min-width: 220px;">
        <select name="status">
            <option value="">Todos os Status</option>
            <option value="sucesso" <?php echo $filtro_status === 'sucesso' ? 'selected' : ''; ?>>✅ Sucesso</option>
            <option value="falha"   <?php echo $filtro_status === 'falha'   ? 'selected' : ''; ?>>❌ Falha</option>
        </select>
        <select name="nivel">
            <option value="">Todos os Níveis</option>
            <option value="mestre"   <?php echo $filtro_nivel === 'mestre'   ? 'selected' : ''; ?>>Mestre</option>
            <option value="admin"    <?php echo $filtro_nivel === 'admin'    ? 'selected' : ''; ?>>Admin</option>
            <option value="gestor"   <?php echo $filtro_nivel === 'gestor'   ? 'selected' : ''; ?>>Gestor</option>
            <option value="instrutor"<?php echo $filtro_nivel === 'instrutor'? 'selected' : ''; ?>>Instrutor</option>
        </select>
        <button type="submit" class="btn-sq">Filtrar</button>
        <a href="logs_acesso.php" class="btn-sq-light" style="height: 42px; display: flex; align-items: center; padding: 0 20px;">Limpar</a>
    </form>

    <!-- Tabela -->
    <div class="dashboard-container" style="padding: 0; overflow: hidden;">
        <table class="table-sq" style="width: 100%;">
            <thead>
                <tr>
                    <th style="padding: 18px 25px; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; white-space: nowrap;">Data / Hora</th>
                    <th style="padding: 18px 25px; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase;">Status</th>
                    <th style="padding: 18px 25px; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase;">Usuário</th>
                    <th style="padding: 18px 25px; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase;">Nível / Unidade</th>
                    <th style="padding: 18px 25px; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase;">IP de Acesso</th>
                    <th style="padding: 18px 25px; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase;">Dispositivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr style="<?php echo $log['status'] === 'falha' ? 'background: #fff5f5;' : ''; ?>">
                    <td style="padding: 14px 25px; white-space: nowrap;">
                        <div style="font-weight: 800; font-size: 13px;"><?php echo date('d/m/Y', strtotime($log['created_at'])); ?></div>
                        <div style="font-size: 12px; color: var(--text-muted); font-weight: 600;"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                    </td>
                    <td style="padding: 14px 25px;">
                        <span class="log-badge <?php echo $log['status']; ?>">
                            <?php echo $log['status'] === 'sucesso' ? '✓ Sucesso' : '✗ Falha'; ?>
                        </span>
                    </td>
                    <td style="padding: 14px 25px;">
                        <div style="font-weight: 800; font-size: 13px; color: var(--text-dark);">
                            <?php echo $log['nome'] ? htmlspecialchars($log['nome']) : '<span style="color:#94a3b8; font-style:italic;">Desconhecido</span>'; ?>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); font-weight: 600;"><?php echo htmlspecialchars($log['email']); ?></div>
                    </td>
                    <td style="padding: 14px 25px;">
                        <?php if ($log['nivel']): ?>
                            <span class="nivel-badge <?php echo $log['nivel']; ?>"><?php echo ucfirst($log['nivel']); ?></span>
                        <?php endif; ?>
                        <?php if ($log['unidade_nome']): ?>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px; font-weight: 600;"><?php echo htmlspecialchars($log['unidade_nome']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 14px 25px;">
                        <code style="font-size: 12px; background: #f8fafc; padding: 3px 8px; border: 1px solid var(--border-color);"><?php echo htmlspecialchars($log['ip']); ?></code>
                    </td>
                    <td style="padding: 14px 25px;">
                        <span class="ua-truncated" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                            <?php echo htmlspecialchars($log['user_agent'] ?? '—'); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" style="padding: 80px; text-align: center; color: var(--text-muted); font-weight: 600;">
                        <i class="fa-solid fa-shield-halved" style="font-size: 30px; margin-bottom: 15px; display: block; opacity: 0.3;"></i>
                        NENHUM LOG ENCONTRADO.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <?php if ($total_pages > 1): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; flex-wrap: wrap; gap: 10px;">
        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
            Mostrando <?php echo ($offset + 1); ?>–<?php echo min($offset + $per_page, $total_rows); ?> de <?php echo $total_rows; ?> registros
        </div>
        <div style="display: flex; gap: 8px;">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"
                   style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border: 1px solid <?php echo $p == $page ? '#08153a' : 'var(--border-color)'; ?>; background: <?php echo $p == $page ? '#08153a' : '#fff'; ?>; color: <?php echo $p == $page ? '#fff' : 'var(--text-dark)'; ?>; font-weight: 800; font-size: 13px; text-decoration: none;">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

</section>

<?php include 'footer.php'; ?>
