<?php
require_once '../config.php';

$unidade_id = getUnidadeId();

// Auto-Migração (Safe check)
try {
    $pdo->query("SELECT 1 FROM cms_posts LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS cms_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        categoria_id INT,
        titulo VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        conteudo LONGTEXT,
        imagem_capa VARCHAR(255),
        status ENUM('rascunho', 'publicado', 'arquivado') DEFAULT 'rascunho',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

// Auto-Migração (Garantir tabelas)
try {
    $pdo->query("SELECT 1 FROM cms_posts LIMIT 1");
} catch (Exception $e) {
    // Criação simplificada se não existir (o script completo está em outros arquivos, aqui é fail-safe)
    $sql = "CREATE TABLE IF NOT EXISTS cms_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        categoria_id INT,
        titulo VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        conteudo LONGTEXT,
        imagem_capa VARCHAR(255),
        status ENUM('rascunho', 'publicado', 'arquivado') DEFAULT 'rascunho',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$item = null;

$custom_title = $id ? "Editar Post" : "Novo Post";
// include header moved to AFTER logic to prevent premature output if redirects happen... 
// BUT we need header for the view. 
// Actually, header.php is included at the end of logic block in original code? No.
// Let's include header later.

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM cms_posts WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$id, $unidade_id]);
    $item = $stmt->fetch();
    if (!$item) {
        header('Location: marketing_blog.php');
        exit;
    }
}

// Buscar Categorias
$stmt_cats = $pdo->prepare("SELECT * FROM cms_categorias WHERE unidade_id = ? ORDER BY nome ASC");
$stmt_cats->execute([$unidade_id]);
$categorias = $stmt_cats->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... logic ...
    // (This block calls header() redirect, so we must NOT include header.php before it)
}

