<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$item = null;

// ─── Auto-migration: garantir colunas e tabelas necessárias ───────────────────
try { $pdo->query("SELECT subcategoria_id FROM cms_produtos LIMIT 1"); }
catch (Exception $e) { $pdo->exec("ALTER TABLE cms_produtos ADD COLUMN subcategoria_id INT NULL AFTER categoria_id"); }

try { $pdo->query("SELECT id FROM cms_produtos_subcategorias LIMIT 1"); }
catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_produtos_subcategorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        categoria_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        descricao TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

try { $pdo->query("SELECT id FROM cms_produtos_grade LIMIT 1"); }
catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_produtos_grade (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produto_id INT NOT NULL,
        idade VARCHAR(50) NOT NULL DEFAULT 'adulto',
        genero VARCHAR(50) NOT NULL DEFAULT 'todos',
        tamanho VARCHAR(50) NULL,
        altura VARCHAR(50) NULL,
        cor_nome VARCHAR(100) NULL,
        cor_hex VARCHAR(7) NULL,
        foto VARCHAR(255) NULL,
        estoque INT NOT NULL DEFAULT 0,
        FOREIGN KEY (produto_id) REFERENCES cms_produtos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
// Garantir coluna idade em grades já existentes
try { $pdo->query("SELECT idade FROM cms_produtos_grade LIMIT 1"); }
catch (Exception $e) { $pdo->exec("ALTER TABLE cms_produtos_grade ADD COLUMN idade VARCHAR(50) NOT NULL DEFAULT 'adulto' AFTER produto_id"); }
// ─────────────────────────────────────────────────────────────────────────────

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM cms_produtos WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$id, $unidade_id]);
    $item = $stmt->fetch();
    if (!$item) { header('Location: catalogo_produtos.php'); exit; }
}

// Buscar Categorias
$stmt_cats = $pdo->prepare("SELECT * FROM cms_produtos_categorias WHERE unidade_id = ? ORDER BY nome ASC");
$stmt_cats->execute([$unidade_id]);
$categorias = $stmt_cats->fetchAll();

// Buscar Subcategorias
$subcategorias_all = [];
try {
    $stmt_sub = $pdo->prepare("SELECT * FROM cms_produtos_subcategorias WHERE unidade_id = ? ORDER BY nome ASC");
    $stmt_sub->execute([$unidade_id]);
    $subcategorias_all = $stmt_sub->fetchAll();
} catch (Exception $e) {}

// Buscar Imagens Atuais
$imagens = [];
if ($id) {
    $stmt_img = $pdo->prepare("SELECT * FROM cms_produtos_imagens WHERE produto_id = ? ORDER BY ordem ASC");
    $stmt_img->execute([$id]);
    $imagens = $stmt_img->fetchAll();
}

// Buscar PDFs Atuais
$pdfs = [];
if ($id) {
    $stmt_pdfs = $pdo->prepare("SELECT * FROM cms_produtos_arquivos WHERE produto_id = ?");
    $stmt_pdfs->execute([$id]);
    $pdfs = $stmt_pdfs->fetchAll();
}

// Buscar Grade Atual
$grade_items = [];
if ($id) {
    try {
        $stmt_grade = $pdo->prepare("SELECT * FROM cms_produtos_grade WHERE produto_id = ? ORDER BY id ASC");
        $stmt_grade->execute([$id]);
        $grade_items = $stmt_grade->fetchAll();
    } catch (Exception $e) {}
}

// ─── Carregar atributos globais de variação ─────────────────────────────────
$opt_idades   = [];
$opt_generos  = [];
$opt_tamanhos = [];
$opt_alturas  = [];
$opt_cores    = [];
try {
    $f = function($tbl) use ($pdo, $unidade_id) {
        $s = $pdo->prepare("SELECT * FROM $tbl WHERE unidade_id = ? ORDER BY ordem ASC, id ASC");
        $s->execute([$unidade_id]);
        return $s->fetchAll();
    };
    $opt_idades   = $f('cms_loja_idades');
    $opt_generos  = $f('cms_loja_generos');
    $opt_tamanhos = $f('cms_loja_tamanhos');
    $opt_alturas  = $f('cms_loja_alturas');
    $opt_cores    = $f('cms_loja_cores');
} catch (Exception $e) {}

$mensagem = '';
$erro = '';

