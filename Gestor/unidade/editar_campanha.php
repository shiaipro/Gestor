<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: marketing_campanhas.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM marketing_campanhas WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$campanha = $stmt->fetch();

if (!$campanha) {
    header('Location: marketing_campanhas.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $plataforma = $_POST['plataforma'] ?? '';
    $investimento = $_POST['investimento'] ?: 0;
    $leads = $_POST['leads_gerados'] ?: 0;
    $status = $_POST['status'] ?? 'ativa';
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');

    if ($nome && $plataforma) {
        try {
            $stmt = $pdo->prepare("UPDATE marketing_campanhas SET nome = ?, plataforma = ?, investimento = ?, leads_gerados = ?, status = ?, data_inicio = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$nome, $plataforma, $investimento, $leads, $status, $data_inicio, $id, $unidade_id]);
            $sucesso = "Campanha atualizada com sucesso!";
            header("refresh:1;url=marketing_campanhas.php");
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o nome e a plataforma da campanha.";
    }
}

$custom_title = "Editar Campanha";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Editar Campanha
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Ajuste os parâmetros e monitore o desempenho da campanha: <strong><?php echo htmlspecialchars($campanha['nome']); ?></strong>
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="marketing_campanhas.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar às Campanhas
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="marketing_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="marketing_campanhas.php" class="tab-item-sq active">Campanhas</a>
    <a href="marketing_social_media.php" class="tab-item-sq">Social Media</a>
    <a href="marketing_website.php" class="tab-item-sq">Website</a>
</div>

    

    <?php if ($erro): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 15px 25px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 700; font-size: var(--fs-base);">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px 25px; border-left: 4px solid var(--primary-green); margin-bottom: 30px; font-weight: 700; font-size: var(--fs-base);">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $sucesso; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 30px; align-items: start;">
            
            <!-- Coluna Principal (Esquerda) -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="dashboard-container" style="padding: 40px;">
                    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                        <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Configuração da Campanha</h2>
                    </div>

                    <div style="display: grid; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Nome da Campanha</label>
                            <input type="text" name="nome" value="<?php echo htmlspecialchars($campanha['nome']); ?>" required placeholder="Ex: Campanha de Verão 2024" style="width:100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 800; color: var(--text-dark);">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                            <div>
                                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Plataforma Anunciada</label>
                                <select name="plataforma" style="width: 100%; height: 45px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 600; color: var(--text-dark); appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                                    <option value="Meta Ads (Instagram/FB)" <?php echo $campanha['plataforma'] == 'Meta Ads (Instagram/FB)' ? 'selected' : ''; ?>>META ADS (INSTA/FB)</option>
                                    <option value="Google Ads" <?php echo $campanha['plataforma'] == 'Google Ads' ? 'selected' : ''; ?>>GOOGLE ADS</option>
                                    <option value="TikTok Ads" <?php echo $campanha['plataforma'] == 'TikTok Ads' ? 'selected' : ''; ?>>TIKTOK ADS</option>
                                    <option value="YouTube Ads" <?php echo $campanha['plataforma'] == 'YouTube Ads' ? 'selected' : ''; ?>>YOUTUBE ADS</option>
                                    <option value="Outro" <?php echo $campanha['plataforma'] == 'Outro' ? 'selected' : ''; ?>>OUTRA PLATAFORMA</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Data de Início</label>
                                <input type="date" name="data_inicio" value="<?php echo $campanha['data_inicio']; ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-container" style="padding: 40px;">
                    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                        <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Performance e Métricas</h2>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Investimento Total (R$)</label>
                            <input type="number" step="0.01" name="investimento" value="<?php echo $campanha['investimento']; ?>" placeholder="0,00" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 800; color: var(--text-dark);">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Total de Leads Gerados</label>
                            <input type="number" name="leads_gerados" value="<?php echo $campanha['leads_gerados']; ?>" placeholder="0" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 800; color: var(--primary-green);">
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 10px;">
                    <button type="submit" class="btn-sq" style="flex: 1; height: 55px; font-size: var(--fs-base);">ATUALIZAR CAMPANHA</button>
                    <a href="marketing_campanhas.php" class="btn-sq-light" style="width: 150px; height: 55px; font-size: var(--fs-base); display: flex; align-items: center; justify-content: center; text-decoration: none;">CANCELAR</a>
                </div>
            </div>

            <!-- Coluna Sidebar (Direita) -->
            <div style="display: flex; flex-direction: column; gap: 30px; position: sticky; top: 30px;">
                <!-- Status da Campanha -->
                <div class="dashboard-container" style="padding: 35px;">
                    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                        <h2 style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1; color: var(--text-muted); letter-spacing: 0.1em;">Status Atual</h2>
                    </div>
                    <select name="status" style="width: 100%; height: 45px; border: 1px solid var(--border-color); background: #fafafa; padding: 0 15px; font-size: var(--fs-sm); font-weight: 900; color: var(--text-dark); appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem; text-transform: uppercase;">
                        <option value="ativa" <?php echo $campanha['status'] == 'ativa' ? 'selected' : ''; ?>>ATIVA / RODANDO</option>
                        <option value="pausada" <?php echo $campanha['status'] == 'pausada' ? 'selected' : ''; ?>>PAUSADA</option>
                        <option value="finalizada" <?php echo $campanha['status'] == 'finalizada' ? 'selected' : ''; ?>>FINALIZADA</option>
                    </select>
                </div>

                <!-- Dicas de Performance -->
                <div class="dashboard-container" style="padding: 35px; background: #08153a; border: none;">
                    <div style="border-left: 4px solid var(--primary-green); padding-left: 15px; margin-bottom: 25px;">
                        <h2 style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1; color: #fff; letter-spacing: 0.1em;">Smart Tips</h2>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div style="padding-bottom: 15px; border-bottom: 1px solid #334155;">
                            <p style="font-weight: 900; font-size: var(--fs-xs); color: var(--primary-green); margin-bottom: 8px; text-transform: uppercase;">Acompanhamento do CPL</p>
                            <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.5;">Seu Custo Por Lead ideal deve ser menor que o seu CAC. Mantenha os olhos nos números diariamente.</p>
                        </div>

                        <div style="padding-bottom: 15px; border-bottom: 1px solid #334155;">
                            <p style="font-weight: 900; font-size: var(--fs-xs); color: var(--primary-green); margin-bottom: 8px; text-transform: uppercase;">Segmentação de Público</p>
                            <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.5;">Utilize o Pixel do site para criar públicos de "Lookalike". Isso otimiza a entrega para perfis semelhantes aos seus alunos.</p>
                        </div>

                        <div style="padding: 15px; background: #1e293b; border-left: 3px solid var(--primary-green);">
                            <p style="font-size: var(--fs-xs); color: #fff; line-height: 1.5; font-weight: 600; font-style: italic;">
                                "O que não pode ser medido, não pode ser gerenciado." - Peter Drucker
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>

<style>
    .form-control:focus {
        border-color: #a3e635 !important;
        background: #fff !important;
        outline: none;
        box-shadow: 0 0 0 4px rgba(163, 230, 53, 0.1);
    }
</style>

<?php include 'footer.php'; ?>