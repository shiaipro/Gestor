<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Processar Troca de Status
if (isset($_POST['update_status']) && isset($_POST['pedido_id'])) {
    $p_id = (int) $_POST['pedido_id'];
    $novo_status = $_POST['novo_status'];

    $stmt = $pdo->prepare("UPDATE loja_pedidos SET status = ? WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$novo_status, $p_id, $unidade_id]);
    $mensagem = "Status do pedido #$p_id atualizado!";
}

// Auto-migração colunas visitante
try {
    $pdo->query("SELECT visitante_nome FROM loja_pedidos LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE loja_pedidos ADD COLUMN visitante_nome VARCHAR(255) NULL AFTER academia_id");
    $pdo->exec("ALTER TABLE loja_pedidos ADD COLUMN visitante_telefone VARCHAR(20) NULL AFTER visitante_nome");
}

// Filtros
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT p.*, a.nome_completo as aluno_nome, ac.nome as academia_nome 
        FROM loja_pedidos p 
        LEFT JOIN alunos a ON p.aluno_id = a.id 
        LEFT JOIN academias ac ON p.academia_id = ac.id 
        WHERE p.unidade_id = ?";
$params = [$unidade_id];

if ($status_filter) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY p.criado_em DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$custom_title = "Gestão de Pedidos";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Pedidos da Loja
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Gerencie as vendas e acompanhe o status de processamento.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="loja_fazer_pedido.php" class="btn-sq" style="width: auto; padding: 12px 25px; background: var(--primary-green) !important; color: #fff !important; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-cart-plus"></i> Fazer Pedido
            </a>
            <a href="loja_dashboard.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-chart-line" style="margin-right: 8px;"></i> Estatísticas
            </a>
            <button onclick="window.print()" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-print" style="margin-right: 8px;"></i> Imprimir Lista
            </button>
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

    

    <?php 
    $msg_sucesso = $_GET['sucesso'] ?? $mensagem ?? '';
    if ($msg_sucesso): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px 25px; border-left: 4px solid #166534; margin-bottom: 30px; font-weight: 700; font-size: var(--fs-base);">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo htmlspecialchars($msg_sucesso); ?>
        </div>
    <?php endif; ?>

    <!-- Filtros Modernizados -->
    <div class="dashboard-container" style="padding: 25px; margin-bottom: 30px;">
        <form method="GET" style="display: flex; gap: 20px; align-items: end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Filtrar por Status</label>
                <select name="status" style="width: 100%; height: 45px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 600; color: var(--text-dark); appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                    <option value="">Todos os Status</option>
                    <option value="pendente" <?php echo $status_filter == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="pago" <?php echo $status_filter == 'pago' ? 'selected' : ''; ?>>Pago</option>
                    <option value="enviado" <?php echo $status_filter == 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                    <option value="entregue" <?php echo $status_filter == 'entregue' ? 'selected' : ''; ?>>Entregue</option>
                    <option value="cancelado" <?php echo $status_filter == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-sq" style="height: 45px; padding: 0 30px;">FILTRAR</button>
                <a href="loja_pedidos.php" class="btn-sq-light" style="height: 45px; padding: 0 20px; display: flex; align-items: center; justify-content: center;"><i class="fa-solid fa-rotate"></i></a>
            </div>
        </form>
    </div>

    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Pedido</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Cliente / Pagamento</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Data</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Total</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Status</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: center; letter-spacing: 0.05em;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pedidos)): ?>
                    <tr>
                        <td colspan="6" style="padding: 60px; text-align: center; color: var(--text-muted); font-weight: 600;">
                            <i class="fa-solid fa-cart-shopping" style="font-size: 40px; opacity: 0.1; display: block; margin-bottom: 15px;"></i>
                            Nenhum pedido encontrado.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($pedidos as $p):
                    $badge_class = [
                        'pendente' => 'badge-sq',
                        'pago' => 'badge-sq',
                        'cancelado' => 'badge-sq',
                        'enviado' => 'badge-sq',
                        'entregue' => 'badge-sq'
                    ][$p['status']] ?? 'badge-sq';
                    
                    $colors = [
                        'pendente' => ['bg' => '#fef3c7', 'text' => '#92400e'],
                        'pago' => ['bg' => '#dcfce7', 'text' => '#166534', 'p' => 'var(--primary-green)'],
                        'cancelado' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'p' => '#ef4444'],
                        'enviado' => ['bg' => '#dbeafe', 'text' => '#1e40af', 'p' => '#3b82f6'],
                        'entregue' => ['bg' => '#f3e8ff', 'text' => '#6b21a8', 'p' => '#8b5cf6']
                    ];
                    $c = $colors[$p['status']] ?? ['bg' => '#f1f5f9', 'text' => '#475569', 'p' => '#64748b'];

                    // Buscar itens comprados
                    $stmt_items = $pdo->prepare("
                        SELECT i.*, pr.nome as produto_nome, g.tamanho, g.altura, g.cor_nome, g.genero, g.idade
                        FROM loja_pedido_itens i
                        JOIN cms_produtos pr ON i.produto_id = pr.id
                        LEFT JOIN cms_produtos_grade g ON i.grade_id = g.id
                        WHERE i.pedido_id = ?
                    ");
                    $stmt_items->execute([$p['id']]);
                    $items_da_venda = $stmt_items->fetchAll();
                    ?>
                    <tr>
                        <td style="padding: 15px 30px;">
                            <span style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base);">#<?php echo $p['id']; ?></span>
                            
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); margin-top: 5px; line-height: 1.4;">
                                <?php foreach ($items_da_venda as $it): 
                                    $var_desc = [];
                                    if (!empty($it['idade'])) $var_desc[] = strtoupper($it['idade']);
                                    if (!empty($it['genero']) && $it['genero'] != 'todos') $var_desc[] = strtoupper($it['genero']);
                                    if (!empty($it['tamanho'])) $var_desc[] = "TAM: " . $it['tamanho'];
                                    if (!empty($it['altura'])) $var_desc[] = "ALT: " . $it['altura'];
                                    if (!empty($it['cor_nome'])) $var_desc[] = $it['cor_nome'];
                                    $desc = implode(' - ', $var_desc);
                                ?>
                                    <div style="margin-bottom: 2px;">
                                        <strong><?php echo $it['quantidade']; ?>x</strong> <?php echo htmlspecialchars($it['produto_nome']); ?>
                                        <?php if ($desc): ?>
                                            <span style="font-size: 9px; background: #e2e8f0; color: #475569; padding: 1px 4px; border-radius: 3px; font-weight: 700; margin-left: 3px;"><?php echo htmlspecialchars($desc); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <?php if ($p['academia_nome']): ?>
                                <div style="font-weight: 800; color: #08153a; font-size: var(--fs-base); text-transform: uppercase;">
                                    <i class="fa-solid fa-building" style="margin-right: 5px; color: var(--primary-green);"></i> <?php echo htmlspecialchars($p['academia_nome']); ?>
                                </div>
                            <?php elseif ($p['aluno_nome']): ?>
                                <div style="font-weight: 800; color: var(--text-dark); font-size: var(--fs-base);">
                                    <i class="fa-solid fa-user" style="margin-right: 5px; color: var(--text-muted); font-size: 10px;"></i> <?php echo htmlspecialchars($p['aluno_nome']); ?>
                                </div>
                            <?php else: ?>
                                <div style="font-weight: 800; color: var(--text-dark); font-size: var(--fs-base);">
                                    <i class="fa-solid fa-user-tag" style="margin-right: 5px; color: #f59e0b; font-size: 10px;"></i>
                                    <?php echo !empty($p['visitante_nome']) ? htmlspecialchars($p['visitante_nome']) : '<span style="color:var(--text-muted); font-weight:600;">Visitante</span>'; ?>
                                </div>
                                <?php if (!empty($p['visitante_telefone'])): ?>
                                    <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 2px;">
                                        <i class="fa-brands fa-whatsapp" style="margin-right: 3px; color: #25d366;"></i> <?php echo htmlspecialchars($p['visitante_telefone']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-top: 2px;">
                                <i class="fa-regular fa-credit-card" style="margin-right: 4px;"></i> <?php echo $p['metodo_pagamento']; ?>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 700; color: var(--text-dark); font-size: var(--fs-sm);"><?php echo date('d/m/Y', strtotime($p['criado_em'])); ?></div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 500;"><?php echo date('H:i', strtotime($p['criado_em'])); ?></div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base);">R$ <?php echo number_format($p['total'], 2, ',', '.'); ?></div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <span class="badge-sq" style="background: <?php echo $c['bg']; ?>; color: <?php echo $c['text']; ?>; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase;">
                                <?php echo $p['status']; ?>
                            </span>
                        </td>
                        <td style="padding: 15px 30px;">
                            <form method="POST" style="display: flex; gap: 8px; justify-content: center;">
                                <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="novo_status" onchange="this.form.submit()" style="height: 35px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 10px; font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); text-transform: uppercase;">
                                    <option value="">Alterar Status</option>
                                    <option value="pendente">Pendente</option>
                                    <option value="pago">Pago</option>
                                    <option value="enviado">Enviado</option>
                                    <option value="entregue">Entregue</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>