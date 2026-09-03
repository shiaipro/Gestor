<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    die("Acesso negado.");
}

$tipo = $_GET['tipo'] ?? 'vendas';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

if ($tipo === 'vendas') {
    // Relatório de Vendas (Pedidos) no Período
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
        LIMIT 10
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
} else {
    // Alertas de Estoque Baixo
    $stmt_low_stock = $pdo->prepare("
        SELECT nome, estoque
        FROM cms_produtos
        WHERE unidade_id = ? AND status = 'ativo' AND estoque <= 3
        ORDER BY estoque ASC
    ");
    $stmt_low_stock->execute([$unidade_id]);
    $low_stock_products = $stmt_low_stock->fetchAll();

    // Valor Total do Inventário
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

    // Listagem Geral de Estoque
    $stmt_full_inventory = $pdo->prepare("
        SELECT p.id as prod_id, p.nome as prod_name, p.estoque as prod_estoque, p.preco, c.nome as cat_name
        FROM cms_produtos p
        LEFT JOIN cms_produtos_categorias c ON p.categoria_id = c.id
        WHERE p.unidade_id = ? AND p.status = 'ativo'
        ORDER BY p.nome ASC
    ");
    $stmt_full_inventory->execute([$unidade_id]);
    $full_inventory = $stmt_full_inventory->fetchAll(PDO::FETCH_ASSOC);

    // Fetch variants
    $stmt_all_variants = $pdo->prepare("
        SELECT g.* 
        FROM cms_produtos_grade g
        JOIN cms_produtos p ON g.produto_id = p.id
        WHERE p.unidade_id = ?
        ORDER BY g.id ASC
    ");
    $stmt_all_variants->execute([$unidade_id]);
    $all_variants = $stmt_all_variants->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório - SHIAI PRO</title>
    <style>
        body { font-family: sans-serif; padding: 20px; color: #333; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #08153a; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 22px; color: #08153a; text-transform: uppercase; }
        .header p { margin: 5px 0 0 0; font-size: 14px; color: #666; font-weight: bold; }
        
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .summary-card { border: 1px solid #ddd; padding: 15px; background: #fafafa; border-radius: 5px; }
        .summary-card span { font-size: 11px; text-transform: uppercase; color: #777; font-weight: bold; display: block; margin-bottom: 5px; }
        .summary-card div { font-size: 18px; font-weight: bold; color: #08153a; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { border: 1px solid #eee; padding: 10px 12px; text-align: left; font-size: 12px; }
        th { background-color: #f8fafc; font-weight: bold; color: #333; text-transform: uppercase; font-size: 10px; border-bottom: 2px solid #ddd; }
        
        .section-title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #08153a; margin-bottom: 12px; border-left: 4px solid #08153a; padding-left: 10px; }
        
        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #888; border-top: 1px solid #eee; padding-top: 15px; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 25px; display: flex; gap: 10px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #08153a; color: #fff; border: none; border-radius: 5px; font-weight: bold; font-size: 13px;">Imprimir Agora / Salvar PDF</button>
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 5px; font-weight: bold; font-size: 13px;">Fechar</button>
    </div>

    <?php if ($tipo === 'vendas'): ?>
        <div class="header">
            <h1>SHIAI PRO - Relatório de Vendas e Faturamento</h1>
            <p>Período: <?php echo date('d/m/Y', strtotime($data_inicio)); ?> até <?php echo date('d/m/Y', strtotime($data_fim)); ?></p>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <span>Faturamento Total</span>
                <div>R$ <?php echo number_format($sales_summary['faturamento_total'], 2, ',', '.'); ?></div>
            </div>
            <div class="summary-card">
                <span>Pedidos Realizados</span>
                <div><?php echo $sales_summary['total_pedidos']; ?></div>
            </div>
            <div class="summary-card">
                <span>Ticket Médio</span>
                <div>R$ <?php echo number_format($avg_ticket, 2, ',', '.'); ?></div>
            </div>
            <div class="summary-card">
                <span>Descontos</span>
                <div>R$ <?php echo number_format($sales_summary['desconto_total'], 2, ',', '.'); ?></div>
            </div>
        </div>

        <div class="section-title">Ranking de Produtos Mais Vendidos</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">Rank</th>
                    <th>Produto</th>
                    <th style="text-align: center; width: 120px;">Qtd Vendida</th>
                    <th style="text-align: right; width: 150px;">Total Faturado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($top_products)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px; color: #888;">Nenhuma venda confirmada neste período.</td>
                    </tr>
                <?php endif; ?>
                <?php $i = 1; foreach ($top_products as $tp): ?>
                    <tr>
                        <td><strong>#<?php echo $i++; ?></strong></td>
                        <td style="text-transform: uppercase;"><strong><?php echo htmlspecialchars($tp['nome']); ?></strong></td>
                        <td style="text-align: center; font-weight: bold;"><?php echo $tp['qtd_vendida']; ?> UN</td>
                        <td style="text-align: right; font-weight: bold; color: #166534;">R$ <?php echo number_format($tp['total_gerado'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="section-title">Vendas por Forma de Pagamento</div>
        <table>
            <thead>
                <tr>
                    <th>Método de Pagamento</th>
                    <th style="text-align: center; width: 150px;">Transações</th>
                    <th style="text-align: right; width: 200px;">Total Faturado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payment_methods)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 20px; color: #888;">Sem faturamento registrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($payment_methods as $pm): ?>
                    <tr>
                        <td style="text-transform: uppercase;"><strong><?php echo htmlspecialchars($pm['metodo_pagamento']); ?></strong></td>
                        <td style="text-align: center; font-weight: bold;"><?php echo $pm['total_vendas']; ?></td>
                        <td style="text-align: right; font-weight: bold; color: #166534;">R$ <?php echo number_format($pm['faturamento'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <div class="header">
            <h1>SHIAI PRO - Relatório de Inventário e Estoque</h1>
            <p>Gerado em: <?php echo date('d/m/Y \à\s H:i'); ?></p>
        </div>

        <div class="summary-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="summary-card">
                <span>Produtos Ativos</span>
                <div><?php echo $inventory_summary['total_produtos'] ?: 0; ?></div>
            </div>
            <div class="summary-card">
                <span>Itens em Estoque</span>
                <div><?php echo $inventory_summary['total_itens_estoque'] ?: 0; ?> UN</div>
            </div>
            <div class="summary-card">
                <span>Valor Total em Estoque</span>
                <div>R$ <?php echo number_format($inventory_summary['valor_total_estoque'] ?: 0.00, 2, ',', '.'); ?></div>
            </div>
        </div>

        <?php if (!empty($low_stock_products)): ?>
            <div style="border: 1px solid #f59e0b; background: #fffbeb; padding: 15px; margin-bottom: 25px; border-radius: 4px; font-size: 12px; color: #b45309;">
                <strong>ATENÇÃO: Produtos com estoque crítico (3 UN ou menos):</strong><br>
                <?php echo implode(', ', array_map(function($lp) { return htmlspecialchars($lp['nome']) . " (" . $lp['estoque'] . " UN)"; }, $low_stock_products)); ?>
            </div>
        <?php endif; ?>

        <div class="section-title">Inventário Detalhado</div>
        <table>
            <thead>
                <tr>
                    <th>Produto / SKU</th>
                    <th>Categoria</th>
                    <th>Grade / Variações</th>
                    <th style="text-align: center; width: 120px;">Estoque Total</th>
                    <th style="text-align: right; width: 120px;">Preço Unitário</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($full_inventory as $item): 
                    $prod_id = $item['prod_id'];
                    $variants_of_prod = $all_variants[$prod_id] ?? [];
                ?>
                    <tr>
                        <td style="text-transform: uppercase;">
                            <strong><?php echo htmlspecialchars($item['prod_name']); ?></strong><br>
                            <span style="font-size: 10px; color: #888;">SKU: #PROD-<?php echo str_pad($prod_id, 4, '0', STR_PAD_LEFT); ?></span>
                        </td>
                        <td style="text-transform: uppercase; font-size: 11px; color: #666;"><?php echo htmlspecialchars($item['cat_name'] ?: 'Sem Categoria'); ?></td>
                        <td>
                            <?php if (!empty($variants_of_prod)): ?>
                                <?php 
                                $v_lines = [];
                                foreach ($variants_of_prod as $v) {
                                    $v_desc = [];
                                    if (!empty($v['idade'])) $v_desc[] = strtoupper($v['idade']);
                                    if (!empty($v['genero']) && $v['genero'] != 'todos') $v_desc[] = strtoupper($v['genero']);
                                    if (!empty($v['tamanho'])) $v_desc[] = "TAM: " . $v['tamanho'];
                                    if (!empty($v['cor_nome'])) $v_desc[] = $v['cor_nome'];
                                    $v_lines[] = implode(' - ', $v_desc) . ": " . $v['estoque'] . " UN";
                                }
                                echo implode('<br>', $v_lines);
                                ?>
                            <?php else: ?>
                                <span style="color: #aaa; font-style: italic;">Sem variações</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; font-weight: bold;"><?php echo $item['prod_estoque']; ?> UN</td>
                        <td style="text-align: right; font-weight: bold;">R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="footer">
        <p>Gerado automaticamente pelo sistema SHIAI PRO em <?php echo date('d/m/Y \à\s H:i:s'); ?></p>
    </div>

    <script>
        // Disparar janela de impressão automaticamente ao carregar
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
