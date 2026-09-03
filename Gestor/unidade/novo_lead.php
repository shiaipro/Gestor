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
    $telefone = $_POST['telefone'] ?? '';
    $email = $_POST['email'] ?? '';
    $origem = $_POST['origem'] ?? '';
    $status = $_POST['status'] ?? 'novo';
    $notas = $_POST['notas'] ?? '';

    if ($nome) {
        try {
            $stmt = $pdo->prepare("INSERT INTO comercial_leads (unidade_id, nome, telefone, email, origem, status, notas) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $nome, $telefone, $email, $origem, $status, $notas]);
            $sucesso = "Lead cadastrado com sucesso!";
            header("refresh:2;url=crm_vendas.php");
        } catch (PDOException $e) {
            $erro = "Erro ao cadastrar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o nome do lead.";
    }
}

$custom_title = "Novo Lead";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">Novo Lead Comercial</h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">Gestão de prospectos e funil de vendas ativo.</p>
        </div>

    
        <div style="display: flex; gap: 10px;">
            <a href="crm_vendas.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar ao CRM
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="comercial_dashboard.php" class="tab-item-sq">Painel</a>
    <a href="marketing_leads.php" class="tab-item-sq">Análise de Leads</a>
    <a href="crm_vendas.php" class="tab-item-sq active">Vendas</a>
    <a href="crm_pos_vendas.php" class="tab-item-sq">Pós-Venda</a>
</div>

    

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 40px; align-items: start;">
            
            <!-- Coluna Principal -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <?php if ($erro): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 15px 25px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
                    </div>
                <?php endif; ?>

                <?php if ($sucesso): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 15px 25px; border-left: 4px solid var(--primary-green); margin-bottom: 30px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
                        <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $sucesso; ?>
                    </div>
                <?php endif; ?>

                <div class="dashboard-container" style="padding: 40px; display: grid; gap: 25px;">
                    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 5px;">
                        <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Identificação do Lead</h2>
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Nome Completo do Prospecto</label>
                        <input type="text" name="nome" required placeholder="EX: MARCOS OLIVEIRA" 
                            style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark); text-transform: uppercase;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">WhatsApp / Contato</label>
                            <input type="text" name="telefone" placeholder="(00) 00000-0000" 
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">E-mail</label>
                            <input type="email" name="email" placeholder="email@exemplo.com" 
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Origem da Captação</label>
                            <select name="origem" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="Instagram">INSTAGRAM</option>
                                <option value="Facebook">FACEBOOK</option>
                                <option value="Google">GOOGLE ADS</option>
                                <option value="Indicação">INDICAÇÃO</option>
                                <option value="Passagem">PASSAGEM (PORTÃO)</option>
                                <option value="Site">WEBSITE OFICIAL</option>
                                <option value="Outros">OUTROS CANAIS</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Status Inicial</label>
                            <select name="status" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="novo">NOVO LEAD / TRIAGEM</option>
                                <option value="contatado">EM NEGOCIAÇÃO</option>
                                <option value="interessado">MUITO INTERESSADO</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Notas de Atendimento / Observações</label>
                        <textarea name="notas" rows="4" 
                            style="width:100%; background:#fafafa; border:1px solid var(--border-color); padding:15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark); resize:none;" placeholder="Descreva aqui o perfil do prospecto ou necessidades específicas..."></textarea>
                    </div>

                    <div style="margin-top: 10px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">
                             <i class="fa-solid fa-user-tag" style="margin-right: 10px;"></i>SALVAR E INICIAR NEGOCIAÇÃO
                        </button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Aceleração Contínua</h3>
                    
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Velocidade de Resposta</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Leads contatados nos primeiros 5 minutos convertem até 10x mais.</p>
                        </div>
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Qualificação Assertiva</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Identifique a principal dor ou objetivo do lead para um fechamento rápido.</p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Dica do Especialista</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">Leads sem telefone devem ser priorizados via e-mail ou redes sociais imediatamente.</p>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>