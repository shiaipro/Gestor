<?php
include '../config.php';

if (!estaLogado() || !moduloAtivo('financeiro')) {
    die("Acesso negado. Por favor, faça login.");
}

$unidade_id = getUnidadeId();
$mes_atual = isset($_GET['mes']) ? str_pad($_GET['mes'], 2, '0', STR_PAD_LEFT) : date('m');
$ano_atual = isset($_GET['ano']) ? $_GET['ano'] : date('Y');
$academia_id_filtro = $_GET['academia_id'] ?? null;

$meses_nomes = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

error_reporting(E_ALL);
ini_set('display_errors', 1);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

$where_academia = "";
$where_academia_m = "";
$where_academia_prefix = "";
$params = ['u' => $unidade_id, 'm' => $mes_atual, 'a' => $ano_atual];

$nome_academia_filtro = "Todas as Academias";

if ($academia_id_filtro) {
    $where_academia = " AND academia_id = ? ";
    $where_academia_m = " AND m.academia_id = ? ";
    $where_academia_prefix = " AND l.academia_id = ? ";

    $stmt_acad = $pdo->prepare("SELECT nome FROM academias WHERE id = ?");
    $stmt_acad->execute([$academia_id_filtro]);
    $nome_academia_filtro = $stmt_acad->fetchColumn();
}

// Resumo Financeiro
$where_academia_rel = $academia_id_filtro ? " AND academia_id = ? " : "";
$where_academia_prefix_rel = $academia_id_filtro ? " AND l.academia_id = ? " : "";

$rel = $pdo->prepare("
    SELECT 
        (SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE unidade_id = ? $where_academia_rel AND status = 'pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) +
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos l WHERE l.unidade_id = ? $where_academia_prefix_rel AND l.status = 'pago' AND l.tipo = 'receita' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) as total_receitas,
        
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos l WHERE l.unidade_id = ? $where_academia_prefix_rel AND l.status = 'pago' AND l.tipo = 'despesa' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) as total_despesas,

        (SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE unidade_id = ? $where_academia_rel AND status = 'pendente' AND MONTH(data_vencimento) = ? AND YEAR(data_vencimento) = ? AND aluno_id IN (SELECT id FROM alunos WHERE status != 'inativo')) +
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos l WHERE l.unidade_id = ? $where_academia_prefix_rel AND l.status = 'pendente' AND l.tipo = 'receita' AND MONTH(l.data_vencimento) = ? AND YEAR(l.data_vencimento) = ?) as prev_receitas,

        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos l WHERE l.unidade_id = ? $where_academia_prefix_rel AND l.status = 'pendente' AND l.tipo = 'despesa' AND MONTH(l.data_vencimento) = ? AND YEAR(l.data_vencimento) = ?) as prev_despesas
");

$p_exec = [];
for($i=0; $i<6; $i++) {
    $p_exec[] = $unidade_id;
    if($academia_id_filtro) $p_exec[] = $academia_id_filtro;
    $p_exec[] = $mes_atual;
    $p_exec[] = $ano_atual;
}

$rel->execute($p_exec);
$data = $rel->fetch();

$saldo_real = $data['total_receitas'] - $data['total_despesas'];
$saldo_previsto = ($data['total_receitas'] + $data['prev_receitas']) - ($data['total_despesas'] + $data['prev_despesas']);

