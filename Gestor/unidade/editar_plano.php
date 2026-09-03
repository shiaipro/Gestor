<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: planos.php');
    exit;
}

// Buscar dados do plano
$stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$plano = $stmt->fetch();

if (!$plano) {
    header('Location: planos.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $valorRaw = $_POST['valor'] ?? '0';
    $valorRaw = str_replace(['R$', ' ', '.'], '', $valorRaw);
    $valor = str_replace(',', '.', $valorRaw);

    $frequencia = $_POST['frequencia'] ?? 'MENSAL';
    $descricao = $_POST['descricao'] ?? '';
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if ($nome && is_numeric($valor)) {
        try {
            $stmt = $pdo->prepare("UPDATE planos SET nome = ?, valor = ?, frequencia = ?, descricao = ?, ativo = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$nome, $valor, $frequencia, $descricao, $ativo, $id, $unidade_id]);
            $sucesso = "Plano atualizado com sucesso!";
            header("refresh:1;url=planos.php");
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o nome e o valor corretamente.";
    }
}

$custom_title = "Editar Plano";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Configurar Plano
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Ajuste: <strong><?php echo htmlspecialchars($plano['nome']); ?></strong>
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="planos.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="equipe.php" class="tab-item-sq">RH</a>
    <a href="academias.php" class="tab-item-sq">Unidades</a>
    <a href="turmas.php" class="tab-item-sq">Turmas</a>
    <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
</div>

    

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 40px; align-items: start;">
            <div class="dashboard-container" style="padding: 40px;">
                <?php if ($sucesso): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid var(--primary-green);">
                        <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $sucesso; ?>
                    </div>
                <?php endif; ?>

                <?php if ($erro): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid #ef4444;">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
                    </div>
                <?php endif; ?>
                <div style="display: grid; gap: 25px;">
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Nome do Plano / Serviço Comercial</label>
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($plano['nome']); ?>" required
                            style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark); text-transform: uppercase;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Valor da Mensalidade (R$)</label>
                            <input type="text" name="valor" id="valor-input" value="<?php echo number_format($plano['valor'], 2, ',', '.'); ?>" required
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:900; color:var(--text-dark);">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Ciclo de Faturamento</label>
                            <select name="frequencia" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="MENSAL" <?php echo $plano['frequencia'] == 'MENSAL' ? 'selected' : ''; ?>>MENSAL</option>
                                <option value="TRIMESTRAL" <?php echo $plano['frequencia'] == 'TRIMESTRAL' ? 'selected' : ''; ?>>TRIMESTRAL</option>
                                <option value="SEMESTRAL" <?php echo $plano['frequencia'] == 'SEMESTRAL' ? 'selected' : ''; ?>>SEMESTRAL</option>
                                <option value="ANUAL" <?php echo $plano['frequencia'] == 'ANUAL' ? 'selected' : ''; ?>>ANUAL</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Regras e Descrição do Plano</label>
                        <textarea name="descricao" rows="5" 
                            style="width:100%; background:#fafafa; border:1px solid var(--border-color); padding:15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark); resize:none;" placeholder="Ex: Acesso livre a todas as modalidades, 10% de desconto em produtos..."><?php echo htmlspecialchars($plano['descricao']); ?></textarea>
                    </div>

                    <div style="display: flex; align-items: center; gap: 15px; padding: 15px; background: #fafafa; border: 1px solid var(--border-color);">
                        <input type="checkbox" name="ativo" id="ativo" value="1" <?php echo $plano['ativo'] ? 'checked' : ''; ?> style="width: 25px; height: 25px; accent-color: var(--primary-green);">
                        <label for="ativo" style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); cursor: pointer; text-transform: uppercase;">Disponível para venda</label>
                    </div>

                    <div style="margin-top: 10px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">
                            <i class="fa-solid fa-save" style="margin-right: 10px;"></i>ATUALIZAR CONFIGURAÇÃO COMERCIAL
                        </button>
                    </div>
                </div>        </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Gestão de Mensalidades</h3>
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Atualização de Valor</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Alterar o valor aqui não mudará mensalidades já geradas no financeiro dos alunos atuais.</p>
                        </div>
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Checkout Online</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Este plano pode ser vinculado à sua loja online para matrículas diretas via website.</p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Segurança</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">Inativar um plano oculta ele das novas vendas sem afetar os alunos que já possuem este contrato.</p>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>