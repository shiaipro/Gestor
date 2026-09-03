<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: mensagens.php');
    exit;
}

// Buscar Template
$stmt = $pdo->prepare("SELECT * FROM comercial_mensagens WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$template = $stmt->fetch();

if (!$template) {
    header('Location: mensagens.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $conteudo = $_POST['conteudo'] ?? '';

    if ($titulo && $conteudo) {
        try {
            $stmt = $pdo->prepare("UPDATE comercial_mensagens SET titulo = ?, categoria = ?, conteudo = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$titulo, $categoria, $conteudo, $id, $unidade_id]);
            $sucesso = "Script atualizado com sucesso!";
            header("refresh:1;url=mensagens.php");
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o título e o conteúdo da mensagem.";
    }
}

$custom_title = "Editar Script Comercial";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #08153a; padding-bottom: 30px;">
        <div style="">
            <h1 style="font-size: 2.5rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.04em; text-transform: uppercase;">
                Editar Script
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 600; text-transform: uppercase;">
                Ajuste: <strong><?php echo htmlspecialchars($template['titulo']); ?></strong>
            </p>
        </div>
        <a href="mensagens.php" class="btn-sq-light" style="padding: 12px 25px;">VOLTAR</a>
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
                    <div style="display: grid; grid-template-columns: 1fr 200px; gap: 25px;">
                        <div class="form-group-sq">
                            <label>Identificação do Script</label>
                            <input type="text" name="titulo" value="<?php echo htmlspecialchars($template['titulo']); ?>" required>
                        </div>
                        <div class="form-group-sq">
                            <label>Categoria</label>
                            <input type="text" name="categoria" list="categorias-padrao" value="<?php echo htmlspecialchars($template['categoria']); ?>">
                            <datalist id="categorias-padrao">
                                <option value="Vendas">
                                <option value="Cobrança">
                                <option value="Boas-vindas">
                                <option value="Graduação">
                                <option value="Avisos Gerais">
                                <option value="Pós-Venda">
                            </datalist>
                        </div>
                    </div>

                    <div class="form-group-sq">
                        <label>Corpo do Script / Mensagem</label>
                        <textarea id="conteudo" name="conteudo" rows="12" required style="font-family: monospace; font-size: var(--fs-base); line-height: 1.6;"><?php echo htmlspecialchars($template['conteudo']); ?></textarea>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">SALVAR ALTERAÇÕES NO SCRIPT</button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Tags Dinâmicas</h3>
                    <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500; margin-bottom: 20px;">Clique nas tags para inserir no editor:</p>
                    
                    <div style="display: grid; gap: 8px;">
                        <?php
                        $tags = [
                            '[NOME_ALUNO]' => 'NOME COMPLETO',
                            '[PRIMEIRO_NOME]' => 'APENAS O 1º NOME',
                            '[VALOR_PLANO]' => 'VALOR R$',
                            '[NOME_UNIDADE]' => 'UNIDADE'
                        ];
                        foreach ($tags as $tag => $label): ?>
                            <div onclick="insertTag('<?php echo $tag; ?>')" 
                                 style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 12px 15px; border: 1px solid var(--border-color); transition: all 0.2s;"
                                 onmouseover="this.style.borderColor='#08153a'; this.style.background='#f8fafc'" 
                                 onmouseout="this.style.borderColor='var(--border-color)'; this.style.background='#fff'">
                                <span style="font-family: monospace; font-size: var(--fs-xs); font-weight: 900; color: #08153a;"><?php echo $tag; ?></span>
                                <i class="fa-solid fa-plus" style="font-size: var(--fs-xs); color: var(--primary-green);"></i>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Dica</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">Templates atualizados garantem que toda a equipe comercial fale a mesma língua com os leads.</p>
                </div>
            </div>
        </div>
    </form>
</section>

<script>
    function insertTag(tag) {
        const textarea = document.getElementById('conteudo');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const before = text.substring(0, start);
        const after = text.substring(end, text.length);
        textarea.value = before + tag + after;
        textarea.selectionStart = textarea.selectionEnd = start + tag.length;
        textarea.focus();
    }
</script>

<?php include 'footer.php'; ?>