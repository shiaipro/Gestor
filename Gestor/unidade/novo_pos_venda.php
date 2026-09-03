<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

$erro = '';
$sucesso = '';

// Buscar Alunos para o Select
$stmt_alunos = $pdo->prepare("SELECT id, nome_completo FROM alunos WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome_completo ASC");
$stmt_alunos->execute([$unidade_id]);
$alunos = $stmt_alunos->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aluno_id = $_POST['aluno_id'] ?? '';
    $data_contato = $_POST['data_contato'] ?? date('Y-m-d');
    $proximo_contato = $_POST['proximo_contato'] ?: null;
    $status = $_POST['status'] ?? 'satisfeito';
    $feedback = $_POST['feedback'] ?? '';

    if ($aluno_id && $data_contato) {
        try {
            $stmt = $pdo->prepare("INSERT INTO comercial_pos_vendas (unidade_id, aluno_id, data_contato, proximo_contato, status, feedback) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $aluno_id, $data_contato, $proximo_contato, $status, $feedback]);
            $sucesso = "Acompanhamento registrado com sucesso!";
            header("refresh:1;url=crm_pos_vendas.php");
        } catch (PDOException $e) {
            $erro = "Erro ao registrar: " . $e->getMessage();
        }
    } else {
        $erro = "Selecione o aluno e a data do contato.";
    }
}

$custom_title = "Novo Pós-Venda";
$custom_subtitle = "Registre o acompanhamento e a satisfação dos seus alunos.";
$back_link = "crm_pos_vendas.php";
$back_text = "Voltar para Pós-Venda";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #08153a; padding-bottom: 30px;">
        <div>
            <h1 style="font-size: 2.5rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.04em; text-transform: uppercase;">
                Registro Pós-Venda
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 600; text-transform: uppercase;">Acompanhamento de satisfação e retenção</p>
        </div>
        <a href="crm_pos_vendas.php" class="btn-sq-light" style="padding: 12px 25px;">VOLTAR</a>
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
                        <label>Selecione o Aluno</label>
                        <select name="aluno_id" required>
                            <option value="">-- SELECIONE UM ALUNO --</option>
                            <?php foreach ($alunos as $a): ?>
                                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nome_completo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div class="form-group-sq">
                            <label>Data do Contato</label>
                            <input type="date" name="data_contato" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group-sq">
                            <label>Status de Satisfação</label>
                            <select name="status">
                                <option value="satisfeito">SATISFEITO</option>
                                <option value="neutro">NEUTRO</option>
                                <option value="insatisfeito">INSATISFEITO</option>
                                <option value="em_risco">EM RISCO DE CANCELAMENTO</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group-sq">
                        <label>Próximo Retorno (Opcional)</label>
                        <input type="date" name="proximo_contato">
                    </div>

                    <div class="form-group-sq">
                        <label>Relato do Aluno / Feedback</label>
                        <textarea name="feedback" placeholder="DESCREVA O QUE FOI CONVERSADO..." style="min-height: 150px;"></textarea>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">SALVAR ACOMPANHAMENTO</button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Sucesso do Aluno</h3>
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Escuta Ativa</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">O Pós-Venda é o momento de ouvir. Registre elogios, mas foque principalmente em entender as dores do aluno.</p>
                        </div>
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Previsibilidade</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Sempre que identificar um aluno "Em Risco", agende um próximo retorno em curto prazo para reverter a situação.</p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Dica Ninja</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">Um aluno satisfeito é a sua melhor ferramenta de marketing. Peça um depoimento caso o feedback seja positivo!</p>
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