<?php
require_once '../Gestor/config.php';

$configs = [
    'exame'   => ['tabela' => 'eventos_graduacao', 'nome_col' => 'titulo', 'label' => 'Exame de Faixa'],
    'torneio' => ['tabela' => 'competicoes', 'nome_col' => 'nome', 'label' => 'Torneio'],
    'oficial' => ['tabela' => 'competicoes_oficiais', 'nome_col' => 'nome', 'label' => 'Competição Oficial'],
];

$tipo = $_POST['tipo'] ?? '';
$id   = (int) ($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id || !isset($configs[$tipo])) {
    header('Location: index.php');
    exit;
}

$cfg = $configs[$tipo];

$nome     = trim(filter_input(INPUT_POST, 'nome', FILTER_DEFAULT) ?? '');
$email    = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$telefone = trim(filter_input(INPUT_POST, 'telefone', FILTER_DEFAULT) ?? '');

$redirect = "evento.php?tipo=" . urlencode($tipo) . "&id=" . $id;

if (!$nome || !$email || !$telefone) {
    header('Location: ' . $redirect . '&erro=1');
    exit;
}

// Busca o evento de novo no banco para confirmar que existe e pegar a unidade dona dele
$sql_evt = $tipo === 'exame'
    ? "SELECT id, unidade_id, {$cfg['nome_col']} AS nome, todas_turmas, taxa, taxa_lote2, lote2_data_inicio FROM {$cfg['tabela']} WHERE id = ?"
    : "SELECT id, unidade_id, {$cfg['nome_col']} AS nome FROM {$cfg['tabela']} WHERE id = ?";
$stmt_evt = $pdo->prepare($sql_evt);
$stmt_evt->execute([$id]);
$evento = $stmt_evt->fetch();

if (!$evento) {
    header('Location: index.php');
    exit;
}

// Para exames com regras por turma, a turma escolhida é obrigatória e o valor é
// sempre recalculado aqui no servidor (nunca confiar no preço vindo do formulário).
$turma_nome = null;
$valor_calculado = null;
$hoje = date('Y-m-d');

if ($tipo === 'exame') {
    if (empty($evento['todas_turmas'])) {
        $turma_id = (int) ($_POST['turma_id'] ?? 0);
        if (!$turma_id) {
            header('Location: ' . $redirect . '&erro=1');
            exit;
        }

        $stmt_turma = $pdo->prepare(
            "SELECT t.nome, egd.valor_lote1, egd.valor_lote1_data_limite, egd.valor_lote2
             FROM eventos_graduacao_turmas egt
             JOIN turmas t ON t.id = egt.turma_id
             LEFT JOIN eventos_graduacao_datas_turma egd ON egd.evento_id = egt.evento_id AND egd.turma_id = egt.turma_id
             WHERE egt.evento_id = ? AND egt.turma_id = ?"
        );
        $stmt_turma->execute([$evento['id'], $turma_id]);
        $turma_row = $stmt_turma->fetch();

        if (!$turma_row) {
            header('Location: ' . $redirect . '&erro=1');
            exit;
        }

        $turma_nome = $turma_row['nome'];
        $lote1_expirado = !empty($turma_row['valor_lote1_data_limite']) && $hoje > $turma_row['valor_lote1_data_limite'];
        if ($lote1_expirado && $turma_row['valor_lote2'] !== null) {
            $valor_calculado = (float) $turma_row['valor_lote2'];
        } elseif ($turma_row['valor_lote1'] !== null) {
            $valor_calculado = (float) $turma_row['valor_lote1'];
        } else {
            $valor_calculado = 0.00;
        }
    } else {
        $lote2_vigente = !empty($evento['lote2_data_inicio']) && $hoje >= $evento['lote2_data_inicio'];
        $valor_calculado = ($lote2_vigente && $evento['taxa_lote2'] !== null && $evento['taxa_lote2'] !== '')
            ? (float) $evento['taxa_lote2']
            : (float) $evento['taxa'];
    }
}

try {
    $notas = "Inscrição via site - Evento: " . $evento['nome'] . " (" . $cfg['label'] . ", ID " . $evento['id'] . ")";
    if ($turma_nome !== null) {
        $notas .= " | Turma: " . $turma_nome;
    }
    if ($valor_calculado !== null) {
        $notas .= " | Valor: R$ " . number_format($valor_calculado, 2, ',', '.');
    }

    $stmt = $pdo->prepare("INSERT INTO comercial_leads (unidade_id, nome, telefone, email, origem, status, notas) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $evento['unidade_id'],
        $nome,
        $telefone,
        $email,
        'Evento - ' . $cfg['label'],
        'novo',
        $notas,
    ]);
} catch (PDOException $e) {
    header('Location: ' . $redirect . '&erro=1');
    exit;
}

header('Location: ' . $redirect . '&ok=1');
exit;
