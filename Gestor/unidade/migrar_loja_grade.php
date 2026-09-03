<?php
require_once '../config.php';

try {
    // 1. Criar tabela de subcategorias
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_produtos_subcategorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        categoria_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        descricao TEXT NULL,
        FOREIGN KEY (categoria_id) REFERENCES cms_produtos_categorias(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Tabela cms_produtos_subcategorias criada ou já existente.<br>";

    // 2. Adicionar subcategoria_id em cms_produtos se não existir
    try {
        $pdo->query("SELECT subcategoria_id FROM cms_produtos LIMIT 1");
        echo "Coluna subcategoria_id já existe em cms_produtos.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE cms_produtos ADD COLUMN subcategoria_id INT NULL AFTER categoria_id;");
        echo "Coluna subcategoria_id adicionada a cms_produtos.<br>";
    }

    // 3. Criar tabela da grade de variações
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_produtos_grade (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produto_id INT NOT NULL,
        genero VARCHAR(50) NOT NULL DEFAULT 'todos',
        tamanho VARCHAR(50) NULL,
        altura VARCHAR(50) NULL,
        cor_nome VARCHAR(100) NULL,
        cor_hex VARCHAR(7) NULL,
        foto VARCHAR(255) NULL,
        estoque INT NOT NULL DEFAULT 0,
        FOREIGN KEY (produto_id) REFERENCES cms_produtos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Tabela cms_produtos_grade criada ou já existente.<br>";

    echo "<h3>Migração Concluída com Sucesso!</h3>";
} catch (Exception $e) {
    echo "<h3>Erro na migração: " . $e->getMessage() . "</h3>";
}
