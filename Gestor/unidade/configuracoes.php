<?php
require_once '../config.php';

if (!estaLogado()) {
    header('Location: ../login.php');
    exit;
}

$unidade_id = getUnidadeId();
$mensagem = '';
$erro = '';

// AUTO-MIGRAÇÃO: Adicionar colunas faltantes se necessário
try {
    $colunas_novas = [
        'razao_social' => "VARCHAR(255) DEFAULT NULL",
        'inscricao_estadual' => "VARCHAR(50) DEFAULT NULL",
        'inscricao_municipal' => "VARCHAR(50) DEFAULT NULL",
        'whatsapp' => "VARCHAR(20) DEFAULT NULL",
        'instagram' => "VARCHAR(255) DEFAULT NULL",
        'facebook' => "VARCHAR(255) DEFAULT NULL",
        'website' => "VARCHAR(255) DEFAULT NULL",
        'asaas_token' => "TEXT DEFAULT NULL",
        'cora_token' => "TEXT DEFAULT NULL",
        'smtp_host' => "VARCHAR(255) DEFAULT NULL",
        'smtp_user' => "VARCHAR(255) DEFAULT NULL",
        'smtp_pass' => "VARCHAR(255) DEFAULT NULL",
        'smtp_port' => "VARCHAR(10) DEFAULT NULL",
        'smtp_secure' => "VARCHAR(10) DEFAULT NULL",
        'logo' => "VARCHAR(255) DEFAULT NULL"
    ];

    foreach ($colunas_novas as $col => $type) {
        $check = $pdo->query("SHOW COLUMNS FROM unidades LIKE '$col'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE unidades ADD $col $type");
        }
    }
} catch (Exception $e) {
    // Silencioso se já existir
}

// AUTO-MIGRAÇÃO: Tabela de Permissões
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS unidade_permissoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        nivel VARCHAR(50) NOT NULL,
        modulo VARCHAR(50) NOT NULL,
        permitido TINYINT(1) DEFAULT 0,
        UNIQUE KEY uni_nivel_modulo (unidade_id, nivel, modulo)
    )");

    // Migrar de permissão por cargo (nivel) para permissão por usuário (usuario_id)
    $check_col = $pdo->query("SHOW COLUMNS FROM unidade_permissoes LIKE 'usuario_id'");
    if ($check_col->rowCount() == 0) {
        $pdo->exec("ALTER TABLE unidade_permissoes ADD usuario_id INT NULL AFTER unidade_id");
    }
} catch (Exception $e) {}

