<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Filtros de Data para Vendas
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// 1. Relatório de Vendas (Pedidos) no Período
$stmt_sales_summary = $pdo->prepare("
    SELECT 
        COUNT(id) as total_pedidos,
        COALESCE(SUM(total), 0) as faturamento_total,
        COALESCE(SUM(desconto), 0) as desconto_total,
        COUNT(CASE WHEN status = 'pago' THEN 1 END) as pedidos_pagos,
        COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pedidos_pendentes,
        COUNT(CASE WHEN status = 'cancelado' THEN 1 END) as pedidos_cancelados
    FROM loja_pedidos
    WHERE unidade_id = ? AND DATE(criado_em) BETWEEN ? AND ?
");
$stmt_sales_summary->execute([$unidade_id, $data_inicio, $data_fim]);
$sales_summary = $stmt_sales_summary->fetch();

$avg_ticket = $sales_summary['total_pedidos'] > 0 ? $sales_summary['faturamento_total'] / $sales_summary['total_pedidos'] : 0.00;

// Ranking de Produtos Mais Vendidos
$stmt_top_products = $pdo->prepare("
    SELECT p.nome, SUM(i.quantidade) as qtd_vendida, SUM(i.subtotal) as total_gerado
    FROM loja_pedido_itens i
    JOIN cms_produtos p ON i.produto_id = p.id
    JOIN loja_pedidos o ON i.pedido_id = o.id
    WHERE o.unidade_id = ? AND o.status = 'pago' AND DATE(o.criado_em) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY qtd_vendida DESC
    LIMIT 5
");
$stmt_top_products->execute([$unidade_id, $data_inicio, $data_fim]);
$top_products = $stmt_top_products->fetchAll();

// Vendas por Método de Pagamento
$stmt_payment_methods = $pdo->prepare("
    SELECT metodo_pagamento, COUNT(id) as total_vendas, SUM(total) as faturamento
    FROM loja_pedidos
    WHERE unidade_id = ? AND status = 'pago' AND DATE(criado_em) BETWEEN ? AND ?
    GROUP BY metodo_pagamento
    ORDER BY faturamento DESC
");
$stmt_payment_methods->execute([$unidade_id, $data_inicio, $data_fim]);
$payment_methods = $stmt_payment_methods->fetchAll();

// 2. Relatório de Produtos & Estoque
// Produtos com estoque geral baixo ou grade baixo
$stmt_low_stock = $pdo->prepare("
    SELECT id, nome, estoque, status
    FROM cms_produtos
    WHERE unidade_id = ? AND status = 'ativo' AND estoque <= 3
    ORDER BY estoque ASC
");
$stmt_low_stock->execute([$unidade_id]);
$low_stock_products = $stmt_low_stock->fetchAll();

// Valor Total do Inventário em Estoque
$stmt_inventory_val = $pdo->prepare("
    SELECT 
        COUNT(id) as total_produtos,
        SUM(estoque) as total_itens_estoque,
        SUM(estoque * preco) as valor_total_estoque
    FROM cms_produtos
    WHERE unidade_id = ? AND status = 'ativo'
");
$stmt_inventory_val->execute([$unidade_id]);
$inventory_summary = $stmt_inventory_val->fetch();

// Listagem Geral de Estoque incluindo Variações
$stmt_full_inventory = $pdo->prepare("
    SELECT p.id as prod_id, p.nome as prod_name, p.estoque as prod_estoque, p.preco, p.status, c.nome as cat_name
    FROM cms_produtos p
    LEFT JOIN cms_produtos_categorias c ON p.categoria_id = c.id
    WHERE p.unidade_id = ?
    ORDER BY p.nome ASC
");
$stmt_full_inventory->execute([$unidade_id]);
$full_inventory = $stmt_full_inventory->fetchAll(PDO::FETCH_ASSOC);

// Fetch all variants for details
$stmt_all_variants = $pdo->prepare("
    SELECT g.* 
    FROM cms_produtos_grade g
    JOIN cms_produtos p ON g.produto_id = p.id
    WHERE p.unidade_id = ?
    ORDER BY g.id ASC
");
$stmt_all_variants->execute([$unidade_id]);
$all_variants = $stmt_all_variants->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

$custom_title = "Relatórios da Loja";
include 'header.php';
?>

<style>
    .report-tabs {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 10px;
    }
    .report-tab-btn {
        background: none;
        border: none;
        padding: 10px 20px;
        font-size: var(--fs-sm);
        font-weight: 800;
        color: var(--text-muted);
        cursor: pointer;
        text-transform: uppercase;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
    }
    .report-tab-btn.active {
        color: var(--text-dark);
        border-bottom-color: #08153a;
    }
    .report-panel {
        display: none;
    }
    .report-panel.active {
        display: block;
    }
    .grid-2col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
    @media (max-width: 768px) {
        .grid-2col {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Relatórios da Loja
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Analise vendas, faturamento, controle de estoque e inventário.
            </p>
        </div>
        <div>
            <button onclick="printActiveReport()" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-file-pdf" style="margin-right: 8px;"></i> Exportar PDF
            </button>
        </div>
    </div>

    <!-- Navegação por Abas Principais da Loja -->
    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="loja_dashboard.php" class="tab-item-sq">Painel</a>
        <a href="loja_pedidos.php" class="tab-item-sq">Pedidos</a>
        <a href="catalogo_produtos.php" class="tab-item-sq">Produtos</a>
        <a href="marketing_produtos_categorias.php" class="tab-item-sq">Categorias</a>
        <a href="loja_cupons.php" class="tab-item-sq">Cupons</a>
        <a href="loja_relatorios.php" class="tab-item-sq active">Relatórios</a>
        <a href="loja_config.php" class="tab-item-sq">Configuração</a>
    </div>

    <!-- Sub-Abas do Relatório -->
    <div class="report-tabs no-print">
        <button class="report-tab-btn active" onclick="switchTab('vendas')"><i class="fa-solid fa-chart-line"></i> Vendas e Faturamento</button>
        <button class="report-tab-btn" onclick="switchTab('estoque')"><i class="fa-solid fa-box-archive"></i> Produtos e Estoque</button>
    </div>

    <!-- PAINEL 1: VENDAS E FATURAMENTO -->
    <div id="panel-vendas" class="report-panel active">
        <!-- Filtro de Data -->
        <div class="dashboard-container no-print" style="padding: 25px; margin-bottom: 30px; background: #fafafa;">
            <form method="GET" style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label style="font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 8px;">Data Início</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?php echo $data_inicio; ?>" style="height: 45px; font-weight: 700;">
                </div>
                <div>
                    <label style="font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 8px;">Data Fim</label>
                    <input type="date" name="data_fim" class="form-control" value="<?php echo $data_fim; ?>" style="height: 45px; font-weight: 700;">
                </div>
                <button type="submit" class="btn-sq" style="height: 45px; padding: 0 30px; width: auto;">FILTRAR PERÍODO</button>
            </form>
        </div>

        <div style="background: #e2e8f0; padding: 10px 20px; margin-bottom: 30px; font-weight: 800; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.05em;" class="print-only">
            Período do Relatório: <?php echo date('d/m/Y', strtotime($data_inicio)); ?> até <?php echo date('d/m/Y', strtotime($data_fim)); ?>
        </div>

        <!-- Métricas Resumo -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin-bottom: 40px;">
            <div class="stat-card-square">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display:block; margin-bottom:10px;">Faturamento Total</span>
                <div style="font-size: 28px; font-weight: 900; color: var(--text-dark);">R$ <?php echo number_format($sales_summary['faturamento_total'], 2, ',', '.'); ?></div>
                <small style="color: var(--primary-green); font-weight:700;">Valor líquido faturado</small>
            </div>
            <div class="stat-card-square">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display:block; margin-bottom:10px;">Pedidos Realizados</span>
                <div style="font-size: 28px; font-weight: 900; color: var(--text-dark);"><?php echo $sales_summary['total_pedidos']; ?></div>
                <small style="color: var(--text-muted); font-weight:700;"><?php echo $sales_summary['pedidos_pagos']; ?> pagos | <?php echo $sales_summary['pedidos_pendentes']; ?> pendentes</small>
            </div>
            <div class="stat-card-square">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display:block; margin-bottom:10px;">Ticket Médio</span>
                <div style="font-size: 28px; font-weight: 900; color: var(--text-dark);">R$ <?php echo number_format($avg_ticket, 2, ',', '.'); ?></div>
                <small style="color: var(--text-muted); font-weight:700;">Média por venda</small>
            </div>
            <div class="stat-card-square">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display:block; margin-bottom:10px;">Descontos Concedidos</span>
                <div style="font-size: 28px; font-weight: 900; color: #ef4444;">R$ <?php echo number_format($sales_summary['desconto_total'], 2, ',', '.'); ?></div>
                <small style="color: #ef4444; font-weight:700;">Cupons e manuais</small>
            </div>
        </div>

        <div class="grid-2col">
            <!-- Ranking de Produtos -->
            <div class="dashboard-container" style="padding: 30px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h3 style="font-size: 1.1rem; font-weight: 900; color: var(--text-dark); text-transform: uppercase; margin: 0;">Mais Vendidos (Top 5)</h3>
                </div>
                <table class="table-sq" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="padding: 10px 15px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted);">Produto</th>
                            <th style="padding: 10px 15px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: center;">Qtd Vendida</th>
                            <th style="padding: 10px 15px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: right;">Total Gerado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_products)): ?>
                            <tr>
                                <td colspan="3" style="padding: 30px; text-align: center; color: var(--text-muted); font-weight: 600;">Nenhuma venda confirmada no período.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($top_products as $tp): ?>
                            <tr>
                                <td style="padding: 12px 15px; font-weight: 800; color: var(--text-dark); text-transform: uppercase; font-size: var(--fs-sm);"><?php echo htmlspecialchars($tp['nome']); ?></td>
                                <td style="padding: 12px 15px; text-align: center; font-weight: 900;"><?php echo $tp['qtd_vendida']; ?> UN</td>
                                <td style="padding: 12px 15px; text-align: right; font-weight: 900; color: var(--primary-green);">R$ <?php echo number_format($tp['total_gerado'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Métodos de Pagamento -->
            <div class="dashboard-container" style="padding: 30px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h3 style="font-size: 1.1rem; font-weight: 900; color: var(--text-dark); text-transform: uppercase; margin: 0;">Faturamento por Forma de Pagamento</h3>
                </div>
                <table class="table-sq" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="padding: 10px 15px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted);">Método</th>
                            <th style="padding: 10px 15px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: center;">Transações</th>
                            <th style="padding: 10px 15px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: right;">Total Faturado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payment_methods)): ?>
                            <tr>
                                <td colspan="3" style="padding: 30px; text-align: center; color: var(--text-muted); font-weight: 600;">Sem registros de faturamento.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($payment_methods as $pm): ?>
                            <tr>
                                <td style="padding: 12px 15px; font-weight: 800; color: var(--text-dark); text-transform: uppercase; font-size: var(--fs-sm);"><?php echo htmlspecialchars($pm['metodo_pagamento']); ?></td>
                                <td style="padding: 12px 15px; text-align: center; font-weight: 900;"><?php echo $pm['total_vendas']; ?></td>
                                <td style="padding: 12px 15px; text-align: right; font-weight: 900; color: var(--primary-green);">R$ <?php echo number_format($pm['faturamento'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PAINEL 2: PRODUTOS E ESTOQUE -->
    <div id="panel-estoque" class="report-panel">
        
        <!-- Alertas de Estoque Baixo -->
        <?php if (!empty($low_stock_products)): ?>
            <div style="background: #fffbeb; color: #b45309; padding: 20px 30px; border-left: 4px solid #f59e0b; margin-bottom: 30px;">
                <h4 style="margin: 0 0 10px 0; font-weight: 900; text-transform: uppercase; font-size: var(--fs-sm); display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> ALERTA: PRODUTOS COM ESTOQUE BAIXO (3 UN OU MENOS)
                </h4>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach ($low_stock_products as $lp): ?>
                        <span style="background: rgba(245, 158, 11, 0.15); padding: 5px 12px; font-size: var(--fs-xs); font-weight: 800; text-transform: uppercase; border-radius: 4px;">
                            <?php echo htmlspecialchars($lp['nome']); ?>: <strong><?php echo $lp['estoque']; ?> UN</strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Resumo do Inventário -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px;">
            <div class="stat-card-square">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display:block; margin-bottom:10px;">Total de Produtos Ativos</span>
                <div style="font-size: 28px; font-weight: 900; color: var(--text-dark);"><?php echo $inventory_summary['total_produtos'] ?: 0; ?></div>
                <small style="color: var(--text-muted); font-weight:700;">Tipos de produtos catalogados</small>
            </div>
            <div class="stat-card-square">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display:block; margin-bottom:10px;">Itens Totais em Estoque</span>
                <div style="font-size: 28px; font-weight: 900; color: var(--text-dark);"><?php echo $inventory_summary['total_itens_estoque'] ?: 0; ?> UN</div>
                <small style="color: var(--text-muted); font-weight:700;">Contagem física no inventário</small>
            </div>
            <div class="stat-card-square">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display:block; margin-bottom:10px;">Valor Total do Inventário</span>
                <div style="font-size: 28px; font-weight: 900; color: var(--primary-green);">R$ <?php echo number_format($inventory_summary['valor_total_estoque'] ?: 0.00, 2, ',', '.'); ?></div>
                <small style="color: var(--text-muted); font-weight:700;">Baseado no preço de venda unitário</small>
            </div>
        </div>

        <!-- Tabela Completa de Inventário -->
        <div class="dashboard-container" style="padding: 0;">
            <div style="padding: 20px 30px; border-bottom: 1px solid var(--border-color); background: #fafafa;">
                <h3 style="margin: 0; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase;">Lista Completa de Estoque & Grade</h3>
            </div>
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table class="table-sq" style="width: 100%; min-width: 720px;">
                <thead>
                    <tr>
                        <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted);">Produto</th>
                        <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted);">Categoria</th>
                        <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: center;">Variação / Grade Detalhada</th>
                        <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: center;">Qtd total em estoque</th>
                        <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: right;">Preço Unitário</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($full_inventory as $item): 
                        $prod_id = $item['prod_id'];
                        $variants_of_prod = $all_variants[$prod_id] ?? [];
                    ?>
                        <tr>
                            <td style="padding: 15px 30px; font-weight: 900; color: var(--text-dark); text-transform: uppercase; font-size: var(--fs-sm);">
                                <?php echo htmlspecialchars($item['prod_name']); ?>
                                <div style="font-size: 10px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-top: 3px; letter-spacing: 0.05em;">SKU: #PROD-<?php echo str_pad($prod_id, 4, '0', STR_PAD_LEFT); ?></div>
                            </td>
                            <td style="padding: 15px 30px; font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; color: var(--text-muted);">
                                <?php echo htmlspecialchars($item['cat_name'] ?: 'Sem Categoria'); ?>
                            </td>
                            <td style="padding: 15px 30px;">
                                <?php if (!empty($variants_of_prod)): ?>
                                    <div style="display: flex; flex-direction: column; gap: 5px;">
                                        <?php foreach ($variants_of_prod as $v): 
                                            $v_desc = [];
                                            if (!empty($v['idade'])) $v_desc[] = strtoupper($v['idade']);
                                            if (!empty($v['genero']) && $v['genero'] != 'todos') $v_desc[] = strtoupper($v['genero']);
                                            if (!empty($v['tamanho'])) $v_desc[] = "TAM: " . $v['tamanho'];
                                            if (!empty($v['cor_nome'])) $v_desc[] = $v['cor_nome'];
                                            $desc = implode(' - ', $v_desc);
                                        ?>
                                            <div style="font-size: var(--fs-xs); display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 3px;">
                                                <span style="font-weight: 600; color: #475569;"><?php echo htmlspecialchars($desc); ?></span>
                                                <span style="font-weight: 800; color: <?php echo $v['estoque'] > 0 ? 'var(--text-dark)' : '#ef4444'; ?>;"><?php echo $v['estoque']; ?> UN</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: var(--fs-xs); color: #94a3b8; font-weight: 600; text-transform: uppercase;">Produto sem variações de grade</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px 30px; text-align: center;">
                                <span style="font-weight: 900; font-size: var(--fs-base); color: <?php echo $item['prod_estoque'] > 0 ? 'var(--text-dark)' : '#ef4444'; ?>;">
                                    <?php echo $item['prod_estoque']; ?> UN
                                </span>
                            </td>
                            <td style="padding: 15px 30px; text-align: right; font-weight: 900; font-size: var(--fs-base); color: var(--text-dark);">
                                R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</section>

<script>
function switchTab(tab) {
    // Esconder painéis
    document.querySelectorAll('.report-panel').forEach(p => p.classList.remove('active'));
    // Desativar botões
    document.querySelectorAll('.report-tab-btn').forEach(b => b.classList.remove('active'));
    
    // Ativar aba
    const panel = document.getElementById('panel-' + tab);
    if (panel) panel.classList.add('active');
    
    // Ativar botão correspondente
    event.currentTarget.classList.add('active');
}

function printActiveReport() {
    // Verificar qual sub-aba está ativa
    const isVendasActive = document.querySelector('button[onclick*="vendas"]').classList.contains('active');
    const tipo = isVendasActive ? 'vendas' : 'estoque';
    
    const dataInicioInput = document.querySelector('input[name="data_inicio"]');
    const dataFimInput = document.querySelector('input[name="data_fim"]');
    
    const dataInicio = dataInicioInput ? dataInicioInput.value : '';
    const dataFim = dataFimInput ? dataFimInput.value : '';
    
    const url = `loja_imprimir_relatorio.php?tipo=${tipo}&data_inicio=${dataInicio}&data_fim=${dataFim}`;
    window.open(url, '_blank');
}
</script>

<?php include 'footer.php'; ?>