// ─── (Gerar em Lote removido — atributos agora são selecionados individualmente) ───
if (false && isset($_POST['gerar_grade_lote']) && $id) {
    $gg_id = (int)$_POST['grade_global_id'];
    
    // Buscar itens da grade global
    $stmt_gg_itens = $pdo->prepare("
        SELECT gi.* FROM cms_loja_grades_itens gi
        JOIN cms_loja_grades g ON gi.grade_id = g.id
        WHERE g.id = ? AND g.unidade_id = ?
    ");
    $stmt_gg_itens->execute([$gg_id, $unidade_id]);
    $itens = $stmt_gg_itens->fetchAll();
    
    foreach ($itens as $it) {
        $nova_foto = null;
        if ($it['foto'] && file_exists("../uploads/cms/" . $it['foto'])) {
            $ext = pathinfo($it['foto'], PATHINFO_EXTENSION);
            $nova_foto = "grade_" . time() . "_" . uniqid() . "." . $ext;
            copy("../uploads/cms/" . $it['foto'], "../uploads/cms/" . $nova_foto);
        }
        
        // Verificar se já existe variação idêntica (tamanho, cor, idade, gênero, altura)
        $stmt_check = $pdo->prepare("
            SELECT id FROM cms_produtos_grade 
            WHERE produto_id = ? 
              AND (tamanho = ? OR (tamanho IS NULL AND ? IS NULL))
              AND (cor_nome = ? OR (cor_nome IS NULL AND ? IS NULL))
              AND (cor_hex = ? OR (cor_hex IS NULL AND ? IS NULL))
              AND (altura = ? OR (altura IS NULL AND ? IS NULL))
              AND idade = ? 
              AND genero = ?
        ");
        $stmt_check->execute([
            $id, 
            $it['tamanho'], $it['tamanho'], 
            $it['cor_nome'], $it['cor_nome'], 
            $it['cor_hex'], $it['cor_hex'], 
            $it['altura'], $it['altura'], 
            $it['idade'], 
            $it['genero']
        ]);
        
        if (!$stmt_check->fetch()) {
            $pdo->prepare("
                INSERT INTO cms_produtos_grade (produto_id, idade, genero, tamanho, altura, cor_nome, cor_hex, foto, estoque) 
                VALUES (?,?,?,?,?,?,?,?,?)
            ")->execute([
                $id, 
                $it['idade'], 
                $it['genero'], 
                $it['tamanho'] ?: null, 
                $it['altura'] ?: null, 
                $it['cor_nome'] ?: null, 
                $it['cor_hex'] ?: null, 
                $nova_foto, 
                0
            ]);
        }
    }
    header("Location: editar_produto.php?id=$id&tab=grade&msg=lote_gerado");
    exit;
}


// ─── EXCLUIR item de grade via GET ───────────────────────────────────────────
if (isset($_GET['del_grade']) && $id) {
    $gid = (int)$_GET['del_grade'];
    try {
        $row = $pdo->prepare("SELECT foto FROM cms_produtos_grade WHERE id = ? AND produto_id = ?");
        $row->execute([$gid, $id]);
        $gfoto = $row->fetchColumn();
        if ($gfoto && file_exists("../uploads/cms/" . $gfoto)) unlink("../uploads/cms/" . $gfoto);
        $pdo->prepare("DELETE FROM cms_produtos_grade WHERE id = ? AND produto_id = ?")->execute([$gid, $id]);
        header("Location: editar_produto.php?id=$id&tab=grade&msg=grade_excluido");
        exit;
    } catch (Exception $e) { $erro = "Erro ao excluir variação."; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ─── Salvar Grade (variação individual) ──────────────────────────────────
    if (isset($_POST['salvar_grade'])) {
        $g_id       = !empty($_POST['grade_id']) ? (int)$_POST['grade_id'] : null;
        $idade      = $_POST['idade'] ?? 'adulto';
        $genero     = $_POST['genero'] ?? 'todos';
        $tamanho    = trim($_POST['tamanho'] ?? '');
        $altura     = trim($_POST['altura'] ?? '');
        $cor_nome   = trim($_POST['cor_nome'] ?? '');
        $cor_hex    = trim($_POST['cor_hex'] ?? '');
        $g_estoque  = (int)($_POST['g_estoque'] ?? 0);
        $foto_grade = null;

        if (!is_dir("../uploads/cms/")) mkdir("../uploads/cms/", 0777, true);

        // Processar foto da variação
        if (isset($_FILES['foto_grade']) && $_FILES['foto_grade']['error'] === 0) {
            // Remover foto anterior
            if ($g_id) {
                $old = $pdo->prepare("SELECT foto FROM cms_produtos_grade WHERE id = ?");
                $old->execute([$g_id]);
                $old_foto = $old->fetchColumn();
                if ($old_foto && file_exists("../uploads/cms/" . $old_foto)) unlink("../uploads/cms/" . $old_foto);
            }
            $ext = pathinfo($_FILES['foto_grade']['name'], PATHINFO_EXTENSION);
            $novo_nome = "grade_" . time() . "_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES['foto_grade']['tmp_name'], "../uploads/cms/" . $novo_nome)) {
                $foto_grade = $novo_nome;
            }
        }

        try {
            if ($g_id) {
                $cols = "idade=?, genero=?, tamanho=?, altura=?, cor_nome=?, cor_hex=?, estoque=?";
                $vals = [$idade, $genero, $tamanho ?: null, $altura ?: null, $cor_nome ?: null, $cor_hex ?: null, $g_estoque];
                if ($foto_grade) { $cols .= ", foto=?"; $vals[] = $foto_grade; }
                $vals[] = $g_id; $vals[] = $id;
                $pdo->prepare("UPDATE cms_produtos_grade SET $cols WHERE id = ? AND produto_id = ?")->execute($vals);
            } else {
                $pdo->prepare("INSERT INTO cms_produtos_grade (produto_id, idade, genero, tamanho, altura, cor_nome, cor_hex, foto, estoque) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$id, $idade, $genero, $tamanho ?: null, $altura ?: null, $cor_nome ?: null, $cor_hex ?: null, $foto_grade, $g_estoque]);
            }
            header("Location: editar_produto.php?id=$id&tab=grade&msg=grade_salvo");
            exit;
        } catch (Exception $e) {
            $erro = "Erro ao salvar variação: " . $e->getMessage();
        }
    }

    // ─── Salvar Produto Principal ──────────────────────────────────────────────
    if (isset($_POST['salvar_produto'])) {
        $nome = $_POST['nome'] ?? '';
        $slug = $_POST['slug'] ?? '';
        $categoria_id    = !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null;
        $subcategoria_id = !empty($_POST['subcategoria_id']) ? $_POST['subcategoria_id'] : null;
        $descricao = $_POST['descricao'] ?? '';
        $preco = (float) str_replace(',', '.', $_POST['preco'] ?? 0);
        $preco_promocional = !empty($_POST['preco_promocional']) ? (float) str_replace(',', '.', $_POST['preco_promocional']) : null;
        $estoque = (int) ($_POST['estoque'] ?? 0);
        $status = $_POST['status'] ?? 'ativo';

        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nome)));
        }

        try {
            $pdo->beginTransaction();

            if ($id) {
                $stmt = $pdo->prepare("UPDATE cms_produtos SET nome=?, slug=?, categoria_id=?, subcategoria_id=?, descricao=?, preco=?, preco_promocional=?, estoque=?, status=? WHERE id=? AND unidade_id=?");
                $stmt->execute([$nome, $slug, $categoria_id, $subcategoria_id, $descricao, $preco, $preco_promocional, $estoque, $status, $id, $unidade_id]);
                $produto_id = $id;
            } else {
                $stmt = $pdo->prepare("INSERT INTO cms_produtos (unidade_id, nome, slug, categoria_id, subcategoria_id, descricao, preco, preco_promocional, estoque, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$unidade_id, $nome, $slug, $categoria_id, $subcategoria_id, $descricao, $preco, $preco_promocional, $estoque, $status]);
                $produto_id = $pdo->lastInsertId();
            }

            // --- Fotos ---
            if (!is_dir("../uploads/cms/")) mkdir("../uploads/cms/", 0777, true);

            if (isset($_POST['remover_imagens'])) {
                foreach ($_POST['remover_imagens'] as $img_id) {
                    $stmt_get = $pdo->prepare("SELECT caminho FROM cms_produtos_imagens WHERE id = ?");
                    $stmt_get->execute([$img_id]);
                    $f = $stmt_get->fetchColumn();
                    if ($f && file_exists("../uploads/cms/" . $f)) unlink("../uploads/cms/" . $f);
                    $pdo->prepare("DELETE FROM cms_produtos_imagens WHERE id = ?")->execute([$img_id]);
                }
            }

            if (isset($_FILES['fotos'])) {
                $total_fotos = count($_FILES['fotos']['name']);
                for ($i = 0; $i < $total_fotos; $i++) {
                    if ($_FILES['fotos']['error'][$i] === 0) {
                        $ext = pathinfo($_FILES['fotos']['name'][$i], PATHINFO_EXTENSION);
                        $novo_nome = "prod_" . time() . "_" . uniqid() . "." . $ext;
                        if (move_uploaded_file($_FILES['fotos']['tmp_name'][$i], "../uploads/cms/" . $novo_nome)) {
                            $pdo->prepare("INSERT INTO cms_produtos_imagens (produto_id, caminho, ordem) VALUES (?,?,?)")
                                ->execute([$produto_id, $novo_nome, $i + count($imagens)]);
                        }
                    }
                }
            }

            // --- PDFs ---
            if (isset($_POST['remover_pdfs'])) {
                foreach ($_POST['remover_pdfs'] as $pdf_id) {
                    $stmt_get = $pdo->prepare("SELECT caminho FROM cms_produtos_arquivos WHERE id = ?");
                    $stmt_get->execute([$pdf_id]);
                    $f = $stmt_get->fetchColumn();
                    if ($f && file_exists("../uploads/cms/" . $f)) unlink("../uploads/cms/" . $f);
                    $pdo->prepare("DELETE FROM cms_produtos_arquivos WHERE id = ?")->execute([$pdf_id]);
                }
            }

            if (isset($_FILES['pdfs'])) {
                $total_pdfs = count($_FILES['pdfs']['name']);
                for ($i = 0; $i < $total_pdfs; $i++) {
                    if ($_FILES['pdfs']['error'][$i] === 0) {
                        $ext = pathinfo($_FILES['pdfs']['name'][$i], PATHINFO_EXTENSION);
                        $orig_nome = $_FILES['pdfs']['name'][$i];
                        $novo_nome = "doc_" . time() . "_" . uniqid() . "." . $ext;
                        if (move_uploaded_file($_FILES['pdfs']['tmp_name'][$i], "../uploads/cms/" . $novo_nome)) {
                            $pdo->prepare("INSERT INTO cms_produtos_arquivos (produto_id, caminho, nome) VALUES (?,?,?)")
                                ->execute([$produto_id, $novo_nome, $orig_nome]);
                        }
                    }
                }
            }

            $pdo->commit();

            if (!$id) {
                header("Location: editar_produto.php?id=$produto_id&msg=criado");
                exit;
            }
            $mensagem = "Produto salvo com sucesso!";
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT * FROM cms_produtos WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$produto_id, $unidade_id]);
            $item = $stmt->fetch();
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = "Erro ao salvar: " . $e->getMessage();
        }
    }
}

