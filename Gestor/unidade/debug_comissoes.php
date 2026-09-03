<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    echo "<h1>Por favor, faça login no sistema primeiro.</h1>";
    exit;
}

echo "<h1>Painel de Depuração de Comissões (Unidade ID: $unidade_id)</h1>";

// AÇÃO: Forçar recálculo de comissão para uma mensalidade específica
if (isset($_GET['recalcular']) && is_numeric($_GET['recalcular'])) {
    $mid = (int)$_GET['recalcular'];
    echo "<div style='background:#fffde7; border:2px solid #f59e0b; padding:15px; margin-bottom:20px;'>";
    echo "<h3>🔄 Forçando recálculo para mensalidade ID: $mid</h3>";
    
    // Buscar mensalidade
    $st = $pdo->prepare("SELECT m.*, a.nome_completo FROM mensalidades m JOIN alunos a ON m.aluno_id = a.id WHERE m.id = ? AND m.unidade_id = ?");
    $st->execute([$mid, $unidade_id]);
    $ms = $st->fetch();
    
    if (!$ms) {
        echo "<p style='color:red'>❌ Mensalidade $mid não encontrada ou não pertence a esta unidade.</p>";
    } elseif ($ms['status'] !== 'pago') {
        echo "<p style='color:red'>❌ Mensalidade $mid com status '{$ms['status']}' — só processa se estiver 'pago'.</p>";
    } else {
        echo "<p>Aluno: <b>{$ms['nome_completo']}</b> | Valor: R$ " . number_format($ms['valor'],2,',','.') . " | Status: <b style='color:green'>{$ms['status']}</b></p>";
        
        // Buscar turmas
        $st2 = $pdo->prepare("SELECT t.* FROM turma_alunos ta JOIN turmas t ON ta.turma_id = t.id WHERE ta.aluno_id = ? AND t.unidade_id = ?");
        $st2->execute([$ms['aluno_id'], $unidade_id]);
        $turmas_rc = $st2->fetchAll();
        echo "<p>Turmas do aluno: " . count($turmas_rc) . "</p>";
        
        foreach ($turmas_rc as $tr) {
            echo "<p>→ Turma: <b>{$tr['nome']}</b> | Instrutor: <b>" . ($tr['instrutor'] ?: 'NENHUM') . "</b> | Comissão: <b>{$tr['comissao_prof_tipo']} = {$tr['comissao_prof_valor']}</b></p>";
            
            if ($tr['comissao_prof_valor'] > 0 && !empty($tr['instrutor'])) {
                $vc = ($tr['comissao_prof_tipo'] === 'percentual')
                    ? round($ms['valor'] * ($tr['comissao_prof_valor'] / 100), 2)
                    : (float)$tr['comissao_prof_valor'];
                    
                echo "<p>💰 Comissão calculada: <b style='color:blue'>R$ " . number_format($vc,2,',','.') . "</b></p>";
                registrarComissoesMensalidade($mid, $unidade_id);
                echo "<p style='color:green; font-weight:bold;'>✅ Função registrarComissoesMensalidade() executada! Verifique a Seção 5 abaixo.</p>";
            } else {
                echo "<p style='color:red'>❌ Comissão = 0 ou instrutor não definido. NADA foi gerado.</p>";
            }
        }
    }
    echo "</div>";
}

// Botão de ação rápida
echo "<div style='background:#e0f2fe; border:1px solid #0ea5e9; padding:15px; margin-bottom:20px;'>
<b>🔧 Ferramenta de Forçar Recálculo</b><br>
Para forçar o recálculo de comissão de uma mensalidade paga específica, acesse:<br>
<code>" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?recalcular=ID_DA_MENSALIDADE</code><br><br>
<b>Exemplo rápido — Recalcular mensalidade 140:</b>
<a href='?recalcular=140' style='display:inline-block; margin-top:8px; padding:8px 16px; background:#0ea5e9; color:#fff; text-decoration:none; font-weight:bold;'>▶ Recalcular Mensalidade 140</a>
</div>";

// 1. Mostrar as últimas 5 mensalidades da unidade (geral)
echo "<h2>1a. Últimas 5 Mensalidades Cadastradas (Geral)</h2>";
$stmt = $pdo->prepare("SELECT m.*, a.nome_completo FROM mensalidades m JOIN alunos a ON m.aluno_id = a.id WHERE m.unidade_id = ? ORDER BY m.id DESC LIMIT 5");
$stmt->execute([$unidade_id]);
$mensalidades = $stmt->fetchAll();

echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>
<tr>
    <th>ID Mensalidade</th>
    <th>Aluno (ID)</th>
    <th>Valor</th>
    <th>Status</th>
    <th>Vencimento</th>
    <th>Pagamento</th>
</tr>";
foreach ($mensalidades as $m) {
    echo "<tr>
        <td>{$m['id']}</td>
        <td>{$m['nome_completo']} ({$m['aluno_id']})</td>
        <td>R$ " . number_format($m['valor'], 2, ',', '.') . "</td>
        <td style='font-weight:bold; color:" . ($m['status'] == 'pago' ? 'green' : 'red') . "'>{$m['status']}</td>
        <td>{$m['data_vencimento']}</td>
        <td>{$m['data_pagamento']}</td>
    </tr>";
}
echo "</table>";

// 1b. Mostrar as últimas 5 mensalidades PAGAS
echo "<h2>1b. Últimas 5 Mensalidades PAGAS</h2>";
$stmt_paid = $pdo->prepare("SELECT m.*, a.nome_completo FROM mensalidades m JOIN alunos a ON m.aluno_id = a.id WHERE m.unidade_id = ? AND m.status = 'pago' ORDER BY m.data_pagamento DESC, m.id DESC LIMIT 5");
$stmt_paid->execute([$unidade_id]);
$mensalidades_pagas = $stmt_paid->fetchAll();

echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>
<tr>
    <th>ID Mensalidade</th>
    <th>Aluno (ID)</th>
    <th>Valor</th>
    <th>Status</th>
    <th>Vencimento</th>
    <th>Pagamento</th>
</tr>";
if (empty($mensalidades_pagas)) {
    echo "<tr><td colspan='6'>Nenhuma mensalidade paga encontrada.</td></tr>";
} else {
    foreach ($mensalidades_pagas as $m) {
        echo "<tr>
            <td>{$m['id']}</td>
            <td>{$m['nome_completo']} ({$m['aluno_id']})</td>
            <td>R$ " . number_format($m['valor'], 2, ',', '.') . "</td>
            <td style='font-weight:bold; color:green'>{$m['status']}</td>
            <td>{$m['data_vencimento']}</td>
            <td>{$m['data_pagamento']}</td>
        </tr>";
    }
}
echo "</table>";

