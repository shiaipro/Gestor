<?php
require_once '../config.php';

$unidade_id = getUnidadeId();

// 1. Auto-Migração para CMS (Garantir que tabelas existem)
try {
    $pdo->query("SELECT 1 FROM cms_categorias LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS cms_categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        descricao TEXT,
        tipo ENUM('blog', 'conteudo') DEFAULT 'blog',
        imagem VARCHAR(255) DEFAULT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

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
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE,
        FOREIGN KEY (categoria_id) REFERENCES cms_categorias(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

// 2. Garantir que a categoria "CONTEUDO" exista para o tipo conteudo
try {
    $stmt_check = $pdo->prepare("SELECT id FROM cms_categorias WHERE unidade_id = ? AND (slug = 'conteudo' OR nome = 'CONTEUDO')");
    $stmt_check->execute([$unidade_id]);
    if (!$stmt_check->fetch()) {
        $stmt_ins = $pdo->prepare("INSERT INTO cms_categorias (unidade_id, nome, slug, tipo, descricao) VALUES (?, ?, ?, ?, ?)");
        $stmt_ins->execute([$unidade_id, 'CONTEUDO', 'conteudo', 'conteudo', 'Categoria automática para conteúdos gerais do site.']);
    }
} catch (Exception $e) {
    // Silencioso se houver algum erro na migração de dados
}

// Filtros
$status_filter = $_GET['status'] ?? '';
$categoria_filter = $_GET['categoria'] ?? '';
$tipo_filter = $_GET['tipo'] ?? ''; // 'blog' ou 'conteudo'

// Definir Título da Página
$custom_title = "Blog e Conteúdo";
if ($tipo_filter == 'blog')
    $custom_title = "Gerenciar Blog";
if ($tipo_filter == 'conteudo')
    $custom_title = "Gerenciar Conteúdo";

include 'header.php';

// Deletar Post
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM cms_posts WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$id, $unidade_id]);
        $mensagem = "Post removido com sucesso!";
    } catch (PDOException $e) {
        $erro = "Erro ao remover post: " . $e->getMessage();
    }
}

$sql = "SELECT p.*, c.nome as categoria_nome, c.tipo as categoria_tipo 
        FROM cms_posts p 
        LEFT JOIN cms_categorias c ON p.categoria_id = c.id 
        WHERE p.unidade_id = ?";
$params = [$unidade_id];

if ($status_filter) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($categoria_filter) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $categoria_filter;
}

if ($tipo_filter) {
    if ($tipo_filter == 'blog') {
        $sql .= " AND (c.tipo = ? OR c.id IS NULL)";
    } else {
        $sql .= " AND (c.tipo = ?)";
    }
    $params[] = $tipo_filter;
}

$sql .= " ORDER BY p.criado_em DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Buscar categorias para filtro
$sql_cats = "SELECT * FROM cms_categorias WHERE unidade_id = ?";
$params_cats = [$unidade_id];
if ($tipo_filter) {
    $sql_cats .= " AND tipo = ?";
    $params_cats[] = $tipo_filter;
}
$sql_cats .= " ORDER BY nome ASC";

