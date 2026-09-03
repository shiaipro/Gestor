<?php
require_once '../config.php';

// Verificar Unidade
$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Salvar Configurações
garantirColunasConfiguracoesPagamento($pdo);

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiKey = $_POST['api_key'] ?? '';
    $ambiente = $_POST['ambiente'] ?? 'sandbox';
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if ($apiKey) {
        try {
            // Verificar se já existe configuração para a unidade
            $stmt = $pdo->prepare("SELECT id FROM configuracoes_pagamento WHERE unidade_id = ? AND gateway = 'asaas'");
            $stmt->execute([$unidade_id]);
            $exists = $stmt->fetch();

            if ($exists) {
                $stmt = $pdo->prepare("UPDATE configuracoes_pagamento SET api_key = ?, ambiente = ?, ativo = ? WHERE id = ?");
                $stmt->execute([$apiKey, $ambiente, $ativo, $exists['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO configuracoes_pagamento (unidade_id, gateway, api_key, ambiente, ativo) VALUES (?, 'asaas', ?, ?, ?)");
                $stmt->execute([$unidade_id, $apiKey, $ambiente, $ativo]);
            }

            // Só um gateway pode estar "ativo" por vez para a unidade.
            if ($ativo) {
                $pdo->prepare("UPDATE configuracoes_pagamento SET ativo = 0 WHERE unidade_id = ? AND gateway != 'asaas'")->execute([$unidade_id]);
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
$stmt = $pdo->prepare("SELECT * FROM configuracoes_pagamento WHERE unidade_id = ? AND gateway = 'asaas'");
$stmt->execute([$unidade_id]);
$config = $stmt->fetch();

$apiKey = $config['api_key'] ?? '';
$ambiente = $config['ambiente'] ?? 'sandbox';
$ativo = !empty($config['ativo']);

$custom_title = "Pagamentos Online (Asaas)";
include 'header.php';
?>

<style>
    .asaas-field-label { display:block; font-size: 11px; font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em; }
    .asaas-grid { display: grid; grid-template-columns: 1fr 200px; gap: 25px; }
    @media (max-width: 640px) { .asaas-grid { grid-template-columns: 1fr; } }
    .asaas-webhook-row { display: flex; gap: 0; flex-wrap: wrap; }
    .asaas-webhook-row code { flex: 1; min-width: 220px; background: rgba(255,255,255,0.05); padding: 12px 15px; font-family: monospace; font-size: var(--fs-sm); color: #fff; border: 1px solid rgba(255,255,255,0.1); word-break: break-all; }
    .asaas-webhook-row button { flex-shrink: 0; }
</style>

<section class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; flex-wrap: wrap; gap: 15px;">
        <div style="border-left: 8px solid var(--primary-gold); padding-left: 20px;">
            <h1 style="font-size: 2rem; font-weight: 900; color: #133080; margin: 0; text-transform: uppercase; letter-spacing: -0.03em;">Integração Financeira</h1>
            <p style="color: var(--text-muted); font-size: 14px; margin: 5px 0 0 0; font-weight: 500;">Conecte sua conta Asaas para automatizar cobranças, PIX e baixas de mensalidades.</p>
        </div>
        <a href="financeiro.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;"><i class="fa-solid fa-arrow-left me-2"></i> Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div style="background: #dcfce7; color: #166534; padding: 20px; border-left: 5px solid #22c55e; margin-bottom: 30px; font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: 0.05em;">
            <i class="fa-solid fa-check-circle me-2"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-left: 5px solid #ef4444; margin-bottom: 30px; font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: 0.05em;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <div style="max-width: 800px;">
        <div style="display:inline-flex; align-items:center; gap:8px; padding: 8px 16px; margin-bottom: 24px; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; <?php echo $apiKey ? 'background:#dcfce7; color:#166534;' : 'background:#f1f5f9; color:#64748b;'; ?>">
            <i class="fa-solid <?php echo $apiKey ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
            <?php echo $apiKey ? 'Conectado' : 'Não configurado'; ?>
        </div>

        <form method="POST">
            <div style="background: white; border: 1px solid var(--border-color); padding: 40px; margin-bottom: 30px;">
                <h3 style="font-size: 14px; font-weight: 900; color: #133080; text-transform: uppercase; margin-bottom: 25px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; display: flex; align-items: center; gap: 12px;">
                    <i class="fa-solid fa-key"></i> Credenciais
                </h3>
                <div class="asaas-grid">
                    <div class="form-group">
                        <label class="asaas-field-label">Chave da API (Asaas Token)</label>
                        <div style="position:relative;">
                            <input type="password" name="api_key" id="api_key" value="<?php echo htmlspecialchars($apiKey); ?>" class="form-control" placeholder="$aact_..." required style="padding-right: 45px;">
                            <button type="button" onclick="toggleVisibility('api_key')" style="position:absolute; right:15px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#94a3b8;">
                                <i class="fa-solid fa-eye" id="eye-icon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="asaas-field-label">Ambiente</label>
                        <select name="ambiente" class="form-control">
                            <option value="sandbox" <?php echo $ambiente == 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testes)</option>
                            <option value="producao" <?php echo $ambiente == 'producao' ? 'selected' : ''; ?>>Produção (Real)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 25px;">
                    <label style="display:flex; align-items:center; gap:10px; font-weight: 800; font-size: 13px; cursor:pointer;">
                        <input type="checkbox" name="ativo" value="1" <?php echo $ativo ? 'checked' : ''; ?> style="width:18px; height:18px;">
                        Usar Asaas como gateway de pagamento ativo desta unidade
                    </label>
                    <p style="font-size: 11px; color: var(--text-muted); margin: 6px 0 0 28px;">Apenas um gateway (Asaas ou Cora) fica ativo por vez. Marcar aqui desativa o Cora automaticamente.</p>
                </div>
            </div>

            <div style="background: #1e293b; color: #fff; padding: 25px; border-left: 6px solid var(--primary-green); margin-bottom: 30px;">
                <h4 style="font-size: 11px; font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin: 0 0 10px 0; letter-spacing: 0.05em; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-bolt"></i> Webhook (Baixa Automática)
                </h4>
                <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; margin-bottom: 15px;">Para que o SHIAI PRO confirme os pagamentos automaticamente, copie a URL abaixo e cole no painel do Asaas (Configurações → Webhooks).</p>
                <div class="asaas-webhook-row">
                    <code id="webhook_url">https://shiaipro.com.br/Gestor/webhook_asaas.php</code>
                    <button type="button" onclick="copyWebhook()" class="btn-sq" style="height: auto; width: auto; padding: 0 20px; font-size: var(--fs-xs); background: #fff; color: #08153a;">COPIAR URL</button>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-sq" style="width: auto; padding: 12px 30px;"><i class="fa-solid fa-floppy-disk me-2"></i>Salvar Credenciais</button>
                <a href="financeiro.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px; text-decoration: none;">Cancelar</a>
            </div>
        </form>
    </div>
</section>

<script>
    function toggleVisibility(id) {
        const x = document.getElementById(id);
        const icon = document.getElementById('eye-icon');
        if (x.type === "password") {
            x.type = "text";
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            x.type = "password";
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function copyWebhook() {
        const text = document.getElementById('webhook_url').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('URL do Webhook copiada!');
        });
    }
</script>

<?php include 'footer.php'; ?>