// 2. Para a última mensalidade paga, simular o processamento
$all_mensalidades = array_merge($mensalidades, $mensalidades_pagas);
if (!empty($all_mensalidades)) {
    $last_m = null;
    foreach ($all_mensalidades as $m) {
        if ($m['status'] == 'pago') {
            $last_m = $m;
            break;
        }
    }

    if ($last_m) {
        echo "<h2>2. Simulação de Cálculo de Comissão para a Mensalidade ID: {$last_m['id']} (Aluno: {$last_m['nome_completo']})</h2>";
        
        // Buscar turmas
        $stmt_turmas = $pdo->prepare("
            SELECT t.* 
            FROM turma_alunos ta 
            JOIN turmas t ON ta.turma_id = t.id 
            WHERE ta.aluno_id = ? AND t.unidade_id = ?
        ");
        $stmt_turmas->execute([$last_m['aluno_id'], $unidade_id]);
        $turmas = $stmt_turmas->fetchAll();

        echo "<p>Turmas encontradas para o aluno: " . count($turmas) . "</p>";
        if (!empty($turmas)) {
            echo "<ul>";
            foreach ($turmas as $t) {
                echo "<li>
                    <b>Turma:</b> {$t['nome']}<br>
                    <b>Professor/Instrutor:</b> " . ($t['instrutor'] ?: '<i>Nenhum</i>') . "<br>
                    <b>Comissão Professor:</b> {$t['comissao_prof_tipo']} - " . number_format($t['comissao_prof_valor'], 2, ',', '.') . "<br>";
                
                // Calcular comissão
                $valor_comissao = 0.0;
                if ($t['comissao_prof_valor'] > 0 && !empty($t['instrutor'])) {
                    if ($t['comissao_prof_tipo'] === 'percentual') {
                        $valor_comissao = round($last_m['valor'] * ($t['comissao_prof_valor'] / 100), 2);
                    } else {
                        $valor_comissao = (float)$t['comissao_prof_valor'];
                    }
                }
                echo "<b>Comissão Calculada:</b> <span style='color:blue; font-weight:bold;'>R$ " . number_format($valor_comissao, 2, ',', '.') . "</span><br>";
                
                // Verificar se a categoria Comissionamento existe
                $stmt_cat = $pdo->prepare("SELECT id FROM financeiro_categorias WHERE nome = 'Comissionamento' AND tipo = 'despesa' AND unidade_id = ?");
                $stmt_cat->execute([$unidade_id]);
                $cat = $stmt_cat->fetch();
                echo "<b>Categoria 'Comissionamento' existe?</b> " . ($cat ? "Sim (ID: {$cat['id']})" : "Não (Será criada no próximo pagamento)") . "<br>";
                
                // Verificar se já existe despesa
                $descr_busca = "Comissão Prof. " . $t['instrutor'] . " - Ref. Mensalidade ID " . $last_m['id'] . " - Turma: " . $t['nome'];
                $stmt_check = $pdo->prepare("SELECT id, status, valor, data_vencimento FROM financeiro_lancamentos WHERE unidade_id = ? AND tipo = 'despesa' AND descricao = ?");
                $stmt_check->execute([$unidade_id, $descr_busca]);
                $lanc = $stmt_check->fetch();
                if ($lanc) {
                    echo "<b>Lançamento em Contas a Pagar:</b> <span style='color:green; font-weight:bold;'>Já existe!</span> (ID: {$lanc['id']}, Valor: R$ {$lanc['valor']}, Status: {$lanc['status']})<br>";
                } else {
                    echo "<b>Lançamento em Contas a Pagar:</b> <span style='color:red;'>Ainda não existe no banco de dados.</span><br>";
                }
                echo "</li><br>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color:red; font-weight:bold;'>O aluno {$last_m['nome_completo']} não está vinculado a nenhuma turma em turma_alunos!</p>";
        }
    } else {
        echo "<h2>2. Diagnóstico: Pagamentos Recentes</h2>";
        echo "<p style='color:red; font-weight:bold;'>⚠️ Nenhuma mensalidade com status 'pago' foi encontrada na tabela <code>mensalidades</code>.<br>Isso pode significar que a baixa foi feita via lançamento avulso (financeiro_lancamentos), não por mensalidade de aluno.</p>";

        // Mostrar os últimos lançamentos pagos no financeiro geral
        echo "<h3>Últimos 5 Lançamentos PAGOS em financeiro_lancamentos:</h3>";
        $stmt_lp = $pdo->prepare("SELECT l.*, c.nome as cat FROM financeiro_lancamentos l LEFT JOIN financeiro_categorias c ON l.categoria_id = c.id WHERE l.unidade_id = ? AND l.status = 'pago' ORDER BY l.data_pagamento DESC, l.id DESC LIMIT 5");
        $stmt_lp->execute([$unidade_id]);
        $lps = $stmt_lp->fetchAll();
        if (empty($lps)) {
            echo "<p>Nenhum lançamento pago encontrado.</p>";
        } else {
            echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'><tr><th>ID</th><th>Tipo</th><th>Descrição</th><th>Categoria</th><th>Valor</th><th>Data Pgto</th></tr>";
            foreach ($lps as $l) {
                echo "<tr><td>{$l['id']}</td><td>{$l['tipo']}</td><td>{$l['descricao']}</td><td>" . htmlspecialchars($l['cat'] ?? '-') . "</td><td>R$ " . number_format($l['valor'], 2, ',', '.') . "</td><td>{$l['data_pagamento']}</td></tr>";
            }
            echo "</table>";
        }
    }
}

// 3. Turmas da Unidade e configuração de comissão
echo "<h2>3. Turmas com Comissionamento Configurado</h2>";
$stmt_turmas_all = $pdo->prepare("SELECT id, nome, instrutor, comissao_prof_tipo, comissao_prof_valor, status FROM turmas WHERE unidade_id = ? ORDER BY id DESC LIMIT 10");
$stmt_turmas_all->execute([$unidade_id]);
$turmas_all = $stmt_turmas_all->fetchAll();

echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>
<tr><th>ID Turma</th><th>Nome</th><th>Instrutor</th><th>Tipo Comissão</th><th>Valor Comissão</th><th>Status Turma</th><th>Tem Comissão?</th></tr>";
foreach ($turmas_all as $t) {
    $tem_comissao = ($t['comissao_prof_valor'] > 0 && !empty($t['instrutor']));
    echo "<tr>
        <td>{$t['id']}</td>
        <td>{$t['nome']}</td>
        <td>" . ($t['instrutor'] ?: '<i style=\"color:red\">NÃO DEFINIDO</i>') . "</td>
        <td>{$t['comissao_prof_tipo']}</td>
        <td>" . number_format($t['comissao_prof_valor'], 2, ',', '.') . "</td>
        <td>{$t['status']}</td>
        <td style='font-weight:bold; color:" . ($tem_comissao ? 'green' : 'red') . "'>" . ($tem_comissao ? '✅ SIM' : '❌ NÃO') . "</td>
    </tr>";
}
echo "</table>";

// 4. Alunos vinculados a turmas com comissão
echo "<h2>4. Vínculos Aluno × Turma (turma_alunos)</h2>";
$stmt_links = $pdo->prepare("
    SELECT a.id as aluno_id, a.nome_completo, t.id as turma_id, t.nome as turma_nome, t.instrutor, t.comissao_prof_valor
    FROM turma_alunos ta
    JOIN alunos a ON ta.aluno_id = a.id
    JOIN turmas t ON ta.turma_id = t.id
    WHERE a.unidade_id = ?
    ORDER BY a.nome_completo ASC
    LIMIT 20
");
$stmt_links->execute([$unidade_id]);
$links = $stmt_links->fetchAll();

if (empty($links)) {
    echo "<p style='color:red; font-weight:bold;'>⚠️ Nenhum aluno está vinculado a turmas em turma_alunos! A comissão não será gerada sem este vínculo.</p>";
} else {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>
    <tr><th>Aluno (ID)</th><th>Turma</th><th>Instrutor</th><th>Comissão</th></tr>";
    foreach ($links as $l) {
        echo "<tr>
            <td>{$l['nome_completo']} ({$l['aluno_id']})</td>
            <td>{$l['turma_nome']} (ID:{$l['turma_id']})</td>
            <td>" . ($l['instrutor'] ?: '<i style=\"color:red\">NÃO DEFINIDO</i>') . "</td>
            <td>" . ($l['comissao_prof_valor'] > 0 ? 'R$ ' . number_format($l['comissao_prof_valor'], 2, ',', '.') : '<i style=\"color:red\">Sem comissão</i>') . "</td>
        </tr>";
    }
    echo "</table>";
}

// 5. Últimos lançamentos de Comissão gerados
echo "<h2>5. Últimos Lançamentos de Comissão em Contas a Pagar</h2>";
$stmt_l = $pdo->prepare("
    SELECT l.*, c.nome as categoria_nome 
    FROM financeiro_lancamentos l 
    LEFT JOIN financeiro_categorias c ON l.categoria_id = c.id 
    WHERE l.unidade_id = ? AND (c.nome = 'Comissionamento' OR l.descricao LIKE 'Comissão Prof.%')
    ORDER BY l.id DESC LIMIT 10
");
$stmt_l->execute([$unidade_id]);
$lancamentos = $stmt_l->fetchAll();

if (!empty($lancamentos)) {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>
    <tr>
        <th>ID</th>
        <th>Descrição</th>
        <th>Categoria</th>
        <th>Valor</th>
        <th>Status</th>
        <th>Vencimento</th>
    </tr>";
    foreach ($lancamentos as $l) {
        echo "<tr>
            <td>{$l['id']}</td>
            <td>{$l['descricao']}</td>
            <td>{$l['categoria_nome']}</td>
            <td>R$ " . number_format($l['valor'], 2, ',', '.') . "</td>
            <td style='font-weight:bold; color:" . ($l['status'] == 'pago' ? 'green' : 'orange') . "'>{$l['status']}</td>
            <td>{$l['data_vencimento']}</td>
        </tr>";
    }
    echo "</table>";
} else {
    echo "<p>Nenhum lançamento de comissão encontrado ainda.</p>";
}
?>
