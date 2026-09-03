<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Auto-Migração: Tabela de Posts de Social Media
try {
    $pdo->query("SELECT 1 FROM social_posts LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS social_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        plataforma ENUM('instagram','facebook','tiktok','youtube','linkedin','whatsapp') DEFAULT 'instagram',
        tipo ENUM('feed','stories','reels','carrossel','video') DEFAULT 'feed',
        legenda TEXT,
        hashtags TEXT,
        imagem VARCHAR(255),
        status ENUM('rascunho','agendado','publicado','arquivado') DEFAULT 'rascunho',
        data_publicacao DATETIME,
        curtidas INT DEFAULT 0,
        comentarios INT DEFAULT 0,
        alcance INT DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Deletar post
if (isset($_GET['delete_id'])) {
    $del_id = (int) $_GET['delete_id'];
    $pdo->prepare("DELETE FROM social_posts WHERE id = ? AND unidade_id = ?")->execute([$del_id, $unidade_id]);
    header("Location: marketing_social_media.php");
    exit;
}

// Salvar novo post / editar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_post'])) {
    $post_id       = (int) ($_POST['post_id'] ?? 0);
    $plataforma    = $_POST['plataforma'] ?? 'instagram';
    $tipo          = $_POST['tipo'] ?? 'feed';
    $legenda       = $_POST['legenda'] ?? '';
    $hashtags      = $_POST['hashtags'] ?? '';
    $status        = $_POST['status'] ?? 'rascunho';
    $data_pub      = !empty($_POST['data_publicacao']) ? $_POST['data_publicacao'] : null;
    $curtidas      = (int) ($_POST['curtidas'] ?? 0);
    $comentarios   = (int) ($_POST['comentarios'] ?? 0);
    $alcance       = (int) ($_POST['alcance'] ?? 0);

    // Upload de imagem
    $imagem = $_POST['imagem_atual'] ?? null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {
        $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $novo = "sm_" . time() . "_" . uniqid() . "." . $ext;
        $dest = "../uploads/cms/" . $novo;
        if (!is_dir("../uploads/cms/")) mkdir("../uploads/cms/", 0777, true);
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $dest)) $imagem = $novo;
    }

    if ($post_id) {
        $pdo->prepare("UPDATE social_posts SET plataforma=?, tipo=?, legenda=?, hashtags=?, status=?, data_publicacao=?, curtidas=?, comentarios=?, alcance=?, imagem=? WHERE id=? AND unidade_id=?")
            ->execute([$plataforma, $tipo, $legenda, $hashtags, $status, $data_pub, $curtidas, $comentarios, $alcance, $imagem, $post_id, $unidade_id]);
    } else {
        $pdo->prepare("INSERT INTO social_posts (unidade_id, plataforma, tipo, legenda, hashtags, status, data_publicacao, curtidas, comentarios, alcance, imagem) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$unidade_id, $plataforma, $tipo, $legenda, $hashtags, $status, $data_pub, $curtidas, $comentarios, $alcance, $imagem]);
    }

    header("Location: marketing_social_media.php");
    exit;
}

// Filtros
$filtro_plataforma = $_GET['plataforma'] ?? '';
$filtro_status     = $_GET['status'] ?? '';

$sql    = "SELECT * FROM social_posts WHERE unidade_id = ?";
$params = [$unidade_id];
if ($filtro_plataforma) { $sql .= " AND plataforma = ?"; $params[] = $filtro_plataforma; }
if ($filtro_status)     { $sql .= " AND status = ?";    $params[] = $filtro_status; }
$sql .= " ORDER BY data_publicacao DESC, criado_em DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Métricas
$m = $pdo->prepare("SELECT COUNT(*) as total, SUM(curtidas) as curtidas, SUM(comentarios) as comentarios, SUM(alcance) as alcance FROM social_posts WHERE unidade_id = ?");
$m->execute([$unidade_id]);
$metricas = $m->fetch();

$agendados = $pdo->prepare("SELECT COUNT(*) FROM social_posts WHERE unidade_id = ? AND status = 'agendado'");
$agendados->execute([$unidade_id]); $total_agendados = $agendados->fetchColumn();

