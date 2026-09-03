<?php
require_once '../config.php';

$unidade_id = getUnidadeId();

// Filtro de Tipo
$tipo_filter = $_GET['tipo'] ?? ''; // 'blog' ou 'conteudo'

// Deletar Categoria
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM cms_categorias WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$id, $unidade_id]);
        $mensagem = "Categoria removida com sucesso!";
    } catch (PDOException $e) {
        $erro = "Erro ao remover categoria: " . $e->getMessage();
    }
}

// Listar Categorias com Filtro
$sql = "SELECT * FROM cms_categorias WHERE unidade_id = ?";
$params = [$unidade_id];

if ($tipo_filter) {
    $sql .= " AND tipo = ?";
    $params[] = $tipo_filter;
}

$sql .= " ORDER BY nome ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categorias = $stmt->fetchAll();

// Título Dinâmico
$titulo_pagina = "Todas as Categorias";
if ($tipo_filter == 'blog')
    $titulo_pagina = "Categorias do Blog";
if ($tipo_filter == 'conteudo')
    $titulo_pagina = "Categorias de Conteúdo";
$custom_title = $titulo_pagina;

include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                <?php echo $custom_title; ?>
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Organize seu conteúdo em categorias para melhorar a estrutura do site.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
             <a href="marketing_blog.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar ao Blog
            </a>
            <a href="editar_blog_categoria.php?tipo=<?php echo $tipo_filter; ?>" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Nova Categoria
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="marketing_website.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'marketing_website.php') ? 'active' : ''; ?>">Dashboard</a>
    <a href="marketing_blog.php?tipo=blog" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'marketing_blog.php' && $tipo_filter == 'blog') ? 'active' : ''; ?>">Posts</a>
    <a href="marketing_blog.php?tipo=conteudo" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'marketing_blog.php' && $tipo_filter == 'conteudo') ? 'active' : ''; ?>">Conteúdo</a>
    <a href="marketing_categorias.php?tipo=blog" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'marketing_categorias.php' && $tipo_filter == 'blog') ? 'active' : ''; ?>">Categoria Blog</a>
    <a href="marketing_categorias.php?tipo=conteudo" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'marketing_categorias.php' && $tipo_filter == 'conteudo') ? 'active' : ''; ?>">Categoria Conteúdo</a>
</div>

    

    <?php if (isset($mensagem)): ?>
        <div style="background: #E8F5E9; color: #2E7D32; padding: 20px; border: 1px solid #C8E6C9; margin-bottom: 30px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-check"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($erro)): ?>
        <div style="background: #FFEBEE; color: #C62828; padding: 20px; border: 1px solid #FFCDD2; margin-bottom: 30px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; width: 80px;"></th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Categoria</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Slug / URL</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Tipo</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right; letter-spacing: 0.05em;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $cat): ?>
                    <tr>
                        <td style="padding: 15px 30px;">
                            <?php if (!empty($cat['imagem'])): ?>
                                <div style="width: 50px; height: 50px; border: 1px solid var(--border-color); overflow: hidden;">
                                    <img src="../uploads/cms/<?php echo htmlspecialchars($cat['imagem']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #f8fafc; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: #cbd5e1;">
                                    <i class="fa-solid fa-folder-open" style="font-size: var(--fs-base);"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 800; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;">
                                <?php echo htmlspecialchars($cat['nome']); ?>
                            </div>
                            <?php if (!empty($cat['descricao'])): ?>
                                <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600; margin-top: 3px;">
                                    <?php echo htmlspecialchars(mb_strimwidth($cat['descricao'], 0, 60, "...")); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px;">
                            <code style="background: #1e293b; color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 700; letter-spacing: 0.02em;">/<?php echo htmlspecialchars($cat['slug']); ?></code>
                        </td>
                        <td style="padding: 15px 30px;">
                            <span style="background: <?php echo $cat['tipo'] == 'blog' ? '#e0f2fe' : '#f0fdf4'; ?>; color: <?php echo $cat['tipo'] == 'blog' ? '#0369a1' : '#15803d'; ?>; padding: 5px 12px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                                <?php echo $cat['tipo'] == 'blog' ? 'Blog' : 'Conteúdo'; ?>
                            </span>
                        </td>
                        <td style="padding: 15px 30px; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                <a href="editar_blog_categoria.php?id=<?php echo $cat['id']; ?>" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Editar">
                                    <i class="fa-solid fa-pen-nib" style="font-size: var(--fs-sm);"></i>
                                </a>
                                <a href="?delete_id=<?php echo $cat['id']; ?>" onclick="return confirm('Excluir esta categoria?')" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; color: #ef4444;" title="Excluir">
                                    <i class="fa-solid fa-trash-can" style="font-size: var(--fs-sm);"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($categorias)): ?>
                    <tr>
                        <td colspan="5" style="padding: 60px; text-align: center; color: var(--text-muted); font-weight: 600;">
                            <i class="fa-solid fa-folder-open" style="font-size: 40px; opacity: 0.1; display: block; margin-bottom: 15px;"></i>
                            Nenhuma categoria cadastrada.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>