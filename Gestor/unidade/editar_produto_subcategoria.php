<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$item = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM cms_produtos_subcategorias WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$id, $unidade_id]);
    $item = $stmt->fetch();
    if (!$item) {
        header('Location: marketing_produtos_categorias.php');
        exit;
    }
}

// Fetch main categories for assignment
$stmt_cats = $pdo->prepare("SELECT * FROM cms_produtos_categorias WHERE unidade_id = ? ORDER BY nome ASC");
$stmt_cats->execute([$unidade_id]);
$categorias = $stmt_cats->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $descricao = $_POST['descricao'] ?? '';

    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nome)));
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE cms_produtos_subcategorias SET nome = ?, slug = ?, categoria_id = ?, descricao = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$nome, $slug, $categoria_id, $descricao, $id, $unidade_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cms_produtos_subcategorias (unidade_id, categoria_id, nome, slug, descricao) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $categoria_id, $nome, $slug, $descricao]);
        }
        header('Location: marketing_produtos_categorias.php');
        exit;
    } catch (PDOException $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

$custom_title = $id ? "Editar Subcategoria de Produto" : "Nova Subcategoria de Produto";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                <?php echo $custom_title; ?>
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Configuração de Subcategorias de Produtos
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="marketing_produtos_categorias.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="loja_dashboard.php" class="tab-item-sq">Painel</a>
        <a href="loja_pedidos.php" class="tab-item-sq">Pedidos</a>
        <a href="catalogo_produtos.php" class="tab-item-sq">Produtos</a>
        <a href="marketing_produtos_categorias.php" class="tab-item-sq active">Categorias</a>
        <a href="loja_cupons.php" class="tab-item-sq">Cupons</a>
        <a href="loja_relatorios.php" class="tab-item-sq">Relatórios</a>
        <a href="loja_config.php" class="tab-item-sq">Configuração</a>
    </div>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr; gap: 40px; align-items: start; max-width: 800px;">
            <div class="dashboard-container" style="padding: 40px;">
                <?php if (isset($erro)): ?>
                    <div style="background: #ef4444; color: #fff; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;"><?php echo $erro; ?></div>
                <?php endif; ?>

                <div style="display: grid; gap: 25px;">
                    <div>
                        <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Categoria Pai</label>
                        <select name="categoria_id" required class="form-control" style="font-size: 0.85rem;">
                            <option value="">Selecione a categoria principal...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (($item['categoria_id'] ?? ($_GET['categoria_id'] ?? 0)) == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo strtoupper(htmlspecialchars($cat['nome'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Nome da Subcategoria</label>
                        <input type="text" name="nome" class="form-control" style="font-size: 0.85rem;" value="<?php echo htmlspecialchars($item['nome'] ?? ''); ?>" required placeholder="EX: MANGA CURTA">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
                        <div>
                            <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Slug (URL AMIGÁVEL)</label>
                            <input type="text" name="slug" class="form-control" style="font-size: 0.85rem;" value="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>" placeholder="GERADO AUTOMATICAMENTE">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="3" style="font-size: 0.85rem; resize: none;" placeholder="DESCREVA BREVEMENTE ESTA SUBCATEGORIA..."><?php echo htmlspecialchars($item['descricao'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">SALVAR SUBCATEGORIA</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>