// Logic continues... we will include header down below before HTML output.
// Wait, the original code had `include 'header.php';` at the bottom of the logic block?
// Let's check original file.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    // $tipo_pre logic only for redirect
    $tipo_pre = $_GET['tipo_pre'] ?? '';

    $slug = $_POST['slug'] ?? '';
    $categoria_id = !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null;
    $status = $_POST['status'] ?? 'rascunho';
    $conteudo = $_POST['conteudo'] ?? '';

    // Upload de Imagem de Capa
    $imagem_capa = $item['imagem_capa'] ?? ''; // Manter anterior se não mudar

    // Se enviou arquivo
    if (isset($_FILES['imagem_capa']) && $_FILES['imagem_capa']['error'] === 0) {
        $ext = pathinfo($_FILES['imagem_capa']['name'], PATHINFO_EXTENSION);
        $novo_nome = "post_" . time() . "_" . uniqid() . "." . $ext;
        $destino = "../uploads/cms/" . $novo_nome;

        if (!is_dir("../uploads/cms/")) {
            mkdir("../uploads/cms/", 0777, true);
        }

        if (move_uploaded_file($_FILES['imagem_capa']['tmp_name'], $destino)) {
            $imagem_capa = "../uploads/cms/" . $novo_nome; // Armazenar o caminho relativo para exibição
        }
    }

    // Gerar slug automaticamente se vazio
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $titulo)));
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE cms_posts SET titulo = ?, slug = ?, categoria_id = ?, status = ?, imagem_capa = ?, conteudo = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$titulo, $slug, $categoria_id, $status, $imagem_capa, $conteudo, $id, $unidade_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cms_posts (unidade_id, titulo, slug, categoria_id, status, imagem_capa, conteudo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $titulo, $slug, $categoria_id, $status, $imagem_capa, $conteudo]);
        }

        $back_link = 'marketing_blog.php';
        if ($tipo_pre)
            $back_link .= '?tipo=' . $tipo_pre;

        header("Location: $back_link");
        exit;
    } catch (PDOException $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

$custom_title = $id ? "Editar Post" : "Novo Post";
include 'header.php';
?>

<!-- Summernote css/js -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js"
    integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n"
    crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js">

<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                <?php echo $custom_title; ?>
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Redija conteúdos relevantes para engajar seus alunos e leads.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <?php
            $tipo_pre = $_GET['tipo_pre'] ?? '';
            $back_link = 'marketing_blog.php';
            if ($tipo_pre) $back_link .= '?tipo=' . $tipo_pre;
            ?>
            <a href="<?php echo $back_link; ?>" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar ao Blog
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="marketing_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="marketing_campanhas.php" class="tab-item-sq">Campanhas</a>
    <a href="marketing_blog.php" class="tab-item-sq active">Social Media</a>
    <a href="marketing_website.php" class="tab-item-sq">Website</a>
</div>

    

    <?php if (isset($erro)): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 15px 25px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 700; font-size: var(--fs-base);">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 30px; align-items: start;">

            <!-- Coluna Principal (Esquerda) -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <!-- Título -->
                <div style="background: #fff; border: 1px solid var(--border-color); padding: 5px;">
                    <input type="text" name="titulo" value="<?php echo htmlspecialchars($item['titulo'] ?? ''); ?>" required placeholder="Digite o título do post aqui..." style="width:100%; height: 70px; border: none; background: #fafafa; padding: 0 25px; font-size: 24px; font-weight: 900; color: var(--text-dark); outline: none; letter-spacing: -0.02em;">
                </div>

                <!-- Slug (Permalink) -->
                <?php if ($id): ?>
                    <div style="display: flex; align-items: center; gap: 10px; background: #f8fafc; padding: 10px 20px; border: 1px solid #e2e8f0; font-size: var(--fs-xs); font-weight: 700; color: var(--text-muted);">
                        <i class="fa-solid fa-link" style="color: var(--primary-green);"></i>
                        <span style="text-transform: uppercase; letter-spacing: 0.05em;">Link Permanente:</span>
                        <span style="color: var(--text-dark); background: #eee; padding: 2px 8px;"><?php echo $_SERVER['HTTP_HOST']; ?>/.../</span>
                        <input type="text" name="slug" value="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>" style="border:none; border-bottom:1px dashed #94a3b8; background:transparent; color: var(--primary-green); font-weight: 900; outline: none; width: 200px;">
                    </div>
                <?php else: ?>
                    <input type="hidden" name="slug" value="">
                <?php endif; ?>

                <!-- Editor -->
                <div class="dashboard-container" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color);">
                    <textarea id="summernote" name="conteudo"><?php echo $item['conteudo'] ?? ''; ?></textarea>
                </div>
            </div>

            <!-- Coluna Sidebar (Direita) -->
            <div style="display: flex; flex-direction: column; gap: 30px; position: sticky; top: 30px;">

                <!-- Status e Publicação -->
                <div class="dashboard-container" style="padding: 35px;">
                    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                        <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Publicação</h2>
                    </div>

                    <div style="display: grid; gap: 20px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Status Atual</label>
                            <select name="status" style="width: 100%; height: 45px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 600; color: var(--text-dark); appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                                <option value="rascunho" <?php echo ($item['status'] ?? '') == 'rascunho' ? 'selected' : ''; ?>>RASCUNHO</option>
                                <option value="publicado" <?php echo ($item['status'] ?? '') == 'publicado' ? 'selected' : ''; ?>>PUBLICADO</option>
                                <option value="arquivado" <?php echo ($item['status'] ?? '') == 'arquivado' ? 'selected' : ''; ?>>ARQUIVADO</option>
                            </select>
                        </div>

                        <div style="margin-top: 10px; display: grid; gap: 12px;">
                            <button type="submit" class="btn-sq" style="width: 100%; height: 50px; font-size: var(--fs-sm);">
                                <i class="fa-solid fa-check" style="margin-right: 8px;"></i> <?php echo $id ? 'ATUALIZAR POST' : 'LANÇAR POST'; ?>
                            </button>
                            <a href="<?php echo $back_link; ?>" class="btn-sq-light" style="width: 100%; height: 50px; font-size: var(--fs-sm); display: flex; align-items: center; justify-content: center; text-decoration: none;">CANCELAR</a>
                        </div>
                    </div>
                </div>

                <!-- Categorias -->
                <div class="dashboard-container" style="padding: 35px;">
                    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                        <h2 style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1; color: var(--text-muted); letter-spacing: 0.1em;">Categorias</h2>
                    </div>
                    
                    <div style="max-height: 250px; overflow-y: auto; display: grid; gap: 12px; padding-right: 5px;">
                        <?php foreach ($categorias as $cat): ?>
                            <label style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; background: #fafafa; border: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s;">
                                <input type="radio" name="categoria_id" value="<?php echo $cat['id']; ?>" <?php echo ($item['categoria_id'] ?? '') == $cat['id'] ? 'checked' : ''; ?> style="width: 16px; height: 16px; accent-color: var(--primary-green);">
                                <span style="font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark); text-transform: uppercase;"><?php echo htmlspecialchars($cat['nome']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 20px;  padding-top: 15px;">
                        <a href="editar_blog_categoria.php" target="_blank" style="font-size: var(--fs-xs); color: var(--text-dark); font-weight: 900; text-decoration: none; display: flex; align-items: center; gap: 8px; text-transform: uppercase;">
                            <i class="fa-solid fa-plus-square" style="font-size: var(--fs-base); color: var(--primary-green);"></i> Nova Categoria
                        </a>
                    </div>
                </div>

                <!-- Imagem de Capa -->
                <div class="dashboard-container" style="padding: 35px;">
                    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                        <h2 style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1; color: var(--text-muted); letter-spacing: 0.1em;">Capa do Post</h2>
                    </div>

                    <?php if (!empty($item['imagem_capa'])): ?>
                        <div style="margin-bottom: 15px; border: 1px solid var(--border-color); background: #08153a;">
                            <img src="<?php echo htmlspecialchars($item['imagem_capa']); ?>" style="width: 100%; display: block; opacity: 0.9;">
                        </div>
                    <?php endif; ?>

                    <div style="position: relative; height: 45px; border: 1px solid var(--border-color); background: #fafafa; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <i class="fa-solid fa-upload" style="margin-right: 8px; color: #94a3b8; font-size: var(--fs-base);"></i>
                        <span style="font-size: var(--fs-xs); font-weight: 900; color: #64748b; text-transform: uppercase;">Selecionar Imagem</span>
                        <input type="file" name="imagem_capa" accept="image/*" style="position: absolute; inset: 0; opacity: 0; cursor: pointer;">
                    </div>
                    <small style="display: block; color: var(--text-muted); margin-top: 10px; font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; text-align: center;">Formatos: JPG, PNG, WEBP (1200x630px)</small>
                </div>

            </div>
        </div>
    </form>
</section>

<style>
    /* Summernote Adjustment for Square Design */
    .note-editor.note-frame { border: none !important; border-bottom: 1px solid var(--border-color) !important; background: #fff !important; }
    .note-toolbar { background: #f8fafc !important; border-bottom: 1px solid var(--border-color) !important; padding: 10px 20px !important; }
    .note-btn { background: #fff !important; border: 1px solid #e2e8f0 !important; border-radius: 0 !important; }
    .note-editing-area { background: #fafafa !important; }
    .note-editable { font-family: 'Inter', sans-serif !important; font-size: var(--fs-base); !important; line-height: 1.8 !important; padding: 40px !important; color: #334155 !important; }
</style>
   }
</style>

<script>
    $('#summernote').summernote({
        placeholder: 'Escreva seu conteúdo aqui...',
        tabsize: 2,
        height: 500,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });
</script>

<?php include 'footer.php'; ?>