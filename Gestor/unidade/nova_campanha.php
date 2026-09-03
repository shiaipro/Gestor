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
    $nome = $_POST['nome'] ?? '';
    $plataforma = $_POST['plataforma'] ?? '';
    $investimento = $_POST['investimento'] ?: 0;
    $leads = $_POST['leads_gerados'] ?: 0;
    $status = $_POST['status'] ?? 'ativa';
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');

    if ($nome && $plataforma) {
        try {
            $stmt = $pdo->prepare("INSERT INTO marketing_campanhas (unidade_id, nome, plataforma, investimento, leads_gerados, status, data_inicio) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $nome, $plataforma, $investimento, $leads, $status, $data_inicio]);
            $sucesso = "Campanha cadastrada com sucesso!";
            header("refresh:1;url=marketing_campanhas.php");
        } catch (PDOException $e) {
            $erro = "Erro ao cadastrar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o nome e a plataforma da campanha.";
    }
}

$custom_title = "Nova Campanha";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Nova Campanha
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Criar Nova Campanha de Marketing e Tráfego
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="marketing_campanhas.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
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

    

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 40px; align-items: start;">
            <div class="dashboard-container" style="padding: 40px;">
                <?php if ($sucesso): ?>
                    <div style="background: var(--primary-green); color: #fff; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;"><?php echo $sucesso; ?></div>
                <?php endif; ?>

                <?php if ($erro): ?>
                    <div style="background: #ef4444; color: #fff; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;"><?php echo $erro; ?></div>
                <?php endif; ?>

                <div style="display: grid; gap: 25px;">
                    <div>
                        <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Identificação da Campanha</label>
                        <input type="text" name="nome" class="form-control" style="font-size: 0.85rem;" placeholder="EX: BLACK FRIDAY 2026 - GOOGLE" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Plataforma de Anúncios</label>
                            <select name="plataforma" class="form-control" style="font-size: 0.85rem;">
                                <option value="Meta Ads (Instagram/FB)">META ADS (IG/FB)</option>
                                <option value="Google Ads">GOOGLE ADS</option>
                                <option value="TikTok Ads">TIKTOK ADS</option>
                                <option value="YouTube Ads">YOUTUBE ADS</option>
                                <option value="Outro">OUTRO</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Data de Lançamento</label>
                            <input type="date" name="data_inicio" value="<?php echo date('Y-m-d'); ?>" class="form-control" style="font-size: 0.85rem;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Investimento Inicial (R$)</label>
                            <input type="number" step="0.01" name="investimento" class="form-control" style="font-size: 0.85rem;" placeholder="0,00">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Leads Gerados (Estimado)</label>
                            <input type="number" name="leads_gerados" class="form-control" style="font-size: 0.85rem;" placeholder="0">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.70rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Status da Campanha</label>
                        <select name="status" class="form-control" style="font-size: 0.85rem;">
                            <option value="ativa">ATIVA / LANÇADA</option>
                            <option value="pausada">PAUSADA</option>
                            <option value="finalizada">FINALIZADA</option>
                        </select>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">LANÇAR CAMPANHA</button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR DE DICAS -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">
                        Diretrizes Estratégicas
                    </h3>

                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Objetivo ROI</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500; margin: 0;">Mantenha o custo por lead (CPL) abaixo de R$ 15,00 para garantir a saúde financeira da operação.</p>
                        </div>
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Teste de Criativo</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500; margin: 0;">Recomendamos testar ao menos 3 variações de anúncios antes de escalar o investimento.</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Lembrete Sazonal</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic; margin: 0;">Campanhas de "Verão" e "Janeiro" costumam ter 40% mais cliques em unidades fitness.</p>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>