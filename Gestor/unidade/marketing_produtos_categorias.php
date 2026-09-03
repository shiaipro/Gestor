<?php
require_once '../config.php';

$unidade_id = getUnidadeId();

// Deletar Categoria
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM cms_produtos_categorias WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$id, $unidade_id]);
    $mensagem = "Categoria de produto removida!";
}

// Deletar Subcategoria
if (isset($_GET['delete_sub_id'])) {
    $sub_id = (int) $_GET['delete_sub_id'];
    $stmt = $pdo->prepare("DELETE FROM cms_produtos_subcategorias WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$sub_id, $unidade_id]);
    $mensagem = "Subcategoria de produto removida!";
}

$custom_title = "Categorias de Produtos";
$custom_subtitle = "Organize seu catálogo de produtos de forma estratégica.";
$back_link = "catalogo_produtos.php";
$back_text = "Voltar para Produtos";
$custom_actions = '<a href="editar_produto_categoria.php" class="btn-agendar">Nova Categoria</a>';
include 'header.php';

// Fetch Categories
$stmt = $pdo->prepare("SELECT * FROM cms_produtos_categorias WHERE unidade_id = ? ORDER BY nome ASC");
$stmt->execute([$unidade_id]);
$categorias = $stmt->fetchAll();

// Fetch Subcategories
$stmt_subs = $pdo->prepare("SELECT * FROM cms_produtos_subcategorias WHERE unidade_id = ? ORDER BY nome ASC");
$stmt_subs->execute([$unidade_id]);
$subcategorias = $stmt_subs->fetchAll();

$subcats_by_parent = [];
foreach ($subcategorias as $sub) {
    $subcats_by_parent[$sub['categoria_id']][] = $sub;
}
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Categorias de Produtos
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Organize seu catálogo de produtos em agrupamentos lógicos.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="catalogo_produtos.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Ver Produtos
            </a>
            <a href="editar_produto_categoria.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Nova Categoria
            </a>
            <a href="editar_produto_subcategoria.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Nova Subcategoria
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="loja_dashboard.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_dashboard.php') ? 'active' : ''; ?>">Painel</a>
    <a href="loja_pedidos.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_pedidos.php') ? 'active' : ''; ?>">Pedidos</a>
    <a href="catalogo_produtos.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'catalogo_produtos.php') ? 'active' : ''; ?>">Produtos</a>
    <a href="marketing_produtos_categorias.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'marketing_produtos_categorias.php') ? 'active' : ''; ?>">Categorias</a>
    <a href="loja_cupons.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_cupons.php') ? 'active' : ''; ?>">Cupons</a>
    <a href="loja_relatorios.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_relatorios.php') ? 'active' : ''; ?>">Relatórios</a>
    <a href="loja_config.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_config.php') ? 'active' : ''; ?>">Configuração</a>
</div>

    

    <?php if (isset($mensagem)): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px 25px; border-left: 4px solid #166534; margin-bottom: 30px; font-weight: 700; font-size: var(--fs-base);">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; width: 100px;"></th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Nome da Categoria</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Link (Slug)</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right; letter-spacing: 0.05em;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categorias)): ?>
                    <tr>
                        <td colspan="4" style="padding: 60px; text-align: center; color: var(--text-muted); font-weight: 600;">
                            <i class="fa-solid fa-folder-open" style="font-size: 40px; opacity: 0.1; display: block; margin-bottom: 15px;"></i>
                            Nenhuma categoria encontrada.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($categorias as $cat): ?>
                    <tr>
                        <td style="padding: 15px 30px;">
                            <?php if (!empty($cat['imagem'])): ?>
                                <div style="width: 50px; height: 50px; border: 1px solid var(--border-color); overflow: hidden;">
                                    <img src="../uploads/cms/<?php echo htmlspecialchars($cat['imagem']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #f8fafc; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: #cbd5e1;">
                                    <i class="fa-solid fa-folder" style="font-size: var(--fs-base);"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 800; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;">
                                <?php echo htmlspecialchars($cat['nome']); ?>
                            </div>
                            <?php if (!empty($cat['descricao'])): ?>
                                <div style="font-size: var(--fs-xs); color: var(--text-muted); margin-top: 2px; font-weight: 600;">
                                    <?php echo mb_strimwidth(htmlspecialchars($cat['descricao']), 0, 80, "..."); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Nested Subcategories -->
                            <?php if (!empty($subcats_by_parent[$cat['id']])): ?>
                                <div style="margin-top: 15px; padding-left: 20px; border-left: 2px solid var(--border-color); display: grid; gap: 8px;">
                                    <?php foreach ($subcats_by_parent[$cat['id']] as $sub): ?>
                                        <div style="display: flex; align-items: center; justify-content: space-between; background: #fafafa; padding: 6px 12px; border: 1px solid #f1f5f9; font-size: var(--fs-xs);">
                                            <div>
                                                <span style="font-weight: 800; color: var(--text-dark); text-transform: uppercase;"><?php echo htmlspecialchars($sub['nome']); ?></span>
                                                <span style="color: var(--text-muted); margin-left: 5px; font-weight: 600;">(<?php echo htmlspecialchars($sub['slug']); ?>)</span>
                                            </div>
                                            <div style="display: flex; gap: 5px;">
                                                <a href="editar_produto_subcategoria.php?id=<?php echo $sub['id']; ?>" style="color: var(--text-dark); padding: 2px 5px;" title="Editar"><i class="fa-solid fa-edit"></i></a>
                                                <a href="?delete_sub_id=<?php echo $sub['id']; ?>" onclick="return confirm('Excluir esta subcategoria?')" style="color: #ef4444; padding: 2px 5px;" title="Excluir"><i class="fa-solid fa-trash"></i></a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px;">
                            <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: lowercase;">
                                /<?php echo htmlspecialchars($cat['slug']); ?>
                            </span>
                        </td>
                        <td style="padding: 15px 30px; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 8px; align-items: flex-start;">
                                <a href="editar_produto_subcategoria.php?categoria_id=<?php echo $cat['id']; ?>" class="btn-sq-light"
                                    style="height: 35px; padding: 0 10px; display: inline-flex; align-items: center; justify-content: center; font-size: var(--fs-xs); font-weight: 800; gap: 5px;"
                                    title="Nova Subcategoria">
                                    <i class="fa-solid fa-plus-circle"></i> SUB
                                </a>
                                <a href="editar_produto_categoria.php?id=<?php echo $cat['id']; ?>" class="btn-sq-light"
                                    style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;"
                                    title="Editar">
                                    <i class="fa-solid fa-pen-to-square" style="font-size: var(--fs-sm);"></i>
                                </a>
                                <a href="?delete_id=<?php echo $cat['id']; ?>" onclick="return confirm('Excluir esta categoria?')"
                                    class="btn-sq-light"
                                    style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; color: #ef4444;"
                                    title="Excluir">
                                    <i class="fa-solid fa-trash" style="font-size: var(--fs-sm);"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>