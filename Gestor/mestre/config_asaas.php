<?php
require_once '../config.php';

// Salvar Configurações
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiKey = $_POST['api_key'] ?? '';
    $ambiente = $_POST['ambiente'] ?? 'sandbox';

    if ($apiKey) {
        try {
            // Verificar se já existe configuração para o mestre (unidade_id IS NULL)
            $stmt = $pdo->prepare("SELECT id FROM configuracoes_pagamento WHERE unidade_id IS NULL AND gateway = 'asaas'");
            $stmt->execute();
            $exists = $stmt->fetch();

            if ($exists) {
                $stmt = $pdo->prepare("UPDATE configuracoes_pagamento SET api_key = ?, ambiente = ? WHERE id = ?");
                $stmt->execute([$apiKey, $ambiente, $exists['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO configuracoes_pagamento (unidade_id, gateway, api_key, ambiente) VALUES (NULL, 'asaas', ?, ?)");
                $stmt->execute([$apiKey, $ambiente]);
            }
            $mensagem = "Configurações do Asaas atualizadas com sucesso!";
        } catch (PDOException $e) {
            $erro = "Erro ao salvar configurações: " . $e->getMessage();
        }
    } else {
        $erro = "A Chave API é obrigatória.";
    }
}

// Carregar Configurações Atuais
$config = $pdo->query("SELECT * FROM configuracoes_pagamento WHERE unidade_id IS NULL AND gateway = 'asaas'")->fetch();
$apiKey = $config['api_key'] ?? '';
$ambiente = $config['ambiente'] ?? 'sandbox';

$custom_title = "Configuração de Pagamento (Asaas)";
include 'header.php';
?>



<?php if ($mensagem): ?>
    <div class="alert"
        style="background:#dcfce7; color:#166534; padding:1rem; margin-bottom:1rem; border-radius:0.5rem; display:flex; gap:10px; align-items:center;">
        <i class="fa-solid fa-check-circle"></i>
        <?php echo $mensagem; ?>
    </div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert"
        style="background:#fee2e2; color:#991b1b; padding:1rem; margin-bottom:1rem; border-radius:0.5rem; display:flex; gap:10px; align-items:center;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <?php echo $erro; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: flex-end;">
        <span class="badge <?php echo $apiKey ? 'badge-ativo' : 'badge-inativo'; ?>">
            <?php echo $apiKey ? 'Conectado' : 'Não Configurado'; ?>
        </span>
    </div>

    <form method="POST" style="padding: 1.5rem;">
        <div class="form-group">
            <label style="font-weight:600; font-size:0.85rem; color:var(--text-main);">Ambiente</label>
            <select name="ambiente" class="form-control" style="max-width:200px;">
                <option value="sandbox" <?php echo $ambiente == 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testes)
                </option>
                <option value="producao" <?php echo $ambiente == 'producao' ? 'selected' : ''; ?>>Produção</option>
            </select>
            <p style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">
                Sandbox usa <code>sandbox.asaas.com</code>. Produção usa <code>www.asaas.com</code>.
            </p>
        </div>

        <div class="form-group">
            <label style="font-weight:600; font-size:0.85rem; color:var(--text-main);">API Key (Chave de
                Acesso)</label>
            <div style="position:relative;">
                <input type="password" name="api_key" id="api_key" class="form-control"
                    value="<?php echo htmlspecialchars($apiKey); ?>"
                    placeholder="<?php echo $ambiente == 'sandbox' ? '$aact_...' : '$aact_...'; ?>"
                    style="padding-right:40px;">
                <button type="button" onclick="toggleVisibility('api_key')"
                    style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none;  cursor:pointer; color:var(--text-muted);">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <p style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">
                Obtenha sua chave em <strong>Minha Conta > Integração</strong> no painel do Asaas.
            </p>
        </div>

        <div
            style="padding:1rem; background:#f8fafc; border-radius:0.5rem; border:1px solid #e2e8f0; margin-bottom:1.5rem;">
            <h4 style="font-size:0.85rem; font-weight:700; margin-bottom:0.5rem;">Webhook (Retorno Automático)</h4>
            <p style="font-size:0.8rem; color:var(--text-muted);">
                Configure a URL abaixo no painel do Asaas para receber atualizações de pagamento automaticamente:
            </p>
            <code
                style="display:block; padding:0.5rem; background:#fff; border:1px solid #cbd5e1; border-radius:0.25rem; font-family:monospace; margin-top:0.5rem;">
                    https://seu-dominio.com/webhook/asaas_mestre.php
                </code>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding: 0.75rem; font-weight:700;">
            <i class="fa-solid fa-save"></i> SALVAR CONFIGURAÇÕES
        </button>
    </form>
</div>


<script>
    function toggleVisibility(id) {
        var x = document.getElementById(id);
        if (x.type === "password") {
            x.type = "text";
        } else {
            x.type = "password";
        }
    }
</script>

<?php include 'footer.php'; ?>