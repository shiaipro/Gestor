<?php
require_once '../config.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: planos.php');
    exit;
}

// Buscar Plano
$stmt = $pdo->prepare("SELECT * FROM planos_mestre WHERE id = ?");
$stmt->execute([$id]);
$plano = $stmt->fetch();

if (!$plano) {
    header('Location: planos.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    // Tratamento de valor
    $valorRaw = $_POST['valor'] ?? '0';
    $valorRaw = str_replace(['R$', ' ', '.'], '', $valorRaw);
    $valor = str_replace(',', '.', $valorRaw);

    $frequencia = $_POST['frequencia'] ?? 'MENSAL';
    $limite_usuarios = (int) ($_POST['limite_usuarios'] ?? 0);
    $limite_alunos = (int) ($_POST['limite_alunos'] ?? 0);
    $descricao = $_POST['descricao'] ?? '';
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if ($nome && is_numeric($valor)) {
        try {
            $stmt = $pdo->prepare("UPDATE planos_mestre SET nome = ?, valor = ?, frequencia = ?, limite_usuarios = ?, limite_alunos = ?, descricao = ?, ativo = ? WHERE id = ?");
            $stmt->execute([$nome, $valor, $frequencia, $limite_usuarios, $limite_alunos, $descricao, $ativo, $id]);
            $sucesso = "Plano atualizado com sucesso!";
            // Atualizar dados locais
            $plano['nome'] = $nome;
            $plano['valor'] = $valor;
            $plano['frequencia'] = $frequencia;
            $plano['limite_usuarios'] = $limite_usuarios;
            $plano['limite_alunos'] = $limite_alunos;
            $plano['descricao'] = $descricao;
            $plano['ativo'] = $ativo;
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o nome e o valor corretamente.";
    }
}

$custom_title = "Editar Plano de Venda";
include 'header.php';
?>


<?php if ($erro): ?>
    <div class="alert" style="background:#fee2e2; color:#991b1b; padding:1rem; margin-bottom:1rem; border-radius:0.5rem;">
        <?php echo $erro; ?>
    </div>
<?php endif; ?>
<?php if ($sucesso): ?>
    <div class="alert" style="background:#dcfce7; color:#166534; padding:1rem; margin-bottom:1rem; border-radius:0.5rem;">
        <?php echo $sucesso; ?>
    </div>
<?php endif; ?>

<div class="card" style="padding: 2rem;">

    <form method="POST" style="display: grid; gap: 1.5rem;">
        <div class="form-group">
            <label for="nome"
                style="display:block; font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-bottom:0.5rem; text-transform:uppercase;">Nome
                do Plano</label>
            <input type="text" id="nome" name="nome" class="form-control"
                value="<?php echo htmlspecialchars($plano['nome']); ?>" required
                style="padding:0.8rem; border-radius:0.5rem; border:1px solid var(--border); width:100%;">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="valor"
                    style="display:block; font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-bottom:0.5rem; text-transform:uppercase;">Valor
                    (R$)</label>
                <input type="text" id="valor" name="valor" class="form-control"
                    value="<?php echo number_format($plano['valor'], 2, ',', '.'); ?>" required
                    style="padding:0.8rem; border-radius:0.5rem; border:1px solid var(--border); width:100%;">
            </div>
            <div class="form-group">
                <label for="frequencia"
                    style="display:block; font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-bottom:0.5rem; text-transform:uppercase;">Frequência
                    de Cobrança</label>
                <select id="frequencia" name="frequencia" class="form-control"
                    style="padding:0.8rem; border-radius:0.5rem; border:1px solid var(--border); width:100%;">
                    <option value="MENSAL" <?php echo $plano['frequencia'] == 'MENSAL' ? 'selected' : ''; ?>>Mensal
                    </option>
                    <option value="TRIMESTRAL" <?php echo $plano['frequencia'] == 'TRIMESTRAL' ? 'selected' : ''; ?>>
                        Trimestral</option>
                    <option value="SEMESTRAL" <?php echo $plano['frequencia'] == 'SEMESTRAL' ? 'selected' : ''; ?>>
                        Semestral</option>
                    <option value="ANUAL" <?php echo $plano['frequencia'] == 'ANUAL' ? 'selected' : ''; ?>>Anual
                    </option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="limite_usuarios"
                    style="display:block; font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-bottom:0.5rem; text-transform:uppercase;">Limite
                    de Usuários (Staff)</label>
                <input type="number" id="limite_usuarios" name="limite_usuarios" class="form-control"
                    value="<?php echo (int) ($plano['limite_usuarios'] ?? 0); ?>" min="0" required
                    style="padding:0.8rem; border-radius:0.5rem; border:1px solid var(--border); width:100%;">
                <small style="color:var(--text-muted);">0 = Ilimitado</small>
            </div>
            <div class="form-group">
                <label for="limite_alunos"
                    style="display:block; font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-bottom:0.5rem; text-transform:uppercase;">Limite
                    de Alunos</label>
                <input type="number" id="limite_alunos" name="limite_alunos" class="form-control"
                    value="<?php echo (int) ($plano['limite_alunos'] ?? 0); ?>" min="0" required
                    style="padding:0.8rem; border-radius:0.5rem; border:1px solid var(--border); width:100%;">
                <small style="color:var(--text-muted);">0 = Ilimitado</small>
            </div>
        </div>

        <div class="form-group">
            <label for="descricao"
                style="display:block; font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-bottom:0.5rem; text-transform:uppercase;">Descrição
                (Opcional)</label>
            <textarea id="descricao" name="descricao" rows="3" class="form-control"
                style="padding:0.8rem; border-radius:0.5rem; border:1px solid var(--border); width:100%;"><?php echo htmlspecialchars($plano['descricao'] ?? ''); ?></textarea>
        </div>

        <div class="form-group" style="display:flex; align-items:center; gap:10px;">
            <input type="checkbox" id="ativo" name="ativo" <?php echo $plano['ativo'] ? 'checked' : ''; ?>
                style="width:20px; height:20px;">
            <label for="ativo" style="font-size:1rem; font-weight:600; color:var(--text-main);">Plano Ativo
                (Disponível para venda)</label>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:1rem;">
            <a href="planos.php" class="btn btn-secondary" style="padding:0.8rem 1.5rem;">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="padding:0.8rem 2rem;">Salvar Alterações</button>
        </div>
    </form>
</div>


<?php include 'footer.php'; ?>