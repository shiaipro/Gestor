<?php
require_once '../config.php';
header('Content-Type: text/plain; charset=utf-8');

$nome_busca = $_GET['nome'] ?? 'MATHEUS';

echo "=== ALUNOS (nome LIKE '%$nome_busca%') ===\n";
$stmt = $pdo->prepare("SELECT id, unidade_id, academia_id, nome_completo, status FROM alunos WHERE nome_completo LIKE ? ORDER BY id DESC LIMIT 10");
$stmt->execute(["%$nome_busca%"]);
foreach ($stmt->fetchAll() as $row) {
    print_r($row);
}

echo "\n=== ÚLTIMOS 10 ALUNOS CADASTRADOS (qualquer nome) ===\n";
$stmt2 = $pdo->query("SELECT id, unidade_id, academia_id, nome_completo, status FROM alunos ORDER BY id DESC LIMIT 10");
foreach ($stmt2->fetchAll() as $row) {
    print_r($row);
}

echo "\n=== ESTRUTURA turma_alunos ===\n";
try {
    $desc = $pdo->query("DESCRIBE turma_alunos")->fetchAll();
    foreach ($desc as $col) { echo $col['Field'] . " (" . $col['Type'] . ")\n"; }
} catch (Exception $e) { echo "ERRO: " . $e->getMessage() . "\n"; }

echo "\n=== TURMA (uuid 61ca057c-2a27-4633-a7e3-7074a570b76e) ===\n";
$stmt4 = $pdo->prepare("SELECT id, unidade_id, academia_id, nome, uuid_inscricao, status FROM turmas WHERE uuid_inscricao = ?");
$stmt4->execute(['61ca057c-2a27-4633-a7e3-7074a570b76e']);
$turma_alvo = $stmt4->fetch();
print_r($turma_alvo);

if ($turma_alvo) {
    echo "\n=== VÍNCULOS EM turma_alunos PARA ESSA TURMA (turma_id={$turma_alvo['id']}) ===\n";
    try {
        $stmt5 = $pdo->prepare("SELECT ta.*, a.nome_completo, a.responsavel_telefone, a.responsavel_email FROM turma_alunos ta LEFT JOIN alunos a ON a.id = ta.aluno_id WHERE ta.turma_id = ?");
        $stmt5->execute([$turma_alvo['id']]);
        foreach ($stmt5->fetchAll() as $row) { print_r($row); }
    } catch (Exception $e) { echo "ERRO: " . $e->getMessage() . "\n"; }
}
