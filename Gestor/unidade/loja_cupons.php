<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Deletar Cupom
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM loja_cupons WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$id, $unidade_id]);
    $mensagem = "Cupom removido com sucesso!";
}

// Salvar/Editar Cupom
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_coupon'])) {
    $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
    $codigo = strtoupper(trim($_POST['codigo']));
    $tipo = $_POST['tipo'];
    $valor = (float) $_POST['valor'];
    $data_expiracao = !empty($_POST['data_expiracao']) ? $_POST['data_expiracao'] : null;
    $limite_uso = (int) $_POST['limite_uso'];
    $status = $_POST['status'];

    if ($id) {
        $stmt = $pdo->prepare("UPDATE loja_cupons SET codigo = ?, tipo = ?, valor = ?, data_expiracao = ?, limite_uso = ?, status = ? WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$codigo, $tipo, $valor, $data_expiracao, $limite_uso, $status, $id, $unidade_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO loja_cupons (unidade_id, codigo, tipo, valor, data_expiracao, limite_uso, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$unidade_id, $codigo, $tipo, $valor, $data_expiracao, $limite_uso, $status]);
    }
    $mensagem = "Cupom salvo com sucesso!";
}

// Listar Cupons
$stmt = $pdo->prepare("SELECT * FROM loja_cupons WHERE unidade_id = ? ORDER BY criado_em DESC");
$stmt->execute([$unidade_id]);
$cupons = $stmt->fetchAll();

$custom_title = "Gestão de Cupons";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Cupons de Desconto
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Crie e gerencie códigos promocionais para impulsionar suas vendas.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <button onclick="document.getElementById('modalCoupon').style.display='flex'" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Novo Cupom
            </button>
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
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Código</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Tipo / Valor</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Expiração</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Usos</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Status</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right; letter-spacing: 0.05em;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cupons)): ?>
                    <tr>
                        <td colspan="6" style="padding: 60px; text-align: center; color: var(--text-muted); font-weight: 600;">
                            <i class="fa-solid fa-ticket" style="font-size: 40px; opacity: 0.1; display: block; margin-bottom: 15px;"></i>
                            Nenhum cupom cadastrado.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($cupons as $c): ?>
                    <tr>
                        <td style="padding: 15px 30px;">
                            <span style="font-weight: 900; color: var(--primary-green); font-size: var(--fs-base); letter-spacing: 1px;"><?php echo htmlspecialchars($c['codigo']); ?></span>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 800; color: var(--text-dark); font-size: var(--fs-base);">
                                <?php echo $c['tipo'] == 'fixo' ? 'R$ ' . number_format($c['valor'], 2, ',', '.') : number_format($c['valor'], 0) . '%'; ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-top: 2px;">
                                <?php echo $c['tipo'] == 'fixo' ? 'Desconto Fixo' : 'Desconto Percentual'; ?>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 700; color: var(--text-dark); font-size: var(--fs-sm);">
                                <?php echo $c['data_expiracao'] ? date('d/m/Y', strtotime($c['data_expiracao'])) : 'Ilimitado'; ?>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 60px; height: 8px; background: #f1f5f9; position: relative;">
                                    <?php 
                                    $percent = $c['limite_uso'] > 0 ? ($c['usos'] / $c['limite_uso']) * 100 : 0;
                                    echo '<div style="position: absolute; left: 0; top: 0; height: 100%; width: '.min($percent, 100).'%; background: var(--primary-green);"></div>';
                                    ?>
                                </div>
                                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark);"><?php echo $c['usos']; ?> <small style="color:var(--text-muted);">/ <?php echo $c['limite_uso'] ?: '∞'; ?></small></span>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <span class="badge-sq" style="background: <?php echo $c['status'] == 'ativo' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $c['status'] == 'ativo' ? '#166534' : '#991b1b'; ?>; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase;">
                                <?php echo $c['status']; ?>
                            </span>
                        </td>
                        <td style="padding: 15px 30px; text-align: right;">
                            <a href="?delete_id=<?php echo $c['id']; ?>" onclick="return confirm('Excluir este cupom?')"
                               class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: inline-flex; align-items: center; justify-content: center; color: #ef4444;" title="Excluir">
                                <i class="fa-solid fa-trash-can" style="font-size: var(--fs-sm);"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<!-- Modal para Novo Cupom (Versão Square) -->
<div id="modalCoupon" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 10001; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div class="dashboard-container" style="width: 100%; max-width: 500px; padding: 40px; position: relative; animation: slideUp 0.3s ease;">
        <button onclick="document.getElementById('modalCoupon').style.display='none'" 
                style="position:absolute; top:20px; right:20px; border:none; background:none; cursor:pointer; font-size:24px; color:var(--text-dark); font-weight: 300;">&times;</button>

        <div style="border-left: 6px solid var(--primary-green); padding-left: 20px; margin-bottom: 30px;">
            <h2 style="font-size: 1.5rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Novo Cupom</h2>
            <p style="color:var(--text-muted); margin: 8px 0 0 0; font-size: var(--fs-sm); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Regras Promocionais</p>
        </div>

        <form method="POST">
            <input type="hidden" name="save_coupon" value="1">
            <div style="display: grid; gap: 20px;">
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; text-transform:uppercase; margin-bottom:8px; color: var(--text-muted);">Código do Cupom</label>
                    <input type="text" name="codigo" required placeholder="EX: VERÃO2026" style="width: 100%; height: 50px; background:#f8fafc; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 900; color: var(--primary-green); text-transform: uppercase; letter-spacing: 1px;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; text-transform:uppercase; margin-bottom:8px; color: var(--text-muted);">Tipo</label>
                        <select name="tipo" style="width: 100%; height: 50px; background:#f8fafc; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 600;">
                            <option value="porcentagem">Porcentagem %</option>
                            <option value="fixo">Valor Fixo R$</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; text-transform:uppercase; margin-bottom:8px; color: var(--text-muted);">Valor</label>
                        <input type="number" step="0.01" name="valor" required style="width: 100%; height: 50px; background:#f8fafc; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 600;">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; text-transform:uppercase; margin-bottom:8px; color: var(--text-muted);">Expiração</label>
                        <input type="date" name="data_expiracao" style="width: 100%; height: 50px; background:#f8fafc; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 600;">
                    </div>
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; text-transform:uppercase; margin-bottom:8px; color: var(--text-muted);">Limite de Uso</label>
                        <input type="number" name="limite_uso" value="0" style="width: 100%; height: 50px; background:#f8fafc; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 600;">
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; text-transform:uppercase; margin-bottom:8px; color: var(--text-muted);">Status</label>
                    <select name="status" style="width: 100%; height: 50px; background:#f8fafc; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 600;">
                        <option value="ativo">Ativo</option>
                        <option value="inativo">Inativo</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button type="button" onclick="document.getElementById('modalCoupon').style.display='none'" class="btn-sq-light" style="flex: 1; padding: 15px;">CANCELAR</button>
                <button type="submit" class="btn-sq" style="flex: 1; padding: 15px;">CRIAR CUPOM</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php include 'footer.php'; ?>