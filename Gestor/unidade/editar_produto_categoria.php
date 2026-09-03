<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$item = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM cms_produtos_categorias WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$id, $unidade_id]);
    $item = $stmt->fetch();
    if (!$item) {
        header('Location: marketing_produtos_categorias.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $descricao = $_POST['descricao'] ?? '';

    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nome)));
    }

    // Upload de Imagem
    $imagem_path = $item['imagem'] ?? null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {
        $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $novo_nome = "pcat_" . time() . "_" . uniqid() . "." . $ext;
        $destino = "../uploads/cms/" . $novo_nome;
        if (!is_dir("../uploads/cms/"))
            mkdir("../uploads/cms/", 0777, true);
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destino))
            $imagem_path = $novo_nome;
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE cms_produtos_categorias SET nome = ?, slug = ?, descricao = ?, imagem = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$nome, $slug, $descricao, $imagem_path, $id, $unidade_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cms_produtos_categorias (unidade_id, nome, slug, descricao, imagem) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $nome, $slug, $descricao, $imagem_path]);
        }
        header('Location: marketing_produtos_categorias.php');
        exit;
    } catch (PDOException $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

$custom_title = $id ? "Editar Categoria de Produto" : "Nova Categoria de Produto";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                <?php echo $custom_title; ?>
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Configuração de Categorias de Produtos
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="marketing_produtos_categorias.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="loja_dashboard.php" class="tab-item-sq">Painel</a>
    <a href="loja_pedidos.php" class="tab-item-sq">Pedidos</a>
    <a href="catalogo_produtos.php" class="tab-item-sq">Produtos</a>
    <a href="marketing_produtos_categorias.php" class="tab-item-sq active">Categorias</a>
    <a href="loja_cupons.php" class="tab-item-sq">Cupons</a>
    <a href="loja_relatorios.php" class="tab-item-sq">Relatórios</a>
    <a href="loja_config.php" class="tab-item-sq">Configuração</a>
</div>

    

    <form method="POST" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 1fr; gap: 40px; align-items: start; max-width: 800px;">
            <div class="dashboard-container" style="padding: 40px;">
                <?php if (isset($erro)): ?>
                    <div style="background: #ef4444; color: #fff; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;"><?php echo $erro; ?></div>
                <?php endif; ?>

                <div style="display: grid; gap: 25px;">
                    <div>
                        <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Nome da Categoria de Produto</label>
                        <input type="text" name="nome" class="form-control" style="font-size: 0.85rem;" value="<?php echo htmlspecialchars($item['nome'] ?? ''); ?>" required placeholder="EX: KIMONOS E ACESSÓRIOS">
                    </div>

                    <div style="background: #fafafa; border: 1px solid var(--border-color); padding: 25px;">
                        <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Ícone / Foto da Categoria</label>
                        
                        <div style="display: flex; gap: 25px; align-items: flex-start; margin-top: 10px;">
                            <?php if (!empty($item['imagem'])): ?>
                                <div style="width: 140px; height: 140px; border: 1px solid var(--border-color); overflow: hidden; background: #eee;">
                                    <img src="../uploads/cms/<?php echo htmlspecialchars($item['imagem']); ?>" style="width:100%; height:100%; object-fit: cover;">
                                </div>
                            <?php endif; ?>
                            
                            <div style="flex: 1;">
                                <input type="file" name="imagem" class="form-control" accept="image/*" style="font-size: 0.85rem;">
                                <p style="font-size: var(--fs-xs); font-weight: 700; color: var(--text-muted); margin-top: 10px; margin-bottom: 0;">FORMATOS SUPORTADOS: JPG, PNG OU WEBP. RECOMENDADO 1:1.</p>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
                        <div>
                            <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Slug (URL AMIGÁVEL)</label>
                            <input type="text" name="slug" class="form-control" style="font-size: 0.85rem;" value="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>" placeholder="GERADO AUTOMATICAMENTE">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Descrição do Catálogo</label>
                            <textarea name="descricao" class="form-control" rows="3" style="font-size: 0.85rem; resize: none;" placeholder="DESCREVA BREVEMENTE ESTA CATEGORIA..."><?php echo htmlspecialchars($item['descricao'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">SALVAR CATEGORIA</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>