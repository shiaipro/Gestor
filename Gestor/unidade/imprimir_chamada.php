<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    die("Acesso negado.");
}

if (!moduloAtivo('turmas')) {
    die("Módulo não ativo.");
}

if (!temPermissaoModulo('chamadas_visualizar')) {
    die("Acesso negado.");
}

$mes_atual = isset($_GET['mes']) ? str_pad($_GET['mes'], 2, '0', STR_PAD_LEFT) : date('m');
$ano_atual = isset($_GET['ano']) ? $_GET['ano'] : date('Y');
$turma_id_filtro = $_GET['turma_id'] ?? null;

// Instrutor só pode imprimir a chamada das suas próprias turmas
$is_instrutor = restringirVisaoAsProprisTurmas();
if ($is_instrutor) {
    if (!$turma_id_filtro) {
        die("Acesso negado.");
    }
    $stmt_check = $pdo->prepare("
        SELECT 1 FROM turmas t
        INNER JOIN unidade_equipe ue ON ue.id = t.instrutor_equipe_id
        WHERE t.id = ? AND t.unidade_id = ? AND ue.usuario_id = ?
    ");
    $stmt_check->execute([$turma_id_filtro, $unidade_id, $_SESSION['usuario_id']]);
    if (!$stmt_check->fetchColumn()) {
        die("Acesso negado.");
    }
}

$meses_nomes = ['01' => 'Janeiro','02' => 'Fevereiro','03' => 'Março','04' => 'Abril','05' => 'Maio','06' => 'Junho','07' => 'Julho','08' => 'Agosto','09' => 'Setembro','10' => 'Outubro','11' => 'Novembro','12' => 'Dezembro'];

$nome_turma_filtro = "Todas as Turmas";
if ($turma_id_filtro) {
    $stmt_t = $pdo->prepare("SELECT nome FROM turmas WHERE id = ? AND unidade_id = ?");
    $stmt_t->execute([$turma_id_filtro, $unidade_id]);
    $nome_turma_filtro = $stmt_t->fetchColumn() ?: "Turma não encontrada";
}

try {
    // 1. Estatísticas Gerais
    $where_turma = $turma_id_filtro ? " AND p.turma_id = :turma_id " : "";
    $sql_stats = "
        SELECT 
            COUNT(DISTINCT p.data_aula) as total_aulas,
            COUNT(CASE WHEN p.status = 'presente' THEN 1 END) as total_presencas,
            COUNT(CASE WHEN p.status = 'falta' THEN 1 END) as total_faltas,
            COUNT(CASE WHEN p.status = 'justificado' THEN 1 END) as total_justificados
        FROM presencas p
        WHERE p.unidade_id = :unidade_id 
        AND MONTH(p.data_aula) = :mes 
        AND YEAR(p.data_aula) = :ano
        $where_turma
    ";
    $stmt_stats = $pdo->prepare($sql_stats);
    $params_stats = ['unidade_id' => $unidade_id, 'mes' => $mes_atual, 'ano' => $ano_atual];
    if ($turma_id_filtro) $params_stats['turma_id'] = $turma_id_filtro;
    $stmt_stats->execute($params_stats);
    $stats = $stmt_stats->fetch();

    // 2. Lista de Alunos e Desempenho
    if ($turma_id_filtro) {
        $sql_alunos = "
            SELECT 
                a.id, a.nome_completo, a.faixa, a.status,
                COUNT(CASE WHEN p.status = 'presente' THEN 1 END) as presencas,
                COUNT(CASE WHEN p.status = 'falta' THEN 1 END) as faltas,
                COUNT(CASE WHEN p.status = 'justificado' THEN 1 END) as justificados,
                (SELECT COUNT(DISTINCT data_aula) FROM presencas WHERE turma_id = :turma_id AND MONTH(data_aula) = :mes AND YEAR(data_aula) = :ano) as total_aulas_turma
            FROM turma_alunos ta
            JOIN alunos a ON ta.aluno_id = a.id
            LEFT JOIN presencas p ON a.id = p.aluno_id 
                AND p.turma_id = :turma_id 
                AND MONTH(p.data_aula) = :mes 
                AND YEAR(p.data_aula) = :ano
            WHERE ta.turma_id = :turma_id AND a.unidade_id = :unidade_id
            GROUP BY a.id, a.status
            ORDER BY a.nome_completo ASC
        ";
        $params_alunos = ['turma_id' => $turma_id_filtro, 'unidade_id' => $unidade_id, 'mes' => $mes_atual, 'ano' => $ano_atual];
    } else {
        $sql_alunos = "
            SELECT 
                a.id, a.nome_completo, a.faixa, a.status,
                COUNT(CASE WHEN p.status = 'presente' THEN 1 END) as presencas,
                COUNT(CASE WHEN p.status = 'falta' THEN 1 END) as faltas,
                COUNT(CASE WHEN p.status = 'justificado' THEN 1 END) as justificados,
                0 as total_aulas_turma
            FROM alunos a
            LEFT JOIN presencas p ON a.id = p.aluno_id 
                AND p.unidade_id = :unidade_id 
                AND MONTH(p.data_aula) = :mes 
                AND YEAR(p.data_aula) = :ano
            WHERE a.unidade_id = :unidade_id AND (a.status = 'ativo' OR p.aluno_id IS NOT NULL)
            GROUP BY a.id, a.status
            HAVING (presencas + faltas + justificados) > 0 OR a.status = 'ativo'
            ORDER BY a.nome_completo ASC
        ";
        $params_alunos = ['unidade_id' => $unidade_id, 'mes' => $mes_atual, 'ano' => $ano_atual];
    }

    $stmt_alunos = $pdo->prepare($sql_alunos);
    $stmt_alunos->execute($params_alunos);
    $alunos_rel = $stmt_alunos->fetchAll();
} catch (PDOException $e) {
    die("Erro no banco de dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Chamadas - <?php echo $meses_nomes[$mes_atual]; ?> / <?php echo $ano_atual; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; color: #333; line-height: 1.6; }
        .header { border-bottom: 3px solid #08153a; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; font-weight: 900; }
        .header-info { text-align: right; font-size: 14px; }
        
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .summary-box { border: 1px solid #ddd; padding: 15px; text-align: center; background: #f9f9f9; }
        .summary-box h3 { margin: 0 0 5px 0; font-size: 10px; text-transform: uppercase; color: #666; }
        .summary-box .value { font-size: 20px; font-weight: 900; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #eee; text-align: left; padding: 12px 10px; font-size: 11px; text-transform: uppercase; border: 1px solid #ddd; }
        td { padding: 10px; border: 1px solid #ddd; font-size: 13px; }
        
        .status-badge { font-size: 10px; font-weight: 900; text-transform: uppercase; padding: 3px 6px; border-radius: 3px; }
        .excelente { background: #dcfce7; color: #166534; }
        .regular { background: #fef3c7; color: #92400e; }
        .alerta { background: #fee2e2; color: #991b1b; }
        
        .footer { margin-top: 50px;  padding-top: 10px; font-size: 11px; color: #888; text-align: center; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <div>
            <h1>Relatório de Chamadas</h1>
            <div style="font-weight: 700; color: #666;"><?php echo htmlspecialchars($nome_turma_filtro); ?></div>
        </div>
        <div class="header-info">
            <div style="font-size: 18px; font-weight: 900;"><?php echo strtoupper($meses_nomes[$mes_atual]); ?> <?php echo $ano_atual; ?></div>
            <div>Gerado em: <?php echo date('d/m/Y H:i'); ?></div>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-box">
            <h3>Aulas</h3>
            <div class="value"><?php echo $stats['total_aulas']; ?></div>
        </div>
        <div class="summary-box">
            <h3>Presenças</h3>
            <div class="value" style="color: #166534;"><?php echo $stats['total_presencas']; ?></div>
        </div>
        <div class="summary-box">
            <h3>Faltas</h3>
            <div class="value" style="color: #991b1b;"><?php echo $stats['total_faltas']; ?></div>
        </div>
        <div class="summary-box">
            <h3>Justificadas</h3>
            <div class="value" style="color: #1e40af;"><?php echo $stats['total_justificados']; ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Aluno</th>
                <th>Faixa</th>
                <th style="text-align: center;">Pres.</th>
                <th style="text-align: center;">Faltas</th>
                <th style="text-align: center;">Justif.</th>
                <th style="text-align: center;">Freq %</th>
                <th style="text-align: right;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($alunos_rel as $aluno): 
                $total_aluno = $aluno['presencas'] + $aluno['faltas'] + $aluno['justificados'];
                $base_aulas = $turma_id_filtro ? $aluno['total_aulas_turma'] : $total_aluno;
                $porcentagem = $base_aulas > 0 ? ($aluno['presencas'] / $base_aulas) * 100 : 0;
                
                $status_txt = "Alerta";
                $status_class = "alerta";
                if ($porcentagem >= 75) { $status_txt = "Excelente"; $status_class = "excelente"; }
                elseif ($porcentagem >= 50) { $status_txt = "Regular"; $status_class = "regular"; }
            ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($aluno['nome_completo']); ?></strong></td>
                    <td><?php echo htmlspecialchars($aluno['faixa']); ?></td>
                    <td style="text-align: center;"><?php echo $aluno['presencas']; ?></td>
                    <td style="text-align: center; color: #991b1b;"><?php echo $aluno['faltas']; ?></td>
                    <td style="text-align: center; color: #1e40af;"><?php echo $aluno['justificados']; ?></td>
                    <td style="text-align: center;"><strong><?php echo round($porcentagem); ?>%</strong></td>
                    <td style="text-align: right;">
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_txt; ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        Gerado pelo sistema SHIAI PRO - Todos os direitos reservados.
    </div>

    <div class="no-print" style="position: fixed; bottom: 30px; right: 30px;">
        <button onclick="window.print()" style="padding: 15px 30px; background: #08153a; color: #fff; border: none; font-weight: 900; cursor: pointer; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
            IMPRIMIR AGORA
        </button>
    </div>

</body>
</html>
