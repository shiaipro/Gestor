<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: crm_vendas.php');
    exit;
}

// Buscar Lead
$stmt = $pdo->prepare("SELECT * FROM comercial_leads WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$lead = $stmt->fetch();

if (!$lead) {
    header('Location: crm_vendas.php');
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
            $stmt = $pdo->prepare("UPDATE comercial_leads SET nome = ?, telefone = ?, email = ?, origem = ?, status = ?, notas = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$nome, $telefone, $email, $origem, $status, $notas, $id, $unidade_id]);
            $sucesso = "Lead atualizado com sucesso!";
            header("refresh:2;url=crm_vendas.php");
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o nome do lead.";
    }
}

$custom_title = "Editar Lead";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Gestão de Lead
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Atualize o progresso da oportunidade: <strong><?php echo htmlspecialchars($lead['nome']); ?></strong>
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="crm_vendas.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
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
        <div style="display: grid; grid-template-columns: 1fr 350px; gap: 40px; align-items: start;">
            <!-- COLUNA FORMULÁRIO -->
            <div>
                <?php if ($erro): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
                    </div>
                <?php endif; ?>

                <?php if ($sucesso): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 20px; border-left: 4px solid var(--primary-green); margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
                        <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $sucesso; ?>
                    </div>
                <?php endif; ?>

                <div class="dashboard-container" style="padding: 40px;">
                    <div style="display: grid; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Nome do Prospecto</label>
                            <input type="text" name="nome" value="<?php echo htmlspecialchars($lead['nome']); ?>" required style="width:100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700; text-transform: uppercase;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                            <div>
                                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">WhatsApp / Contato</label>
                                <input type="text" name="telefone" value="<?php echo htmlspecialchars($lead['telefone']); ?>" style="width:100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                            </div>
                            <div>
                                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">E-mail</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($lead['email']); ?>" style="width:100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                            <div>
                                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Origem da Captação</label>
                                <select name="origem" style="width:100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark); appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                                    <option value="Instagram" <?php echo $lead['origem'] == 'Instagram' ? 'selected' : ''; ?>>INSTAGRAM</option>
                                    <option value="Facebook" <?php echo $lead['origem'] == 'Facebook' ? 'selected' : ''; ?>>FACEBOOK</option>
                                    <option value="Google" <?php echo $lead['origem'] == 'Google' ? 'selected' : ''; ?>>GOOGLE ADS</option>
                                    <option value="Indicação" <?php echo $lead['origem'] == 'Indicação' ? 'selected' : ''; ?>>INDICAÇÃO</option>
                                    <option value="Passagem" <?php echo $lead['origem'] == 'Passagem' ? 'selected' : ''; ?>>PASSAGEM (PORTÃO)</option>
                                    <option value="Outro" <?php echo $lead['origem'] == 'Outro' ? 'selected' : ''; ?>>OUTRO</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Status no Funil</label>
                                <select name="status" style="width:100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark); appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                                    <option value="novo" <?php echo $lead['status'] == 'novo' ? 'selected' : ''; ?>>NOVO LEAD / TRIAGEM</option>
                                    <option value="em_contato" <?php echo $lead['status'] == 'em_contato' ? 'selected' : ''; ?>>EM CONTATO / NEGOCIAÇÃO</option>
                                    <option value="agendado" <?php echo $lead['status'] == 'agendado' ? 'selected' : ''; ?>>VISITA AGENDADA</option>
                                    <option value="fechado" <?php echo $lead['status'] == 'fechado' ? 'selected' : ''; ?>>MATRICULADO / FECHADO</option>
                                    <option value="perdido" <?php echo $lead['status'] == 'perdido' ? 'selected' : ''; ?>>OPORTUNIDADE PERDIDA</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Histórico / Observações</label>
                            <textarea name="notas" rows="5" style="width:100%; background: #fafafa; border: 1px solid var(--border-color); padding: 15px; font-size: var(--fs-base); font-weight: 600; resize: none;"><?php echo htmlspecialchars($lead['notas'] ?? ''); ?></textarea>
                        </div>

                        <div style="margin-top: 30px; display: flex; gap: 15px;  padding-top: 30px;">
                            <button type="submit" class="btn-sq" style="height: 60px; flex: 1; font-size: var(--fs-sm);">
                                <i class="fa-solid fa-save" style="margin-right: 10px;"></i>ATUALIZAR LEAD
                            </button>
                            <a href="crm_vendas.php" class="btn-sq-outline" style="height: 60px; width: 150px; font-size: var(--fs-sm); text-decoration: none; display: flex; align-items: center; justify-content: center;">CANCELAR</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div style="background: #fafafa; border: 1px solid var(--border-color); padding: 30px;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.05em;">Ação Imediata</h3>
                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $lead['telefone']); ?>" target="_blank" class="btn-sq" style="width: 100%; background: #25d366; border: none; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: var(--fs-xs);">
                        <i class="fa-brands fa-whatsapp" style="font-size: 18px;"></i> CHAMAR NO WHATSAPP
                    </a>
                </div>

                <div style="background: #08153a; color: #fff; padding: 30px;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.05em;">Inside Sales</h3>
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #fff; text-transform: uppercase; margin-bottom: 8px;">Acompanhamento</h4>
                            <p style="font-size: var(--fs-xs); color: #94a3b8; line-height: 1.6; margin: 0;">O follow-up constante é o segredo da conversão. Mantenha as notas atualizadas após cada contato.</p>
                        </div>
                        <div style="padding: 15px; background: #1e293b; border-left: 3px solid var(--primary-green);">
                            <p style="font-size: var(--fs-xs); color: #fff; line-height: 1.5; font-weight: 600; font-style: italic; margin: 0;">
                                "Vendas é sobre resolver problemas, não apenas fechar contratos."
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>