$publicados = $pdo->prepare("SELECT COUNT(*) FROM social_posts WHERE unidade_id = ? AND status = 'publicado'");
$publicados->execute([$unidade_id]); $total_publicados = $publicados->fetchColumn();

// Editar post
$edit_post = null;
if (isset($_GET['edit_id'])) {
    $ep = $pdo->prepare("SELECT * FROM social_posts WHERE id = ? AND unidade_id = ?");
    $ep->execute([(int)$_GET['edit_id'], $unidade_id]);
    $edit_post = $ep->fetch();
}

$custom_title = "Social Media";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">Social Media</h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">Gerencie conteúdos, agendamentos e métricas das suas redes sociais.</p>
        </div>

    
        <div style="display: flex; gap: 10px;">
            <button onclick="abrirModal()" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Novo Post
            </button>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="marketing_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="marketing_campanhas.php" class="tab-item-sq">Campanhas</a>
    <a href="marketing_social_media.php" class="tab-item-sq active">Social Media</a>
    <a href="marketing_agenda.php" class="tab-item-sq">Offline</a>
</div>

    

    <!-- Métricas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="stat-card-square">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total de Posts</span>
                <i class="fa-solid fa-images" style="color: var(--primary-green); opacity: 0.5;"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo $metricas['total'] ?? 0; ?></div>
            <div style="margin-top: 10px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600;"><?php echo $total_publicados; ?> publicados · <?php echo $total_agendados; ?> agendados</div>
        </div>
        <div class="stat-card-square">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Curtidas</span>
                <i class="fa-solid fa-heart" style="color: #e11d48; opacity: 0.5;"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo number_format($metricas['curtidas'] ?? 0); ?></div>
        </div>
        <div class="stat-card-square">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Comentários</span>
                <i class="fa-solid fa-comments" style="color: #3b82f6; opacity: 0.5;"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo number_format($metricas['comentarios'] ?? 0); ?></div>
        </div>
        <div class="stat-card-square">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Alcance Total</span>
                <i class="fa-solid fa-users-viewfinder" style="color: #8b5cf6; opacity: 0.5;"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo number_format($metricas['alcance'] ?? 0); ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <div style="display: flex; gap: 12px; margin-bottom: 30px; flex-wrap: wrap; align-items: center;">
        <span style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">FILTRAR:</span>
        <?php
        $plataformas_filter = [''=>'Todas','instagram'=>'Instagram','facebook'=>'Facebook','tiktok'=>'TikTok','youtube'=>'YouTube','linkedin'=>'LinkedIn','whatsapp'=>'WhatsApp'];
        foreach ($plataformas_filter as $val => $label):
            $active = $filtro_plataforma === $val;
        ?>
            <a href="?plataforma=<?php echo $val; ?>&status=<?php echo $filtro_status; ?>" style="padding: 7px 16px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; text-decoration: none; border: 1px solid <?php echo $active ? '#08153a' : 'var(--border-color)'; ?>; background: <?php echo $active ? '#08153a' : '#fff'; ?>; color: <?php echo $active ? '#fff' : 'var(--text-dark)'; ?>; letter-spacing: 0.04em;"><?php echo $label; ?></a>
        <?php endforeach; ?>

        <span style="width: 1px; height: 24px; background: var(--border-color);"></span>

        <?php
        $statuses_filter = [''=>'Todos','rascunho'=>'Rascunho','agendado'=>'Agendado','publicado'=>'Publicado','arquivado'=>'Arquivado'];
        foreach ($statuses_filter as $val => $label):
            $active = $filtro_status === $val;
        ?>
            <a href="?plataforma=<?php echo $filtro_plataforma; ?>&status=<?php echo $val; ?>" style="padding: 7px 16px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; text-decoration: none; border: 1px solid <?php echo $active ? '#08153a' : 'var(--border-color)'; ?>; background: <?php echo $active ? '#08153a' : '#fff'; ?>; color: <?php echo $active ? '#fff' : 'var(--text-dark)'; ?>; letter-spacing: 0.04em;"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Grid de Posts -->
    <?php if (empty($posts)): ?>
        <div class="dashboard-container" style="padding: 80px; text-align: center;">
            <i class="fa-brands fa-instagram" style="font-size: 48px; color: var(--border-color); display: block; margin-bottom: 20px;"></i>
            <p style="font-size: var(--fs-base); font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin: 0;">Nenhum post cadastrado ainda.</p>
            <p style="font-size: var(--fs-sm); color: var(--text-muted); margin-top: 8px;">Clique em "Novo Post" para começar a planejar conteúdo.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php
            $platform_icons = [
                'instagram' => ['icon' => 'fa-brands fa-instagram', 'color' => '#e1306c'],
                'facebook'  => ['icon' => 'fa-brands fa-facebook',  'color' => '#1877f2'],
                'tiktok'    => ['icon' => 'fa-brands fa-tiktok',    'color' => '#08153a'],
                'youtube'   => ['icon' => 'fa-brands fa-youtube',   'color' => '#ff0000'],
                'linkedin'  => ['icon' => 'fa-brands fa-linkedin',  'color' => '#0077b5'],
                'whatsapp'  => ['icon' => 'fa-brands fa-whatsapp',  'color' => '#25d366'],
            ];
            $status_colors = [
                'rascunho'  => ['bg' => '#f1f5f9', 'color' => '#64748b'],
                'agendado'  => ['bg' => '#fef3c7', 'color' => '#92400e'],
                'publicado' => ['bg' => '#d1fae5', 'color' => '#065f46'],
                'arquivado' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
            ];
            foreach ($posts as $post):
                $pi = $platform_icons[$post['plataforma']] ?? ['icon'=>'fa-solid fa-share-nodes','color'=>'#08153a'];
                $sc = $status_colors[$post['status']] ?? ['bg'=>'#f1f5f9','color'=>'#64748b'];
            ?>
            <div class="dashboard-container" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
                <!-- Imagem / Preview -->
                <div style="height: 180px; background: #08153a; position: relative; overflow: hidden;">
                    <?php if (!empty($post['imagem'])): ?>
                        <img src="../uploads/cms/<?php echo htmlspecialchars($post['imagem']); ?>" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.85;">
                    <?php else: ?>
                        <div style="height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 10px;">
                            <i class="<?php echo $pi['icon']; ?>" style="font-size: 48px; color: <?php echo $pi['color']; ?>; opacity: 0.4;"></i>
                            <span style="font-size: var(--fs-xs); font-weight: 900; color: #555; text-transform: uppercase; letter-spacing: 0.1em;"><?php echo strtoupper($post['tipo']); ?></span>
                        </div>
                    <?php endif; ?>
                    <!-- Platform Badge -->
                    <div style="position: absolute; top: 12px; left: 12px; background: rgba(0,0,0,0.7); padding: 6px 12px; display: flex; align-items: center; gap: 8px;">
                        <i class="<?php echo $pi['icon']; ?>" style="font-size: var(--fs-base); color: <?php echo $pi['color']; ?>;"></i>
                        <span style="font-size: var(--fs-xs); font-weight: 900; color: #fff; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo ucfirst($post['plataforma']); ?></span>
                    </div>
                    <!-- Status Badge -->
                    <div style="position: absolute; top: 12px; right: 12px; background: <?php echo $sc['bg']; ?>; color: <?php echo $sc['color']; ?>; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.07em;">
                        <?php echo strtoupper($post['status']); ?>
                    </div>
                </div>

                <!-- Conteúdo -->
                <div style="padding: 20px; flex: 1; display: flex; flex-direction: column; gap: 10px;">
                    <p style="font-size: var(--fs-sm); color: var(--text-dark); font-weight: 600; line-height: 1.5; margin: 0; flex: 1;">
                        <?php echo htmlspecialchars(mb_strimwidth($post['legenda'] ?? '(sem legenda)', 0, 100, '...')); ?>
                    </p>

                    <?php if (!empty($post['data_publicacao'])): ?>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: var(--fs-xs); font-weight: 700; color: var(--text-muted);">
                        <i class="fa-solid fa-calendar-day"></i>
                        <?php echo date('d/m/Y H:i', strtotime($post['data_publicacao'])); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Engajamento -->
                    <div style="display: flex; gap: 15px; padding-top: 10px; ">
                        <span style="font-size: var(--fs-xs); font-weight: 700; color: #e11d48; display: flex; align-items: center; gap: 5px;">
                            <i class="fa-solid fa-heart"></i> <?php echo number_format($post['curtidas']); ?>
                        </span>
                        <span style="font-size: var(--fs-xs); font-weight: 700; color: #3b82f6; display: flex; align-items: center; gap: 5px;">
                            <i class="fa-solid fa-comment"></i> <?php echo number_format($post['comentarios']); ?>
                        </span>
                        <span style="font-size: var(--fs-xs); font-weight: 700; color: #8b5cf6; display: flex; align-items: center; gap: 5px;">
                            <i class="fa-solid fa-users"></i> <?php echo number_format($post['alcance']); ?>
                        </span>
                    </div>

                    <!-- Ações -->
                    <div style="display: flex; gap: 8px; margin-top: 5px;">
                        <a href="?edit_id=<?php echo $post['id']; ?>" onclick="abrirModal(<?php echo htmlspecialchars(json_encode($post)); ?>); return false;" class="btn-sq-light" style="flex: 1; height: 38px; font-size: var(--fs-xs); display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <i class="fa-solid fa-pen-nib"></i> Editar
                        </a>
                        <a href="?delete_id=<?php echo $post['id']; ?>" onclick="return confirm('Excluir este post?')" class="btn-sq-light" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; color: #ef4444;">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Modal: Novo / Editar Post -->
