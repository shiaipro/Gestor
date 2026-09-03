<?php
require_once 'config.php';

try {
    $columns = [
        "cpf VARCHAR(14)",
        "rg VARCHAR(20)",
        "cep VARCHAR(9)",
        "endereco_numero VARCHAR(20)",
        "endereco_complemento VARCHAR(100)",
        "bairro VARCHAR(100)",
        "cidade VARCHAR(100)",
        "estado CHAR(2)",
        "responsavel_nome VARCHAR(255)",
        "responsavel_cpf VARCHAR(14)",
        "responsavel_parentesco VARCHAR(50)",
        "responsavel_telefone VARCHAR(20)",
        "email_pessoal VARCHAR(255)"
    ];

    foreach ($columns as $column) {
        $colName = explode(' ', $column)[0];
        // Check if column exists
        $check = $pdo->query("SHOW COLUMNS FROM alunos LIKE '$colName'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE alunos ADD COLUMN $column");
            echo "Coluna $colName adicionada.<br>";
        } else {
            echo "Coluna $colName já existe.<br>";
        }
    }
    echo "Migração concluída com sucesso!";
} catch (PDOException $e) {
    echo "Erro na migração: " . $e->getMessage();
}
?>