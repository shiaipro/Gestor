<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $data = $_POST['data_planejada'] ?? date('Y-m-d');
    $plataforma = $_POST['plataforma'] ?? 'Instagram';
    $status = $_POST['status'] ?? 'planejado';

    if ($titulo && $data) {
        try {
            $stmt = $pdo->prepare("INSERT INTO marketing_social_media (unidade_id, titulo, data_planejada, plataforma, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $titulo, $data, $plataforma, $status]);
            $sucesso = "Postagem agendada com sucesso!";
            header("refresh:1;url=marketing_social.php");
        } catch (PDOException $e) {
            $erro = "Erro ao agendar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o título e a data da postagem.";
    }
}

$custom_title = "Novo Post Social";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Novo Post
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Agendar Novo Conteúdo Social
            </p>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <a href="marketing_social.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr; gap: 40px; align-items: start; max-width: 800px;">
            <div class="dashboard-container" style="padding: 40px;">
                <?php if ($sucesso): ?>
                    <div style="background: var(--primary-green); color: #fff; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;"><?php echo $sucesso; ?></div>
                <?php endif; ?>

                <?php if ($erro): ?>
                    <div style="background: #ef4444; color: #fff; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;"><?php echo $erro; ?></div>
                <?php endif; ?>
                <div style="display: grid; gap: 25px;">
                    <div>
                        <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Título da Postagem / Tema Criativo</label>
                        <input type="text" name="titulo" class="form-control" style="font-size: 0.85rem;" placeholder="EX: VÍDEO MOTIVACIONAL - TREINO DE SEGUNDA" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Data de Publicação</label>
                            <input type="date" name="data_planejada" value="<?php echo date('Y-m-d'); ?>" class="form-control" style="font-size: 0.85rem;" required>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Plataforma Destino</label>
                            <select name="plataforma" class="form-control" style="font-size: 0.85rem;">
                                <option value="Instagram">INSTAGRAM</option>
                                <option value="Facebook">FACEBOOK</option>
                                <option value="TikTok">TIKTOK</option>
                                <option value="YouTube">YOUTUBE</option>
                                <option value="Outro">OUTRO</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Status do Planejamento</label>
                        <select name="status" class="form-control" style="font-size: 0.85rem;">
                            <option value="planejado">PLANEJADO (AGENDADO)</option>
                            <option value="publicado">PUBLICADO</option>
                            <option value="cancelado">CANCELADO</option>
                        </select>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">AGENDAR POSTAGEM</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>