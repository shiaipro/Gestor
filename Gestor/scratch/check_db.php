<?php
require_once 'config.php';
$stmt = $pdo->query("DESCRIBE unidades");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($columns, JSON_PRETTY_PRINT);
?>
