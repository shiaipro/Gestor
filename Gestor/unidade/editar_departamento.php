<?php
require_once '../config.php';
$custom_title = "Editar Departamento";
$custom_subtitle = "Ajuste o nome e a cor de identificação do departamento.";
$back_link = "departamentos.php";
$back_text = "Voltar para Listagem";
include 'header.php';

$unidade_id = getUnidadeId();
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: departamentos.php');
    exit;
}

// Buscar Departamento
$stmt = $pdo->prepare("SELECT * FROM departamentos WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$dept = $stmt->fetch();

if (!$dept) {
    header('Location: departamentos.php');
    exit;
}

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $cor = $_POST['cor'] ?? '#3b82f6';

    if ($nome) {
        try {
            $stmt = $pdo->prepare("UPDATE departamentos SET nome = ?, cor = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([strtoupper($nome), $cor, $id, $unidade_id]);
            $mensagem = "Departamento atualizado com sucesso!";
            // Atualizar objeto local para o form
            $dept['nome'] = strtoupper($nome);
            $dept['cor'] = $cor;
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar departamento: " . $e->getMessage();
        }
    } else {
        $erro = "O nome do departamento é obrigatório.";
    }
}
?>

<div class="dashboard-container" style="max-width: 600px; margin: 40px auto; padding: 40px;">
    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
        <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Identificação do Departamento</h2>
    </div>

    <?php if ($mensagem): ?>
        <div style="background: #E8F5E9; color: #2E7D32; padding: 20px; border: 1px solid #C8E6C9; margin-bottom: 30px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-check"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div style="background: #FFEBEE; color: #C62828; padding: 20px; border: 1px solid #FFCDD2; margin-bottom: 30px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div style="margin-bottom: 25px;">
            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Nome do Departamento</label>
            <input type="text" name="nome" class="form-control" required value="<?php echo htmlspecialchars($dept['nome']); ?>" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700; text-transform: uppercase;">
        </div>

        <div style="margin-bottom: 35px;">
            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Cor de Identificação</label>
            <div style="display: flex; gap: 15px; align-items: center;">
                <input type="color" name="cor" value="<?php echo htmlspecialchars($dept['cor']); ?>" style="width: 60px; height: 60px; border: 1px solid var(--border-color); cursor: pointer; padding: 0; background: none;">
                <span style="color: var(--text-muted); font-size: var(--fs-sm); font-weight: 700;">Selecione o tom para visualização em relatórios.</span>
            </div>
        </div>

        <button type="submit" class="btn-sq" style="width: 100%; height: 55px; font-size: var(--fs-base);">SALVAR ALTERAÇÕES</button>
    </form>
</div>

<style>
    .form-control-custom:focus {
        border-color: var(--primary) !important;
        outline: none;
    }
</style>

<?php include 'footer.php'; ?>