<div id="modalOverlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9998; backdrop-filter: blur(2px);" onclick="fecharModal(event)">
    <div style="position: absolute; right: 0; top: 0; bottom: 0; width: 520px; max-width: 100%; background: #fff; overflow-y: auto; box-shadow: -10px 0 40px rgba(0,0,0,0.2);" onclick="event.stopPropagation()">
        <!-- Header do Modal -->
        <div style="padding: 30px 35px; border-bottom: 1px solid var(--border-color); background: #08153a; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 10;">
            <div>
                <h2 id="modalTitulo" style="font-size: 1rem; font-weight: 900; text-transform: uppercase; color: #fff; margin: 0; letter-spacing: 0.05em;">Novo Post</h2>
                <p style="font-size: var(--fs-xs); color: #94a3b8; margin: 4px 0 0 0; font-weight: 600; text-transform: uppercase;">Social Media Manager</p>
            </div>
            <button onclick="fecharModalBtn()" style="background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; padding: 5px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Formulário -->
        <form method="POST" enctype="multipart/form-data" style="padding: 35px; display: grid; gap: 22px;">
            <input type="hidden" name="salvar_post" value="1">
            <input type="hidden" name="post_id" id="input_post_id" value="">
            <input type="hidden" name="imagem_atual" id="input_imagem_atual" value="">

            <!-- Plataforma -->
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:10px; text-transform:uppercase; letter-spacing: 0.05em;">Plataforma</label>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;" id="plataforma_grid">
                    <?php
                    $platforms = [
                        'instagram' => ['label'=>'Instagram', 'icon'=>'fa-brands fa-instagram', 'color'=>'#e1306c'],
                        'facebook'  => ['label'=>'Facebook',  'icon'=>'fa-brands fa-facebook',  'color'=>'#1877f2'],
                        'tiktok'    => ['label'=>'TikTok',    'icon'=>'fa-brands fa-tiktok',    'color'=>'#08153a'],
                        'youtube'   => ['label'=>'YouTube',   'icon'=>'fa-brands fa-youtube',   'color'=>'#ff0000'],
                        'linkedin'  => ['label'=>'LinkedIn',  'icon'=>'fa-brands fa-linkedin',  'color'=>'#0077b5'],
                        'whatsapp'  => ['label'=>'WhatsApp',  'icon'=>'fa-brands fa-whatsapp',  'color'=>'#25d366'],
                    ];
                    foreach ($platforms as $val => $p):
                    ?>
                    <label style="cursor: pointer;">
                        <input type="radio" name="plataforma" value="<?php echo $val; ?>" style="display: none;" class="plataforma_radio">
                        <div class="plataforma_btn" data-val="<?php echo $val; ?>" style="border: 2px solid var(--border-color); padding: 12px 8px; text-align: center; transition: all 0.15s;">
                            <i class="<?php echo $p['icon']; ?>" style="font-size: 20px; color: <?php echo $p['color']; ?>; display: block; margin-bottom: 6px;"></i>
                            <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); text-transform: uppercase;"><?php echo $p['label']; ?></span>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tipo de Conteúdo -->
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Tipo de Conteúdo</label>
                <select name="tipo" id="input_tipo" style="width:100%; height: 45px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark); appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                    <option value="feed">FEED / POST</option>
                    <option value="stories">STORIES</option>
                    <option value="reels">REELS / SHORT</option>
                    <option value="carrossel">CARROSSEL</option>
                    <option value="video">VÍDEO</option>
                </select>
            </div>

            <!-- Legenda -->
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Legenda / Texto do Post</label>
                <textarea name="legenda" id="input_legenda" rows="5" placeholder="Escreva a legenda do post aqui..." style="width:100%; background: #fafafa; border: 1px solid var(--border-color); padding: 15px; font-size: var(--fs-sm); font-weight: 500; line-height: 1.6; resize: vertical;"></textarea>
            </div>

            <!-- Hashtags -->
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Hashtags</label>
                <input type="text" name="hashtags" id="input_hashtags" placeholder="#jiujitsu #academia #treino" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 600;">
            </div>

            <!-- Upload de Imagem -->
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Imagem / Capa</label>
                <div id="preview_imagem" style="display:none; margin-bottom: 10px; border: 1px solid var(--border-color); height: 150px; overflow: hidden; background: #08153a;">
                    <img id="preview_img_tag" src="" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div style="position: relative; height: 45px; border: 1px solid var(--border-color); background: #fafafa; display: flex; align-items: center; justify-content: center; overflow: hidden; cursor: pointer;">
                    <i class="fa-solid fa-upload" style="margin-right: 8px; color: #94a3b8;"></i>
                    <span style="font-size: var(--fs-xs); font-weight: 900; color: #64748b; text-transform: uppercase;">Selecionar Imagem</span>
                    <input type="file" name="imagem" accept="image/*" style="position: absolute; inset: 0; opacity: 0; cursor: pointer;" onchange="previewImagem(this)">
                </div>
            </div>

            <!-- Grid: Status + Data -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Status</label>
                    <select name="status" id="input_status" style="width:100%; height: 45px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark); appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                        <option value="rascunho">RASCUNHO</option>
                        <option value="agendado">AGENDADO</option>
                        <option value="publicado">PUBLICADO</option>
                        <option value="arquivado">ARQUIVADO</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Data / Hora</label>
                    <input type="datetime-local" name="data_publicacao" id="input_data" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 600;">
                </div>
            </div>

            <!-- Grid: Métricas (Engajamento) -->
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:10px; text-transform:uppercase; letter-spacing: 0.05em;">Métricas de Engajamento</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:#e11d48; margin-bottom:5px; text-transform:uppercase;"><i class="fa-solid fa-heart"></i> Curtidas</label>
                        <input type="number" name="curtidas" id="input_curtidas" value="0" style="width:100%; height: 40px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 10px; font-size: var(--fs-base); font-weight: 800; color: #e11d48;">
                    </div>
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:#3b82f6; margin-bottom:5px; text-transform:uppercase;"><i class="fa-solid fa-comment"></i> Comentários</label>
                        <input type="number" name="comentarios" id="input_comentarios" value="0" style="width:100%; height: 40px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 10px; font-size: var(--fs-base); font-weight: 800; color: #3b82f6;">
                    </div>
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:#8b5cf6; margin-bottom:5px; text-transform:uppercase;"><i class="fa-solid fa-users"></i> Alcance</label>
                        <input type="number" name="alcance" id="input_alcance" value="0" style="width:100%; height: 40px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 10px; font-size: var(--fs-base); font-weight: 800; color: #8b5cf6;">
                    </div>
                </div>
            </div>

            <!-- Botões -->
            <div style="display: flex; gap: 12px; padding-top: 10px; ">
                <button type="submit" class="btn-sq" style="flex: 1; height: 52px; font-size: var(--fs-sm);">
                    <i class="fa-solid fa-check" style="margin-right: 8px;"></i> SALVAR POST
                </button>
                <button type="button" onclick="fecharModalBtn()" class="btn-sq-light" style="height: 52px; padding: 0 25px; font-size: var(--fs-sm);">CANCELAR</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal(post = null) {
    // Reset
    document.getElementById('input_post_id').value = '';
    document.getElementById('input_imagem_atual').value = '';
    document.getElementById('input_legenda').value = '';
    document.getElementById('input_hashtags').value = '';
    document.getElementById('input_status').value = 'rascunho';
    document.getElementById('input_tipo').value = 'feed';
    document.getElementById('input_data').value = '';
    document.getElementById('input_curtidas').value = 0;
    document.getElementById('input_comentarios').value = 0;
    document.getElementById('input_alcance').value = 0;
    document.getElementById('preview_imagem').style.display = 'none';
    document.querySelectorAll('.plataforma_btn').forEach(b => b.style.border = '2px solid var(--border-color)');
    document.querySelectorAll('.plataforma_radio').forEach(r => r.checked = false);
    // Selecionar instagram por padrão
    const igRadio = document.querySelector('input[name="plataforma"][value="instagram"]');
    if (igRadio) { igRadio.checked = true; highlightPlataforma('instagram'); }

    if (post) {
        document.getElementById('modalTitulo').innerText = 'EDITAR POST';
        document.getElementById('input_post_id').value = post.id;
        document.getElementById('input_legenda').value = post.legenda || '';
        document.getElementById('input_hashtags').value = post.hashtags || '';
        document.getElementById('input_status').value = post.status || 'rascunho';
        document.getElementById('input_tipo').value = post.tipo || 'feed';
        document.getElementById('input_curtidas').value = post.curtidas || 0;
        document.getElementById('input_comentarios').value = post.comentarios || 0;
        document.getElementById('input_alcance').value = post.alcance || 0;
        if (post.imagem) {
            document.getElementById('input_imagem_atual').value = post.imagem;
            document.getElementById('preview_img_tag').src = '../uploads/cms/' + post.imagem;
            document.getElementById('preview_imagem').style.display = 'block';
        }
        if (post.data_publicacao) {
            document.getElementById('input_data').value = post.data_publicacao.replace(' ', 'T').slice(0,16);
        }
        const radio = document.querySelector('input[name="plataforma"][value="' + post.plataforma + '"]');
        if (radio) { radio.checked = true; highlightPlataforma(post.plataforma); }
    } else {
        document.getElementById('modalTitulo').innerText = 'NOVO POST';
    }

    document.getElementById('modalOverlay').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function fecharModalBtn() {
    document.getElementById('modalOverlay').style.display = 'none';
    document.body.style.overflow = '';
}

function fecharModal(e) {
    if (e.target === document.getElementById('modalOverlay')) fecharModalBtn();
}

function highlightPlataforma(val) {
    document.querySelectorAll('.plataforma_btn').forEach(b => {
        b.style.border = b.dataset.val === val ? '2px solid #08153a' : '2px solid var(--border-color)';
        b.style.background = b.dataset.val === val ? '#f9fafb' : '#fff';
    });
}

document.querySelectorAll('.plataforma_radio').forEach(r => {
    r.addEventListener('change', () => highlightPlataforma(r.value));
});

document.querySelectorAll('.plataforma_btn').forEach(btn => {
    btn.addEventListener('click', () => highlightPlataforma(btn.dataset.val));
});

function previewImagem(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('preview_img_tag').src = e.target.result;
            document.getElementById('preview_imagem').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

<?php if ($edit_post): ?>
window.addEventListener('DOMContentLoaded', () => abrirModal(<?php echo json_encode($edit_post); ?>));
<?php endif; ?>
</script>

<?php include 'footer.php'; ?>
