<?php
require_once '../config.php';
header('Content-Type: application/json');

$unidade_id = getUnidadeId();

if (!temPermissaoModulo('chamadas_criar') && !temPermissaoModulo('chamadas_editar')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para registrar chamada']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$turma_id = $data['turma_id'] ?? null;
$aluno_id = $data['aluno_id'] ?? null;
$data_aula = $data['data_aula'] ?? null;
$status = $data['status'] ?? 'presente';

if (!$turma_id || !$aluno_id || !$data_aula) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

try {
    // Auto-migration: Garantir coluna atualizado_em
    try {
        $pdo->query("SELECT atualizado_em FROM presencas LIMIT 1");
    } catch (Exception $e) {
        $pdo->query("ALTER TABLE presencas ADD COLUMN atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    // Verificar se o aluno pertence à unidade (segurança)
    $stmt_check = $pdo->prepare("SELECT id FROM alunos WHERE id = ? AND unidade_id = ?");
    $stmt_check->execute([$aluno_id, $unidade_id]);
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Aluno não autorizado']);
        exit;
    }

    // Usar INSERT ... ON DUPLICATE KEY UPDATE para registrar a presença
    $sql = "INSERT INTO presencas (unidade_id, turma_id, aluno_id, data_aula, status) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE status = VALUES(status), atualizado_em = CURRENT_TIMESTAMP";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$unidade_id, $turma_id, $aluno_id, $data_aula, $status]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
