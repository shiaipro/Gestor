<?php
require_once '../config.php';
header('Content-Type: application/json');

$unidade_id = getUnidadeId();
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

try {
    $stmt = $pdo->prepare("SELECT id, titulo as title, data_inicio as start, data_fim as end, cor as backgroundColor, status, descricao FROM cms_agenda WHERE unidade_id = ? AND data_inicio >= ? AND data_fim <= ?");
    $stmt->execute([$unidade_id, $start, $end]);
    $eventos = $stmt->fetchAll();

    echo json_encode($eventos);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>