// Mensagens via GET
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'grade_salvo')   $mensagem = "Variação salva com sucesso!";
    if ($_GET['msg'] === 'grade_excluido') $mensagem = "Variação excluída!";
    if ($_GET['msg'] === 'criado')         $mensagem = "Produto criado! Agora configure as variações e fotos.";
    if ($_GET['msg'] === 'lote_gerado')    $mensagem = "Variações geradas em lote com sucesso!";
}

// Recarregar grade após redirect
if ($id) {
    try {
        $stmt_grade = $pdo->prepare("SELECT * FROM cms_produtos_grade WHERE produto_id = ? ORDER BY id ASC");
        $stmt_grade->execute([$id]);
        $grade_items = $stmt_grade->fetchAll();
    } catch (Exception $e) { $grade_items = []; }
}

$active_tab = $_GET['tab'] ?? 'produto';
$edit_grade = null;
if (isset($_GET['edit_grade']) && $id) {
    try {
        $eg = $pdo->prepare("SELECT * FROM cms_produtos_grade WHERE id = ? AND produto_id = ?");
        $eg->execute([(int)$_GET['edit_grade'], $id]);
        $edit_grade = $eg->fetch();
    } catch (Exception $e) {}
}

$custom_title = $id ? "Editar Produto" : "Novo Produto";
include 'header.php';
?>
<!-- Summernote -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>

