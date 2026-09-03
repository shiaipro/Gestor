<?php
require_once '../config.php';

$unidade_id = getUnidadeId();

// 1. Auto-Migração para Campanhas
try {
    $pdo->query("SELECT 1 FROM marketing_campanhas LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS marketing_campanhas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        plataforma VARCHAR(50),
        investimento DECIMAL(10,2) DEFAULT 0.00,
        leads_gerados INT DEFAULT 0,
        status ENUM('ativa', 'pausada', 'finalizada') DEFAULT 'ativa',
        data_inicio DATE,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

// Processar Exclusão
if (isset($_POST['excluir_id'])) {
    $stmt = $pdo->prepare("DELETE FROM marketing_campanhas WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$_POST['excluir_id'], $unidade_id]);
    $mensagem = "Campanha removida!";
}

// Buscar Campanhas
$stmt = $pdo->prepare("SELECT * FROM marketing_campanhas WHERE unidade_id = ? ORDER BY data_inicio DESC");
$stmt->execute([$unidade_id]);
$campanhas = $stmt->fetchAll();

$custom_title = "Campanhas de Marketing";
include 'header.php';
?>

<!-- Navegação por Abas -->
<!-- Navegação por Abas (Dinâmica) -->


<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Gestão de Campanhas
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Acompanhe o desempenho de seus anúncios e o custo de aquisição de leads.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="marketing_relatorios.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-chart-line" style="margin-right: 8px;"></i> ROI & Relatórios
            </a>
            <a href="nova_campanha.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Nova Campanha
            </a>
        </div>
    </div>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <?php if (isset($_GET['menu']) && $_GET['menu'] === 'comercial'): ?>
        <a href="comercial_dashboard.php" class="tab-item-sq">Início</a>
        <a href="crm_vendas.php" class="tab-item-sq">Vendas</a>
        <a href="crm_pos_vendas.php" class="tab-item-sq">Pós Vendas</a>
        <a href="marketing_leads.php" class="tab-item-sq">CRM</a>
        <a href="mensagens.php" class="tab-item-sq">Mensagens</a>
        <a href="marketing_campanhas.php?menu=comercial" class="tab-item-sq active">Funis de Vendas</a>
    <?php else: ?>
        <a href="marketing_dashboard.php" class="tab-item-sq">Dashboard</a>
        <a href="marketing_campanhas.php" class="tab-item-sq active">Campanhas</a>
        <a href="marketing_social_media.php" class="tab-item-sq">Social Media</a>
        <a href="marketing_agenda.php" class="tab-item-sq">Offline</a>
    <?php endif; ?>
</div>

    

    <?php if (isset($mensagem)): ?>
        <div style="background: #E8F5E9; color: #2E7D32; padding: 20px; border: 1px solid #C8E6C9; margin-bottom: 30px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-check"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Campanha / Plataforma</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Status</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Investimento</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Leads</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">CPL (MÉDIO)</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right; letter-spacing: 0.05em;">Gerenciar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campanhas as $c):
                    $cpl = $c['leads_gerados'] > 0 ? $c['investimento'] / $c['leads_gerados'] : 0;
                    ?>
                    <tr>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;">
                                <?php echo htmlspecialchars($c['nome']); ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: #3b82f6; font-weight: 900; text-transform: uppercase; margin-top: 3px; letter-spacing: 0.03em;">
                                <?php echo htmlspecialchars($c['plataforma']); ?>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <span style="background: <?php echo $c['status'] == 'ativa' ? 'var(--primary-green)' : '#94a3b8'; ?>; color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                                <?php echo $c['status']; ?>
                            </span>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-sm);">R$ <?php echo number_format($c['investimento'], 2, ',', '.'); ?></div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-align: center; background: #fafafa; border: 1px solid var(--border-color); display: inline-block; padding: 5px 15px;">
                                <?php echo $c['leads_gerados']; ?>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 900; color: <?php echo $cpl > 15 ? '#ef4444' : 'var(--primary-green)'; ?>; font-size: var(--fs-sm);">
                                R$ <?php echo number_format($cpl, 2, ',', '.'); ?>
                            </div>
                        </td>
                        <td style="padding: 15px 30px; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                <button onclick="abrirModalCaptura(<?php echo $c['id']; ?>, '<?php echo addslashes($c['nome']); ?>')" 
                                    class="btn-sq-light" style="width: auto; height: 35px; padding: 0 15px; font-size: var(--fs-xs); text-transform: uppercase; font-weight: 900;">
                                    <i class="fa-solid fa-code"></i> INTEGRAR
                                </button>
                                <a href="editar_campanha.php?id=<?php echo $c['id']; ?>" class="btn-sq-light"
                                    style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;"
                                    title="Editar">
                                    <i class="fa-solid fa-pen-nib" style="font-size: var(--fs-sm);"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir esta campanha?')">
                                    <input type="hidden" name="excluir_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn-sq-light"
                                        style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; color: #ef4444;">
                                        <i class="fa-solid fa-trash-can" style="font-size: var(--fs-sm);"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($campanhas)): ?>
                    <tr>
                        <td colspan="6" style="padding: 60px; text-align: center; color: var(--text-muted); font-weight: 600;">
                            <i class="fa-solid fa-bullseye" style="font-size: 40px; opacity: 0.1; display: block; margin-bottom: 15px;"></i>
                            Nenhuma campanha encontrada.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<!-- Modal Captura de Leads (Versão Square) -->
