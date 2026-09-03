<?php
require_once '../config.php';

$unidade_id = getUnidadeId();

// 1. Auto-Migração para Produtos
try {
    $pdo->query("SELECT 1 FROM cms_produtos_categorias LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS cms_produtos_categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        descricao TEXT,
        imagem VARCHAR(255) DEFAULT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

try {
    $pdo->query("SELECT 1 FROM cms_produtos LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS cms_produtos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        categoria_id INT,
        nome VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        descricao LONGTEXT,
        preco DECIMAL(10,2) DEFAULT 0.00,
        preco_promocional DECIMAL(10,2) DEFAULT NULL,
        estoque INT DEFAULT 0,
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE,
        FOREIGN KEY (categoria_id) REFERENCES cms_produtos_categorias(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

try {
    $pdo->query("SELECT 1 FROM cms_produtos_imagens LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS cms_produtos_imagens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produto_id INT NOT NULL,
        caminho VARCHAR(255) NOT NULL,
        ordem INT DEFAULT 0,
        FOREIGN KEY (produto_id) REFERENCES cms_produtos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

$custom_title = "Gestão de Produtos";
include 'header.php';
?>

<style>
    .grade-tree-row { display:none; }
    .grade-tree-row.open { display:table-row; }
    .grade-tree-inner { padding:0; background:linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%); border-top:2px solid #133080; }
    .grade-tree-header { display:grid; grid-template-columns:20px 1fr 1fr 1fr 1fr 200px 90px 60px; gap:0; padding:8px 30px 8px 90px; background:#e2e8f0; }
    .grade-tree-header span { font-size:0.60rem; font-weight:900; text-transform:uppercase; color:#64748b; letter-spacing:0.06em; }
    .grade-tree-item { display:grid; grid-template-columns:20px 1fr 1fr 1fr 1fr 200px 90px 60px; gap:0; padding:9px 30px 9px 90px; border-bottom:1px solid #e8edf2; align-items:center; transition:background 0.15s; }
    .grade-tree-item:last-child { border-bottom:none; }
    .grade-tree-item:hover { background:#eff6ff; }
    .tree-connector { font-family:'Courier New', monospace; font-size:0.80rem; color:#94a3b8; font-weight:900; line-height:1; user-select:none; }
    .btn-toggle-grade { display:inline-flex; align-items:center; gap:6px; border:none; background:transparent; cursor:pointer; font-size:0.65rem; font-weight:900; text-transform:uppercase; letter-spacing:0.04em; color:#133080; padding:6px 10px; border:1px solid #c7d2fe; transition:all 0.2s; }
    .btn-toggle-grade:hover, .btn-toggle-grade.open { background:#133080; color:#a3e635; border-color:#133080; }
    .btn-toggle-grade .icon-chevron { transition:transform 0.25s; font-size:10px; }
    .btn-toggle-grade.open .icon-chevron { transform:rotate(180deg); }
</style>

<?php
$cat_filter = $_GET['categoria'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT p.*, c.nome as categoria_nome 
        FROM cms_produtos p 
        LEFT JOIN cms_produtos_categorias c ON p.categoria_id = c.id 
        WHERE p.unidade_id = ?";
$params = [$unidade_id];

if ($cat_filter) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $cat_filter;
}
if ($status_filter) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY p.nome ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// Categorias para filtro
$stmt_cats = $pdo->prepare("SELECT id, nome FROM cms_produtos_categorias WHERE unidade_id = ? ORDER BY nome ASC");
$stmt_cats->execute([$unidade_id]);
$categorias = $stmt_cats->fetchAll();

// ─── Carregar variações individuais por produto (batch) ───────────────────
$grade_por_produto = [];
$estoque_grade_sum = [];
if (!empty($produtos)) {
    $ids = array_column($produtos, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt_grades = $pdo->prepare("
            SELECT produto_id, id, idade, genero, tamanho, altura, cor_nome, cor_hex, estoque, foto
            FROM cms_produtos_grade
            WHERE produto_id IN ($placeholders)
            ORDER BY produto_id ASC, id ASC
        ");
        $stmt_grades->execute($ids);
        foreach ($stmt_grades->fetchAll() as $row) {
            $grade_por_produto[$row['produto_id']][] = $row;
            $estoque_grade_sum[$row['produto_id']] = ($estoque_grade_sum[$row['produto_id']] ?? 0) + (int)$row['estoque'];
        }
    } catch (Exception $e) {}
}
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Catálogo de Produtos
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Gerencie o inventário e a visibilidade dos seus produtos na loja.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="marketing_produtos_categorias.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-tags" style="margin-right: 8px;"></i> Categorias
            </a>
            <a href="editar_produto.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Novo Produto
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="loja_dashboard.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_dashboard.php') ? 'active' : ''; ?>">Painel</a>
    <a href="loja_pedidos.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_pedidos.php') ? 'active' : ''; ?>">Pedidos</a>
    <a href="catalogo_produtos.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'catalogo_produtos.php') ? 'active' : ''; ?>">Produtos</a>
    <a href="marketing_produtos_categorias.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'marketing_produtos_categorias.php') ? 'active' : ''; ?>">Categorias</a>
    <a href="loja_cupons.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_cupons.php') ? 'active' : ''; ?>">Cupons</a>
    <a href="loja_relatorios.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_relatorios.php') ? 'active' : ''; ?>">Relatórios</a>
    <a href="loja_config.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_config.php') ? 'active' : ''; ?>">Configuração</a>
</div>

    

    <!-- Filtros Modernizados -->
    <div class="dashboard-container" style="padding: 25px; margin-bottom: 30px;">
        <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 20px; align-items: end;">
            <div>
                <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Categoria</label>
                <select name="categoria" class="form-control" style="font-size: 0.85rem;">
                    <option value="">TODAS AS CATEGORIAS</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $cat_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo strtoupper(htmlspecialchars($cat['nome'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Status</label>
                <select name="status" class="form-control" style="font-size: 0.85rem;">
                    <option value="">TODOS OS STATUS</option>
                    <option value="ativo" <?php echo $status_filter == 'ativo' ? 'selected' : ''; ?>>ATIVO</option>
                    <option value="inativo" <?php echo $status_filter == 'inativo' ? 'selected' : ''; ?>>INATIVO</option>
                </select>
            </div>
            <button type="submit" class="btn-sq" style="height: 45px; padding: 0 30px;">APLICAR</button>
        </form>
    </div>

    <!-- Tabela de Produtos -->
    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; width: 80px;"></th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Produto / SKU</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Categoria</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Preço</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Estoque</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Variações</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Status</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right; letter-spacing: 0.05em;">Gerenciar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($produtos)): ?>
                    <tr>
                        <td colspan="8" style="padding: 60px; text-align: center; color: var(--text-muted); font-weight: 600;">
                            <i class="fa-solid fa-box-open" style="font-size: 40px; opacity: 0.1; display: block; margin-bottom: 15px;"></i>
                            Nenhum produto encontrado.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($produtos as $p):
                    $stmt_img = $pdo->prepare("SELECT caminho FROM cms_produtos_imagens WHERE produto_id = ? ORDER BY ordem ASC LIMIT 1");
                    $stmt_img->execute([$p['id']]);
                    $img = $stmt_img->fetchColumn();
                    ?>
                    <tr>
                        <td style="padding: 15px 30px;">
                            <?php if ($img): ?>
                                <div style="width: 60px; height: 60px; border: 1px solid var(--border-color); overflow: hidden; background: #fafafa;">
                                    <img src="../uploads/cms/<?php echo htmlspecialchars($img); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            <?php else: ?>
                                <div style="width: 60px; height: 60px; background: #fafafa; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: #cbd5e1;">
                                    <i class="fa-solid fa-box" style="font-size: 20px;"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase; letter-spacing: -0.02em;">
                                <?php echo htmlspecialchars($p['nome']); ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 900; text-transform: uppercase; margin-top: 3px; letter-spacing: 0.05em;">SKU: #PROD-<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <?php if ($p['categoria_nome']): ?>
                                <span style="background: #1e293b; color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                                    <?php echo htmlspecialchars($p['categoria_nome']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase;">Sem Categoria</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base);">R$ <?php echo number_format($p['preco'], 2, ',', '.'); ?></div>
                            <?php if ($p['preco_promocional']): ?>
                                <div style="font-size: var(--fs-xs); color: #ef4444; font-weight: 900; text-decoration: line-through; opacity: 0.6;">R$ <?php echo number_format($p['preco_promocional'], 2, ',', '.'); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px;">
                            <?php
                            $gds = $grade_por_produto[$p['id']] ?? [];
                            $tem_grade = !empty($gds);
                            $estoque_exibir = $tem_grade ? ($estoque_grade_sum[$p['id']] ?? 0) : (int)$p['estoque'];
                            ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 8px; height: 8px; background: <?php echo $estoque_exibir > 0 ? 'var(--primary-green)' : '#ef4444'; ?>;"></div>
                                <span style="color: <?php echo $estoque_exibir > 0 ? 'var(--text-dark)' : '#ef4444'; ?>; font-weight: 900; font-size: var(--fs-base);">
                                    <?php echo $estoque_exibir; ?> <small style="font-weight:900; opacity:0.6; font-size: var(--fs-xs);">UN</small>
                                </span>
                            </div>
                            <?php if ($tem_grade): ?>
                                <div style="font-size:0.58rem; color:var(--text-muted); font-weight:700; margin-top:2px;">soma das variações</div>
                            <?php endif; ?>
                        </td>

                        <!-- ── COLUNA VARIAÇÕES: botão expandir ── -->
                        <td style="padding: 12px 20px;">
                            <?php if ($tem_grade): ?>
                                <button id="btn-grade-<?php echo $p['id']; ?>"
                                        onclick="toggleGrade(<?php echo $p['id']; ?>)"
                                        class="btn-toggle-grade">
                                    <i class="fa-solid fa-table-cells" style="font-size:11px;"></i>
                                    <?php echo count($gds); ?> VAR.
                                    <i class="fa-solid fa-chevron-down icon-chevron"></i>
                                </button>
                            <?php else: ?>
                                <span style="color:#cbd5e1; font-size:0.65rem; font-weight:700; text-transform:uppercase;">
                                    <i class="fa-solid fa-minus" style="font-size:9px; margin-right:3px;"></i>Nenhuma
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px;">
                            <span style="background: <?php echo $p['status'] == 'ativo' ? 'var(--primary-green)' : '#ef4444'; ?>; color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                                <?php echo $p['status']; ?>
                            </span>
                        </td>
                        <td style="padding: 15px 30px; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                <a href="editar_produto.php?id=<?php echo $p['id']; ?>" class="btn-sq-light"
                                    style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;"
                                    title="Editar">
                                    <i class="fa-solid fa-pen-nib" style="font-size: var(--fs-sm);"></i>
                                </a>
                                <a href="?delete_id=<?php echo $p['id']; ?>" onclick="return confirm('Excluir este produto?')"
                                    class="btn-sq-light"
                                    style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; color: #ef4444;"
                                    title="Excluir">
                                    <i class="fa-solid fa-trash-can" style="font-size: var(--fs-sm);"></i>
                                </a>
                            </div>
                        </td>
                    </tr>

                    <?php if ($tem_grade): ?>
                    <!-- ── LINHA ÁRVORE DE VARIAÇÕES ── -->
                    <tr id="grade-row-<?php echo $p['id']; ?>" class="grade-tree-row">
                        <td colspan="8" class="grade-tree-inner">

                            <!-- Cabeçalho da árvore -->
                            <div class="grade-tree-header">
                                <span></span>
                                <span>Idade</span>
                                <span>Gênero</span>
                                <span>Tamanho</span>
                                <span>Altura</span>
                                <span>Cor</span>
                                <span style="text-align:right;">Estoque</span>
                                <span></span>
                            </div>

                            <!-- Itens da árvore -->
                            <?php foreach ($gds as $idx => $gv):
                                $is_last = ($idx === count($gds) - 1);
                                $connector = $is_last ? '└─' : '├─';
                            ?>
                            <div class="grade-tree-item">

                                <!-- Conector árvore -->
                                <div class="tree-connector"><?php echo $connector; ?></div>

                                <!-- Idade -->
                                <div>
                                    <?php if (!empty($gv['idade'])): ?>
                                        <span style="background:#f1f5f9; color:#0f172a; font-size:0.60rem; font-weight:900; padding:3px 8px; text-transform:uppercase; border:1px solid #e2e8f0;"><?php echo htmlspecialchars($gv['idade']); ?></span>
                                    <?php else: ?><span style="color:#cbd5e1; font-size:0.75rem;">—</span><?php endif; ?>
                                </div>

                                <!-- Gênero -->
                                <div>
                                    <?php if (!empty($gv['genero'])): ?>
                                        <span style="background:#f1f5f9; color:#0f172a; font-size:0.60rem; font-weight:900; padding:3px 8px; text-transform:uppercase; border:1px solid #e2e8f0;"><?php echo htmlspecialchars($gv['genero']); ?></span>
                                    <?php else: ?><span style="color:#cbd5e1; font-size:0.75rem;">—</span><?php endif; ?>
                                </div>

                                <!-- Tamanho -->
                                <div>
                                    <?php if (!empty($gv['tamanho'])): ?>
                                        <span style="background:#133080; color:#a3e635; font-size:0.62rem; font-weight:900; padding:3px 10px; text-transform:uppercase; letter-spacing:0.02em;"><?php echo htmlspecialchars($gv['tamanho']); ?></span>
                                    <?php else: ?><span style="color:#cbd5e1; font-size:0.75rem;">—</span><?php endif; ?>
                                </div>

                                <!-- Altura -->
                                <div>
                                    <?php if (!empty($gv['altura'])): ?>
                                        <span style="background:#f8fafc; color:#475569; font-size:0.60rem; font-weight:800; padding:3px 8px; text-transform:uppercase; border:1px solid #e2e8f0;"><?php echo htmlspecialchars($gv['altura']); ?></span>
                                    <?php else: ?><span style="color:#cbd5e1; font-size:0.75rem;">—</span><?php endif; ?>
                                </div>

                                <!-- Cor (swatch + nome) -->
                                <div style="display:flex; align-items:center; gap:7px;">
                                    <?php if (!empty($gv['cor_hex'])): ?>
                                        <div style="width:16px; height:16px; border-radius:50%; background:<?php echo htmlspecialchars($gv['cor_hex']); ?>; border:2px solid rgba(0,0,0,0.12); box-shadow:0 1px 3px rgba(0,0,0,0.15); flex-shrink:0;"></div>
                                    <?php endif; ?>
                                    <?php if (!empty($gv['cor_nome'])): ?>
                                        <span style="font-size:0.62rem; font-weight:800; color:#0f172a; text-transform:uppercase;"><?php echo htmlspecialchars($gv['cor_nome']); ?></span>
                                    <?php elseif (empty($gv['cor_hex'])): ?>
                                        <span style="color:#cbd5e1; font-size:0.75rem;">—</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Estoque desta variação -->
                                <div style="text-align:right;">
                                    <span style="font-size:0.65rem; font-weight:900; padding:3px 10px; color:<?php echo (int)$gv['estoque'] > 0 ? '#166534' : '#991b1b'; ?>; background:<?php echo (int)$gv['estoque'] > 0 ? '#dcfce7' : '#fee2e2'; ?>;">
                                        <?php echo (int)$gv['estoque']; ?> UN
                                    </span>
                                </div>

                                <!-- Atalho editar -->
                                <div style="text-align:center;">
                                    <a href="editar_produto.php?id=<?php echo $p['id']; ?>&tab=grade&edit_grade=<?php echo $gv['id']; ?>"
                                       style="color:#94a3b8; font-size:11px; text-decoration:none;" title="Editar esta variação">
                                        <i class="fa-solid fa-pen-nib"></i>
                                    </a>
                                </div>

                            </div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>

<script>
function toggleGrade(id) {
    const row = document.getElementById('grade-row-' + id);
    const btn = document.getElementById('btn-grade-' + id);
    if (!row) return;
    const isOpen = row.classList.contains('open');
    row.classList.toggle('open', !isOpen);
    btn && btn.classList.toggle('open', !isOpen);
}
</script>