// Garantir que a chave única (unidade_id, usuario_id, modulo) realmente existe.
// Se a coluna já existia mas a chave nunca foi criada (ex: falhou na 1ª tentativa por
// causa de linhas duplicadas), o "INSERT ... ON DUPLICATE KEY UPDATE" do salvamento de
// permissões não tem o que disparar e passa a criar linhas novas em vez de atualizar —
// causando checkboxes que "voltam errados" depois de salvar.
try {
    $check_key = $pdo->query("SHOW KEYS FROM unidade_permissoes WHERE Key_name = 'uni_usuario_modulo'");
    if ($check_key->rowCount() == 0) {
        // Remove duplicatas (mantém a linha mais recente) antes de criar a chave única,
        // senão o ALTER falha de novo pelo mesmo motivo.
        $pdo->exec("
            DELETE t1 FROM unidade_permissoes t1
            INNER JOIN unidade_permissoes t2
                ON t1.unidade_id = t2.unidade_id
               AND t1.usuario_id <=> t2.usuario_id
               AND t1.modulo = t2.modulo
               AND t1.id < t2.id
        ");
        $pdo->exec("ALTER TABLE unidade_permissoes ADD UNIQUE KEY uni_usuario_modulo (unidade_id, usuario_id, modulo)");
    }
} catch (Exception $e) {}

// Remover a chave única antiga (unidade_id, nivel, modulo): com permissão por usuário,
// vários usuários compartilham o mesmo "nivel", então essa chave antiga colide com o
// INSERT...ON DUPLICATE KEY UPDATE do sistema novo e acaba atualizando a linha legada
// errada (sem usuario_id) em vez de criar/atualizar a linha certa do usuário — fazendo
// o salvamento de permissões parecer que "não grava" ao editar.
try {
    $check_old_key = $pdo->query("SHOW KEYS FROM unidade_permissoes WHERE Key_name = 'uni_nivel_modulo'");
    if ($check_old_key->rowCount() > 0) {
        $pdo->exec("ALTER TABLE unidade_permissoes DROP INDEX uni_nivel_modulo");
    }
    // Linhas legadas do sistema antigo (por cargo) nunca têm usuario_id e não são mais
    // lidas por nenhuma tela — só ficam competindo por engano com os inserts novos.
    $pdo->exec("DELETE FROM unidade_permissoes WHERE usuario_id IS NULL");
} catch (Exception $e) {}

// Não existe mais categoria de "nível de acesso" — Função/Cargo é texto livre e as
// permissões são 100% por usuário (grade de módulos abaixo). O único requisito técnico
// é usuarios.nivel nunca ficar vazio, senão o usuário perde acesso a tudo sem explicação.
try {
    $pdo->prepare("
        UPDATE usuarios u
        INNER JOIN unidade_equipe ue ON ue.usuario_id = u.id
        SET u.nivel = 'colaborador'
        WHERE u.unidade_id = ? AND (u.nivel IS NULL OR TRIM(u.nivel) = '')
    ")->execute([$unidade_id]);
} catch (Exception $e) {}

// Limpeza de dados legados: categorias antigas (instrutor/comercial/secretaria/financeiro)
// não existem mais como "nivel" — normaliza para 'colaborador'. Não apaga ninguém,
// não mexe em login/senha/acesso, só o rótulo técnico interno.
try {
    $pdo->prepare("
        UPDATE usuarios
        SET nivel = 'colaborador'
        WHERE unidade_id = ? AND nivel IN ('instrutor', 'comercial', 'secretaria', 'financeiro')
    ")->execute([$unidade_id]);

    // Só um 'admin' por unidade (o Super Admin vinculado em editar_unidade.php, o de menor id).
    // Duplicados antigos criados pelo RH viram 'colaborador' — mantêm login e permissões normalmente.
    $stmt_admin_principal = $pdo->prepare("SELECT id FROM usuarios WHERE unidade_id = ? AND nivel = 'admin' ORDER BY id ASC LIMIT 1");
    $stmt_admin_principal->execute([$unidade_id]);
    $admin_principal_id = $stmt_admin_principal->fetchColumn();

    if ($admin_principal_id) {
        $pdo->prepare("UPDATE usuarios SET nivel = 'colaborador' WHERE unidade_id = ? AND nivel = 'admin' AND id != ?")
            ->execute([$unidade_id, $admin_principal_id]);
    }
} catch (Exception $e) {}

// AUTO-MIGRAÇÃO: Tabela de Graduações
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS unidade_graduacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        cor_hex VARCHAR(7) DEFAULT '#000000',
        carencia_meses INT DEFAULT 0,
        grau VARCHAR(50) DEFAULT NULL,
        ordem INT DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// AUTO-MIGRAÇÃO: Tabela de Checklist de Graduação
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS unidade_graduacao_checklist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        graduacao_id INT NOT NULL,
        descricao VARCHAR(255) NOT NULL,
        ordem INT DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (graduacao_id) REFERENCES unidade_graduacoes(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

// AUTO-MIGRAÇÃO: Tópicos de Checklist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS unidade_graduacao_checklist_topicos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        graduacao_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        ordem INT DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (graduacao_id) REFERENCES unidade_graduacoes(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

// AUTO-MIGRAÇÃO: Itens do Checklist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS unidade_graduacao_checklist_itens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topico_id INT NOT NULL,
        descricao VARCHAR(255) NOT NULL,
        ordem INT DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (topico_id) REFERENCES unidade_graduacao_checklist_topicos(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

// AUTO-MIGRAÇÃO: Avaliações do Checklist — permite mais de uma prova por faixa
// (ex: Faixa Verde -> "Avaliação 1", "Avaliação 2"), cada uma com seus próprios tópicos/itens.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS unidade_graduacao_avaliacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        graduacao_id INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        ordem INT DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (graduacao_id) REFERENCES unidade_graduacoes(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

// AUTO-MIGRAÇÃO: vincular tópicos a uma avaliação (Prova 1/2/x) em vez de direto na faixa.
try {
    $check_col = $pdo->query("SHOW COLUMNS FROM unidade_graduacao_checklist_topicos LIKE 'avaliacao_id'");
    if ($check_col->rowCount() == 0) {
        $pdo->exec("ALTER TABLE unidade_graduacao_checklist_topicos ADD avaliacao_id INT NULL AFTER graduacao_id");
    }
} catch (Exception $e) {}

// Migração de dados: tópicos já cadastrados antes de existir "avaliação" viram a
// "Avaliação 1" da própria faixa automaticamente — preserva o checklist existente
// sem exigir recadastro manual.
try {
    $stmt_legado = $pdo->prepare("
        SELECT DISTINCT graduacao_id FROM unidade_graduacao_checklist_topicos
        WHERE avaliacao_id IS NULL AND graduacao_id IN (SELECT id FROM unidade_graduacoes WHERE unidade_id = ?)
    ");
    $stmt_legado->execute([$unidade_id]);
    foreach ($stmt_legado->fetchAll(PDO::FETCH_COLUMN) as $grad_id_legado) {
        $ins_aval = $pdo->prepare("INSERT INTO unidade_graduacao_avaliacoes (graduacao_id, nome, ordem) VALUES (?, 'Avaliação 1', 1)");
        $ins_aval->execute([$grad_id_legado]);
        $nova_aval_id = $pdo->lastInsertId();
        $pdo->prepare("UPDATE unidade_graduacao_checklist_topicos SET avaliacao_id = ? WHERE graduacao_id = ? AND avaliacao_id IS NULL")
            ->execute([$nova_aval_id, $grad_id_legado]);
    }
} catch (Exception $e) {}

// CRUD Graduações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_graduacao'])) {
    $action = $_POST['action_graduacao'];
    
    if ($action === 'salvar') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $nome = $_POST['nome'] ?? '';
        $cor_hex = $_POST['cor_hex'] ?? '#000000';
        $carencia_meses = !empty($_POST['carencia_meses']) ? (int)$_POST['carencia_meses'] : 0;
        $grau = $_POST['grau'] ?? '';
        $ordem = !empty($_POST['ordem']) ? (int)$_POST['ordem'] : 0;
        
        if ($id) {
            $stmt = $pdo->prepare("UPDATE unidade_graduacoes SET nome = ?, cor_hex = ?, carencia_meses = ?, grau = ?, ordem = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$nome, $cor_hex, $carencia_meses, $grau, $ordem, $id, $unidade_id]);
            $mensagem = "Graduação atualizada com sucesso!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO unidade_graduacoes (unidade_id, nome, cor_hex, carencia_meses, grau, ordem) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $nome, $cor_hex, $carencia_meses, $grau, $ordem]);
            $mensagem = "Graduação cadastrada com sucesso!";
        }
        header("Location: configuracoes.php?tab=faixas");
        exit;
    }
    
    if ($action === 'excluir') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM unidade_graduacoes WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$id, $unidade_id]);
        $mensagem = "Graduação excluída com sucesso!";
        header("Location: configuracoes.php?tab=faixas");
        exit;
    }

    if ($action === 'reordenar') {
        header('Content-Type: application/json');
        $ids = json_decode($_POST['ordem_ids'] ?? '[]', true);
        if (is_array($ids)) {
            foreach ($ids as $pos => $gid) {
                $pdo->prepare("UPDATE unidade_graduacoes SET ordem = ? WHERE id = ? AND unidade_id = ?")
                    ->execute([$pos + 1, (int)$gid, $unidade_id]);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }
}

// CRUD Checklist de Avaliação (Tópicos & Itens)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_checklist'])) {
    header('Content-Type: application/json');
    $action = $_POST['action_checklist'];

    // 0. AVALIAÇÕES (Provas dentro da faixa: Prova 1, Prova 2...)
    if ($action === 'adicionar_avaliacao') {
        $graduacao_id = (int)($_POST['graduacao_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');

        $chk = $pdo->prepare("SELECT id FROM unidade_graduacoes WHERE id = ? AND unidade_id = ?");
        $chk->execute([$graduacao_id, $unidade_id]);
        if (!$chk->fetch() || !$nome) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos.']); exit;
        }

        $ordem_stmt = $pdo->prepare("SELECT COALESCE(MAX(ordem),0)+1 FROM unidade_graduacao_avaliacoes WHERE graduacao_id = ?");
        $ordem_stmt->execute([$graduacao_id]);
        $prox_ordem = (int)$ordem_stmt->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO unidade_graduacao_avaliacoes (graduacao_id, nome, ordem) VALUES (?, ?, ?)");
        $ins->execute([$graduacao_id, $nome, $prox_ordem]);
        $novo_id = $pdo->lastInsertId();

        echo json_encode(['ok' => true, 'id' => $novo_id, 'nome' => $nome, 'ordem' => $prox_ordem, 'graduacao_id' => $graduacao_id]);
        exit;
    }

    if ($action === 'editar_avaliacao') {
        $avaliacao_id = (int)($_POST['avaliacao_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');

        $chk = $pdo->prepare("SELECT a.id FROM unidade_graduacao_avaliacoes a JOIN unidade_graduacoes g ON g.id = a.graduacao_id WHERE a.id = ? AND g.unidade_id = ?");
        $chk->execute([$avaliacao_id, $unidade_id]);
        if (!$chk->fetch() || !$nome) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos.']); exit;
        }

        $pdo->prepare("UPDATE unidade_graduacao_avaliacoes SET nome = ? WHERE id = ?")->execute([$nome, $avaliacao_id]);
        echo json_encode(['ok' => true, 'nome' => $nome]);
        exit;
    }

    if ($action === 'excluir_avaliacao') {
        $avaliacao_id = (int)($_POST['avaliacao_id'] ?? 0);

        $chk = $pdo->prepare("SELECT a.id FROM unidade_graduacao_avaliacoes a JOIN unidade_graduacoes g ON g.id = a.graduacao_id WHERE a.id = ? AND g.unidade_id = ?");
        $chk->execute([$avaliacao_id, $unidade_id]);
        if (!$chk->fetch()) {
            echo json_encode(['ok' => false, 'erro' => 'Avaliação não encontrada.']); exit;
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE i FROM unidade_graduacao_checklist_itens i JOIN unidade_graduacao_checklist_topicos t ON t.id = i.topico_id WHERE t.avaliacao_id = ?")->execute([$avaliacao_id]);
            $pdo->prepare("DELETE FROM unidade_graduacao_checklist_topicos WHERE avaliacao_id = ?")->execute([$avaliacao_id]);
            $pdo->prepare("DELETE FROM unidade_graduacao_avaliacoes WHERE id = ?")->execute([$avaliacao_id]);
            $pdo->commit();
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Erro ao excluir: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'reordenar_avaliacoes') {
        $ids = json_decode($_POST['ordem_ids'] ?? '[]', true);
        if (is_array($ids)) {
            foreach ($ids as $pos => $aid) {
                $pdo->prepare("UPDATE unidade_graduacao_avaliacoes a JOIN unidade_graduacoes g ON g.id = a.graduacao_id SET a.ordem = ? WHERE a.id = ? AND g.unidade_id = ?")
                    ->execute([$pos + 1, (int)$aid, $unidade_id]);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // 1. TÓPICOS
    if ($action === 'adicionar_topico') {
        $avaliacao_id = (int)($_POST['avaliacao_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');

        // Valida se a avaliação pertence a uma graduação desta unidade
        $chk = $pdo->prepare("SELECT a.graduacao_id FROM unidade_graduacao_avaliacoes a JOIN unidade_graduacoes g ON g.id = a.graduacao_id WHERE a.id = ? AND g.unidade_id = ?");
        $chk->execute([$avaliacao_id, $unidade_id]);
        $aval_row = $chk->fetch();
        if (!$aval_row || !$nome) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos.']); exit;
        }
        $graduacao_id = $aval_row['graduacao_id'];

        $ordem_stmt = $pdo->prepare("SELECT COALESCE(MAX(ordem),0)+1 FROM unidade_graduacao_checklist_topicos WHERE avaliacao_id = ?");
        $ordem_stmt->execute([$avaliacao_id]);
        $prox_ordem = (int)$ordem_stmt->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO unidade_graduacao_checklist_topicos (graduacao_id, avaliacao_id, nome, ordem) VALUES (?, ?, ?, ?)");
        $ins->execute([$graduacao_id, $avaliacao_id, $nome, $prox_ordem]);
        $novo_id = $pdo->lastInsertId();

        echo json_encode(['ok' => true, 'id' => $novo_id, 'nome' => $nome, 'ordem' => $prox_ordem]);
        exit;
    }

    if ($action === 'editar_topico') {
        $topico_id = (int)($_POST['topico_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');

        $chk = $pdo->prepare("SELECT t.id FROM unidade_graduacao_checklist_topicos t JOIN unidade_graduacoes g ON g.id = t.graduacao_id WHERE t.id = ? AND g.unidade_id = ?");
        $chk->execute([$topico_id, $unidade_id]);
        if (!$chk->fetch() || !$nome) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos.']); exit;
        }

        $pdo->prepare("UPDATE unidade_graduacao_checklist_topicos SET nome = ? WHERE id = ?")->execute([$nome, $topico_id]);
        echo json_encode(['ok' => true, 'nome' => $nome]);
        exit;
    }

    if ($action === 'excluir_topico') {
        $topico_id = (int)($_POST['topico_id'] ?? 0);

        $chk = $pdo->prepare("SELECT t.id FROM unidade_graduacao_checklist_topicos t JOIN unidade_graduacoes g ON g.id = t.graduacao_id WHERE t.id = ? AND g.unidade_id = ?");
        $chk->execute([$topico_id, $unidade_id]);
        if (!$chk->fetch()) {
            echo json_encode(['ok' => false, 'erro' => 'Tópico não encontrado.']); exit;
        }

        $pdo->prepare("DELETE FROM unidade_graduacao_checklist_topicos WHERE id = ?")->execute([$topico_id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'reordenar_topicos') {
        $ids = json_decode($_POST['ordem_ids'] ?? '[]', true);
        if (is_array($ids)) {
            foreach ($ids as $pos => $tid) {
                $pdo->prepare("UPDATE unidade_graduacao_checklist_topicos t JOIN unidade_graduacoes g ON g.id = t.graduacao_id SET t.ordem = ? WHERE t.id = ? AND g.unidade_id = ?")
                    ->execute([$pos + 1, (int)$tid, $unidade_id]);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // 2. ITENS
    if ($action === 'adicionar_item') {
        $topico_id = (int)($_POST['topico_id'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');

        // Valida se o tópico pertence à unidade via join com graduações
        $chk = $pdo->prepare("SELECT t.id FROM unidade_graduacao_checklist_topicos t JOIN unidade_graduacoes g ON g.id = t.graduacao_id WHERE t.id = ? AND g.unidade_id = ?");
        $chk->execute([$topico_id, $unidade_id]);
        if (!$chk->fetch() || !$descricao) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos.']); exit;
        }

        $ordem_stmt = $pdo->prepare("SELECT COALESCE(MAX(ordem),0)+1 FROM unidade_graduacao_checklist_itens WHERE topico_id = ?");
        $ordem_stmt->execute([$topico_id]);
        $prox_ordem = (int)$ordem_stmt->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO unidade_graduacao_checklist_itens (topico_id, descricao, ordem) VALUES (?, ?, ?)");
        $ins->execute([$topico_id, $descricao, $prox_ordem]);
        $novo_id = $pdo->lastInsertId();

        echo json_encode(['ok' => true, 'id' => $novo_id, 'descricao' => $descricao, 'ordem' => $prox_ordem]);
        exit;
    }

    if ($action === 'editar_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');

        $chk = $pdo->prepare("SELECT i.id FROM unidade_graduacao_checklist_itens i JOIN unidade_graduacao_checklist_topicos t ON t.id = i.topico_id JOIN unidade_graduacoes g ON g.id = t.graduacao_id WHERE i.id = ? AND g.unidade_id = ?");
        $chk->execute([$item_id, $unidade_id]);
        if (!$chk->fetch() || !$descricao) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos.']); exit;
        }

        $pdo->prepare("UPDATE unidade_graduacao_checklist_itens SET descricao = ? WHERE id = ?")->execute([$descricao, $item_id]);
        echo json_encode(['ok' => true, 'descricao' => $descricao]);
        exit;
    }

    if ($action === 'excluir_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);

        $chk = $pdo->prepare("SELECT i.id FROM unidade_graduacao_checklist_itens i JOIN unidade_graduacao_checklist_topicos t ON t.id = i.topico_id JOIN unidade_graduacoes g ON g.id = t.graduacao_id WHERE i.id = ? AND g.unidade_id = ?");
        $chk->execute([$item_id, $unidade_id]);
        if (!$chk->fetch()) {
            echo json_encode(['ok' => false, 'erro' => 'Item não encontrado.']); exit;
        }

        $pdo->prepare("DELETE FROM unidade_graduacao_checklist_itens WHERE id = ?")->execute([$item_id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'reordenar_itens') {
        $ids = json_decode($_POST['ordem_ids'] ?? '[]', true);
        if (is_array($ids)) {
            foreach ($ids as $pos => $iid) {
                $pdo->prepare("UPDATE unidade_graduacao_checklist_itens i JOIN unidade_graduacao_checklist_topicos t ON t.id = i.topico_id JOIN unidade_graduacoes g ON g.id = t.graduacao_id SET i.ordem = ? WHERE i.id = ? AND g.unidade_id = ?")
                    ->execute([$pos + 1, (int)$iid, $unidade_id]);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'duplicar_checklist') {
        $de_grad_id = (int)($_POST['de_graduacao_id'] ?? 0);
        $para_grad_id = (int)($_POST['para_graduacao_id'] ?? 0);

        // Valida se ambas as graduações pertencem à unidade do usuário
        $chk1 = $pdo->prepare("SELECT id FROM unidade_graduacoes WHERE id = ? AND unidade_id = ?");
        $chk1->execute([$de_grad_id, $unidade_id]);
        $val1 = $chk1->fetch();

        $chk2 = $pdo->prepare("SELECT id FROM unidade_graduacoes WHERE id = ? AND unidade_id = ?");
        $chk2->execute([$para_grad_id, $unidade_id]);
        $val2 = $chk2->fetch();

        if (!$val1 || !$val2 || $de_grad_id === $para_grad_id) {
            echo json_encode(['ok' => false, 'erro' => 'Graduações de origem ou destino inválidas.']); exit;
        }

        try {
            $pdo->beginTransaction();

            // 1. Limpar checklist de destino existente (avaliações, tópicos e itens)
            $stmt_del_itens = $pdo->prepare("DELETE i FROM unidade_graduacao_checklist_itens i JOIN unidade_graduacao_checklist_topicos t ON t.id = i.topico_id WHERE t.graduacao_id = ?");
            $stmt_del_itens->execute([$para_grad_id]);

            $stmt_del_tops = $pdo->prepare("DELETE FROM unidade_graduacao_checklist_topicos WHERE graduacao_id = ?");
            $stmt_del_tops->execute([$para_grad_id]);

            $stmt_del_avals = $pdo->prepare("DELETE FROM unidade_graduacao_avaliacoes WHERE graduacao_id = ?");
            $stmt_del_avals->execute([$para_grad_id]);

            // 2. Buscar avaliações de origem
            $stmt_avals = $pdo->prepare("SELECT * FROM unidade_graduacao_avaliacoes WHERE graduacao_id = ? ORDER BY ordem ASC, id ASC");
            $stmt_avals->execute([$de_grad_id]);
            $avals_origem = $stmt_avals->fetchAll(PDO::FETCH_ASSOC);

            foreach ($avals_origem as $aval) {
                // Inserir nova avaliação
                $ins_aval = $pdo->prepare("INSERT INTO unidade_graduacao_avaliacoes (graduacao_id, nome, ordem) VALUES (?, ?, ?)");
                $ins_aval->execute([$para_grad_id, $aval['nome'], $aval['ordem']]);
                $nova_aval_id = $pdo->lastInsertId();

                // Buscar tópicos dessa avaliação de origem
                $stmt_tops = $pdo->prepare("SELECT * FROM unidade_graduacao_checklist_topicos WHERE avaliacao_id = ? ORDER BY ordem ASC, id ASC");
                $stmt_tops->execute([$aval['id']]);
                $topicos_origem = $stmt_tops->fetchAll(PDO::FETCH_ASSOC);

                foreach ($topicos_origem as $top) {
                    // Inserir novo tópico
                    $ins_top = $pdo->prepare("INSERT INTO unidade_graduacao_checklist_topicos (graduacao_id, avaliacao_id, nome, ordem) VALUES (?, ?, ?, ?)");
                    $ins_top->execute([$para_grad_id, $nova_aval_id, $top['nome'], $top['ordem']]);
                    $novo_topico_id = $pdo->lastInsertId();

                    // Buscar itens desse tópico de origem
                    $stmt_itens = $pdo->prepare("SELECT * FROM unidade_graduacao_checklist_itens WHERE topico_id = ? ORDER BY ordem ASC, id ASC");
                    $stmt_itens->execute([$top['id']]);
                    $itens_origem = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($itens_origem as $item) {
                        $ins_item = $pdo->prepare("INSERT INTO unidade_graduacao_checklist_itens (topico_id, descricao, ordem) VALUES (?, ?, ?)");
                        $ins_item->execute([$novo_topico_id, $item['descricao'], $item['ordem']]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Erro ao duplicar checklist: ' . $e->getMessage()]);
        }
        exit;
    }


    echo json_encode(['ok' => false, 'erro' => 'Ação desconhecida.']);
    exit;
}

// Buscar dados atuais da unidade
$stmt = $pdo->prepare("SELECT * FROM unidades WHERE id = ?");
$stmt->execute([$unidade_id]);
$unidade = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['logo_file'])) {
        if ($_FILES['logo_file']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $formatos_permitidos = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            
            if (in_array($ext, $formatos_permitidos)) {
                $novo_nome = 'logo_' . $unidade_id . '_' . time() . '.' . $ext;
                $path = getUploadPath('logos');
                
                if ($path && move_uploaded_file($_FILES['logo_file']['tmp_name'], $path . '/' . $novo_nome)) {
                    $pdo->prepare("UPDATE unidades SET logo = ? WHERE id = ?")->execute([$novo_nome, $unidade_id]);
                    $unidade['logo'] = $novo_nome;
                } else {
                    $erro = "FALHA NO SERVIDOR: O PHP não conseguiu mover o arquivo para: " . ($path ?: 'Caminho nulo') . ". Verifique se a pasta 'uploads' tem permissão de escrita (CHMOD 777).";
                }
            } else {
                $erro = "FORMATO INVÁLIDO: Apenas JPG, PNG, WEBP ou SVG são permitidos.";
            }
        } elseif ($_FILES['logo_file']['error'] !== 4) { // 4 = Nenhum arquivo enviado
            $erro = "ERRO DE UPLOAD: Código " . $_FILES['logo_file']['error'] . ". (Pode ser o tamanho do arquivo excedendo o limite do servidor).";
        }
    }

    $update_fields = [
        'nome', 'razao_social', 'documento', 'inscricao_estadual', 'inscricao_municipal',
        'telefone', 'whatsapp', 'email_contato', 'website', 'instagram', 'facebook',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado',
        'cora_token', 'smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port', 'smtp_secure'
    ];

    $sql = "UPDATE unidades SET ";
    $params = [];
    foreach ($update_fields as $f) {
        $sql .= "$f = ?, ";
        $params[] = $_POST[$f] ?? '';
    }
    $sql = rtrim($sql, ", ") . " WHERE id = ?";
    $params[] = $unidade_id;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Salvar Permissões de Acesso (por usuário)
        $stmt_usuarios_perm = $pdo->prepare("
            SELECT u.id, u.nivel
            FROM usuarios u
            INNER JOIN unidade_equipe ue ON ue.usuario_id = u.id
            WHERE u.unidade_id = ? AND u.nivel != 'mestre'
        ");
        $stmt_usuarios_perm->execute([$unidade_id]);
        $usuarios_permissao_save = $stmt_usuarios_perm->fetchAll();

        foreach ($usuarios_permissao_save as $u_perm) {
            $uid = $u_perm['id'];

            // Primeiro zera todas as permissões desse usuário para essa unidade
            $pdo->prepare("UPDATE unidade_permissoes SET permitido = 0 WHERE unidade_id = ? AND usuario_id = ?")
                ->execute([$unidade_id, $uid]);

            // Se houver permissões enviadas para este usuário, ativa/cria as marcadas
            if (isset($_POST['permissoes'][$uid])) {
                foreach ($_POST['permissoes'][$uid] as $modulo => $valor) {
                    $pdo->prepare("INSERT INTO unidade_permissoes (unidade_id, usuario_id, nivel, modulo, permitido) VALUES (?, ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE permitido = 1")
                        ->execute([$unidade_id, $uid, $u_perm['nivel'], $modulo]);
                }
            }
        }

        // Post/Redirect/Get: evita o aviso "Confirmar reenvio do formulário" ao atualizar
        // a página, e garante que o que aparece depois é sempre o estado real salvo no banco.
        $tab_ativo = $_POST['tab_ativo'] ?? 'perfil';
        header("Location: configuracoes.php?tab=" . urlencode($tab_ativo) . "&salvo=1");
        exit;
    } catch (Exception $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

if (isset($_GET['salvo'])) {
    $mensagem = "Configurações atualizadas com sucesso!";
}

// Buscar Permissões atuais (por usuário)
$stmt_perm = $pdo->prepare("SELECT * FROM unidade_permissoes WHERE unidade_id = ? AND usuario_id IS NOT NULL ORDER BY id ASC");
$stmt_perm->execute([$unidade_id]);
$permissoes_raw = $stmt_perm->fetchAll();
$permissoes = [];
foreach ($permissoes_raw as $p) {
    $permissoes[$p['usuario_id']][$p['modulo']] = $p['permitido'];
}

// Buscar usuários da unidade para exibir as abas de permissão (apenas o Super Admin/mestre tem acesso total automático).
// INNER JOIN propositalmente: só lista quem tem ficha em RH (unidade_equipe) — contas de login
// órfãs (criadas fora do fluxo normal, sem ficha vinculada) não aparecem aqui.
$stmt_usuarios_lista = $pdo->prepare("
    SELECT u.id, u.nome, ue.funcao
    FROM usuarios u
    INNER JOIN unidade_equipe ue ON ue.usuario_id = u.id
    WHERE u.unidade_id = ? AND u.nivel != 'mestre'
    ORDER BY u.nome ASC
");
$stmt_usuarios_lista->execute([$unidade_id]);
$usuarios_lista = $stmt_usuarios_lista->fetchAll();

// Buscar graduações da unidade
$stmt_grads = $pdo->prepare("SELECT * FROM unidade_graduacoes WHERE unidade_id = ? ORDER BY ordem ASC, nome ASC");
$stmt_grads->execute([$unidade_id]);
$graduacoes = $stmt_grads->fetchAll();

// Buscar avaliações (provas) de cada graduação — ex: "Avaliação 1", "Avaliação 2"
$avaliacoes_por_grad = [];
if (!empty($graduacoes)) {
    $ids_grad_av = array_column($graduacoes, 'id');
    $placeholders_av = implode(',', array_fill(0, count($ids_grad_av), '?'));

    $stmt_avals = $pdo->prepare("SELECT * FROM unidade_graduacao_avaliacoes WHERE graduacao_id IN ($placeholders_av) ORDER BY graduacao_id ASC, ordem ASC, id ASC");
    $stmt_avals->execute($ids_grad_av);
    foreach ($stmt_avals->fetchAll(PDO::FETCH_ASSOC) as $aval) {
        $avaliacoes_por_grad[$aval['graduacao_id']][] = $aval;
    }
}

// Buscar checklists de todas as graduações desta unidade (Tópicos & Itens), agrupados por avaliacao_id
$checklist_topicos = [];
if (!empty($graduacoes)) {
    $ids_grad = array_column($graduacoes, 'id');
    $placeholders = implode(',', array_fill(0, count($ids_grad), '?'));

    // Buscar Tópicos
    $stmt_topicos = $pdo->prepare("SELECT * FROM unidade_graduacao_checklist_topicos WHERE graduacao_id IN ($placeholders) ORDER BY avaliacao_id ASC, ordem ASC, id ASC");
    $stmt_topicos->execute($ids_grad);
    $topicos_all = $stmt_topicos->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($topicos_all)) {
        $ids_topicos = array_column($topicos_all, 'id');
        $placeholders_topicos = implode(',', array_fill(0, count($ids_topicos), '?'));

        // Buscar Itens
        $stmt_itens = $pdo->prepare("SELECT * FROM unidade_graduacao_checklist_itens WHERE topico_id IN ($placeholders_topicos) ORDER BY topico_id ASC, ordem ASC, id ASC");
        $stmt_itens->execute($ids_topicos);
        $itens_all = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar itens por topico_id
        $itens_by_topico = [];
        foreach ($itens_all as $item) {
            $itens_by_topico[$item['topico_id']][] = $item;
        }

        // Agrupar tópicos por avaliacao_id e injetar itens
        foreach ($topicos_all as $topico) {
            $topico['itens'] = $itens_by_topico[$topico['id']] ?? [];
            $checklist_topicos[$topico['avaliacao_id']][] = $topico;
        }
    }
}

include 'header.php';
?>

<style>
    .config-tabs { display: flex; gap: 5px; margin-bottom: 30px; border-bottom: 1px solid var(--border-color); }
    .config-tab-item { padding: 15px 25px; font-size: 12px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.3s; }
    .config-tab-item.active { color: var(--brand); border-bottom-color: var(--brand); background: #f8fafc; }
    .config-content { display: none; animation: fadeIn 0.3s ease; }
    .config-content.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    .integration-card { background: white; border: 1px solid var(--border-color); padding: 25px; display: flex; align-items: center; gap: 20px; transition: all 0.3s; }
    .integration-card:hover { border-color: var(--brand); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
    .integration-icon { width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; font-size: 24px; background: #f8fafc; border: 1px solid var(--border-color); }

    .logo-dropzone { border: 2px dashed var(--border-color); padding: 40px; text-align: center; background: #fafafa; cursor: pointer; transition: all 0.3s; position: relative; overflow: hidden; }
    .logo-dropzone:hover { border-color: var(--brand); background: #f0f7ff; }
    .logo-preview { max-width: 200px; max-height: 100px; object-fit: contain; }

    /* Switch CSS */
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--primary-green) !important; }
    input:checked + .slider:before { transform: translateX(20px); }
    input:disabled + .slider { opacity: 0.5; cursor: not-allowed; }
</style>

<section class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
        <div style="border-left: 8px solid var(--primary-gold); padding-left: 20px;">
            <h1 style="font-size: 2rem; font-weight: 900; color: #133080; margin: 0; text-transform: uppercase; letter-spacing: -0.03em;">Configurações da Unidade</h1>
            <p style="color: var(--text-muted); font-size: 14px; margin: 5px 0 0 0; font-weight: 500;">Gerencie os dados, integrações e identidade da sua academia.</p>
        </div>
        <a href="index.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;"><i class="fa-solid fa-arrow-left me-2"></i> Voltar Operações</a>
    </div>

    <?php if ($mensagem): ?>
        <div style="background: #dcfce7; color: #166534; padding: 20px; border-left: 5px solid #22c55e; margin-bottom: 30px; font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: 0.05em;">
            <i class="fa-solid fa-check-circle me-2"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <div class="config-tabs">
        <div class="config-tab-item active" onclick="showTab('tab-perfil')">Dados Empresariais</div>
        <div class="config-tab-item" onclick="showTab('tab-endereco')">Endereço & Contatos</div>
        <div class="config-tab-item" onclick="showTab('tab-digital')">Redes Sociais & Website</div>
        <div class="config-tab-item" onclick="showTab('tab-integracoes')">Integrações (APIs)</div>
        <div class="config-tab-item" onclick="showTab('tab-acessos')">Permissões de Acesso</div>
        <div class="config-tab-item" onclick="showTab('tab-faixas')">Faixas & Graduações</div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="form-config">
        <input type="hidden" name="tab_ativo" id="tab_ativo" value="perfil">
        <!-- ABA 5: PERMISSÕES DE ACESSO -->
        <div id="tab-acessos" class="config-content">
            <div style="background: white; border: 1px solid var(--border-color); padding: 40px;">
                <h3 style="font-size: 14px; font-weight: 900; color: #133080; text-transform: uppercase; margin-bottom: 25px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; display: flex; align-items: center; gap: 12px;">
                    <i class="fa-solid fa-user-shield"></i> Configuração de Permissões por Usuário
                </h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 30px;">Gerencie o que cada colaborador pode visualizar e fazer no sistema.</p>

                <style>
                    .role-tab-btn {
                        border-radius: 0px !important;
                        border: 1px solid var(--border-color) !important;
                        color: var(--text-dark) !important;
                        background: #fff !important;
                        font-weight: 800;
                        text-transform: uppercase;
                        cursor: pointer;
                        padding: 15px;
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        transition: all 0.2s ease-in-out;
                        margin-bottom: 8px;
                    }
                    .role-tab-btn:hover {
                        background: #f8fafc !important;
                        border-color: #08153a !important;
                        color: #08153a !important;
                    }
                    .role-tab-btn.active {
                        background: #08153a !important;
                        color: #fff !important;
                        border-color: #08153a !important;
                    }
                    .role-content-pane {
                        display: none;
                        animation: roleFadeIn 0.3s ease;
                    }
                    .role-content-pane.active {
                        display: block;
                    }
                    @keyframes roleFadeIn {
                        from { opacity: 0; transform: translateY(5px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                </style>

                <div style="display: grid; grid-template-columns: 280px 1fr; gap: 40px; align-items: start;">
                    <!-- Sub-Abas de Usuários (Esquerda) -->
                    <div style="display: flex; flex-direction: column; gap: 4px; border-right: 1px solid var(--border-color); padding-right: 25px;">
                        <div style="font-size: 10px; font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Selecione o Usuário</div>

                        <?php if (empty($usuarios_lista)): ?>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 600; padding: 5px 0 15px;">
                                Nenhum usuário cadastrado. Adicione membros da equipe em RH para configurar as permissões individuais.
                            </div>
                        <?php else: ?>
                            <?php foreach ($usuarios_lista as $i => $u): ?>
                            <div class="role-tab-btn <?php echo $i === 0 ? 'active' : ''; ?>" onclick="switchRoleTab('role-<?php echo $u['id']; ?>')" style="text-align: left;">
                                <i class="fa-solid fa-user" style="width: 16px;"></i>
                                <span>
                                    <?php echo htmlspecialchars($u['nome']); ?><br>
                                    <small style="font-weight: 600; opacity: 0.7; text-transform: uppercase; font-size: 9px;"><?php echo htmlspecialchars($u['funcao'] ?: 'Função não definida'); ?></small>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div style="margin-top: 25px; padding: 20px; background: #fafafa; border: 1px solid var(--border-color); font-size: 11px; color: var(--text-muted); line-height: 1.5; font-weight: 500;">
                            <i class="fa-solid fa-shield-halved" style="color: #08153a; margin-right: 6px; font-size: 14px;"></i>
                            Apenas o <strong>Super Admin</strong> possui acesso irrestrito e automático. Todo usuário da unidade tem suas permissões configuradas por módulo aqui.
                        </div>
                    </div>

                    <!-- Conteúdo das Abas (Direita) -->
                    <div style="flex: 1;">
                        <?php if (empty($usuarios_lista)): ?>
                        <div style="padding: 60px 20px; text-align: center; color: var(--text-muted); font-weight: 700;">
                            <i class="fa-solid fa-users-slash" style="font-size: 32px; opacity: 0.2; margin-bottom: 15px; display: block;"></i>
                            Cadastre usuários na equipe (RH) para definir as permissões de cada um.
                        </div>
                        <?php endif; ?>
                        <?php foreach ($usuarios_lista as $i => $u):
                            $role_key = $u['id'];
                        ?>
                        <div id="role-<?php echo $role_key; ?>" class="role-content-pane <?php echo ($i === 0) ? 'active' : ''; ?>">
                            <!-- Módulos Gerais -->
                            <h4 style="font-size: 11px; font-weight: 900; color: #08153a; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-cubes"></i> Módulos Globais (Acesso Completo)
                            </h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px;">
                                <?php
                                $modulos_globais = [
                                    'comercial' => ['label' => 'Comercial (CRM)', 'desc' => 'Visualizar e gerenciar funil e leads.'],
                                    'marketing' => ['label' => 'Marketing & Campanhas', 'desc' => 'Postagens, disparos e banners.'],
                                    'eventos' => ['label' => 'Gestão de Eventos', 'desc' => 'Campeonatos, seminários e graduações.'],
                                    'website' => ['label' => 'Configurações de Website', 'desc' => 'Editar informações públicas do site.']
                                ];
                                foreach ($modulos_globais as $m_key => $m_data): ?>
                                <div style="background: #fafafa; border: 1px solid var(--border-color); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
                                    <div style="padding-right: 15px;">
                                        <strong style="font-size: 13px; color: var(--text-dark); display: block; margin-bottom: 2px;"><?php echo $m_data['label']; ?></strong>
                                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 500; display: block; line-height: 1.3;"><?php echo $m_data['desc']; ?></span>
                                    </div>
                                    <label class="switch" style="position: relative; display: inline-block; width: 40px; height: 20px; flex-shrink: 0;">
                                        <input type="checkbox" name="permissoes[<?php echo $role_key; ?>][<?php echo $m_key; ?>]" value="1" <?php echo (isset($permissoes[$role_key][$m_key]) && $permissoes[$role_key][$m_key]) ? 'checked' : ''; ?>>
                                        <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 20px;"></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Módulos Administrativos Detalhados -->
                            <h4 style="font-size: 11px; font-weight: 900; color: #08153a; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-folder-tree"></i> Painel Administrativo (Acesso Fino)
                            </h4>
                            <div style="border: 1px solid var(--border-color); background: #fff; overflow-x: auto;">
                                <table class="table" style="margin: 0; vertical-align: middle; min-width: 500px;">
                                    <thead>
                                        <tr style="background: #f8fafc;">
                                            <th style="padding: 15px 20px; font-size: 10px; font-weight: 900; color: #64748b; border: none; text-transform: uppercase;">Menu / Recurso</th>
                                            <th style="padding: 15px 20px; font-size: 10px; font-weight: 900; color: #64748b; border: none; text-transform: uppercase; text-align: center; width: 90px;">Visualizar</th>
                                            <th style="padding: 15px 20px; font-size: 10px; font-weight: 900; color: #64748b; border: none; text-transform: uppercase; text-align: center; width: 90px;">Criar</th>
                                            <th style="padding: 15px 20px; font-size: 10px; font-weight: 900; color: #64748b; border: none; text-transform: uppercase; text-align: center; width: 90px;">Editar</th>
                                            <th style="padding: 15px 20px; font-size: 10px; font-weight: 900; color: #64748b; border: none; text-transform: uppercase; text-align: center; width: 90px;">Remover</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sub_menus = [
                                            'alunos' => ['label' => 'Alunos (Alunos)', 'icon' => 'fa-users'],
                                            'rh' => ['label' => 'RH / Equipe', 'icon' => 'fa-users-gear'],
                                            'unidades' => ['label' => 'Unidades / Academias', 'icon' => 'fa-building'],
                                            'turmas' => ['label' => 'Turmas', 'icon' => 'fa-calendar-days'],
                                            'chamadas' => ['label' => 'Chamadas', 'icon' => 'fa-clipboard-user'],
                                            'financeiro' => ['label' => 'Financeiro / Mensalidades', 'icon' => 'fa-dollar-sign'],
                                            'planejamento' => ['label' => 'Planejamento / Aulas', 'icon' => 'fa-clipboard-list']
                                        ];
                                        foreach ($sub_menus as $key => $data): ?>
                                        <tr>
                                            <td style="padding: 15px 20px; font-weight: 700; color: var(--text-dark); font-size: 13px; border-bottom: 1px solid var(--border-color);">
                                                <i class="fa-solid <?php echo $data['icon']; ?>" style="color: #08153a; margin-right: 8px; width: 16px;"></i>
                                                <?php echo $data['label']; ?>
                                            </td>
                                            <?php foreach (['visualizar', 'criar', 'editar', 'remover'] as $action): 
                                                $perm_key = $key . '_' . $action;
                                            ?>
                                            <td style="text-align: center; padding: 15px 20px; border-bottom: 1px solid var(--border-color);">
                                                <input type="checkbox" name="permissoes[<?php echo $role_key; ?>][<?php echo $perm_key; ?>]" value="1" 
                                                    style="width: 18px; height: 18px; accent-color: #08153a; cursor: pointer;"
                                                    <?php echo (isset($permissoes[$role_key][$perm_key]) && $permissoes[$role_key][$perm_key]) ? 'checked' : ''; ?>>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <script>
                    function switchRoleTab(paneId) {
                        document.querySelectorAll('.role-content-pane').forEach(c => c.classList.remove('active'));
                        document.querySelectorAll('.role-tab-btn').forEach(t => t.classList.remove('active'));
                        document.getElementById(paneId).classList.add('active');
                        event.currentTarget.classList.add('active');
                    }
                </script>
            </div>
        </div>

        <!-- ABA 1: PERFIL EMPRESARIAL -->
        <div id="tab-perfil" class="config-content active">
            <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 40px;">
                <div>
                    <h3 style="font-size: 14px; font-weight: 900; color: #133080; text-transform: uppercase; margin-bottom: 20px;">Logotipo da Unidade</h3>
                    <label for="logo_file" class="logo-dropzone" style="display: block;">
                        <?php if ($unidade['logo']): ?>
                            <img src="<?php echo getUploadURL('logos', $unidade['logo']); ?>" class="logo-preview">
                        <?php else: ?>
                            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 40px; color: #cbd5e1; display: block; margin-bottom: 15px;"></i>
                            <span style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Arraste ou clique para enviar</span>
                        <?php endif; ?>
                    </label>
                    <input type="file" name="logo_file" id="logo_file" style="display: none;" onchange="previewLogo(this)">
                </div>
                <div style="background: white; border: 1px solid var(--border-color); padding: 40px;">
                    <div class="form-group mb-4">
                        <label class="label-caps">Nome Comercial / Nome Fantasia</label>
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($unidade['nome']); ?>" class="form-control" required>
                    </div>
                    <div class="form-group mb-4">
                        <label class="label-caps">Razão Social</label>
                        <input type="text" name="razao_social" value="<?php echo htmlspecialchars($unidade['razao_social'] ?? ''); ?>" class="form-control">
                    </div>
                    <div class="form-group mb-4">
                        <label class="label-caps">CNPJ / CPF</label>
                        <input type="text" name="documento" value="<?php echo htmlspecialchars($unidade['documento']); ?>" class="form-control">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="label-caps">Inscrição Estadual</label>
                            <input type="text" name="inscricao_estadual" value="<?php echo htmlspecialchars($unidade['inscricao_estadual'] ?? ''); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="label-caps">Inscrição Municipal</label>
                            <input type="text" name="inscricao_municipal" value="<?php echo htmlspecialchars($unidade['inscricao_municipal'] ?? ''); ?>" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ABA 2: ENDEREÇO & CONTATOS -->
        <div id="tab-endereco" class="config-content">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                <div style="background: white; border: 1px solid var(--border-color); padding: 40px;">
                    <h3 class="label-caps" style="color: #133080; margin-bottom: 25px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">Localização</h3>
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="label-caps">CEP</label>
                            <input type="text" name="cep" id="cep" value="<?php echo htmlspecialchars($unidade['cep']); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="label-caps">Logradouro</label>
                            <input type="text" name="logradouro" id="logradouro" value="<?php echo htmlspecialchars($unidade['logradouro']); ?>" class="form-control">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="label-caps">Número</label>
                            <input type="text" name="numero" value="<?php echo htmlspecialchars($unidade['numero']); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="label-caps">Complemento</label>
                            <input type="text" name="complemento" value="<?php echo htmlspecialchars($unidade['complemento']); ?>" class="form-control">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="label-caps">Bairro</label>
                            <input type="text" name="bairro" id="bairro" value="<?php echo htmlspecialchars($unidade['bairro']); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="label-caps">Cidade</label>
                            <input type="text" name="cidade" id="cidade" value="<?php echo htmlspecialchars($unidade['cidade']); ?>" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="label-caps">Estado (UF)</label>
                        <input type="text" name="estado" id="estado" value="<?php echo htmlspecialchars($unidade['estado']); ?>" class="form-control">
                    </div>
                </div>
                <div style="background: white; border: 1px solid var(--border-color); padding: 40px;">
                    <h3 class="label-caps" style="color: #133080; margin-bottom: 25px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">Canais de Atendimento</h3>
                    <div class="form-group mb-4">
                        <label class="label-caps">Telefone Principal</label>
                        <input type="text" name="telefone" value="<?php echo htmlspecialchars($unidade['telefone']); ?>" class="form-control">
                    </div>
                    <div class="form-group mb-4">
                        <label class="label-caps">WhatsApp Atendimento</label>
                        <input type="text" name="whatsapp" value="<?php echo htmlspecialchars($unidade['whatsapp'] ?? ''); ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="label-caps">E-mail Público</label>
                        <input type="email" name="email_contato" value="<?php echo htmlspecialchars($unidade['email_contato']); ?>" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <!-- ABA 3: REDES SOCIAIS -->
        <div id="tab-digital" class="config-content">
            <div style="background: white; border: 1px solid var(--border-color); padding: 40px; max-width: 800px; margin: 0 auto;">
                <div class="form-group mb-4">
                    <label class="label-caps">Website Oficial</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: #f1f5f9; padding: 12px 15px; border: 1px solid var(--border-color); color: var(--text-muted);"><i class="fa-solid fa-globe"></i></div>
                        <input type="url" name="website" value="<?php echo htmlspecialchars($unidade['website'] ?? ''); ?>" class="form-control" placeholder="https://seusite.com.br">
                    </div>
                </div>
                <div class="form-group mb-4">
                    <label class="label-caps">Instagram (URL Completa)</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: #f1f5f9; padding: 12px 15px; border: 1px solid var(--border-color); color: var(--text-muted);"><i class="fa-brands fa-instagram"></i></div>
                        <input type="url" name="instagram" value="<?php echo htmlspecialchars($unidade['instagram'] ?? ''); ?>" class="form-control" placeholder="https://instagram.com/suaacademia">
                    </div>
                </div>
                <div class="form-group">
                    <label class="label-caps">Facebook (URL Completa)</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: #f1f5f9; padding: 12px 15px; border: 1px solid var(--border-color); color: var(--text-muted);"><i class="fa-brands fa-facebook"></i></div>
                        <input type="url" name="facebook" value="<?php echo htmlspecialchars($unidade['facebook'] ?? ''); ?>" class="form-control" placeholder="https://facebook.com/suaacademia">
                    </div>
                </div>
            </div>
        </div>

        <!-- ABA 4: INTEGRAÇÕES -->
        <div id="tab-integracoes" class="config-content">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                <div class="integration-card">
                    <div class="integration-icon" style="color: #0060df;"><i class="fa-solid fa-credit-card"></i></div>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; color: #1e293b; font-size: 14px;">Asaas (Pagamentos)</h4>
                        <p style="margin: 6px 0 10px 0; font-size: 12px; color: var(--text-muted); font-weight: 500; line-height: 1.4;">
                            A chave de API do Asaas é configurada em uma tela própria (não aqui), que também mostra a URL do webhook de baixa automática.
                        </p>
                        <a href="config_asaas.php" class="btn-sq-outline" style="display: inline-flex; width: auto; padding: 8px 18px; font-size: 11px; text-decoration: none;">
                            <i class="fa-solid fa-arrow-up-right-from-square me-2"></i> Configurar Asaas
                        </a>
                    </div>
                </div>
                <div class="integration-card">
                    <div class="integration-icon" style="color: #ff3333;"><i class="fa-solid fa-building-columns"></i></div>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; color: #1e293b; font-size: 14px;">Cora (Boleto + PIX)</h4>
                        <p style="margin: 6px 0 10px 0; font-size: 12px; color: var(--text-muted); font-weight: 500; line-height: 1.4;">
                            A integração com o Cora usa certificado mTLS e é configurada em uma tela própria, que também mostra a URL do webhook de baixa automática.
                        </p>
                        <a href="config_cora.php" class="btn-sq-outline" style="display: inline-flex; width: auto; padding: 8px 18px; font-size: 11px; text-decoration: none;">
                            <i class="fa-solid fa-arrow-up-right-from-square me-2"></i> Configurar Cora
                        </a>
                    </div>
                </div>
            </div>

            <div style="background: white; border: 1px solid var(--border-color); padding: 40px;">
                <h3 class="label-caps" style="color: #133080; margin-bottom: 25px;">Configuração SMTP (E-mail de Saída)</h3>
                <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="label-caps">SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($unidade['smtp_host'] ?? ''); ?>" class="form-control" placeholder="Ex: smtp.hostinger.com">
                    </div>
                    <div class="form-group">
                        <label class="label-caps">SMTP Usuário (E-mail)</label>
                        <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($unidade['smtp_user'] ?? ''); ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="label-caps">Porta</label>
                        <input type="text" name="smtp_port" value="<?php echo htmlspecialchars($unidade['smtp_port'] ?? ''); ?>" class="form-control" placeholder="465 ou 587">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="label-caps">SMTP Senha</label>
                        <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($unidade['smtp_pass'] ?? ''); ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="label-caps">Segurança</label>
                        <select name="smtp_secure" class="form-control">
                            <option value="ssl" <?php echo ($unidade['smtp_secure'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="tls" <?php echo ($unidade['smtp_secure'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn-sq" style="width: auto; padding: 15px 50px;">Salvar Todas as Configurações</button>
        </div>
    </form>

    <!-- ABA: FAIXAS & GRADUAÇÕES -->
    <div id="tab-faixas" class="config-content">
        <style>
            .grad-card-wrap { display: flex; flex-direction: column; gap: 0; }
            .grad-card {
                display: flex; align-items: center; gap: 16px;
                padding: 16px 20px; background: #fff;
                border: 1px solid var(--border-color); border-top: none;
                cursor: grab; transition: background 0.15s, box-shadow 0.15s;
                user-select: none;
            }
            .grad-card:first-child { border-top: 1px solid var(--border-color); }
            .grad-card:active { cursor: grabbing; }
            .grad-card.sortable-ghost { background: #f0f7ff; opacity: 0.7; }
            .grad-card.sortable-chosen { background: #f8fafc; box-shadow: 0 4px 20px rgba(0,0,0,0.12); z-index: 9999; }
            .grad-drag-handle { color: #cbd5e1; font-size: 18px; flex-shrink: 0; cursor: grab; }
            .grad-drag-handle:hover { color: #08153a; }
            .grad-color-bar { width: 10px; height: 48px; border-radius: 3px; flex-shrink: 0; border: 1px solid rgba(0,0,0,0.08); }
            .grad-info { flex: 1; min-width: 0; }
            .grad-info-name { font-size: 14px; font-weight: 900; text-transform: uppercase; color: #1e293b; }
            .grad-info-meta { font-size: 11px; color: #64748b; font-weight: 600; margin-top: 2px; display: flex; gap: 12px; flex-wrap: wrap; }
            .grad-info-meta span { display: flex; align-items: center; gap: 4px; }
            .grad-actions { display: flex; gap: 8px; flex-shrink: 0; }
            .grad-order-badge { font-size: 11px; font-weight: 900; color: #94a3b8; width: 28px; text-align: center; flex-shrink: 0; }
            
            /* Drawer Form */
            #grad-drawer { 
                background: #f8fafc; border: 1px solid var(--border-color); 
                padding: 30px; margin-bottom: 0; display: none;
                animation: slideDown 0.25s ease;
            }
            @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
            #grad-drawer h4 { font-size: 12px; font-weight: 900; color: #133080; text-transform: uppercase; letter-spacing: 0.07em; margin: 0 0 24px 0; display: flex; align-items: center; gap: 10px; }

            .grad-form-grid { display: grid; grid-template-columns: 1fr 180px 160px 160px; gap: 16px; align-items: end; }
            .grad-form-grid-2 { display: grid; grid-template-columns: auto 1fr; gap: 12px; align-items: center; }
            @media (max-width: 900px) { .grad-form-grid { grid-template-columns: 1fr 1fr; } }
            @media (max-width: 600px) { .grad-form-grid { grid-template-columns: 1fr; } }

            .grad-form-label { font-size: 10px; font-weight: 900; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 6px; }
            .grad-form-input { width: 100%; height: 42px; border: 1px solid var(--border-color); background: #fff; padding: 0 14px; font-size: 13px; font-weight: 700; color: #1e293b; outline: none; transition: border-color 0.2s; }
            .grad-form-input:focus { border-color: #08153a; }
            
            .grad-save-indicator { display: none; position: fixed; bottom: 25px; right: 25px; background: #08153a; color: #a3e635; padding: 12px 22px; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 2px; z-index: 9999; box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
        </style>

        <div style="background: white; border: 1px solid var(--border-color); padding: 30px 30px 0 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h3 style="font-size: 14px; font-weight: 900; color: #133080; text-transform: uppercase; margin: 0;">Faixas & Graduações</h3>
                    <p style="color: var(--text-muted); font-size: 12px; margin: 4px 0 0 0;">Arraste as linhas para reordenar. A ordem é salva automaticamente.</p>
                </div>
                <button type="button" id="btn-add-grad" class="btn-sq" style="width: auto; padding: 10px 22px;" onclick="toggleDrawer()">
                    <i class="fa-solid fa-plus me-2"></i> Adicionar Nova Faixa
                </button>
            </div>

            <!-- Drawer inline -->
            <div id="grad-drawer">
                <h4><i class="fa-solid fa-layer-group"></i> <span id="drawer-title">Adicionar Graduação</span></h4>
                <form method="POST" id="form-grad">
                    <input type="hidden" name="action_graduacao" value="salvar">
                    <input type="hidden" name="id" id="grad-id" value="">

                    <!-- Linha 1: nome + cor + grau + carência -->
                    <div class="grad-form-grid" style="margin-bottom: 16px;">
                        <div>
                            <label class="grad-form-label">Nome da Faixa</label>
                            <input type="text" name="nome" id="grad-nome" class="grad-form-input" required placeholder="Ex: Faixa Azul">
                        </div>
                        <div>
                            <label class="grad-form-label">Cor Visual</label>
                            <div class="grad-form-grid-2">
                                <input type="color" name="cor_hex" id="grad-cor-color" 
                                    style="width: 42px; height: 42px; padding: 2px; cursor: pointer; border: 1px solid var(--border-color); background: #fff; flex-shrink:0;"
                                    value="#000000" oninput="document.getElementById('grad-cor-text').value = this.value">
                                <input type="text" id="grad-cor-text" class="grad-form-input" 
                                    style="font-family: monospace; font-size: 12px;"
                                    value="#000000" placeholder="#000000" 
                                    oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)) document.getElementById('grad-cor-color').value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="grad-form-label">Grau / Nível</label>
                            <input type="text" name="grau" id="grad-grau" class="grad-form-input" placeholder="Ex: 1º Grau">
                        </div>
                        <div>
                            <label class="grad-form-label">Carência (Meses)</label>
                            <input type="number" name="carencia_meses" id="grad-carencia" class="grad-form-input" min="0" value="0" placeholder="0">
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="btn-sq-outline" onclick="fecharDrawer()" style="width: auto; padding: 9px 20px; font-size: 12px;">Cancelar</button>
                        <button type="submit" class="btn-sq" style="width: auto; padding: 9px 28px; font-size: 12px;"><i class="fa-solid fa-floppy-disk me-2"></i>Salvar Faixa</button>
                    </div>
                </form>
            </div>

            <!-- Lista Drag-and-drop -->
            <?php if (empty($graduacoes)): ?>
                <div style="padding: 50px 0; text-align: center; color: var(--text-muted); border-top: 1px solid var(--border-color); margin-top: 0;">
                    <i class="fa-solid fa-layer-group" style="font-size: 32px; opacity: 0.15; display: block; margin-bottom: 12px;"></i>
                    <p style="font-weight: 800; text-transform: uppercase; font-size: 12px;">Nenhuma graduação cadastrada ainda.</p>
                </div>
            <?php else: ?>
                <div id="sortable-grads" class="grad-card-wrap" style="border-top: 1px solid var(--border-color); margin-top: 24px;">
                    <?php foreach ($graduacoes as $i => $g):
                        $g_avaliacoes = $avaliacoes_por_grad[$g['id']] ?? [];
                        $cl_count = 0;
                        foreach ($g_avaliacoes as $av) {
                            foreach (($checklist_topicos[$av['id']] ?? []) as $tp) {
                                $cl_count += count($tp['itens']);
                            }
                        }
                    ?>
                        <!-- Card principal da faixa -->
                        <div class="grad-card" data-id="<?php echo $g['id']; ?>" style="flex-wrap: wrap;">
                            <i class="fa-solid fa-grip-vertical grad-drag-handle"></i>
                            <span class="grad-order-badge"><?php echo ($i + 1); ?></span>
                            <div class="grad-color-bar" style="background: <?php echo htmlspecialchars($g['cor_hex']); ?>;" title="<?php echo htmlspecialchars($g['cor_hex']); ?>"></div>
                            <div class="grad-info">
                                <div class="grad-info-name">
                                    <?php echo htmlspecialchars($g['nome']); ?>
                                    <?php if ($g['grau']): ?>
                                        <span style="font-weight: 600; color: #64748b; font-size: 11px; text-transform: none;"> — <?php echo htmlspecialchars($g['grau']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="grad-info-meta">
                                    <span><i class="fa-solid fa-palette" style="color: <?php echo htmlspecialchars($g['cor_hex']); ?>;"></i><?php echo htmlspecialchars($g['cor_hex']); ?></span>
                                    <?php if ((int)$g['carencia_meses'] > 0): ?>
                                        <span><i class="fa-regular fa-clock"></i>Carência: <?php echo $g['carencia_meses']; ?> meses</span>
                                    <?php endif; ?>
                                    <span style="color: #6366f1; font-weight: 700;">
                                        <i class="fa-solid fa-list-check"></i>
                                        <span class="cl-count-<?php echo $g['id']; ?>"><?php echo $cl_count; ?></span> <?php echo $cl_count === 1 ? 'item' : 'itens'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="grad-actions">
                                <button type="button" title="Checklist de Avaliação"
                                    style="background:#f0f0ff; border:1px solid #c7d2fe; padding: 7px 12px; cursor: pointer; color: #4f46e5; font-size: 13px; display:flex; align-items:center; gap:6px;"
                                    onclick="toggleChecklist(<?php echo $g['id']; ?>)">
                                    <i class="fa-solid fa-list-check"></i>
                                    <span style="font-size:11px; font-weight:800;">CHECKLIST</span>
                                </button>
                                <button type="button" title="Editar" style="background:#f1f5f9; border:1px solid var(--border-color); padding: 7px 12px; cursor: pointer; color: #08153a; font-size: 13px;"
                                    onclick="editarGraduacao(<?php echo htmlspecialchars(json_encode($g)); ?>)">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" title="Excluir" style="background:#fff0f0; border:1px solid #fecaca; padding: 7px 12px; cursor: pointer; color: #ef4444; font-size: 13px;"
                                    onclick="excluirGraduacao(<?php echo $g['id']; ?>)">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Painel Checklist (expansível) -->
                        <div id="checklist-panel-<?php echo $g['id']; ?>"
                             style="display:none; border-left: 4px solid #6366f1; background: #fafbff; border-bottom: 1px solid #e0e7ff; padding: 20px 24px 20px 24px; margin: 0;">

                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <span style="font-size:11px; font-weight:900; text-transform:uppercase; color:#4f46e5; letter-spacing:0.07em;">
                                        <i class="fa-solid fa-list-check" style="margin-right:6px;"></i>
                                        Checklist de Avaliação — <?php echo htmlspecialchars($g['nome']); ?>
                                    </span>
                                    <p style="font-size:11px; color:#94a3b8; margin:3px 0 0 0; font-weight:500;">
                                        Defina os tópicos (nomes das listas) e os itens avaliados nesta graduação.
                                    </p>
                                </div>
                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <!-- Duplicar Checklist -->
                                    <div style="display:inline-flex; align-items:center; background:#f1f5f9; border:1px solid #cbd5e1; padding:3px 8px; gap:6px;">
                                        <span style="font-size:10px; font-weight:800; color:#475569;">COPIAR DE:</span>
                                        <select id="cl-duplicar-origem-<?php echo $g['id']; ?>" style="font-size:11px; font-weight:700; height:26px; border:1px solid #cbd5e1; background:#fff; color:#334155; outline:none; padding:0 5px;">
                                            <option value="">(Selecione a faixa origem)</option>
                                            <?php foreach ($graduacoes as $orig): 
                                                if ($orig['id'] == $g['id']) continue; 
                                            ?>
                                                <option value="<?php echo $orig['id']; ?>"><?php echo htmlspecialchars($orig['nome']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" 
                                                onclick="duplicarChecklist(<?php echo $g['id']; ?>)"
                                                style="background:#08153a; color:#fff; border:none; padding:5px 10px; font-size:10px; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">
                                            <i class="fa-solid fa-clone"></i> Copiar
                                        </button>
                                    </div>

                                    <button type="button"
                                        style="background:#08153a; color:#fff; border:none; padding:8px 18px; font-size:12px; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:7px;"
                                        onclick="abrirAddAvaliacao(<?php echo $g['id']; ?>)">
                                        <i class="fa-solid fa-plus"></i> Nova Avaliação (Prova)
                                    </button>
                                </div>
                            </div>

                            <!-- Formulário inline para criação/edição de AVALIAÇÃO (Prova 1, Prova 2...) -->
                            <div id="cl-aval-form-<?php echo $g['id']; ?>" style="display:none; margin-bottom:20px; background:#fff; border:1px solid #cbd5e1; padding:14px;">
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="hidden" id="cl-aval-edit-id-<?php echo $g['id']; ?>" value="">
                                    <input type="text" id="cl-aval-input-<?php echo $g['id']; ?>"
                                        placeholder="Nome da Avaliação (Ex: Prova 1, Prova Teórica, Prova Prática...)"
                                        style="flex:1; height:38px; border:1px solid #cbd5e1; padding:0 12px; font-size:13px; font-weight:600; color:#1e293b; outline:none; border-radius:0;"
                                        onkeydown="if(event.key==='Enter'){salvarAvaliacao(<?php echo $g['id']; ?>);}">
                                    <button type="button"
                                        style="background:#08153a; color:#fff; border:none; padding:0 18px; height:38px; font-size:12px; font-weight:800; cursor:pointer;"
                                        onclick="salvarAvaliacao(<?php echo $g['id']; ?>)">
                                        <i class="fa-solid fa-check"></i> Salvar Avaliação
                                    </button>
                                    <button type="button"
                                        style="background:#f1f5f9; color:#64748b; border:1px solid var(--border-color); padding:0 14px; height:38px; font-size:12px; font-weight:800; cursor:pointer;"
                                        onclick="fecharFormAvaliacao(<?php echo $g['id']; ?>)">
                                        Cancelar
                                    </button>
                                </div>
                            </div>

                            <!-- Lista de avaliações (Prova 1, Prova 2...), cada uma com seus tópicos/itens (sortable) -->
                            <div id="cl-avals-list-<?php echo $g['id']; ?>" data-gradid="<?php echo $g['id']; ?>" style="display:flex; flex-direction:column; gap:20px;">
                                <?php if (empty($g_avaliacoes)): ?>
                                    <div id="cl-avals-empty-<?php echo $g['id']; ?>"
                                        style="padding:30px; text-align:center; color:#94a3b8; font-size:12px; font-weight:700; border:1px dashed #c7d2fe; background:#f8f9ff;">
                                        <i class="fa-solid fa-clipboard-list" style="font-size:24px; opacity:0.15; display:block; margin-bottom:8px;"></i>
                                        Nenhuma avaliação cadastrada ainda. Clique em "Nova Avaliação (Prova)".
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($g_avaliacoes as $av):
                                        $av_topicos = $checklist_topicos[$av['id']] ?? [];
                                    ?>
                                    <div id="cl-aval-card-<?php echo $av['id']; ?>" data-id="<?php echo $av['id']; ?>" data-gradid="<?php echo $g['id']; ?>" style="background:#f8f9ff; border:1px solid #c7d2fe; padding:16px;">
                                        <!-- Cabeçalho da Avaliação -->
                                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <i class="fa-solid fa-grip-vertical aval-drag-handle" style="color:#a5b4fc; cursor:grab; font-size:16px;"></i>
                                                <strong class="aval-name" style="font-size:13px; font-weight:900; color:#133080; text-transform:uppercase;">
                                                    <i class="fa-solid fa-clipboard-check" style="color:#4f46e5; margin-right:4px;"></i> <?php echo htmlspecialchars($av['nome']); ?>
                                                </strong>
                                            </div>
                                            <div style="display:flex; gap:6px;">
                                                <button type="button"
                                                    style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:4px 10px; font-size:11px; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:4px;"
                                                    onclick="abrirAddTopico(<?php echo $av['id']; ?>)">
                                                    <i class="fa-solid fa-folder-plus"></i> Tópico
                                                </button>
                                                <button type="button"
                                                    style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; padding:4px 8px; font-size:11px; cursor:pointer;"
                                                    onclick="editarAvaliacao(<?php echo $g['id']; ?>, <?php echo $av['id']; ?>, <?php echo htmlspecialchars(json_encode($av['nome'])); ?>)">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button type="button"
                                                    style="background:#fff0f0; border:1px solid #fecaca; color:#ef4444; padding:4px 8px; font-size:11px; cursor:pointer;"
                                                    onclick="excluirAvaliacao(<?php echo $av['id']; ?>, <?php echo $g['id']; ?>)">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Formulário inline para criação/edição de TÓPICO desta avaliação -->
                                        <div id="cl-topic-form-<?php echo $av['id']; ?>" style="display:none; margin-bottom:16px; background:#fff; border:1px solid #c7d2fe; padding:14px;">
                                            <div style="display:flex; gap:10px; align-items:center;">
                                                <input type="hidden" id="cl-topic-edit-id-<?php echo $av['id']; ?>" value="">
                                                <input type="text" id="cl-topic-input-<?php echo $av['id']; ?>"
                                                    placeholder="Nome do Checklist / Tópico (Ex: Golpes de Projeção, Técnicas de Solo...)"
                                                    style="flex:1; height:38px; border:1px solid #c7d2fe; padding:0 12px; font-size:13px; font-weight:600; color:#1e293b; outline:none; border-radius:0;"
                                                    onkeydown="if(event.key==='Enter'){salvarTopico(<?php echo $av['id']; ?>);}">
                                                <button type="button"
                                                    style="background:#6366f1; color:#fff; border:none; padding:0 18px; height:38px; font-size:12px; font-weight:800; cursor:pointer;"
                                                    onclick="salvarTopico(<?php echo $av['id']; ?>)">
                                                    <i class="fa-solid fa-check"></i> Salvar Tópico
                                                </button>
                                                <button type="button"
                                                    style="background:#f1f5f9; color:#64748b; border:1px solid var(--border-color); padding:0 14px; height:38px; font-size:12px; font-weight:800; cursor:pointer;"
                                                    onclick="fecharFormTopico(<?php echo $av['id']; ?>)">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Lista de tópicos desta avaliação (sortable) -->
                                        <div id="cl-topics-list-<?php echo $av['id']; ?>" data-gradid="<?php echo $g['id']; ?>" style="display:flex; flex-direction:column; gap:16px;">
                                            <?php if (empty($av_topicos)): ?>
                                                <div id="cl-topics-empty-<?php echo $av['id']; ?>"
                                                    style="padding:20px; text-align:center; color:#94a3b8; font-size:12px; font-weight:700; border:1px dashed #c7d2fe; background:#fff;">
                                                    Nenhum tópico cadastrado nesta avaliação ainda.
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($av_topicos as $tp): ?>
                                                    <div id="cl-topic-card-<?php echo $tp['id']; ?>" data-id="<?php echo $tp['id']; ?>" style="background:#fff; border:1px solid #cbd5e1; padding:15px; display:flex; flex-direction:column; gap:10px;">
                                                        <!-- Cabeçalho do Tópico -->
                                                        <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                                                            <div style="display:flex; align-items:center; gap:8px;">
                                                                <i class="fa-solid fa-grip-vertical topic-drag-handle" style="color:#cbd5e1; cursor:grab; font-size:14px;"></i>
                                                                <strong class="topic-name" style="font-size:12px; font-weight:900; color:#1e293b; text-transform:uppercase;">
                                                                    <i class="fa-regular fa-folder-open" style="color:#6366f1; margin-right:4px;"></i> <?php echo htmlspecialchars($tp['nome']); ?>
                                                                </strong>
                                                            </div>
                                                            <div style="display:flex; gap:6px;">
                                                                <button type="button"
                                                                    style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:4px 10px; font-size:11px; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:4px;"
                                                                    onclick="abrirAddItem(<?php echo $tp['id']; ?>)">
                                                                    <i class="fa-solid fa-plus"></i> Item
                                                                </button>
                                                                <button type="button"
                                                                    style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; padding:4px 8px; font-size:11px; cursor:pointer;"
                                                                    onclick="editarTopico(<?php echo $av['id']; ?>, <?php echo $tp['id']; ?>, <?php echo htmlspecialchars(json_encode($tp['nome'])); ?>)">
                                                                    <i class="fa-solid fa-pen"></i>
                                                                </button>
                                                                <button type="button"
                                                                    style="background:#fff0f0; border:1px solid #fecaca; color:#ef4444; padding:4px 8px; font-size:11px; cursor:pointer;"
                                                                    onclick="excluirTopico(<?php echo $tp['id']; ?>, <?php echo $av['id']; ?>)">
                                                                    <i class="fa-solid fa-trash-can"></i>
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <!-- Formulário inline para criação/edição de ITEM no tópico -->
                                                        <div id="cl-item-form-<?php echo $tp['id']; ?>" style="display:none; background:#f8fafc; border:1px dashed #cbd5e1; padding:10px; margin-bottom:6px;">
                                                            <div style="display:flex; gap:10px; align-items:center;">
                                                                <input type="hidden" id="cl-item-edit-id-<?php echo $tp['id']; ?>" value="">
                                                                <input type="text" id="cl-item-input-<?php echo $tp['id']; ?>"
                                                                    placeholder="Descreva o item de avaliação..."
                                                                    style="flex:1; height:32px; border:1px solid #cbd5e1; padding:0 10px; font-size:12px; font-weight:600; color:#1e293b; outline:none; border-radius:0;"
                                                                    onkeydown="if(event.key==='Enter'){salvarItem(<?php echo $tp['id']; ?>, <?php echo $g['id']; ?>);}">
                                                                <button type="button"
                                                                    style="background:#6366f1; color:#fff; border:none; padding:0 12px; height:32px; font-size:11px; font-weight:800; cursor:pointer;"
                                                                    onclick="salvarItem(<?php echo $tp['id']; ?>, <?php echo $g['id']; ?>)">
                                                                    Salvar
                                                                </button>
                                                                <button type="button"
                                                                    style="background:#e2e8f0; color:#475569; border:none; padding:0 10px; height:32px; font-size:11px; font-weight:800; cursor:pointer;"
                                                                    onclick="fecharFormItem(<?php echo $tp['id']; ?>)">
                                                                    Cancelar
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <!-- Lista de itens do Tópico -->
                                                        <ul id="cl-items-list-<?php echo $tp['id']; ?>" style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:4px;">
                                                            <?php if (empty($tp['itens'])): ?>
                                                                <li id="cl-items-empty-<?php echo $tp['id']; ?>" style="padding:12px; text-align:center; color:#94a3b8; font-size:11px; font-weight:600; border:1px dashed #cbd5e1; background:#fafafa;">
                                                                    Nenhum item cadastrado neste tópico ainda.
                                                                </li>
                                                            <?php else: ?>
                                                                <?php foreach ($tp['itens'] as $itm_idx => $itm): ?>
                                                                    <li id="cl-item-<?php echo $itm['id']; ?>" data-id="<?php echo $itm['id']; ?>" style="display:flex; align-items:center; gap:8px; padding:6px 10px; background:#fafafa; border:1px solid #e2e8f0; cursor:grab;" class="cl-item">
                                                                        <i class="fa-solid fa-grip-vertical item-drag-handle" style="color:#cbd5e1; font-size:12px; cursor:grab;"></i>
                                                                        <span style="font-size:11px; font-weight:800; color:#94a3b8; width:15px; text-align:center;"><?php echo ($itm_idx + 1); ?></span>
                                                                        <i class="fa-regular fa-circle-check" style="color:#6366f1; font-size:13px;"></i>
                                                                        <span class="item-desc" style="flex:1; font-size:12px; font-weight:600; color:#334155;"><?php echo htmlspecialchars($itm['descricao']); ?></span>
                                                                        <div style="display:flex; gap:4px;">
                                                                            <button type="button"
                                                                                style="background:none; border:none; color:#4f46e5; cursor:pointer; font-size:12px; padding:4px;"
                                                                                onclick="editarItem(<?php echo $tp['id']; ?>, <?php echo $itm['id']; ?>, <?php echo htmlspecialchars(json_encode($itm['descricao'])); ?>)">
                                                                                <i class="fa-solid fa-pen"></i>
                                                                            </button>
                                                                            <button type="button"
                                                                                style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:12px; padding:4px;"
                                                                                onclick="excluirItem(<?php echo $itm['id']; ?>, <?php echo $tp['id']; ?>, <?php echo $g['id']; ?>)">
                                                                                <i class="fa-solid fa-trash-can"></i>
                                                                            </button>
                                                                        </div>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Padding bottom -->
            <div style="height: 30px;"></div>
        </div>
    </div>

    <!-- Form Excluir -->
    <form id="form-excluir-grad" method="POST" style="display:none;">
        <input type="hidden" name="action_graduacao" value="excluir">
        <input type="hidden" name="id" id="excluir-grad-id">
    </form>

    <!-- Indicador de salvamento -->
    <div id="grad-save-indicator" class="grad-save-indicator">
        <i class="fa-solid fa-check me-2"></i> Ordem salva!
    </div>
</section>

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
function showTab(tabId) {
    document.querySelectorAll('.config-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.config-tab-item').forEach(t => t.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');

    if (window.event && window.event.currentTarget && window.event.currentTarget.classList) {
        window.event.currentTarget.classList.add('active');
    } else {
        document.querySelectorAll('.config-tab-item').forEach(t => {
            if (t.getAttribute('onclick') && t.getAttribute('onclick').includes(tabId)) {
                t.classList.add('active');
            }
        });
    }

    const tabAtivoInput = document.getElementById('tab_ativo');
    if (tabAtivoInput) tabAtivoInput.value = tabId.replace('tab-', '');
}

// ── Drawer (inline form) ──────────────────────────────────────────────────
function toggleDrawer(forceOpen) {
    const drawer = document.getElementById('grad-drawer');
    const isVisible = drawer.style.display !== 'none';
    if (forceOpen === true || !isVisible) {
        drawer.style.display = 'block';
        drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        fecharDrawer();
    }
}

function fecharDrawer() {
    document.getElementById('grad-drawer').style.display = 'none';
    limparFormGrad();
}

function limparFormGrad() {
    document.getElementById('drawer-title').innerText = 'Adicionar Graduação';
    document.getElementById('btn-add-grad').innerHTML = '<i class="fa-solid fa-plus me-2"></i> Adicionar Nova Faixa';
    document.getElementById('grad-id').value = '';
    document.getElementById('grad-nome').value = '';
    document.getElementById('grad-cor-color').value = '#000000';
    document.getElementById('grad-cor-text').value = '#000000';
    document.getElementById('grad-grau').value = '';
    document.getElementById('grad-carencia').value = '0';
}

function abrirModalGraduacao() { toggleDrawer(true); limparFormGrad(); }
function fecharModalGraduacao() { fecharDrawer(); }

function editarGraduacao(g) {
    document.getElementById('drawer-title').innerText = 'Editar Graduação';
    document.getElementById('btn-add-grad').innerHTML = '<i class="fa-solid fa-plus me-2"></i> Adicionar Nova Faixa';
    document.getElementById('grad-id').value = g.id;
    document.getElementById('grad-nome').value = g.nome;
    document.getElementById('grad-cor-color').value = g.cor_hex || '#000000';
    document.getElementById('grad-cor-text').value = g.cor_hex || '#000000';
    document.getElementById('grad-grau').value = g.grau || '';
    document.getElementById('grad-carencia').value = g.carencia_meses || 0;
    toggleDrawer(true);
    document.getElementById('grad-drawer').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function excluirGraduacao(id) {
    if (confirm('Deseja realmente excluir esta graduação? Esta ação não poderá ser desfeita.')) {
        document.getElementById('excluir-grad-id').value = id;
        document.getElementById('form-excluir-grad').submit();
    }
}

// ── Drag-and-drop SortableJS ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const sortableEl = document.getElementById('sortable-grads');
    
    if (sortableEl) {
        Sortable.create(sortableEl, {
            handle: '.grad-drag-handle',
            animation: 180,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: function () {
                // Atualizar badges de ordem
                sortableEl.querySelectorAll('.grad-card').forEach((card, idx) => {
                    const badge = card.querySelector('.grad-order-badge');
                    if (badge) badge.textContent = idx + 1;
                });
                salvarOrdem();
            }
        });
    }
    
    // Aba via URL (preserva a aba ativa depois do redirect de salvar)
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam && document.getElementById('tab-' + tabParam)) {
        showTab('tab-' + tabParam);
    }
});

function salvarOrdem() {
    const cards = document.querySelectorAll('#sortable-grads .grad-card');
    const ids = Array.from(cards).map(c => c.dataset.id);
    
    const fd = new FormData();
    fd.append('action_graduacao', 'reordenar');
    fd.append('ordem_ids', JSON.stringify(ids));
    
    fetch('configuracoes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const ind = document.getElementById('grad-save-indicator');
                ind.style.display = 'block';
                setTimeout(() => ind.style.display = 'none', 2000);
            }
        })
        .catch(() => {});
}

// ── CHECKLIST (TÓPICOS E ITENS) ──────────────────────────────────────────────

function toggleChecklist(gradId) {
    const panel = document.getElementById('checklist-panel-' + gradId);
    const isOpen = panel.style.display !== 'none';
    panel.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// TÓPICOS
function abrirAddTopico(gradId) {
    const form = document.getElementById('cl-topic-form-' + gradId);
    document.getElementById('cl-topic-edit-id-' + gradId).value = '';
    document.getElementById('cl-topic-input-' + gradId).value = '';
    form.style.display = 'block';
    document.getElementById('cl-topic-input-' + gradId).focus();
}

function duplicarChecklist(paraGradId) {
    const origSelect = document.getElementById('cl-duplicar-origem-' + paraGradId);
    if (!origSelect) return;
    const deGradId = origSelect.value;
    if (!deGradId) {
        alert('Selecione uma faixa de origem para copiar o checklist.');
        return;
    }

    const deNome = origSelect.options[origSelect.selectedIndex].text;
    if (!confirm('Deseja realmente copiar o checklist de "' + deNome + '"? Isso apagará o checklist atual desta faixa e criará uma cópia idêntica.')) {
        return;
    }

    const fd = new FormData();
    fd.append('action_checklist', 'duplicar_checklist');
    fd.append('de_graduacao_id', deGradId);
    fd.append('para_graduacao_id', paraGradId);

    fetch('configuracoes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) {
                alert(data.erro || 'Erro ao copiar o checklist.');
                return;
            }
            alert('Checklist copiado com sucesso!');
            window.location.reload();
        })
        .catch(err => {
            console.error(err);
            alert('Erro de conexão ao duplicar checklist.');
        });
}

function fecharFormTopico(gradId) {
    document.getElementById('cl-topic-form-' + gradId).style.display = 'none';
    document.getElementById('cl-topic-edit-id-' + gradId).value = '';
    document.getElementById('cl-topic-input-' + gradId).value = '';
}

function salvarTopico(avaliacaoId) {
    const editId = document.getElementById('cl-topic-edit-id-' + avaliacaoId).value;
    const inputEl = document.getElementById('cl-topic-input-' + avaliacaoId);
    const nome = inputEl.value.trim();
    if (!nome) { inputEl.focus(); return; }

    const fd = new FormData();
    if (editId) {
        fd.append('action_checklist', 'editar_topico');
        fd.append('topico_id', editId);
    } else {
        fd.append('action_checklist', 'adicionar_topico');
        fd.append('avaliacao_id', avaliacaoId);
    }
    fd.append('nome', nome);

    fetch('configuracoes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { alert(data.erro || 'Erro ao salvar tópico.'); return; }

            const list = document.getElementById('cl-topics-list-' + avaliacaoId);
            const gradId = list.dataset.gradid;
            const emptyEl = document.getElementById('cl-topics-empty-' + avaliacaoId);
            if (emptyEl) emptyEl.remove();

            if (editId) {
                const card = document.getElementById('cl-topic-card-' + editId);
                if (card) {
                    card.querySelector('.topic-name').innerHTML = `<i class="fa-regular fa-folder-open" style="color:#6366f1; margin-right:4px;"></i> ` + escapeHtml(data.nome);
                }
            } else {
                const div = document.createElement('div');
                div.id = 'cl-topic-card-' + data.id;
                div.dataset.id = data.id;
                div.style.cssText = 'background:#fff; border:1px solid #cbd5e1; padding:15px; display:flex; flex-direction:column; gap:10px;';
                div.innerHTML = `
                    <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-grip-vertical topic-drag-handle" style="color:#cbd5e1; cursor:grab; font-size:14px;"></i>
                            <strong class="topic-name" style="font-size:12px; font-weight:900; color:#1e293b; text-transform:uppercase;">
                                <i class="fa-regular fa-folder-open" style="color:#6366f1; margin-right:4px;"></i> ${escapeHtml(data.nome)}
                            </strong>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button"
                                style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:4px 10px; font-size:11px; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:4px;"
                                onclick="abrirAddItem(${data.id})">
                                <i class="fa-solid fa-plus"></i> Item
                            </button>
                            <button type="button"
                                style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; padding:4px 8px; font-size:11px; cursor:pointer;"
                                onclick="editarTopico(${avaliacaoId}, ${data.id}, ${JSON.stringify(data.nome)})">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button"
                                style="background:#fff0f0; border:1px solid #fecaca; color:#ef4444; padding:4px 8px; font-size:11px; cursor:pointer;"
                                onclick="excluirTopico(${data.id}, ${avaliacaoId})">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                    <div id="cl-item-form-${data.id}" style="display:none; background:#f8fafc; border:1px dashed #cbd5e1; padding:10px; margin-bottom:6px;">
                        <div style="display:flex; gap:10px; align-items:center;">
                            <input type="hidden" id="cl-item-edit-id-${data.id}" value="">
                            <input type="text" id="cl-item-input-${data.id}"
                                placeholder="Descreva o item de avaliação..."
                                style="flex:1; height:32px; border:1px solid #cbd5e1; padding:0 10px; font-size:12px; font-weight:600; color:#1e293b; outline:none; border-radius:0;"
                                onkeydown="if(event.key==='Enter'){salvarItem(${data.id}, ${gradId});}">
                            <button type="button"
                                style="background:#6366f1; color:#fff; border:none; padding:0 12px; height:32px; font-size:11px; font-weight:800; cursor:pointer;"
                                onclick="salvarItem(${data.id}, ${gradId})">
                                Salvar
                            </button>
                            <button type="button"
                                style="background:#e2e8f0; color:#475569; border:none; padding:0 10px; height:32px; font-size:11px; font-weight:800; cursor:pointer;"
                                onclick="fecharFormItem(${data.id})">
                                Cancelar
                            </button>
                        </div>
                    </div>
                    <ul id="cl-items-list-${data.id}" style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:4px;">
                        <li id="cl-items-empty-${data.id}" style="padding:12px; text-align:center; color:#94a3b8; font-size:11px; font-weight:600; border:1px dashed #cbd5e1; background:#fafafa;">
                            Nenhum item cadastrado neste tópico ainda.
                        </li>
                    </ul>
                `;
                list.appendChild(div);

                // Inicializar Sortable para o novo tópico e sua lista de itens
                inicializarSortableItens(data.id, gradId);
            }
            fecharFormTopico(avaliacaoId);
        })
        .catch(() => alert('Erro de conexão.'));
}

function editarTopico(avaliacaoId, topicoId, nome) {
    const form = document.getElementById('cl-topic-form-' + avaliacaoId);
    document.getElementById('cl-topic-edit-id-' + avaliacaoId).value = topicoId;
    document.getElementById('cl-topic-input-' + avaliacaoId).value = nome;
    form.style.display = 'block';
    document.getElementById('cl-topic-input-' + avaliacaoId).focus();
}

function excluirTopico(topicoId, avaliacaoId) {
    if (!confirm('Deseja realmente remover este tópico e todos os seus itens?')) return;
    const fd = new FormData();
    fd.append('action_checklist', 'excluir_topico');
    fd.append('topico_id', topicoId);

    fetch('configuracoes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { alert('Erro ao excluir.'); return; }
            const list = document.getElementById('cl-topics-list-' + avaliacaoId);
            const gradId = list.dataset.gradid;
            document.getElementById('cl-topic-card-' + topicoId).remove();
            atualizarContador(gradId);

            if (list.children.length === 0) {
                const emptyDiv = document.createElement('div');
                emptyDiv.id = 'cl-topics-empty-' + avaliacaoId;
                emptyDiv.style.cssText = 'padding:20px; text-align:center; color:#94a3b8; font-size:12px; font-weight:700; border:1px dashed #c7d2fe; background:#fff;';
                emptyDiv.textContent = 'Nenhum tópico cadastrado nesta avaliação ainda.';
                list.appendChild(emptyDiv);
            }
        })
        .catch(() => alert('Erro de conexão.'));
}

// AVALIAÇÕES (Prova 1, Prova 2...)
function abrirAddAvaliacao(gradId) {
    const form = document.getElementById('cl-aval-form-' + gradId);
    document.getElementById('cl-aval-edit-id-' + gradId).value = '';
    document.getElementById('cl-aval-input-' + gradId).value = '';
    form.style.display = 'block';
    document.getElementById('cl-aval-input-' + gradId).focus();
}

function fecharFormAvaliacao(gradId) {
    document.getElementById('cl-aval-form-' + gradId).style.display = 'none';
    document.getElementById('cl-aval-edit-id-' + gradId).value = '';
    document.getElementById('cl-aval-input-' + gradId).value = '';
}

function salvarAvaliacao(gradId) {
    const editId = document.getElementById('cl-aval-edit-id-' + gradId).value;
    const inputEl = document.getElementById('cl-aval-input-' + gradId);
    const nome = inputEl.value.trim();
    if (!nome) { inputEl.focus(); return; }

    const fd = new FormData();
    if (editId) {
        fd.append('action_checklist', 'editar_avaliacao');
        fd.append('avaliacao_id', editId);
    } else {
        fd.append('action_checklist', 'adicionar_avaliacao');
        fd.append('graduacao_id', gradId);
    }
    fd.append('nome', nome);

    fetch('configuracoes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { alert(data.erro || 'Erro ao salvar avaliação.'); return; }

            const list = document.getElementById('cl-avals-list-' + gradId);
            const emptyEl = document.getElementById('cl-avals-empty-' + gradId);
            if (emptyEl) emptyEl.remove();

            if (editId) {
                const card = document.getElementById('cl-aval-card-' + editId);
                if (card) {
                    card.querySelector('.aval-name').innerHTML = `<i class="fa-solid fa-clipboard-check" style="color:#4f46e5; margin-right:4px;"></i> ` + escapeHtml(data.nome);
                }
            } else {
                const div = document.createElement('div');
                div.id = 'cl-aval-card-' + data.id;
                div.dataset.id = data.id;
                div.dataset.gradid = gradId;
                div.style.cssText = 'background:#f8f9ff; border:1px solid #c7d2fe; padding:16px;';
                div.innerHTML = `
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-grip-vertical aval-drag-handle" style="color:#a5b4fc; cursor:grab; font-size:16px;"></i>
                            <strong class="aval-name" style="font-size:13px; font-weight:900; color:#133080; text-transform:uppercase;">
                                <i class="fa-solid fa-clipboard-check" style="color:#4f46e5; margin-right:4px;"></i> ${escapeHtml(data.nome)}
                            </strong>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button"
                                style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:4px 10px; font-size:11px; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:4px;"
                                onclick="abrirAddTopico(${data.id})">
                                <i class="fa-solid fa-folder-plus"></i> Tópico
                            </button>
                            <button type="button"
                                style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; padding:4px 8px; font-size:11px; cursor:pointer;"
                                onclick="editarAvaliacao(${gradId}, ${data.id}, ${JSON.stringify(data.nome)})">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button"
                                style="background:#fff0f0; border:1px solid #fecaca; color:#ef4444; padding:4px 8px; font-size:11px; cursor:pointer;"
                                onclick="excluirAvaliacao(${data.id}, ${gradId})">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                    <div id="cl-topic-form-${data.id}" style="display:none; margin-bottom:16px; background:#fff; border:1px solid #c7d2fe; padding:14px;">
                        <div style="display:flex; gap:10px; align-items:center;">
                            <input type="hidden" id="cl-topic-edit-id-${data.id}" value="">
                            <input type="text" id="cl-topic-input-${data.id}"
                                placeholder="Nome do Checklist / Tópico (Ex: Golpes de Projeção, Técnicas de Solo...)"
                                style="flex:1; height:38px; border:1px solid #c7d2fe; padding:0 12px; font-size:13px; font-weight:600; color:#1e293b; outline:none; border-radius:0;"
                                onkeydown="if(event.key==='Enter'){salvarTopico(${data.id});}">
                            <button type="button"
                                style="background:#6366f1; color:#fff; border:none; padding:0 18px; height:38px; font-size:12px; font-weight:800; cursor:pointer;"
                                onclick="salvarTopico(${data.id})">
                                <i class="fa-solid fa-check"></i> Salvar Tópico
                            </button>
                            <button type="button"
                                style="background:#f1f5f9; color:#64748b; border:1px solid var(--border-color); padding:0 14px; height:38px; font-size:12px; font-weight:800; cursor:pointer;"
                                onclick="fecharFormTopico(${data.id})">
                                Cancelar
                            </button>
                        </div>
                    </div>
                    <div id="cl-topics-list-${data.id}" data-gradid="${gradId}" style="display:flex; flex-direction:column; gap:16px;">
                        <div id="cl-topics-empty-${data.id}" style="padding:20px; text-align:center; color:#94a3b8; font-size:12px; font-weight:700; border:1px dashed #c7d2fe; background:#fff;">
                            Nenhum tópico cadastrado nesta avaliação ainda.
                        </div>
                    </div>
                `;
                list.appendChild(div);

                // Inicializar Sortable para os tópicos desta nova avaliação
                Sortable.create(document.getElementById('cl-topics-list-' + data.id), {
                    handle: '.topic-drag-handle',
                    animation: 150,
                    onEnd: function() { salvarOrdemTopicos(data.id); }
                });
            }
            fecharFormAvaliacao(gradId);
        })
        .catch(() => alert('Erro de conexão.'));
}

function editarAvaliacao(gradId, avaliacaoId, nome) {
    const form = document.getElementById('cl-aval-form-' + gradId);
    document.getElementById('cl-aval-edit-id-' + gradId).value = avaliacaoId;
    document.getElementById('cl-aval-input-' + gradId).value = nome;
    form.style.display = 'block';
    document.getElementById('cl-aval-input-' + gradId).focus();
}

function excluirAvaliacao(avaliacaoId, gradId) {
    if (!confirm('Deseja realmente remover esta avaliação, com todos os seus tópicos e itens?')) return;
    const fd = new FormData();
    fd.append('action_checklist', 'excluir_avaliacao');
    fd.append('avaliacao_id', avaliacaoId);

    fetch('configuracoes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { alert(data.erro || 'Erro ao excluir.'); return; }
            document.getElementById('cl-aval-card-' + avaliacaoId).remove();
            atualizarContador(gradId);

            const list = document.getElementById('cl-avals-list-' + gradId);
            if (list.children.length === 0) {
                const emptyDiv = document.createElement('div');
                emptyDiv.id = 'cl-avals-empty-' + gradId;
                emptyDiv.style.cssText = 'padding:30px; text-align:center; color:#94a3b8; font-size:12px; font-weight:700; border:1px dashed #c7d2fe; background:#f8f9ff;';
                emptyDiv.innerHTML = '<i class="fa-solid fa-clipboard-list" style="font-size:24px; opacity:0.15; display:block; margin-bottom:8px;"></i>Nenhuma avaliação cadastrada ainda. Clique em "Nova Avaliação (Prova)".';
                list.appendChild(emptyDiv);
            }
        })
        .catch(() => alert('Erro de conexão.'));
}

function salvarOrdemAvaliacoes(gradId) {
    const list = document.getElementById('cl-avals-list-' + gradId);
    if (!list) return;
    const ids = Array.from(list.children).filter(el => el.id.startsWith('cl-aval-card-')).map(el => el.dataset.id);
    const fd = new FormData();
    fd.append('action_checklist', 'reordenar_avaliacoes');
    fd.append('ordem_ids', JSON.stringify(ids));
    fetch('configuracoes.php', { method: 'POST', body: fd }).catch(() => {});
}

// ITENS
function abrirAddItem(topicoId) {
    const form = document.getElementById('cl-item-form-' + topicoId);
    document.getElementById('cl-item-edit-id-' + topicoId).value = '';
    document.getElementById('cl-item-input-' + topicoId).value = '';
    form.style.display = 'block';
    document.getElementById('cl-item-input-' + topicoId).focus();
}

function fecharFormItem(topicoId) {
    document.getElementById('cl-item-form-' + topicoId).style.display = 'none';
    document.getElementById('cl-item-edit-id-' + topicoId).value = '';
    document.getElementById('cl-item-input-' + topicoId).value = '';
}

function salvarItem(topicoId, gradId) {
    const editId = document.getElementById('cl-item-edit-id-' + topicoId).value;
    const inputEl = document.getElementById('cl-item-input-' + topicoId);
    const descricao = inputEl.value.trim();
    if (!descricao) { inputEl.focus(); return; }

    const fd = new FormData();
    if (editId) {
        fd.append('action_checklist', 'editar_item');
        fd.append('item_id', editId);
    } else {
        fd.append('action_checklist', 'adicionar_item');
        fd.append('topico_id', topicoId);
    }
    fd.append('descricao', descricao);

    fetch('configuracoes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { alert(data.erro || 'Erro ao salvar item.'); return; }

            const list = document.getElementById('cl-items-list-' + topicoId);
            const emptyEl = document.getElementById('cl-items-empty-' + topicoId);
            if (emptyEl) emptyEl.remove();

            if (editId) {
                const li = document.getElementById('cl-item-' + editId);
                if (li) {
                    li.querySelector('.item-desc').textContent = data.descricao;
                }
            } else {
                const count = list.querySelectorAll('.cl-item').length;
                const li = document.createElement('li');
                li.id = 'cl-item-' + data.id;
                li.dataset.id = data.id;
                li.className = 'cl-item';
                li.style.cssText = 'display:flex; align-items:center; gap:8px; padding:6px 10px; background:#fafafa; border:1px solid #e2e8f0; cursor:grab;';
                li.innerHTML = `
                    <i class="fa-solid fa-grip-vertical item-drag-handle" style="color:#cbd5e1; font-size:12px; cursor:grab;"></i>
                    <span style="font-size:11px; font-weight:800; color:#94a3b8; width:15px; text-align:center;">${count + 1}</span>
                    <i class="fa-regular fa-circle-check" style="color:#6366f1; font-size:13px;"></i>
                    <span class="item-desc" style="flex:1; font-size:12px; font-weight:600; color:#334155;">${escapeHtml(data.descricao)}</span>
                    <div style="display:flex; gap:4px;">
                        <button type="button"
                            style="background:none; border:none; color:#4f46e5; cursor:pointer; font-size:12px; padding:4px;"
                            onclick="editarItem(${topicoId}, ${data.id}, ${JSON.stringify(data.descricao)})">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button"
                            style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:12px; padding:4px;"
                            onclick="excluirItem(${data.id}, ${topicoId}, ${gradId})">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                `;
                list.appendChild(li);
            }
            atualizarContador(gradId);
            fecharFormItem(topicoId);
        })
        .catch(() => alert('Erro de conexão.'));
}

function editarItem(topicoId, itemId, descricao) {
    const form = document.getElementById('cl-item-form-' + topicoId);
    document.getElementById('cl-item-edit-id-' + topicoId).value = itemId;
    document.getElementById('cl-item-input-' + topicoId).value = descricao;
    form.style.display = 'block';
    document.getElementById('cl-item-input-' + topicoId).focus();
}

function excluirItem(itemId, topicoId, gradId) {
    if (!confirm('Deseja realmente remover este item?')) return;
    const fd = new FormData();
    fd.append('action_checklist', 'excluir_item');
    fd.append('item_id', itemId);

    fetch('configuracoes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { alert('Erro ao excluir.'); return; }
            document.getElementById('cl-item-' + itemId).remove();
            
            // Reindexar ordem dos badges
            const list = document.getElementById('cl-items-list-' + topicoId);
            list.querySelectorAll('.cl-item').forEach((el, idx) => {
                const badge = el.querySelector('span');
                if (badge) badge.textContent = idx + 1;
            });

            if (list.children.length === 0) {
                const emptyLi = document.createElement('li');
                emptyLi.id = 'cl-items-empty-' + topicoId;
                emptyLi.style.cssText = 'padding:12px; text-align:center; color:#94a3b8; font-size:11px; font-weight:600; border:1px dashed #cbd5e1; background:#fafafa;';
                emptyLi.textContent = 'Nenhum item cadastrado neste tópico ainda.';
                list.appendChild(emptyLi);
            }
            atualizarContador(gradId);
        })
        .catch(() => alert('Erro de conexão.'));
}

function atualizarContador(gradId) {
    const panel = document.getElementById('checklist-panel-' + gradId);
    if (!panel) return;
    const itemsCount = panel.querySelectorAll('.cl-item').length;
    const badge = document.querySelector('.cl-count-' + gradId);
    if (badge) {
        badge.textContent = itemsCount;
    }
}

function salvarOrdemTopicos(gradId) {
    const list = document.getElementById('cl-topics-list-' + gradId);
    if (!list) return;
    const ids = Array.from(list.children).filter(el => el.id.startsWith('cl-topic-card-')).map(el => el.dataset.id);
    const fd = new FormData();
    fd.append('action_checklist', 'reordenar_topicos');
    fd.append('ordem_ids', JSON.stringify(ids));
    fetch('configuracoes.php', { method: 'POST', body: fd }).catch(() => {});
}

function salvarOrdemItens(topicoId) {
    const list = document.getElementById('cl-items-list-' + topicoId);
    if (!list) return;
    const ids = Array.from(list.querySelectorAll('.cl-item')).map(el => el.dataset.id);
    const fd = new FormData();
    fd.append('action_checklist', 'reordenar_itens');
    fd.append('ordem_ids', JSON.stringify(ids));
    fetch('configuracoes.php', { method: 'POST', body: fd }).catch(() => {});
}

function escapeHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function inicializarSortableItens(topicoId, gradId) {
    const listEl = document.getElementById('cl-items-list-' + topicoId);
    if (listEl) {
        Sortable.create(listEl, {
            handle: '.item-drag-handle',
            animation: 150,
            onEnd: function() {
                listEl.querySelectorAll('.cl-item').forEach((el, idx) => {
                    const badge = el.querySelector('span');
                    if (badge) badge.textContent = idx + 1;
                });
                salvarOrdemItens(topicoId);
            }
        });
    }
}

// Inicializar Sortable nos checklists
document.addEventListener('DOMContentLoaded', () => {
    // 0. Sortable nas Avaliações (Provas) de cada faixa
    document.querySelectorAll('[id^="cl-avals-list-"]').forEach(listEl => {
        const gradId = listEl.id.replace('cl-avals-list-', '');
        Sortable.create(listEl, {
            handle: '.aval-drag-handle',
            animation: 180,
            onEnd: function() { salvarOrdemAvaliacoes(gradId); }
        });
    });

    // 1. Sortable nos Tópicos de cada avaliação
    document.querySelectorAll('[id^="cl-topics-list-"]').forEach(listEl => {
        const gradId = listEl.id.replace('cl-topics-list-', '');
        Sortable.create(listEl, {
            handle: '.topic-drag-handle',
            animation: 150,
            onEnd: function() {
                salvarOrdemTopicos(gradId);
            }
        });
    });

    // 2. Sortable nos Itens de cada Tópico existente
    document.querySelectorAll('[id^="cl-items-list-"]').forEach(listEl => {
        const topicoId = listEl.id.replace('cl-items-list-', '');
        const gradId = listEl.closest('[id^="checklist-panel-"]').id.replace('checklist-panel-', '');
        inicializarSortableItens(topicoId, gradId);
    });
});

const dropzone = document.querySelector('.logo-dropzone');
const fileInput = document.getElementById('logo_file');

dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.style.borderColor = 'var(--brand)';
    dropzone.style.background = '#f0f7ff';
});

dropzone.addEventListener('dragleave', () => {
    dropzone.style.borderColor = 'var(--border-color)';
    dropzone.style.background = '#fafafa';
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.style.borderColor = 'var(--border-color)';
    dropzone.style.background = '#fafafa';
    
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        // Disparar o evento de mudança manualmente para garantir que o preview e o vínculo ocorram
        const event = new Event('change', { bubbles: true });
        fileInput.dispatchEvent(event);
    }
});

function previewLogo(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            dropzone.innerHTML = '<img src="' + e.target.result + '" class="logo-preview">';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Busca de CEP
document.getElementById('cep').addEventListener('blur', function() {
    const cep = this.value.replace(/\D/g, '');
    if (cep.length === 8) {
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(res => res.json())
            .then(data => {
                if (!data.erro) {
                    document.getElementById('logradouro').value = data.logradouro;
                    document.getElementById('bairro').value = data.bairro;
                    document.getElementById('cidade').value = data.localidade;
                    document.getElementById('estado').value = data.uf;
                }
            });
    }
});
</script>

<?php include 'footer.php'; ?>
