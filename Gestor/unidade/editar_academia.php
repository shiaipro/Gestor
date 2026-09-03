<?php
require_once '../config.php';
$custom_title = "Editar Unidade";
$custom_subtitle = "Gerencie as informações detalhadas desta academia.";
$back_link = "academias.php";
$back_text = "Voltar para Listagem";
include 'header.php';

$unidade_id = getUnidadeId();
$id = $_GET['id'] ?? null;
$mensagem = '';
$erro = '';

if (!$id) {
    header("Location: academias.php");
    exit;
}

if (!temPermissaoModulo('unidades_editar')) {
    header("Location: academias.php?erro=sem_permissao");
    exit;
}

// Buscar dados da academia
$stmt = $pdo->prepare("SELECT * FROM academias WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$academia = $stmt->fetch();

if (!$academia) {
    echo "<div style='padding:20px;'><div class='alert alert-danger'>Academia não encontrada.</div></div>";
    include 'footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['excluir'])) {
        if (!temPermissaoModulo('unidades_remover')) {
            header("Location: academias.php?erro=sem_permissao");
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM academias WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$id, $unidade_id]);
            header("Location: academias.php?msg=excluido");
            exit;
        } catch (PDOException $e) {
            $erro = "Erro ao excluir academia: " . $e->getMessage();
        }
    } else {
        $nome        = $_POST['nome']        ?? '';
        $cnpj        = $_POST['cnpj']        ?? '';
        $endereco    = $_POST['endereco']    ?? '';
        $telefone    = $_POST['telefone']    ?? '';
        $whatsapp    = $_POST['whatsapp']    ?? '';
        $email       = $_POST['email']       ?? '';
        $valor       = !empty($_POST['valor']) ? (float)str_replace(['.', ','], ['', '.'], $_POST['valor']) : 0.00;
        $recorrencia = $_POST['recorrencia'] ?? '';
        $dia_vencimento = !empty($_POST['dia_vencimento']) ? (int)$_POST['dia_vencimento'] : null;
        $status      = $_POST['status']      ?? 'ativo';

        if ($nome) {
            try {
                $stmt = $pdo->prepare("UPDATE academias SET nome = ?, cnpj = ?, endereco = ?, telefone = ?, whatsapp = ?, email = ?, valor = ?, recorrencia = ?, dia_vencimento = ?, status = ? WHERE id = ? AND unidade_id = ?");
                $stmt->execute([$nome, $cnpj, $endereco, $telefone, $whatsapp, $email, $valor, $recorrencia, $dia_vencimento, $status, $id, $unidade_id]);
                $mensagem = "Academia atualizada com sucesso!";

                // Atualizar dados locais
                $academia['nome'] = $nome;
                $academia['cnpj'] = $cnpj;
                $academia['endereco'] = $endereco;
                $academia['telefone'] = $telefone;
                $academia['whatsapp'] = $whatsapp;
                $academia['email'] = $email;
                $academia['valor'] = $valor;
                $academia['recorrencia'] = $recorrencia;
                $academia['dia_vencimento'] = $dia_vencimento;
                $academia['status'] = $status;
            } catch (PDOException $e) {
                $erro = "Erro ao atualizar academia: " . $e->getMessage();
            }
        } else {
            $erro = "O nome da academia é obrigatório.";
        }
    }
}
?>

<section class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">Dados da Unidade</h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">Configurações de filial e ponto de treino: <strong><?php echo htmlspecialchars($academia['nome']); ?></strong></p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="academias.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 40px; align-items: start;">
            <div class="dashboard-container" style="padding: 40px;">
                <?php if ($mensagem): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid var(--primary-green);">
                        <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $mensagem; ?>
                    </div>
                <?php endif; ?>

                <?php if ($erro): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid #ef4444;">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; gap: 25px;">
                    <div style="display: grid; grid-template-columns: 1fr 220px; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Nome Oficial da Unidade</label>
                            <input type="text" name="nome" required value="<?php echo htmlspecialchars($academia['nome']); ?>" 
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark); text-transform: uppercase;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Status Operacional</label>
                            <select name="status" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="ativo" <?php echo $academia['status'] == 'ativo' ? 'selected' : ''; ?>>ATIVO / OPERANTE</option>
                                <option value="inativo" <?php echo $academia['status'] == 'inativo' ? 'selected' : ''; ?>>INATIVO / FECHADO</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">CNPJ</label>
                            <input type="text" name="cnpj" value="<?php echo htmlspecialchars($academia['cnpj'] ?? ''); ?>" 
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark);">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">E-mail Administrativo</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($academia['email']); ?>" 
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark);">
                        </div>
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Endereço Completo & Localização</label>
                        <input type="text" name="endereco" value="<?php echo htmlspecialchars($academia['endereco']); ?>" 
                            style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark);">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Telefone (Fixo)</label>
                            <input type="text" name="telefone" value="<?php echo htmlspecialchars($academia['telefone']); ?>" 
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark);">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">WhatsApp</label>
                            <input type="text" name="whatsapp" value="<?php echo htmlspecialchars($academia['whatsapp'] ?? ''); ?>" 
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark);">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px; padding: 20px; background: #f8fafc; border: 1px solid var(--border-color);">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Valor do Contrato (R$)</label>
                            <input type="text" name="valor" value="<?php echo !empty($academia['valor']) ? number_format($academia['valor'], 2, ',', '.') : ''; ?>" 
                                style="width:100%; height:50px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:900; color:var(--text-dark);">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Recorrência</label>
                            <?php $rec = $academia['recorrencia'] ?? ''; ?>
                            <select name="recorrencia" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fff; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="" <?php echo $rec == '' ? 'selected' : ''; ?>>Não cobrar</option>
                                <option value="Mensal" <?php echo $rec == 'Mensal' ? 'selected' : ''; ?>>Mensal</option>
                                <option value="Trimestral" <?php echo $rec == 'Trimestral' ? 'selected' : ''; ?>>Trimestral</option>
                                <option value="Semestral" <?php echo $rec == 'Semestral' ? 'selected' : ''; ?>>Semestral</option>
                                <option value="Anual" <?php echo $rec == 'Anual' ? 'selected' : ''; ?>>Anual</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Dia do Vencimento</label>
                            <input type="number" name="dia_vencimento" value="<?php echo htmlspecialchars($academia['dia_vencimento'] ?? ''); ?>" placeholder="Ex: 5" min="1" max="31"
                                style="width:100%; height:50px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:900; color:var(--text-dark);">
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px;  display: flex; justify-content: space-between; align-items: center; gap: 20px;">
                        <button type="submit" class="btn btn-lg btn-primary" style="height: 60px; flex: 1; font-size: var(--fs-base);">
                            <i class="fa-solid fa-save" style="margin-right: 10px;"></i>ATUALIZAR INFORMAÇÕES
                        </button>
                        <button type="submit" name="excluir" class="btn btn-lg btn-danger" style="height: 60px; padding: 0 30px;" onclick="return confirm('ATENÇÃO: A exclusão é irreversível. Confirmar?')">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Segurança & Dados</h3>
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Geração de Documentos</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Alterações nestes dados afetam todos os contratos e recibos gerados a partir de agora para esta localização.</p>
                        </div>
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Acesso de Alunos</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Se inativar a unidade, os alunos vinculados a ela poderão ter restrições de visualização de horários no APP.</p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Aviso de Exclusão</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">A exclusão de uma unidade removerá permanentemente o histórico de turmas e faturamento vinculado a este local. Recomendamos apenas a **Inativação** em 99% dos casos.</p>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>