$stmt_cats = $pdo->prepare($sql_cats);
$stmt_cats->execute($params_cats);
$categorias = $stmt_cats->fetchAll();
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                <?php echo $custom_title; ?>
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Gerencie as publicações e o conteúdo institucional do seu website.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="marketing_categorias.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-tags" style="margin-right: 8px;"></i> Categorias
            </a>
            <a href="editar_blog_post.php<?php echo $tipo_filter ? '?tipo_pre=' . $tipo_filter : ''; ?>" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Novo Post
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

    

    <!-- Filtros Modernizados -->
    <div class="dashboard-container" style="padding: 25px; margin-bottom: 30px;">
        <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 20px; align-items: end;">
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Status</label>
                <select name="status" style="width: 100%; height: 45px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark); appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                    <option value="">TODOS OS STATUS</option>
                    <option value="publicado" <?php echo $status_filter == 'publicado' ? 'selected' : ''; ?>>PUBLICADO</option>
                    <option value="rascunho" <?php echo $status_filter == 'rascunho' ? 'selected' : ''; ?>>RASCUNHO</option>
                    <option value="arquivado" <?php echo $status_filter == 'arquivado' ? 'selected' : ''; ?>>ARQUIVADO</option>
                </select>
            </div>
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Categoria</label>
                <select name="categoria" style="width: 100%; height: 45px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark); appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                    <option value="">TODAS AS CATEGORIAS</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo strtoupper(htmlspecialchars($cat['nome'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo_filter); ?>">
            <button type="submit" class="btn-sq" style="height: 45px; padding: 0 30px;">FILTRAR</button>
        </form>
    </div>

    <?php if (isset($mensagem)): ?>
        <div style="background: #E8F5E9; color: #2E7D32; padding: 20px; border: 1px solid #C8E6C9; margin-bottom: 30px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-check"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px;">
        <?php foreach ($posts as $post): ?>
            <div class="dashboard-container" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.3s ease;">
                <?php if ($post['imagem_capa']): ?>
                    <div style="height: 180px; overflow: hidden; background: #08153a;">
                        <img src="<?php echo htmlspecialchars($post['imagem_capa']); ?>" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.9;">
                    </div>
                <?php else: ?>
                    <div style="height: 180px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #cbd5e1;">
                        <i class="fa-solid fa-image" style="font-size: 40px;"></i>
                    </div>
                <?php endif; ?>

                <div style="padding: 25px; flex: 1; display: flex; flex-direction: column;">
                    <div style="display: flex; gap: 8px; margin-bottom: 15px;">
                        <span style="background: <?php echo $post['status'] == 'publicado' ? 'var(--primary-green)' : '#f59e0b'; ?>; color: #fff; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; padding: 3px 8px; letter-spacing: 0.05em;">
                            <?php echo $post['status']; ?>
                        </span>
                        <?php if ($post['categoria_nome']): ?>
                            <span style="background: #1e293b; color: #fff; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; padding: 3px 8px; letter-spacing: 0.05em;">
                                <?php echo htmlspecialchars($post['categoria_nome']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <h3 style="font-size: 1.1rem; font-weight: 900; color: var(--text-dark); margin: 0 0 12px 0; line-height: 1.3; text-transform: uppercase; letter-spacing: -0.02em;">
                        <?php echo htmlspecialchars($post['titulo']); ?>
                    </h3>
                    
                    <p style="color: var(--text-muted); font-size: var(--fs-sm); font-weight: 500; line-height: 1.6; margin-bottom: 25px; flex: 1;">
                        <?php echo htmlspecialchars(mb_strimwidth(strip_tags($post['conteudo']), 0, 100, "...")); ?>
                    </p>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 20px; ">
                        <span style="font-size: var(--fs-xs); font-weight: 700; color: var(--text-muted);">
                            <i class="fa-regular fa-calendar-check" style="margin-right: 5px;"></i>
                            <?php echo date('d/m/Y', strtotime($post['criado_em'])); ?>
                        </span>
                        <div style="display: flex; gap: 8px;">
                            <a href="editar_blog_post.php?id=<?php echo $post['id']; ?>" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Editar">
                                <i class="fa-solid fa-pen-nib" style="font-size: var(--fs-sm);"></i>
                            </a>
                            <a href="?delete_id=<?php echo $post['id']; ?>" onclick="return confirm('Excluir este post?')" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; color: #ef4444;" title="Excluir">
                                <i class="fa-solid fa-trash-can" style="font-size: var(--fs-sm);"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($posts)): ?>
        <div class="dashboard-container" style="text-align: center; padding: 80px 40px;">
            <i class="fa-solid fa-newspaper" style="font-size: 60px; opacity: 0.1; display: block; margin-bottom: 20px;"></i>
            <h4 style="font-size: 1.25rem; font-weight: 900; color: var(--text-dark); margin: 0 0 10px 0; text-transform: uppercase;">Nenhum conteúdo encontrado</h4>
            <p style="color: var(--text-muted); font-weight: 500; margin-bottom: 30px;">Crie sua primeira postagem para alimentar seu website.</p>
            <a href="editar_blog_post.php<?php echo $tipo_filter ? '?tipo_pre=' . $tipo_filter : ''; ?>" class="btn-sq" style="display: inline-flex; width: auto; padding: 15px 40px; text-decoration: none;">CRIAR POSTAGEM AGORA</a>
        </div>
    <?php endif; ?>
</section>

<?php include 'footer.php'; ?>