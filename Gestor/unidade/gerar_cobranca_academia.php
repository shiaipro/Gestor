<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('financeiro_criar') && !temPermissaoModulo('unidades_editar')) {
    header('Location: academias.php?erro=sem_permissao');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: academias.php');
    exit;
}

// Buscar academia
$stmt = $pdo->prepare("SELECT * FROM academias WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$academia = $stmt->fetch();

if (!$academia) {
    header('Location: academias.php?erro=nao_encontrado');
    exit;
}

if (empty($academia['valor']) || $academia['valor'] <= 0) {
    header('Location: academias.php?erro=sem_valor_configurado');
    exit;
}

// Calcular Data de Vencimento
$dia = !empty($academia['dia_vencimento']) ? $academia['dia_vencimento'] : date('d');
$mes_atual = date('m');
$ano_atual = date('Y');

// Validar se o dia existe no mês atual (ex: fev 30)
$max_dias = date('t', strtotime("$ano_atual-$mes_atual-01"));
$dia_final = min($dia, $max_dias);
$data_vencimento = date('Y-m-d', strtotime("$ano_atual-$mes_atual-$dia_final"));

// Se o vencimento desse mês já passou, joga pro mês que vem
if (strtotime($data_vencimento) < strtotime(date('Y-m-d'))) {
    $data_obj = new DateTime("$ano_atual-$mes_atual-01");
    $data_obj->modify('+1 month');
    $mes_novo = $data_obj->format('m');
    $ano_novo = $data_obj->format('Y');
    
    $max_dias_novo = date('t', strtotime("$ano_novo-$mes_novo-01"));
    $dia_final = min($dia, $max_dias_novo);
    $data_vencimento = date('Y-m-d', strtotime("$ano_novo-$mes_novo-$dia_final"));
}

$meses = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];
$recorrencia = $academia['recorrencia'] ? strtolower($academia['recorrencia']) : 'nenhuma';

// Definir repetições baseadas em 1 ano de contrato
$num_repeticoes = 1;
if ($recorrencia == 'mensal') $num_repeticoes = 12;
elseif ($recorrencia == 'trimestral') $num_repeticoes = 4;
elseif ($recorrencia == 'semestral') $num_repeticoes = 2;
elseif ($recorrencia == 'anual') $num_repeticoes = 1;

try {
    $parent_id = null;
    for ($i = 0; $i < $num_repeticoes; $i++) {
        // Calcular data para a parcela atual
        $data_obj = new DateTime($data_vencimento); // Data inicial calculada
        
        if ($i > 0) {
            if ($recorrencia == 'mensal') $data_obj->modify("+$i month");
            elseif ($recorrencia == 'trimestral') { $m = $i * 3; $data_obj->modify("+$m month"); }
            elseif ($recorrencia == 'semestral') { $m = $i * 6; $data_obj->modify("+$m month"); }
            elseif ($recorrencia == 'anual') $data_obj->modify("+$i year");
        }
        
        $venc_atual = $data_obj->format('Y-m-d');
        
        $mes_venc = date('m', strtotime($venc_atual));
        $ano_venc = date('Y', strtotime($venc_atual));
        $mes_nome = $meses[$mes_venc];
        
        $descricao = "Faturamento Unidade: {$academia['nome']} - {$mes_nome}/{$ano_venc}";
        if ($num_repeticoes > 1) {
            $descricao .= " (" . ($i+1) . "/$num_repeticoes)";
        }

        $stmt_insert = $pdo->prepare("INSERT INTO financeiro_lancamentos 
            (unidade_id, academia_id, tipo, descricao, valor, data_vencimento, status, recorrencia, total_parcelas, parcela_atual, parent_id) 
            VALUES (?, ?, 'receita', ?, ?, ?, 'pendente', ?, ?, ?, ?)");
            
        $stmt_insert->execute([
            $unidade_id, 
            $id, 
            $descricao, 
            $academia['valor'], 
            $venc_atual, 
            $recorrencia, 
            $num_repeticoes, 
            ($i + 1),
            $parent_id
        ]);
        
        // Salvar ID do pai para agrupar
        if ($i == 0) $parent_id = $pdo->lastInsertId();
    }
    
    header("Location: academias.php?sucesso=cobranca_gerada");
    exit;
} catch (PDOException $e) {
    header("Location: academias.php?erro=falha_banco");
    exit;
}
?>
