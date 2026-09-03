<?php
require_once '../config.php';

// Verificar Unidade
$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    // Tratamento de valor: remove R$, remove pontos de milhar, troca vírgula por ponto
    $valorRaw = $_POST['valor'] ?? '0';
    $valorRaw = str_replace(['R$', ' ', '.'], '', $valorRaw);
    $valor = str_replace(',', '.', $valorRaw);

    $frequencia = $_POST['frequencia'] ?? 'MENSAL';
    $descricao = $_POST['descricao'] ?? '';

    if ($nome && is_numeric($valor)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO planos (unidade_id, nome, valor, frequencia, descricao, ativo) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$unidade_id, $nome, $valor, $frequencia, $descricao]);
            $sucesso = "Plano criado com sucesso!";
            header("refresh:1;url=planos.php");
        } catch (PDOException $e) {
            $erro = "Erro ao criar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o nome e o valor corretamente.";
    }
}

$custom_title = "Novo Plano";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Novo Plano
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Configure as condições comerciais e benefícios do novo contrato.
            </p>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <a href="planos.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 40px; align-items: start;">
            <div class="dashboard-container" style="padding: 40px;">
                <?php if ($sucesso): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 20px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid var(--primary-green); margin-bottom: 30px;">
                        <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $sucesso; ?>
                    </div>
                <?php endif; ?>

                <?php if ($erro): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 20px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid #ef4444; margin-bottom: 30px;">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; gap: 25px;">
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Nome do Plano / Serviço</label>
                        <input type="text" name="nome" required 
                            style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark);">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Valor da Mensalidade (R$)</label>
                            <input type="text" name="valor" placeholder="0,00" required
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Frequência de Cobrança</label>
                            <select name="frequencia" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="MENSAL">MENSAL (MENSALIDADE)</option>
                                <option value="TRIMESTRAL">TRIMESTRAL</option>
                                <option value="SEMESTRAL">SEMESTRAL</option>
                                <option value="ANUAL">ANUAL</option>
                                <option value="UNICO">PAGAMENTO ÚNICO (TAXAS)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Descrição dos Benefícios / Notas</label>
                        <textarea name="descricao" rows="4" 
                            style="width:100%; background:#fafafa; border:1px solid var(--border-color); padding:15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark); resize:none;"></textarea>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">
                             <i class="fa-solid fa-save" style="margin-right: 10px;"></i>CRIAR NOVO PLANO COMERCIAL
                        </button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Dicas de Configuração</h3>
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Cobrança Recorrente</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Os planos mensais serão cobrados automaticamente via Asaas ou Cartão se configurado.</p>
                        </div>
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Inativação</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Você pode inativar planos a qualquer momento para que novos alunos não os vejam.</p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Segurança</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">Dados financeiros são protegidos e auditados conforme normas LGPD.</p>
                </div>
            </div>
        </div>
    </form>
</section>


<?php include 'footer.php'; ?>