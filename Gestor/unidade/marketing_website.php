<?php
require_once '../config.php';
$custom_title = "Website";
include 'header.php';

$unidade_id = getUnidadeId();

// 1. Auto-Migração para Marketing Website
try {
    $pdo->query("SELECT 1 FROM marketing_website LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS marketing_website (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        url_referencia VARCHAR(255),
        pixel_id VARCHAR(100),
        google_analytics VARCHAR(100),
        metas_mensais INT DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

// Buscar ou Criar Registro Inicial
$stmt = $pdo->prepare("SELECT * FROM marketing_website WHERE unidade_id = ?");
$stmt->execute([$unidade_id]);
$site_data = $stmt->fetch();

if (!$site_data) {
    $pdo->prepare("INSERT INTO marketing_website (unidade_id) VALUES (?)")->execute([$unidade_id]);
    $stmt->execute([$unidade_id]);
    $site_data = $stmt->fetch();
}

// Salvar Configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config'])) {
    $url = $_POST['url_referencia'] ?? '';
    $pixel = $_POST['pixel_id'] ?? '';
    $ga = $_POST['google_analytics'] ?? '';
    $meta = (int) ($_POST['metas_mensais'] ?? 0);

    $stmt = $pdo->prepare("UPDATE marketing_website SET url_referencia = ?, pixel_id = ?, google_analytics = ?, metas_mensais = ? WHERE unidade_id = ?");
    $stmt->execute([$url, $pixel, $ga, $meta, $unidade_id]);
    $mensagem = "Configurações de marketing salvas!";
    header("refresh:1;url=marketing_website.php");
}
?>

<?php $tipo_filter = $_GET['tipo'] ?? ''; ?>


<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Presença Digital
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Configure rastreamento, SEO e atalhos de gerenciamento de conteúdo.
            </p>
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

    

    <!-- Quick Access Modernizado -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <a href="marketing_categorias.php?tipo=blog" class="dashboard-container" style="text-decoration:none; padding:25px; display:flex; flex-direction:column; align-items:center; text-align:center; transition: transform 0.2s ease;">
            <i class="fa-solid fa-folder-tree" style="font-size:24px; color:var(--primary-green); margin-bottom:12px;"></i>
            <span style="font-size: var(--fs-xs); font-weight:900; color:var(--text-dark); text-transform:uppercase; letter-spacing:0.05em;">Categorias Blog</span>
        </a>
        <a href="marketing_categorias.php?tipo=conteudo" class="dashboard-container" style="text-decoration:none; padding:25px; display:flex; flex-direction:column; align-items:center; text-align:center; transition: transform 0.2s ease;">
            <i class="fa-solid fa-folder-open" style="font-size:24px; color:#10b981; margin-bottom:12px;"></i>
            <span style="font-size: var(--fs-xs); font-weight:900; color:var(--text-dark); text-transform:uppercase; letter-spacing:0.05em;">Categorias Site</span>
        </a>
        <a href="marketing_blog.php?tipo=blog" class="dashboard-container" style="text-decoration:none; padding:25px; display:flex; flex-direction:column; align-items:center; text-align:center; transition: transform 0.2s ease;">
            <i class="fa-solid fa-pen-nib" style="font-size:24px; color:#8b5cf6; margin-bottom:12px;"></i>
            <span style="font-size: var(--fs-xs); font-weight:900; color:var(--text-dark); text-transform:uppercase; letter-spacing:0.05em;">Postagens</span>
        </a>
        <a href="marketing_blog.php?tipo=conteudo" class="dashboard-container" style="text-decoration:none; padding:25px; display:flex; flex-direction:column; align-items:center; text-align:center; transition: transform 0.2s ease;">
            <i class="fa-solid fa-file-lines" style="font-size:24px; color:#3b82f6; margin-bottom:12px;"></i>
            <span style="font-size: var(--fs-xs); font-weight:900; color:var(--text-dark); text-transform:uppercase; letter-spacing:0.05em;">Conteúdo Estático</span>
        </a>
        <a href="catalogo_produtos.php" class="dashboard-container" style="text-decoration:none; padding:25px; display:flex; flex-direction:column; align-items:center; text-align:center; transition: transform 0.2s ease;">
            <i class="fa-solid fa-cart-shopping" style="font-size:24px; color:#f59e0b; margin-bottom:12px;"></i>
            <span style="font-size: var(--fs-xs); font-weight:900; color:var(--text-dark); text-transform:uppercase; letter-spacing:0.05em;">Catálogo</span>
        </a>
    </div>

    <?php if (isset($mensagem)): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px 25px;  margin-bottom: 30px; font-weight: 700; font-size: var(--fs-base);">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

</section>

<?php include 'footer.php'; ?>