<div id="modal_captura" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:10001; align-items:center; justify-content:center; backdrop-filter: blur(8px);">
    <div class="dashboard-container" style="width:100%; max-width:650px; padding:40px; position:relative; animation: slideUp 0.3s ease;">
        <button onclick="document.getElementById('modal_captura').style.display='none'" 
                style="position:absolute; top:20px; right:20px; border:none; background:none; cursor:pointer; font-size:24px; color:var(--text-dark); font-weight: 300;">&times;</button>
        
        <div style="border-left: 6px solid var(--primary-green); padding-left: 20px; margin-bottom: 30px;">
            <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;"><i class="fa-solid fa-bullhorn" style="margin-right: 15px; color: var(--primary-green);"></i> <span id="campanha_nome_modal"></span></h2>
            <p style="color:var(--text-muted); margin: 8px 0 0 0; font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Integração de Captação Externa</p>
        </div>

        <div style="margin-top: 30px;">
            <div style="margin-bottom: 25px;">
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; text-transform:uppercase; margin-bottom:10px; color: var(--text-muted); letter-spacing: 0.05em;">Link Direto (WhatsApp / Bio)</label>
                <div style="display:flex;">
                    <input type="text" id="link_direto" readonly style="flex: 1; height: 50px; background:#fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark);">
                    <button onclick="copy('link_direto')" class="btn-sq" style="width: auto; padding: 0 25px;">COPIAR</button>
                </div>
            </div>

            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; text-transform:uppercase; margin-bottom:10px; color: var(--text-muted); letter-spacing: 0.05em;">Código Iframe (Site)</label>
                <textarea id="html_codigo" readonly style="width: 100%; height: 100px; background:#fafafa; border: 1px solid var(--border-color); padding: 15px; font-family: 'JetBrains Mono', monospace; font-size: var(--fs-xs); color: #1e293b; resize: none; margin-bottom: 15px; font-weight: 600;"></textarea>
                <button onclick="copy('html_codigo')" class="btn-sq-light" style="width: 100%; padding: 15px; font-size: var(--fs-xs); font-weight: 900;">COPIAR CÓDIGO EMBARCADO</button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
function abrirModalCaptura(id, nome) {
    const baseUrl = window.location.origin;
    const projectPath = window.location.pathname.replace('/unidade/marketing_campanhas.php', '');
    const url = baseUrl + projectPath + '/Gestor/marketing_v1.php?cid=' + id;
    
    document.getElementById('campanha_nome_modal').innerText = nome;
    document.getElementById('link_direto').value = url;
    document.getElementById('html_codigo').value = `<iframe src="${url}" width="100%" height="600px" frameborder="0"></iframe>`;
    document.getElementById('modal_captura').style.display = 'flex';
}

function copy(id) {
    const el = document.getElementById(id);
    el.select();
    document.execCommand('copy');
    alert('Copiado para a área de transferência!');
}
</script>

<?php include 'footer.php'; ?>