<style>
    .tab-prod-btn { padding: 12px 22px; border: none; background: none; font-size: 0.72rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer; border-bottom: 3px solid transparent; color: var(--text-muted); transition: all 0.2s; }
    .tab-prod-btn.active { border-bottom-color: #133080; color: #133080; }
    .tab-prod-content { display: none; }
    .tab-prod-content.active { display: block; }
    .grade-card { display: flex; align-items: center; gap: 15px; background: #fafafa; border: 1px solid var(--border-color); padding: 15px 20px; transition: all 0.2s; }
    .grade-card:hover { border-color: #133080; }
    .badge-genero { display: inline-block; padding: 3px 10px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em; }
    .badge-idade  { display: inline-block; padding: 3px 10px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em; }
    .badge-adulto   { background: #0f172a; color: #a3e635; }
    .badge-infantil { background: #fef9c3; color: #854d0e; }
    .badge-masc   { background: #dbeafe; color: #1d4ed8; }
    .badge-fem    { background: #fce7f3; color: #9d174d; }
    .badge-todos  { background: #f1f5f9; color: #475569; }
    .color-dot    { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 2px #cbd5e1; flex-shrink: 0; }
    .note-editor.note-frame { border: none !important; }
    .note-toolbar { background: #f8fafc !important; border-bottom: 1px solid #f1f5f9 !important; padding: 1rem !important; }
    .form-control:focus { border-color: #a3e635 !important; outline: none; box-shadow: 0 0 0 4px rgba(163,230,53,0.1); }
</style>

<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:30px; flex-wrap:wrap; gap:20px;">
        <div>
            <h1 style="font-size:2rem; font-weight:900; color:var(--text-dark); margin:0; letter-spacing:-0.03em; text-transform:uppercase;">
                <?php echo $id ? "Editar Produto" : "Novo Produto"; ?>
            </h1>
            <p style="color:var(--text-muted); font-size:var(--fs-base); margin:5px 0 0 0; font-weight:500;">
                <?php echo htmlspecialchars($item['nome'] ?? 'CADASTRANDO NOVO ITEM'); ?>
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="catalogo_produtos.php" class="btn-sq-outline" style="width:auto; padding:12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right:8px;"></i> Voltar
            </a>
        </div>
    </div>

    <!-- Nav Loja -->
    <div class="nav-tabs-sq" style="margin-bottom:2rem;">
        <a href="loja_dashboard.php" class="tab-item-sq">Painel</a>
        <a href="loja_pedidos.php" class="tab-item-sq">Pedidos</a>
        <a href="catalogo_produtos.php" class="tab-item-sq active">Produtos</a>
        <a href="marketing_produtos_categorias.php" class="tab-item-sq">Categorias</a>
        <a href="loja_cupons.php" class="tab-item-sq">Cupons</a>
        <a href="loja_relatorios.php" class="tab-item-sq">Relatórios</a>
        <a href="loja_config.php" class="tab-item-sq">Configuração</a>
    </div>

    <?php if ($mensagem): ?>
        <div style="background:#dcfce7; color:#166534; padding:15px 25px; border-left:4px solid var(--primary-green); margin-bottom:25px; font-weight:900; font-size:var(--fs-sm); text-transform:uppercase;">
            <i class="fa-solid fa-circle-check" style="margin-right:10px;"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:15px 25px; border-left:4px solid #ef4444; margin-bottom:25px; font-weight:900; font-size:var(--fs-sm); text-transform:uppercase;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right:10px;"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <!-- Abas internas do produto -->
    <div style="display:flex; gap:0; border-bottom:2px solid var(--border-color); margin-bottom:30px;">
        <button class="tab-prod-btn <?php echo $active_tab === 'produto' ? 'active' : ''; ?>" onclick="switchTab('produto')">
            <i class="fa-solid fa-box" style="margin-right:6px;"></i>Dados do Produto
        </button>
        <?php if ($id): ?>
        <button class="tab-prod-btn <?php echo $active_tab === 'grade' ? 'active' : ''; ?>" onclick="switchTab('grade')">
            <i class="fa-solid fa-table-cells" style="margin-right:6px;"></i>Grade / Variações
            <?php if (count($grade_items) > 0): ?>
                <span style="background:#133080; color:var(--primary-green); font-size:0.60rem; padding:2px 6px; border-radius:10px; margin-left:5px;"><?php echo count($grade_items); ?></span>
            <?php endif; ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- ══════════ TAB PRODUTO ══════════ -->
    <div id="tab-produto" class="tab-prod-content <?php echo $active_tab === 'produto' ? 'active' : ''; ?>">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="salvar_produto" value="1">
            <div style="display:grid; grid-template-columns:1fr 380px; gap:30px; align-items:start;">

                <!-- Coluna Principal -->
                <div style="display:flex; flex-direction:column; gap:30px;">

                    <!-- Identificação -->
                    <div class="dashboard-container">
                        <div style="padding:25px; border-bottom:1px solid var(--border-color); background:#fafafa;">
                            <h3 style="margin:0; font-size:var(--fs-base); font-weight:800; text-transform:uppercase;">Identificação</h3>
                        </div>
                        <div style="padding:40px; display:grid; gap:25px;">
                            <div>
                                <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:0.3rem; text-transform:uppercase;">Nome do Produto</label>
                                <input type="text" name="nome" value="<?php echo htmlspecialchars($item['nome'] ?? ''); ?>" required placeholder="Ex: Kimono Premium A2"
                                    style="width:100%; height:50px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size:var(--fs-base); font-weight:800; color:var(--text-dark);">
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                                <div>
                                    <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:0.3rem; text-transform:uppercase;">Preço de Venda (R$)</label>
                                    <input type="text" name="preco" value="<?php echo number_format($item['preco'] ?? 0, 2, ',', ''); ?>" placeholder="0,00"
                                        style="width:100%; height:45px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size:var(--fs-base); font-weight:700;">
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:0.3rem; text-transform:uppercase;">Preço Promocional</label>
                                    <input type="text" name="preco_promocional" value="<?php echo $item['preco_promocional'] ? number_format($item['preco_promocional'], 2, ',', '') : ''; ?>" placeholder="0,00"
                                        style="width:100%; height:45px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size:var(--fs-base); font-weight:700; color:var(--primary-green);">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Descrição -->
                    <div class="dashboard-container">
                        <div style="padding:25px; border-bottom:1px solid var(--border-color); background:#fafafa;">
                            <h3 style="margin:0; font-size:var(--fs-base); font-weight:800; text-transform:uppercase;">Descrição Detalhada</h3>
                        </div>
                        <div style="padding:40px;">
                            <div style="border:1px solid var(--border-color); background:#fff;">
                                <textarea id="summernote" name="descricao"><?php echo $item['descricao'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Galeria -->
                    <div class="dashboard-container" style="padding:40px;">
                        <div style="border-left:6px solid #08153a; padding-left:15px; margin-bottom:30px; display:flex; justify-content:space-between; align-items:center;">
                            <h2 style="font-size:1.1rem; font-weight:900; text-transform:uppercase; margin:0;">Galeria Visual</h2>
                            <span style="font-size:var(--fs-xs); font-weight:800; color:var(--text-muted); background:#eee; padding:4px 10px;">MÁX 10 FOTOS</span>
                        </div>

                        <!-- Grid de imagens já salvas -->
                        <div id="galeria-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:20px; margin-bottom:20px;">
                            <?php foreach ($imagens as $img): ?>
                                <div style="position:relative; aspect-ratio:1; border:1px solid var(--border-color); background:#08153a;">
                                    <img src="../uploads/cms/<?php echo htmlspecialchars($img['caminho']); ?>" style="width:100%; height:100%; object-fit:cover; opacity:0.9;">
                                    <label style="position:absolute; top:5px; right:5px; background:#ef4444; color:white; width:25px; height:25px; display:flex; align-items:center; justify-content:center; cursor:pointer;" title="Remover">
                                        <input type="checkbox" name="remover_imagens[]" value="<?php echo $img['id']; ?>" style="display:none;">
                                        <i class="fa-solid fa-xmark"></i>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Previews JS das novas fotos selecionadas -->
                        <div id="preview-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:20px; margin-bottom:20px;"></div>

                        <!-- Zona de upload clicável + drag-and-drop -->
                        <?php if (count($imagens) < 10): ?>
                        <label id="upload-zone"
                            for="input-fotos"
                            style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px;
                                   border:2px dashed #94a3b8; padding:40px 20px; background:#fafafa; cursor:pointer;
                                   transition:all 0.2s; text-align:center; user-select:none;">
                            <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px; color:#94a3b8;"></i>
                            <div>
                                <span style="font-size:var(--fs-sm); font-weight:900; color:#475569; text-transform:uppercase; display:block;">
                                    Clique para selecionar ou arraste as fotos aqui
                                </span>
                                <span style="font-size:var(--fs-xs); font-weight:600; color:#94a3b8; text-transform:uppercase; display:block; margin-top:4px;">
                                    JPG, PNG, WEBP — Máx. 10 imagens
                                </span>
                            </div>
                        </label>
                        <input type="file" id="input-fotos" name="fotos[]" multiple accept="image/*"
                               style="position:absolute; width:1px; height:1px; opacity:0; pointer-events:none;">
                        <?php endif; ?>

                        <div style="display:flex; align-items:center; gap:12px; background:#f8fafc; padding:12px 20px; border:1px solid #e2e8f0; margin-top:20px;">
                            <i class="fa-solid fa-circle-info" style="color:var(--primary-green);"></i>
                            <span style="font-size:var(--fs-xs); font-weight:600; color:var(--text-muted); text-transform:uppercase;">Use imagens quadradas (1:1) para melhor visualização na loja.</span>
                        </div>
                    </div>

                    <!-- PDFs -->
                    <div class="dashboard-container" style="padding:40px;">
                        <div style="border-left:6px solid #08153a; padding-left:15px; margin-bottom:30px;">
                            <h2 style="font-size:1.1rem; font-weight:900; text-transform:uppercase; margin:0;">Fichas Técnicas & Manuais</h2>
                        </div>
                        <div style="display:grid; gap:12px; margin-bottom:30px;">
                            <?php foreach ($pdfs as $pdf): ?>
                                <div style="display:flex; align-items:center; justify-content:space-between; background:#f8fafc; padding:15px 20px; border:1px solid var(--border-color); border-left:4px solid #ef4444;">
                                    <div style="display:flex; align-items:center; gap:15px;">
                                        <i class="fa-solid fa-file-pdf" style="font-size:20px; color:#ef4444;"></i>
                                        <div style="font-weight:800; color:var(--text-dark); font-size:var(--fs-sm); text-transform:uppercase;"><?php echo htmlspecialchars($pdf['nome']); ?></div>
                                    </div>
                                    <label style="background:white; color:#ef4444; border:1px solid #ef4444; padding:6px 12px; font-size:var(--fs-xs); font-weight:900; cursor:pointer; text-transform:uppercase;">
                                        <input type="checkbox" name="remover_pdfs[]" value="<?php echo $pdf['id']; ?>" style="display:none;"> EXCLUIR
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <div style="position:relative; padding:30px; border:2px dashed var(--border-color); display:flex; flex-direction:column; align-items:center; gap:12px; background:#fff; text-align:center;">
                                <i class="fa-solid fa-file-circle-plus" style="font-size:24px; color:#cbd5e1;"></i>
                                <span style="font-size:var(--fs-xs); font-weight:800; color:var(--text-dark); text-transform:uppercase;">Anexar novos documentos (PDF)</span>
                                <input type="file" name="pdfs[]" multiple accept=".pdf" style="position:absolute; inset:0; opacity:0; cursor:pointer;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div style="display:flex; flex-direction:column; gap:30px; position:sticky; top:30px;">

                    <!-- Status & Estoque -->
                    <div class="dashboard-container" style="padding:30px; background:#fafafa;">
                        <h3 style="font-size:var(--fs-xs); font-weight:900; color:var(--text-dark); margin-bottom:25px; text-transform:uppercase; letter-spacing:0.1em; border-bottom:2px solid #08153a; padding-bottom:10px;">Status & Estoque</h3>
                        <div style="display:grid; gap:20px;">
                            <div>
                                <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:0.3rem; text-transform:uppercase;">Situação do Produto</label>
                                <select name="status" class="form-control" style="font-size:0.85rem;">
                                    <option value="ativo"   <?php echo ($item['status'] ?? '') == 'ativo'   ? 'selected' : ''; ?>>ATIVO / PUBLICADO</option>
                                    <option value="inativo" <?php echo ($item['status'] ?? '') == 'inativo' ? 'selected' : ''; ?>>INATIVO / OCULTO</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:0.3rem; text-transform:uppercase;">Estoque Inicial / Geral</label>
                                <input type="number" name="estoque" class="form-control" style="font-size:0.85rem; font-weight:800;" value="<?php echo $item['estoque'] ?? 0; ?>">
                                <p style="font-size:0.65rem; color:var(--text-muted); margin:6px 0 0 0;">Usado quando não há grade de variações.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Categoria + Subcategoria -->
                    <div class="dashboard-container" style="padding:30px; background:#fff;">
                        <div style="border-bottom:1px solid var(--border-color); padding-bottom:15px; margin-bottom:20px;">
                            <h3 style="font-size:var(--fs-xs); font-weight:900; text-transform:uppercase; margin:0; color:var(--text-muted); letter-spacing:0.1em;">Agrupamento</h3>
                        </div>
                        <div style="display:grid; gap:15px;">
                            <div>
                                <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:0.3rem; text-transform:uppercase;">Categoria</label>
                                <select name="categoria_id" id="sel_categoria" class="form-control" style="font-size:0.85rem;" onchange="filtrarSubcategorias()">
                                    <option value="">SEM CATEGORIA</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($item['categoria_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo strtoupper(htmlspecialchars($cat['nome'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:0.3rem; text-transform:uppercase;">Subcategoria</label>
                                <select name="subcategoria_id" id="sel_subcategoria" class="form-control" style="font-size:0.85rem;">
                                    <option value="">SEM SUBCATEGORIA</option>
                                    <?php foreach ($subcategorias_all as $sub): ?>
                                        <option value="<?php echo $sub['id']; ?>"
                                            data-cat="<?php echo $sub['categoria_id']; ?>"
                                            <?php echo ($item['subcategoria_id'] ?? '') == $sub['id'] ? 'selected' : ''; ?>>
                                            <?php echo strtoupper(htmlspecialchars($sub['nome'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="editar_produto_subcategoria.php" style="font-size:0.65rem; color:var(--primary-green); font-weight:800; text-decoration:none; display:block; margin-top:6px;">+ Nova Subcategoria</a>
                            </div>
                        </div>
                    </div>

                    <!-- Slug -->
                    <div class="dashboard-container" style="padding:30px; background:#fff;">
                        <div style="border-bottom:1px solid var(--border-color); padding-bottom:15px; margin-bottom:20px;">
                            <h3 style="font-size:var(--fs-xs); font-weight:900; text-transform:uppercase; margin:0; color:var(--text-muted); letter-spacing:0.1em;">SEO / Link</h3>
                        </div>
                        <div style="display:flex; align-items:center; background:#f8fafc; padding:0 15px; border:1px solid var(--border-color); height:45px;">
                            <span style="color:#94a3b8; font-size:var(--fs-xs); font-weight:700; margin-right:5px;">/PRODUTO/</span>
                            <input type="text" name="slug" value="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>" placeholder="AUTOMÁTICO"
                                style="flex:1; border:none; background:transparent; font-size:var(--fs-xs); font-weight:900; color:var(--text-dark); outline:none;">
                        </div>
                    </div>

                    <div style="display:grid; gap:10px;">
                        <button type="submit" class="btn-sq" style="height:55px; font-size:var(--fs-sm);">SALVAR PRODUTO</button>
                        <a href="catalogo_produtos.php" class="btn-sq-outline" style="height:50px; font-size:var(--fs-sm); display:flex; align-items:center; justify-content:center; text-decoration:none;">CANCELAR</a>
                    </div>
                </div>
            </div>
        </form>
    </div><!-- /tab-produto -->


    <!-- ══════════ TAB GRADE ══════════ -->
    <?php if ($id): ?>
    <div id="tab-grade" class="tab-prod-content <?php echo $active_tab === 'grade' ? 'active' : ''; ?>">
        <div style="display:grid; grid-template-columns:1fr 370px; gap:30px; align-items:start;">

            <!-- Lista de Variações -->
            <div style="display:flex; flex-direction:column; gap:30px; flex:1;">
                
                <!-- Box de Geração em Lote removido: atributos agora são configurados em loja_config.php -->
                <?php if (false): ?>
                <div class="dashboard-container" style="padding: 25px 30px;">
                    <div style="border-left: 5px solid #133080; padding-left: 15px; margin-bottom: 20px;">
                        <h3 style="margin: 0; font-size: var(--fs-sm); font-weight: 900; text-transform: uppercase; color: #133080;">Gerar Variações em Lote</h3>
                        <p style="color: var(--text-muted); font-size: var(--fs-xs); margin: 3px 0 0 0;">Crie variações rapidamente a partir de uma grade global.</p>
                    </div>
                    <form method="POST" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                        <input type="hidden" name="gerar_grade_lote" value="1">
                        
                        <div style="flex: 1; min-width: 180px;">
                            <label style="display:block; font-size:0.65rem; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase;">Selecionar Grade Global</label>
                            <select name="grade_global_id" required class="form-control" style="font-size:0.80rem; height: 40px; background:#fff; border:1px solid var(--border-color); padding:0 10px;">
                                <?php foreach ($grades_globais as $gg): ?>
                                    <option value="<?php echo $gg['id']; ?>"><?php echo strtoupper($gg['nome']); ?> (<?php echo $gg['tipo'] === 'cor' ? 'Cores' : 'Tamanhos'; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="width: 110px;">
                            <label style="display:block; font-size:0.65rem; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase;">Idade</label>
                            <select name="lote_idade" class="form-control" style="font-size:0.80rem; height: 40px; background:#fff; border:1px solid var(--border-color); padding:0 10px;">
                                <option value="adulto">ADULTO</option>
                                <option value="infantil">INFANTIL</option>
                            </select>
                        </div>

                        <div style="width: 110px;">
                            <label style="display:block; font-size:0.65rem; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase;">Gênero</label>
                            <select name="lote_genero" class="form-control" style="font-size:0.80rem; height: 40px; background:#fff; border:1px solid var(--border-color); padding:0 10px;">
                                <option value="todos">TODOS</option>
                                <option value="masc">MASC</option>
                                <option value="fem">FEM</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-sq" style="height: 40px; font-size: var(--fs-xs); padding: 0 20px;">GERAR</button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="dashboard-container">
                    <div style="padding:25px 30px; border-bottom:1px solid var(--border-color); background:#fafafa; display:flex; align-items:center; justify-content:space-between;">
                        <h3 style="margin:0; font-size:var(--fs-base); font-weight:800; text-transform:uppercase;">
                            <i class="fa-solid fa-table-cells" style="margin-right:8px; color:#133080;"></i>Variações Cadastradas
                        </h3>
                        <span style="font-size:var(--fs-xs); font-weight:800; color:var(--text-muted); background:#e2e8f0; padding:4px 12px;"><?php echo count($grade_items); ?> VARIAÇÕES</span>
                    </div>

                    <div style="padding:30px; display:grid; gap:12px;">
                        <?php if (empty($grade_items)): ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <i class="fa-solid fa-table-cells" style="font-size:40px; color:#cbd5e1; display:block; margin-bottom:15px;"></i>
                                <p style="color:var(--text-muted); font-size:var(--fs-sm); font-weight:700; text-transform:uppercase; margin:0;">Nenhuma variação cadastrada.<br>Use o formulário ao lado.</p>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($grade_items as $g): ?>
                        <div class="grade-card">
                            <!-- Foto / Cor -->
                            <div style="width:52px; height:52px; flex-shrink:0; border:1px solid var(--border-color); overflow:hidden; display:flex; align-items:center; justify-content:center; background:#f1f5f9;">
                                <?php if ($g['foto']): ?>
                                    <img src="../uploads/cms/<?php echo htmlspecialchars($g['foto']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                <?php elseif ($g['cor_hex']): ?>
                                    <div class="color-dot" style="background:<?php echo htmlspecialchars($g['cor_hex']); ?>; width:30px; height:30px;"></div>
                                <?php else: ?>
                                    <i class="fa-solid fa-shirt" style="color:#cbd5e1; font-size:18px;"></i>
                                <?php endif; ?>
                            </div>

                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:5px;">
                                    <span class="badge-idade badge-<?php echo $g['idade'] ?? 'adulto'; ?>"><?php echo strtoupper($g['idade'] ?? 'ADULTO'); ?></span>
                                    <span class="badge-genero badge-<?php echo $g['genero']; ?>"><?php echo strtoupper($g['genero']); ?></span>
                                    <?php if ($g['tamanho']): ?>
                                        <span style="background:#133080; color:var(--primary-green); font-size:0.62rem; padding:2px 8px; font-weight:900; text-transform:uppercase;"><?php echo htmlspecialchars($g['tamanho']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($g['altura']): ?>
                                        <span style="background:#f1f5f9; color:#475569; font-size:0.62rem; padding:2px 8px; font-weight:800; text-transform:uppercase;">Alt: <?php echo htmlspecialchars($g['altura']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($g['cor_nome']): ?>
                                        <span style="display:inline-flex; align-items:center; gap:5px; font-size:0.62rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">
                                            <?php if ($g['cor_hex']): ?>
                                                <span style="width:10px; height:10px; border-radius:50%; background:<?php echo htmlspecialchars($g['cor_hex']); ?>; display:inline-block; border:1px solid #cbd5e1;"></span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($g['cor_nome']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:var(--fs-xs); color:var(--text-muted); font-weight:700;">
                                    Estoque: <strong style="color:<?php echo $g['estoque'] > 0 ? '#166534' : '#991b1b'; ?>;"><?php echo $g['estoque']; ?> un.</strong>
                                </div>
                            </div>

                            <div style="display:flex; gap:6px; flex-shrink:0;">
                                <a href="editar_produto.php?id=<?php echo $id; ?>&tab=grade&edit_grade=<?php echo $g['id']; ?>" 
                                   class="btn-sq-light" style="width:32px; height:32px; padding:0; display:flex; align-items:center; justify-content:center;" title="Editar">
                                    <i class="fa-solid fa-edit" style="font-size:12px;"></i>
                                </a>
                                <a href="editar_produto.php?id=<?php echo $id; ?>&tab=grade&del_grade=<?php echo $g['id']; ?>"
                                   onclick="return confirm('Excluir esta variação?')"
                                   style="width:32px; height:32px; padding:0; display:flex; align-items:center; justify-content:center; background:transparent; border:1px solid #ef4444; color:#ef4444; text-decoration:none;" title="Excluir">
                                    <i class="fa-solid fa-trash" style="font-size:12px;"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Formulário de Nova/Editar Variação -->
            <div class="dashboard-container" style="position:sticky; top:30px; padding:30px;">
                <div style="border-left:5px solid #133080; padding-left:15px; margin-bottom:25px;">
                    <h3 style="margin:0; font-size:var(--fs-base); font-weight:900; text-transform:uppercase; color:#133080;">
                        <?php echo $edit_grade ? 'Editar Variação #' . $edit_grade['id'] : 'Nova Variação'; ?>
                    </h3>
                </div>

                <form method="POST" enctype="multipart/form-data" id="form-grade">
                    <input type="hidden" name="salvar_grade" value="1">
                    <input type="hidden" name="grade_id" value="<?php echo $edit_grade['id'] ?? ''; ?>">

                    <!-- Preenchimento Rápido removido — campos agora usam selects com atributos globais -->

                    <!-- Idade -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase;">Idade</label>
                        <?php if (!empty($opt_idades)): ?>
                            <select name="idade" class="form-control" style="font-size:var(--fs-sm); height:44px; font-weight:800; text-transform:uppercase;">
                                <option value="">— Selecione —</option>
                                <?php foreach ($opt_idades as $op): ?>
                                    <option value="<?php echo htmlspecialchars($op['nome']); ?>"
                                        <?php echo ($edit_grade['idade'] ?? '') === $op['nome'] ? 'selected' : ''; ?>>
                                        <?php echo strtoupper(htmlspecialchars($op['nome'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="idade" value="<?php echo htmlspecialchars($edit_grade['idade'] ?? ''); ?>" placeholder="Ex: Adulto, Infantil..."
                                   class="form-control" style="font-size:var(--fs-sm); height:44px; font-weight:800; text-transform:uppercase;">
                            <p style="font-size:0.65rem; color:#f59e0b; margin:4px 0 0 0;"><i class="fa-solid fa-triangle-exclamation"></i> Cadastre opções em <a href="loja_config.php?tab=grades&attr=idade" target="_blank" style="color:#f59e0b;">Configurações → Atributos</a>.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Gênero -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase;">Gênero</label>
                        <?php if (!empty($opt_generos)): ?>
                            <select name="genero" class="form-control" style="font-size:var(--fs-sm); height:44px; font-weight:800; text-transform:uppercase;">
                                <option value="">— Selecione —</option>
                                <?php foreach ($opt_generos as $op): ?>
                                    <option value="<?php echo htmlspecialchars($op['nome']); ?>"
                                        <?php echo ($edit_grade['genero'] ?? '') === $op['nome'] ? 'selected' : ''; ?>>
                                        <?php echo strtoupper(htmlspecialchars($op['nome'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="genero" value="<?php echo htmlspecialchars($edit_grade['genero'] ?? ''); ?>" placeholder="Ex: Masculino, Feminino..."
                                   class="form-control" style="font-size:var(--fs-sm); height:44px; font-weight:800; text-transform:uppercase;">
                            <p style="font-size:0.65rem; color:#f59e0b; margin:4px 0 0 0;"><i class="fa-solid fa-triangle-exclamation"></i> Cadastre opções em <a href="loja_config.php?tab=grades&attr=genero" target="_blank" style="color:#f59e0b;">Configurações → Atributos</a>.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Tamanho -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase;">Tamanho</label>
                        <?php if (!empty($opt_tamanhos)): ?>
                            <select name="tamanho" class="form-control" style="font-size:var(--fs-sm); height:44px; font-weight:800; text-transform:uppercase;">
                                <option value="">— Nenhum —</option>
                                <?php foreach ($opt_tamanhos as $op): ?>
                                    <option value="<?php echo htmlspecialchars($op['nome']); ?>"
                                        <?php echo ($edit_grade['tamanho'] ?? '') === $op['nome'] ? 'selected' : ''; ?>>
                                        <?php echo strtoupper(htmlspecialchars($op['nome'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="tamanho" value="<?php echo htmlspecialchars($edit_grade['tamanho'] ?? ''); ?>" placeholder="P, M, G, A1..."
                                   class="form-control" style="font-size:var(--fs-sm); height:44px; font-weight:800; text-transform:uppercase;">
                            <p style="font-size:0.65rem; color:#f59e0b; margin:4px 0 0 0;"><i class="fa-solid fa-triangle-exclamation"></i> <a href="loja_config.php?tab=grades&attr=tamanho" target="_blank" style="color:#f59e0b;">Cadastrar tamanhos</a></p>
                        <?php endif; ?>
                    </div>

                    <!-- Altura -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase;">Altura</label>
                        <?php if (!empty($opt_alturas)): ?>
                            <select name="altura" class="form-control" style="font-size:var(--fs-sm); height:44px; font-weight:800; text-transform:uppercase;">
                                <option value="">— Nenhuma —</option>
                                <?php foreach ($opt_alturas as $op): ?>
                                    <option value="<?php echo htmlspecialchars($op['nome']); ?>"
                                        <?php echo ($edit_grade['altura'] ?? '') === $op['nome'] ? 'selected' : ''; ?>>
                                        <?php echo strtoupper(htmlspecialchars($op['nome'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="altura" value="<?php echo htmlspecialchars($edit_grade['altura'] ?? ''); ?>" placeholder="170cm, Curto, Longo..."
                                   class="form-control" style="font-size:var(--fs-sm); height:44px; font-weight:800; text-transform:uppercase;">
                            <p style="font-size:0.65rem; color:#f59e0b; margin:4px 0 0 0;"><i class="fa-solid fa-triangle-exclamation"></i> <a href="loja_config.php?tab=grades&attr=altura" target="_blank" style="color:#f59e0b;">Cadastrar alturas</a></p>
                        <?php endif; ?>
                    </div>

                    <!-- Cor -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase;">Cor</label>
                        <!-- Campo hidden que armazena o hex da cor selecionada -->
                        <input type="hidden" name="cor_hex" id="campo-cor-hex" value="<?php echo htmlspecialchars($edit_grade['cor_hex'] ?? ''); ?>">
                        <?php if (!empty($opt_cores)): ?>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <div id="cor-swatch-prod" style="width:44px; height:44px; border-radius:6px; border:1px solid var(--border-color); background:<?php echo htmlspecialchars($edit_grade['cor_hex'] ?? '#fff'); ?>; flex-shrink:0; transition:background 0.2s;"></div>
                                <select name="cor_nome" id="sel-cor-prod" onchange="aplicarCorProd(this)"
                                        class="form-control" style="font-size:var(--fs-sm); height:44px; font-weight:800; text-transform:uppercase; flex:1;">
                                    <option value="">— Nenhuma —</option>
                                    <?php foreach ($opt_cores as $op): ?>
                                        <option value="<?php echo htmlspecialchars($op['nome']); ?>"
                                                data-hex="<?php echo htmlspecialchars($op['hex'] ?? '#ffffff'); ?>"
                                                <?php echo ($edit_grade['cor_nome'] ?? '') === $op['nome'] ? 'selected' : ''; ?>>
                                            <?php echo strtoupper(htmlspecialchars($op['nome'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="text" name="cor_nome" value="<?php echo htmlspecialchars($edit_grade['cor_nome'] ?? ''); ?>" placeholder="Ex: Branco, Azul Marinho..."
                                   class="form-control" style="font-size:var(--fs-sm); height:44px; font-weight:700;">
                            <p style="font-size:0.65rem; color:#f59e0b; margin:4px 0 0 0;"><i class="fa-solid fa-triangle-exclamation"></i> <a href="loja_config.php?tab=grades&attr=cor" target="_blank" style="color:#f59e0b;">Cadastrar cores</a></p>
                        <?php endif; ?>
                    </div>

                    <!-- Foto da Variação -->
                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Foto da Variação</label>
                        <?php if (!empty($edit_grade['foto'])): ?>
                            <div style="margin-bottom:10px; position:relative; display:inline-block;">
                                <img src="../uploads/cms/<?php echo htmlspecialchars($edit_grade['foto']); ?>" style="width:80px; height:80px; object-fit:cover; border:1px solid var(--border-color);">
                            </div>
                            <p style="font-size:0.65rem; color:var(--text-muted); margin:0 0 6px 0;">Envie nova foto para substituir a atual.</p>
                        <?php endif; ?>
                        <div style="position:relative; border:2px dashed var(--border-color); padding:20px; text-align:center; background:#fff; cursor:pointer;" id="foto-grade-area">
                            <i class="fa-solid fa-image" style="font-size:20px; color:#cbd5e1; display:block; margin-bottom:6px;"></i>
                            <span style="font-size:var(--fs-xs); font-weight:800; color:#94a3b8; text-transform:uppercase;" id="foto-grade-label">Clique para selecionar</span>
                            <input type="file" name="foto_grade" accept="image/*" style="position:absolute; inset:0; opacity:0; cursor:pointer;" onchange="mostrarNomeFoto(this)">
                        </div>
                    </div>

                    <!-- Estoque -->
                    <div style="margin-bottom:22px;">
                        <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Estoque desta Variação</label>
                        <input type="number" name="g_estoque" value="<?php echo $edit_grade['estoque'] ?? 0; ?>" min="0"
                            style="width:100%; height:45px; border:2px solid #133080; padding:0 15px; font-weight:900; font-size:var(--fs-base); color:#133080;">
                    </div>

                    <button type="submit" class="btn-sq" style="width:100%; height:52px; font-size:var(--fs-sm);">
                        <i class="fa-solid fa-<?php echo $edit_grade ? 'save' : 'plus-circle'; ?>" style="margin-right:8px;"></i>
                        <?php echo $edit_grade ? 'SALVAR ALTERAÇÕES' : 'ADICIONAR VARIAÇÃO'; ?>
                    </button>

                    <?php if ($edit_grade): ?>
                        <a href="editar_produto.php?id=<?php echo $id; ?>&tab=grade" class="btn-sq-light"
                            style="width:100%; height:42px; margin-top:8px; display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;">
                            CANCELAR EDIÇÃO
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div><!-- /tab-grade -->
    <?php endif; ?>

</section>

<script>
    // ── Abas internas ─────────────────────────────────────────────────────────
    function switchTab(name) {
        document.querySelectorAll('.tab-prod-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-prod-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        document.querySelectorAll('.tab-prod-btn').forEach(b => {
            if (b.getAttribute('onclick') === "switchTab('" + name + "')") b.classList.add('active');
        });
        history.replaceState(null, '', '?id=<?php echo $id; ?>&tab=' + name);
    }

    // ── Preencher hex ao selecionar uma cor global ───────────────────────────
    function aplicarCorProd(sel) {
        const hex = sel.options[sel.selectedIndex]?.getAttribute('data-hex') || '#ffffff';
        const campo = document.getElementById('campo-cor-hex');
        const swatch = document.getElementById('cor-swatch-prod');
        if (campo) campo.value = hex;
        if (swatch) swatch.style.background = hex;
    }

    // Inicializar cor ao carregar (caso já haja seleção)
    document.addEventListener('DOMContentLoaded', function() {
        const selCor = document.getElementById('sel-cor-prod');
        if (selCor && selCor.value) aplicarCorProd(selCor);
    });

    // ── Subcategorias filtradas por categoria ─────────────────────────────────
    function filtrarSubcategorias() {
        const catId = document.getElementById('sel_categoria')?.value;
        const sel = document.getElementById('sel_subcategoria');
        if (!sel) return;
        const opts = sel.querySelectorAll('option');
        opts.forEach(opt => {
            const dataCat = opt.getAttribute('data-cat');
            opt.style.display = (!catId || !dataCat || dataCat == catId) ? '' : 'none';
        });
        // Se opção selecionada foi ocultada, resetar
        const selected = sel.options[sel.selectedIndex];
        if (selected && selected.style.display === 'none') sel.value = '';
    }

    // ── Mostrar nome do arquivo selecionado ───────────────────────────────────
    function mostrarNomeFoto(input) {
        const label = document.getElementById('foto-grade-label');
        if (label && input.files[0]) {
            label.textContent = input.files[0].name;
        }
    }

    // ── Inicialização ─────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Summernote
        if (typeof $.fn.summernote !== 'undefined') {
            $('#summernote').summernote({
                placeholder: 'Descreva detalhadamente as características do produto...',
                tabsize: 2, height: 400,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture']],
                    ['view', ['fullscreen', 'codeview']]
                ]
            });
        }

        // ── Galeria: upload via clique (label) + drag-and-drop ────────────────
        const inputFotos = document.getElementById('input-fotos');
        const uploadZone = document.getElementById('upload-zone');
        const previewGrid = document.getElementById('preview-grid');

        function mostrarPreviews(files) {
            if (!previewGrid) return;
            previewGrid.innerHTML = '';
            Array.from(files).forEach(function(file) {
                if (!file.type.startsWith('image/')) return;
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.style.cssText = 'position:relative; aspect-ratio:1; border:2px solid var(--primary-green); background:#08153a; overflow:hidden;';
                    div.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;opacity:0.9;">'
                        + '<span style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.6);color:#a3e635;font-size:0.6rem;font-weight:900;padding:4px 6px;text-transform:uppercase;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">' + file.name + '</span>';
                    previewGrid.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
            if (files.length > 0 && uploadZone) {
                uploadZone.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size:28px;color:var(--primary-green);"></i>'
                    + '<span style="font-size:var(--fs-sm);font-weight:900;color:#166534;text-transform:uppercase;">' + files.length + ' foto(s) selecionada(s)</span>'
                    + '<span style="font-size:var(--fs-xs);color:#94a3b8;text-transform:uppercase;">Clique para trocar</span>';
                uploadZone.style.borderColor = 'var(--primary-green)';
                uploadZone.style.background = '#f0fdf4';
            }
        }

        if (inputFotos) {
            inputFotos.addEventListener('change', function() {
                mostrarPreviews(this.files);
            });
        }

        if (uploadZone) {
            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = '#133080';
                this.style.background = '#eff6ff';
            });
            uploadZone.addEventListener('dragleave', function() {
                this.style.borderColor = '#94a3b8';
                this.style.background = '#fafafa';
            });
            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '#94a3b8';
                this.style.background = '#fafafa';
                if (!inputFotos) return;
                // Transferir os arquivos soltos para o input
                const dt = new DataTransfer();
                Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
                inputFotos.files = dt.files;
                mostrarPreviews(dt.files);
            });
        }
        // ─────────────────────────────────────────────────────────────────────

        // Filtrar subcategorias na carga
        filtrarSubcategorias();

        // Estilo inicial da checkbox de imagens (marcar como remoção)
        document.querySelectorAll('input[name="remover_imagens[]"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                const div = this.closest('div[style]');
                if (div) div.style.opacity = this.checked ? '0.4' : '1';
            });
        });
    });

    // applyQuickGrade removida — sistema substituído por dropdowns de atributos
</script>

<?php include 'footer.php'; ?>