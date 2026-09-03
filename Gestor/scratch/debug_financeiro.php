<?php
require_once 'config.php';

try {
    echo "COLUNAS MENSALIDADES:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM mensalidades");
    print_r($stmt->fetchAll());

    echo "\nCOLUNAS FINANCEIRO_LANCAMENTOS:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM financeiro_lancamentos");
    print_r($stmt->fetchAll());

    echo "\nULTIMAS 5 MENSALIDADES:\n";
    $stmt = $pdo->query("SELECT * FROM mensalidades ORDER BY id DESC LIMIT 5");
    print_r($stmt->fetchAll());

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
}
?>
