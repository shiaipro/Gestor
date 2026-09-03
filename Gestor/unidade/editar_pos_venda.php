<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: crm_pos_vendas.php');
    exit;
}

// Buscar Registro de Pós-Venda
$stmt = $pdo->prepare("SELECT * FROM comercial_pos_vendas WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$registro = $stmt->fetch();

if (!$registro) {
    header('Location: crm_pos_vendas.php');
    exit;
}

$erro = '';
$sucesso = '';

// Buscar Alunos para o Select
$stmt_alunos = $pdo->prepare("SELECT id, nome_completo FROM alunos WHERE unidade_id = ? AND (status = 'ativo' OR id = ?) ORDER BY nome_completo ASC");
$stmt_alunos->execute([$unidade_id, $registro['aluno_id']]);
$alunos = $stmt_alunos->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['excluir'])) {
        $stmt = $pdo->prepare("DELETE FROM comercial_pos_vendas WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$id, $unidade_id]);
        header("Location: crm_pos_vendas.php?msg=excluido");
        exit;
    }

    $aluno_id = $_POST['aluno_id'] ?? '';
    $data_contato = $_POST['data_contato'] ?? date('Y-m-d');
    $proximo_contato = $_POST['proximo_contato'] ?: null;
    $status = $_POST['status'] ?? 'satisfeito';
    $feedback = $_POST['feedback'] ?? '';

    if ($aluno_id && $data_contato) {
        try {
            $stmt = $pdo->prepare("UPDATE comercial_pos_vendas SET aluno_id = ?, data_contato = ?, proximo_contato = ?, status = ?, feedback = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$aluno_id, $data_contato, $proximo_contato, $status, $feedback, $id, $unidade_id]);
            $sucesso = "Acompanhamento atualizado com sucesso!";
            header("refresh:1;url=crm_pos_vendas.php");
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    } else {
        $erro = "Selecione o aluno e a data do contato.";
    }
}

$custom_title = "Editar Pós-Venda";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #08153a; padding-bottom: 30px;">
        <div>
            <h1 style="font-size: 2.5rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.04em; text-transform: uppercase;">
                Dados do Contato
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 600; text-transform: uppercase;">Ajuste de feedback e nível de satisfação</p>
        </div>

    
        <a href="crm_pos_vendas.php" class="btn-sq-light" style="padding: 12px 25px;">VOLTAR</a>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="comercial_dashboard.php" class="tab-item-sq">Painel</a>
    <a href="marketing_leads.php" class="tab-item-sq">Análise de Leads</a>
    <a href="crm_vendas.php" class="tab-item-sq">Vendas</a>
    <a href="crm_pos_vendas.php" class="tab-item-sq active">Pós-Venda</a>
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
                    <div class="form-group-sq">
                        <label>Aluno Relacionado</label>
                        <select name="aluno_id" required>
                            <?php foreach ($alunos as $a): ?>
                                <option value="<?php echo $a['id']; ?>" <?php echo $a['id'] == $registro['aluno_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($a['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div class="form-group-sq">
                            <label>Data do Contato</label>
                            <input type="date" name="data_contato" value="<?php echo $registro['data_contato']; ?>" required>
                        </div>
                        <div class="form-group-sq">
                            <label>Status de Satisfação</label>
                            <select name="status">
                                <option value="satisfeito" <?php echo $registro['status'] == 'satisfeito' ? 'selected' : ''; ?>>SATISFEITO</option>
                                <option value="neutro" <?php echo $registro['status'] == 'neutro' ? 'selected' : ''; ?>>NEUTRO</option>
                                <option value="insatisfeito" <?php echo $registro['status'] == 'insatisfeito' ? 'selected' : ''; ?>>INSATISFEITO</option>
                                <option value="em_risco" <?php echo $registro['status'] == 'em_risco' ? 'selected' : ''; ?>>EM RISCO DE CANCELAMENTO</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group-sq">
                        <label>Próximo Retorno Agendado (Opcional)</label>
                        <input type="date" name="proximo_contato" value="<?php echo $registro['proximo_contato']; ?>">
                    </div>

                    <div class="form-group-sq">
                        <label>Relato do Aluno / Feedback</label>
                        <textarea name="feedback" placeholder="DESCREVA O QUE FOI CONVERSADO..." style="min-height: 150px;"><?php echo htmlspecialchars($registro['feedback']); ?></textarea>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px;  display: flex; justify-content: space-between; align-items: center; gap: 20px;">
                        <button type="submit" class="btn-sq" style="height: 60px; flex: 1; font-size: var(--fs-base);">ATUALIZAR REGISTRO</button>
                        <button type="submit" name="excluir" class="btn-sq-light" style="height: 60px; padding: 0 30px; color: #ef4444; border-color: #ef4444;" onclick="return confirm('ATENÇÃO: A exclusão é irreversível. Confirmar?')">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Retenção & CS</h3>
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Acompanhamento</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Manter um histórico limpo ajuda a identificar padrões de insatisfação que podem levar à evasão.</p>
                        </div>
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Satisfação</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Alunos em risco de cancelamento devem ser PRIORIDADE total da equipe comercial.</p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Aviso Crítico</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">A exclusão de um registro de pós-venda remove permanentemente a evidência do contato. Use com cautela.</p>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>