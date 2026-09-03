<?php
require_once 'config.php';
$stmt = $pdo->prepare("SELECT id, nome FROM turmas WHERE unidade_id = 14");
$stmt->execute();
echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
?>