// Quantidade de alunos pagos e pendentes
$stmt_qtd_alunos = $pdo->prepare("
    SELECT 
        (SELECT COUNT(DISTINCT aluno_id) FROM mensalidades WHERE unidade_id = ? $where_academia_rel AND status = 'pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) as alunos_pagos,
        (SELECT COUNT(DISTINCT aluno_id) FROM mensalidades WHERE unidade_id = ? $where_academia_rel AND status = 'pendente' AND MONTH(data_vencimento) = ? AND YEAR(data_vencimento) = ? AND aluno_id IN (SELECT id FROM alunos WHERE status != 'inativo')) as alunos_pendentes
");
$p_qtd = [$unidade_id];
if ($academia_id_filtro) $p_qtd[] = $academia_id_filtro;
$p_qtd[] = $mes_atual;
$p_qtd[] = $ano_atual;
$p_qtd[] = $unidade_id;
if ($academia_id_filtro) $p_qtd[] = $academia_id_filtro;
$p_qtd[] = $mes_atual;
$p_qtd[] = $ano_atual;

$stmt_qtd_alunos->execute($p_qtd);
$qtd_alunos = $stmt_qtd_alunos->fetch();

// Todas as Receitas Detalhadas
$stmt_rec_mensalidades = $pdo->prepare("
    SELECT m.data_vencimento, m.data_pagamento, 'Mensalidades de Alunos' as categoria, m.status, m.valor, a.nome_completo as descricao
    FROM mensalidades m
    JOIN alunos a ON m.aluno_id = a.id
    WHERE m.unidade_id = ? $where_academia_m 
    AND (
        (m.status = 'pago' AND MONTH(m.data_pagamento) = ? AND YEAR(m.data_pagamento) = ?) OR
        (m.status != 'pago' AND MONTH(m.data_vencimento) = ? AND YEAR(m.data_vencimento) = ? AND a.status != 'inativo')
    )
");
$p_rec_m = [$unidade_id];
if($academia_id_filtro) $p_rec_m[] = $academia_id_filtro;
array_push($p_rec_m, $mes_atual, $ano_atual, $mes_atual, $ano_atual);
$stmt_rec_mensalidades->execute($p_rec_m);
$rec_m = $stmt_rec_mensalidades->fetchAll();

$stmt_rec_lancamentos = $pdo->prepare("
    SELECT l.data_vencimento, l.data_pagamento, COALESCE(c.nome, 'Sem Categoria') as categoria, l.status, l.valor, l.descricao
    FROM financeiro_lancamentos l
    LEFT JOIN financeiro_categorias c ON l.categoria_id = c.id
    WHERE l.unidade_id = ? AND l.tipo = 'receita' $where_academia_prefix_rel
    AND (
        (l.status = 'pago' AND MONTH(l.data_pagamento) = ? AND YEAR(l.data_pagamento) = ?) OR
        (l.status != 'pago' AND MONTH(l.data_vencimento) = ? AND YEAR(l.data_vencimento) = ?)
    )
");
$p_rec_l = [$unidade_id];
if($academia_id_filtro) $p_rec_l[] = $academia_id_filtro;
array_push($p_rec_l, $mes_atual, $ano_atual, $mes_atual, $ano_atual);
$stmt_rec_lancamentos->execute($p_rec_l);
$rec_l = $stmt_rec_lancamentos->fetchAll();

$receitas_detalhes = array_merge($rec_m, $rec_l);
usort($receitas_detalhes, function($a, $b) {
    return strcasecmp($a['descricao'], $b['descricao']);
});

// Todas as Despesas Detalhadas
$stmt_desp = $pdo->prepare("
    SELECT l.data_vencimento, l.data_pagamento, COALESCE(c.nome, 'Sem Categoria') as categoria, l.status, l.valor, l.descricao
    FROM financeiro_lancamentos l
    LEFT JOIN financeiro_categorias c ON l.categoria_id = c.id
    WHERE l.unidade_id = ? AND l.tipo = 'despesa' $where_academia_prefix_rel
    AND (
        (l.status = 'pago' AND MONTH(l.data_pagamento) = ? AND YEAR(l.data_pagamento) = ?) OR
        (l.status != 'pago' AND MONTH(l.data_vencimento) = ? AND YEAR(l.data_vencimento) = ?)
    )
    ORDER BY l.descricao ASC
");
$p_desp = [$unidade_id];
if($academia_id_filtro) $p_desp[] = $academia_id_filtro;
array_push($p_desp, $mes_atual, $ano_atual, $mes_atual, $ano_atual);
$stmt_desp->execute($p_desp);
$despesas_detalhes = $stmt_desp->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Financeiro - <?php echo $meses_nomes[$mes_atual]; ?> <?php echo $ano_atual; ?></title>
    <!-- FontAwesome required for the print icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #08153a;
            --success: #22c55e;
            --danger: #ef4444;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
            margin: 0;
            padding: 2rem;
            background: #fff;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            text-transform: uppercase;
            font-weight: 900;
        }

        .header .info {
            text-align: right;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .summary-box {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            background: #f8fafc;
            page-break-inside: avoid;
        }

        .summary-box h3 {
            margin: 0 0 0.5rem 0;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .summary-box .value {
            font-size: 1.25rem;
            font-weight: 800;
        }

        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3rem;
        }

        th {
            background: #f1f5f9;
            text-align: left;
            padding: 0.75rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border);
        }

        td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }

        .badge-status {
            font-size: 0.65rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pago { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .status-pendente { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

        .section-title {
            font-size: 1.1rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--text-main);
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
        }

        @media print {
            body { padding: 0 !important; }
            .no-print { display: none !important; }
            @page { margin: 1cm; }
            .summary-box { border: 1px solid #ccc; background: transparent; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <div>
            <h1>Relatório Financeiro</h1>
            <div style="font-weight: 700; color: var(--text-muted); margin-top: 5px;">
                <?php echo htmlspecialchars($nome_academia_filtro); ?>
            </div>
        </div>
        <div class="info">
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary);">
                <?php echo strtoupper($meses_nomes[$mes_atual]); ?> <?php echo $ano_atual; ?>
            </div>
            <div style="margin-top: 8px;">
                Filtros: Contas Pagas e Pendentes<br>
                Gerado em: <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>
    </div>

    <!-- Resumo -->
    <div class="summary-grid">
        <div class="summary-box">
            <h3>Total Recebido</h3>
            <div class="value text-success">R$ <?php echo number_format($data['total_receitas'], 2, ',', '.'); ?></div>
        </div>
        <div class="summary-box">
            <h3>Total Pago</h3>
            <div class="value text-danger">R$ <?php echo number_format($data['total_despesas'], 2, ',', '.'); ?></div>
        </div>
        <div class="summary-box" style="background: <?php echo $saldo_real >= 0 ? 'rgba(34, 197, 94, 0.05)' : 'rgba(239, 68, 68, 0.05)'; ?>;">
            <h3>Saldo Líquido Real</h3>
            <div class="value" style="color: <?php echo $saldo_real >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                R$ <?php echo number_format($saldo_real, 2, ',', '.'); ?>
            </div>
        </div>
        <div class="summary-box">
            <h3>Saldo Projetado (Final do Mês)</h3>
            <div class="value">R$ <?php echo number_format($saldo_previsto, 2, ',', '.'); ?></div>
        </div>
        <div class="summary-box">
            <h3>Alunos Pagos / Pendentes</h3>
            <div class="value" style="color: var(--primary);">
                <span class="text-success"><?php echo $qtd_alunos['alunos_pagos']; ?></span> / <span class="text-danger"><?php echo $qtd_alunos['alunos_pendentes']; ?></span>
            </div>
        </div>
    </div>

    <!-- Listagem de Entradas -->
    <div class="section-title">Detalhamento de Entradas (Receitas)</div>
    <table>
        <thead>
            <tr>
                <th style="width: 100px;">Data</th>
                <th>Descrição / Aluno</th>
                <th>Categoria</th>
                <th style="text-align: center; width: 100px;">Status</th>
                <th style="text-align: right; width: 120px;">Valor (R$)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $soma_rec = 0;
            foreach ($receitas_detalhes as $r): 
                $soma_rec += $r['valor'];
                $isPago = ($r['status'] == 'pago');
            ?>
            <tr>
                <td><?php echo date('d/m/Y', strtotime($isPago ? $r['data_pagamento'] : $r['data_vencimento'])); ?></td>
                <td style="font-weight: 600;"><?php echo htmlspecialchars($r['descricao']); ?></td>
                <td style="color: var(--text-muted);"><?php echo htmlspecialchars($r['categoria']); ?></td>
                <td style="text-align: center;">
                    <span class="badge-status <?php echo $isPago ? 'status-pago' : 'status-pendente'; ?>">
                        <?php echo $isPago ? 'Recebido' : 'Pendente'; ?>
                    </span>
                </td>
                <td style="text-align: right; font-weight: 700; color: <?php echo $isPago ? 'var(--success)' : 'var(--text-main)'; ?>;">
                    <?php echo number_format($r['valor'], 2, ',', '.'); ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($receitas_detalhes)): ?>
            <tr>
                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Nenhuma receita encontrada para este período.</td>
            </tr>
            <?php else: ?>
            <tr style="background: #f8fafc; ">
                <td colspan="4" style="text-align: right; font-weight: 800;">TOTAL GERAL DE ENTRADAS:</td>
                <td style="text-align: right; font-weight: 800; color: var(--success);">R$ <?php echo number_format($soma_rec, 2, ',', '.'); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Listagem de Saídas -->
    <div class="section-title">Detalhamento de Saídas (Despesas)</div>
    <table>
        <thead>
            <tr>
                <th style="width: 100px;">Data</th>
                <th>Descrição</th>
                <th>Categoria</th>
                <th style="text-align: center; width: 100px;">Status</th>
                <th style="text-align: right; width: 120px;">Valor (R$)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $soma_desp = 0;
            foreach ($despesas_detalhes as $d): 
                $soma_desp += $d['valor'];
                $isPago = ($d['status'] == 'pago');
            ?>
            <tr>
                <td><?php echo date('d/m/Y', strtotime($isPago ? $d['data_pagamento'] : $d['data_vencimento'])); ?></td>
                <td style="font-weight: 600;"><?php echo htmlspecialchars($d['descricao']); ?></td>
                <td style="color: var(--text-muted);"><?php echo htmlspecialchars($d['categoria']); ?></td>
                <td style="text-align: center;">
                    <span class="badge-status <?php echo $isPago ? 'status-pago' : 'status-pendente'; ?>">
                        <?php echo $isPago ? 'Pago' : 'Pendente'; ?>
                    </span>
                </td>
                <td style="text-align: right; font-weight: 700; color: <?php echo $isPago ? 'var(--danger)' : 'var(--text-main)'; ?>;">
                    <?php echo number_format($d['valor'], 2, ',', '.'); ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($despesas_detalhes)): ?>
            <tr>
                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Nenhuma despesa encontrada para este período.</td>
            </tr>
            <?php else: ?>
            <tr style="background: #f8fafc; ">
                <td colspan="4" style="text-align: right; font-weight: 800;">TOTAL GERAL DE SAÍDAS:</td>
                <td style="text-align: right; font-weight: 800; color: var(--danger);">R$ <?php echo number_format($soma_desp, 2, ',', '.'); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="no-print" style="position: fixed; bottom: 2rem; right: 2rem;">
        <button onclick="window.print()"
            style="padding: 1rem 2rem; background: var(--primary); color: #fff; border: none; border-radius: 2rem; font-weight: 800; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
            <i class="fa-solid fa-print" style="margin-right: 8px;"></i> IMPRIMIR RELATÓRIO
        </button>
    </div>

</body>
</html>
