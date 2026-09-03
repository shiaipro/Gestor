<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// 1. Auto-Migração para Banco de Mensagens
try {
    $pdo->query("SELECT 1 FROM comercial_mensagens LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS comercial_mensagens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        categoria VARCHAR(100),
        conteudo TEXT,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

// Processar Exclusão
if (isset($_POST['excluir_id'])) {
    $stmt = $pdo->prepare("DELETE FROM comercial_mensagens WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$_POST['excluir_id'], $unidade_id]);
    $mensagem_sucesso = "Template removido com sucesso!";
}

// Buscar Templates
$stmt = $pdo->prepare("SELECT * FROM comercial_mensagens WHERE unidade_id = ? ORDER BY categoria ASC, titulo ASC");
$stmt->execute([$unidade_id]);
$templates = $stmt->fetchAll();

$custom_title = "Scripts de Vendas";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #08153a; padding-bottom: 30px;">
        <div>
            <h1 style="font-size: 2.5rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.04em; text-transform: uppercase;">
                Scripts de Vendas
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 600; text-transform: uppercase;">Templates de mensagens e abordagens comerciais</p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="novo_template.php" class="btn-sq" style="padding: 12px 25px;">
                <i class="fa-solid fa-plus-circle" style="margin-right: 8px;"></i> NOVO SCRIPT
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="comercial_dashboard.php" class="tab-item-sq">Início</a>
    <a href="crm_vendas.php" class="tab-item-sq">Vendas</a>
    <a href="crm_pos_vendas.php" class="tab-item-sq">Pós Vendas</a>
    <a href="marketing_leads.php" class="tab-item-sq">CRM</a>
    <a href="mensagens.php" class="tab-item-sq active">Mensagens</a>
    <a href="marketing_campanhas.php?menu=comercial" class="tab-item-sq">Funis de Vendas</a>
</div>

    

    <?php if (isset($mensagem_sucesso)): ?>
        <div style="background: var(--primary-green); color: #fff; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;"><?php echo $mensagem_sucesso; ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 30px;">
        <?php foreach ($templates as $t): ?>
            <div class="dashboard-container" style="padding: 30px; display: flex; flex-direction: column; border: 1px solid var(--border-color); background: #fff; transition: border-color 0.2s;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <span style="background: #08153a; color: #fff; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; padding: 4px 12px; letter-spacing: 0.05em;">
                        <?php echo htmlspecialchars($t['categoria'] ?: 'GERAL'); ?>
                    </span>
                    <div style="display: flex; gap: 10px;">
                        <a href="editar_template.php?id=<?php echo $t['id']; ?>" class="btn-sq-light" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Editar">
                            <i class="fa-solid fa-pen-nib" style="font-size: var(--fs-xs);"></i>
                        </a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este script permanentemente?')">
                            <input type="hidden" name="excluir_id" value="<?php echo $t['id']; ?>">
                            <button type="submit" class="btn-sq-light" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; color: #ef4444;" title="Excluir">
                                <i class="fa-solid fa-trash-can" style="font-size: var(--fs-xs);"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <h3 style="font-size: var(--fs-base); font-weight: 900; color: var(--text-dark); margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.02em;">
                    <?php echo htmlspecialchars($t['titulo']); ?>
                </h3>

                <div style="background: #fafafa; padding: 20px; border: 1px solid var(--border-color); flex-grow: 1; display: flex; flex-direction: column;">
                    <div style="max-height: 150px; overflow-y: auto; font-size: var(--fs-sm); color: var(--text-dark); line-height: 1.6; font-weight: 500; font-family: monospace; white-space: pre-wrap;" class="custom-scrollbar">
                        <?php echo htmlspecialchars($t['conteudo']); ?>
                    </div>

                    <div style="margin-top: 20px; padding-top: 15px;  display: flex; justify-content: flex-end;">
                        <button onclick="copyToClipboard('template-<?php echo $t['id']; ?>')" class="btn-sq-light" style="padding: 8px 15px; font-size: var(--fs-xs); font-weight: 900; gap: 8px; width: auto; color: #08153a;">
                            <i class="fa-regular fa-copy"></i> COPIAR MENSAGEM
                        </button>
                    </div>

                    <textarea id="template-<?php echo $t['id']; ?>" style="display:none;"><?php echo htmlspecialchars($t['conteudo']); ?></textarea>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($templates)): ?>
            <div style="grid-column: 1 / -1; padding: 80px; text-align: center; background: #fafafa; border: 2px dashed var(--border-color);">
                <i class="fa-solid fa-comment-slash" style="font-size: 40px; color: #cbd5e1; margin-bottom: 20px; display: block;"></i>
                <h4 style="font-size: var(--fs-base); color: #08153a; margin-bottom: 10px; font-weight: 900; text-transform: uppercase;">Nenhum script encontrado</h4>
                <p style="color: var(--text-muted); font-size: var(--fs-sm); margin-bottom: 25px; font-weight: 600;">Crie templates de mensagens para agilizar o atendimento comercial.</p>
                <a href="novo_template.php" class="btn-sq" style="padding: 12px 30px;">CRIAR PRIMEIRO SCRIPT</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
    function copyToClipboard(id) {
        const text = document.getElementById(id).value;
        navigator.clipboard.writeText(text).then(() => {
            alert('MENSAGEM COPIADA PARA A ÁREA DE TRANSFERÊNCIA!');
        });
    }
</script>

<?php include 'footer.php'; ?>