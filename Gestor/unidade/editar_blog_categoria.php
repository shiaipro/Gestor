<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$item = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM cms_categorias WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$id, $unidade_id]);
    $item = $stmt->fetch();
    if (!$item) {
        header('Location: marketing_categorias.php');
        exit;
    }
} else {
    // Definir tipo padrão se vindo via GET
    $item = ['tipo' => $_GET['tipo'] ?? 'blog'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $tipo = $_POST['tipo'] ?? 'blog';
    $descricao = $_POST['descricao'] ?? '';

    // Gerar slug automaticamente se vazio
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nome)));
    }

    // Upload de Imagem
    $imagem_path = $item['imagem'] ?? null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {
        $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $novo_nome = "cat_" . time() . "_" . uniqid() . "." . $ext;
        $destino = "../uploads/cms/" . $novo_nome;
        if (!is_dir("../uploads/cms/")) {
            mkdir("../uploads/cms/", 0777, true);
        }
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
            $imagem_path = $novo_nome;
        }
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE cms_categorias SET nome = ?, slug = ?, tipo = ?, descricao = ?, imagem = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$nome, $slug, $tipo, $descricao, $imagem_path, $id, $unidade_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cms_categorias (unidade_id, nome, slug, tipo, descricao, imagem) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $nome, $slug, $tipo, $descricao, $imagem_path]);
        }
        header('Location: marketing_categorias.php');
        exit;
    } catch (PDOException $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

$custom_title = $id ? "Editar Categoria" : "Nova Categoria";
include 'header.php';
?>

<!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 3.5rem;">
    <a href="marketing_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="marketing_campanhas.php" class="tab-item-sq">Campanhas</a>
    <a href="marketing_blog.php" class="tab-item-sq active">Social Media</a>
    <a href="marketing_website.php" class="tab-item-sq">Website</a>
</div>

<div class="dashboard-container" style="max-width: 800px; margin: 40px auto; padding: 40px;">
    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 35px;">
        <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;"><?php echo $custom_title; ?></h2>
    </div>

    <?php if (isset($erro)): ?>
        <div style="background: #FFEBEE; color: #C62828; padding: 20px; border: 1px solid #FFCDD2; margin-bottom: 30px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div style="display: grid; gap: 25px;">
            <div style="margin-bottom: 10px;">
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Nome da Categoria</label>
                <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($item['nome'] ?? ''); ?>" required placeholder="EX: CONTEÚDO TÉCNICO" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700; text-transform: uppercase;">
            </div>

            <div style="background: #fafafa; border: 1px solid var(--border-color); padding: 25px;">
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:15px; text-transform:uppercase; letter-spacing: 0.05em;">Identidade Visual (Capa)</label>
                
                <div style="display: flex; gap: 25px; align-items: flex-start;">
                    <?php if (!empty($item['imagem'])): ?>
                        <div style="width: 120px; height:120px; border: 1px solid var(--border-color); overflow: hidden; background: #eee;">
                            <img src="../uploads/cms/<?php echo htmlspecialchars($item['imagem']); ?>" style="width:100%; height:100%; object-fit: cover;">
                        </div>
                    <?php endif; ?>
                    
                    <div style="flex: 1;">
                        <input type="file" name="imagem" class="form-control" accept="image/*" style="width:100%; height: 45px; background: #fff; border: 1px solid var(--border-color); padding: 8px 15px; font-size: var(--fs-sm);">
                        <p style="font-size: var(--fs-xs); font-weight: 700; color: var(--text-muted); margin-top: 10px; margin-bottom: 0;">PREFERÊNCIA: FORMATO QUADRADO OU 16:9 (JPG/PNG)</p>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Slug (URL AMIGÁVEL)</label>
                    <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>" placeholder="GERADO AUTOMATICAMENTE" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700; color: #64748b;">
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Tipo de Categoria</label>
                    <select name="tipo" class="form-control" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                        <option value="blog" <?php echo ($item['tipo'] ?? '') == 'blog' ? 'selected' : ''; ?>>BLOG (NOTÍCIAS)</option>
                        <option value="conteudo" <?php echo ($item['tipo'] ?? '') == 'conteudo' ? 'selected' : ''; ?>>CONTEÚDO GERAL</option>
                    </select>
                </div>
            </div>

            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Descrição Curta</label>
                <textarea name="descricao" class="form-control" rows="4" placeholder="BREVE DESCRIÇÃO PARA IDENTIFICAÇÃO..." style="width:100%; background: #fafafa; border: 1px solid var(--border-color); padding: 15px; font-size: var(--fs-base); font-weight: 700; line-height: 1.5; resize: none;"><?php echo htmlspecialchars($item['descricao'] ?? ''); ?></textarea>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 15px;  padding-top: 30px; justify-content: space-between;">
                <button type="submit" class="btn-sq" style="height: 50px; padding: 0 40px; font-size: var(--fs-sm);">SALVAR CATEGORIA</button>
                <a href="marketing_categorias.php" class="btn-sq-light" style="height: 50px; padding: 0 30px; font-size: var(--fs-sm); text-decoration: none; display: flex; align-items: center; justify-content: center;">CANCELAR</a>
            </div>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>