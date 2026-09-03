<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

garantirColunasConfiguracoesPagamento($pdo);

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'registrar_webhook') {
    try {
        $cora = getCoraClientParaUnidade($pdo, $unidade_id);
        if (!$cora) {
            throw new Exception('Salve o Client ID + certificado + chave antes de registrar o webhook.');
        }
        $cora->createWebhookEndpoint('https://shiaipro.com.br/Gestor/webhook_cora.php', 'invoice', 'paid');
        $mensagem = "Webhook registrado com sucesso na Cora! A baixa automática já está ativa.";
    } catch (Exception $e) {
        $erro = "Falha ao registrar webhook: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') !== 'registrar_webhook') {
    $clientId = trim($_POST['client_id'] ?? '');
    $ambiente = $_POST['ambiente'] ?? 'sandbox';
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("SELECT id, cert_path, key_path FROM configuracoes_pagamento WHERE unidade_id = ? AND gateway = 'cora'");
        $stmt->execute([$unidade_id]);
        $exists = $stmt->fetch();

        $certPath = $exists['cert_path'] ?? null;
        $keyPath = $exists['key_path'] ?? null;

        if (!empty($_FILES['certificado']['tmp_name'])) {
            $dir = getCoraCertDir($unidade_id);
            $certPath = $dir . '/certificate.pem';
            if (!move_uploaded_file($_FILES['certificado']['tmp_name'], $certPath)) {
                throw new Exception('Falha ao salvar o certificado.');
            }
        }
        if (!empty($_FILES['chave_privada']['tmp_name'])) {
            $dir = getCoraCertDir($unidade_id);
            $keyPath = $dir . '/private-key.key';
            if (!move_uploaded_file($_FILES['chave_privada']['tmp_name'], $keyPath)) {
                throw new Exception('Falha ao salvar a chave privada.');
            }
        }

        if (!$clientId) {
            throw new Exception('O Client ID é obrigatório.');
        }
        if (!$certPath || !$keyPath) {
            throw new Exception('É necessário enviar o certificado (.pem) e a chave privada (.key).');
        }

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE configuracoes_pagamento SET api_key = ?, cert_path = ?, key_path = ?, ambiente = ?, ativo = ? WHERE id = ?");
            $stmt->execute([$clientId, $certPath, $keyPath, $ambiente, $ativo, $exists['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO configuracoes_pagamento (unidade_id, gateway, api_key, cert_path, key_path, ambiente, ativo) VALUES (?, 'cora', ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $clientId, $certPath, $keyPath, $ambiente, $ativo]);
        }

        // Só um gateway pode estar "ativo" por vez para a unidade.
        if ($ativo) {
            $pdo->prepare("UPDATE configuracoes_pagamento SET ativo = 0 WHERE unidade_id = ? AND gateway != 'cora'")->execute([$unidade_id]);
        }

        $mensagem = "Configurações do Cora atualizadas com sucesso!";
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT * FROM configuracoes_pagamento WHERE unidade_id = ? AND gateway = 'cora'");
$stmt->execute([$unidade_id]);
$config = $stmt->fetch();

$clientId = $config['api_key'] ?? '';
$ambiente = $config['ambiente'] ?? 'sandbox';
$ativo = !empty($config['ativo']);
$temCertificado = !empty($config['cert_path']) && is_file($config['cert_path']);
$temChave = !empty($config['key_path']) && is_file($config['key_path']);

$custom_title = "Pagamentos Online (Cora)";
include 'header.php';
?>

<style>
    .cora-field-label { display:block; font-size: 11px; font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em; }
    .cora-grid { display: grid; grid-template-columns: 1fr 200px; gap: 25px; }
    @media (max-width: 640px) { .cora-grid { grid-template-columns: 1fr; } }
    .cora-webhook-row { display: flex; gap: 0; flex-wrap: wrap; }
    .cora-webhook-row code { flex: 1; min-width: 220px; background: rgba(255,255,255,0.05); padding: 12px 15px; font-family: monospace; font-size: var(--fs-sm); color: #fff; border: 1px solid rgba(255,255,255,0.1); word-break: break-all; }
    .cora-webhook-row button { flex-shrink: 0; }
    .cora-file-status { display:inline-flex; align-items:center; gap:6px; font-size: 11px; font-weight: 800; margin-top: 8px; }
</style>

<section class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; flex-wrap: wrap; gap: 15px;">
        <div style="border-left: 8px solid var(--primary-gold); padding-left: 20px;">
            <h1 style="font-size: 2rem; font-weight: 900; color: #133080; margin: 0; text-transform: uppercase; letter-spacing: -0.03em;">Integração Cora</h1>
            <p style="color: var(--text-muted); font-size: 14px; margin: 5px 0 0 0; font-weight: 500;">Conecte sua conta Cora (certificado mTLS) para gerar boleto + PIX de mensalidades e taxas de exame.</p>
        </div>
        <a href="financeiro.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;"><i class="fa-solid fa-arrow-left me-2"></i> Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div style="background: #dcfce7; color: #166534; padding: 20px; border-left: 5px solid #22c55e; margin-bottom: 30px; font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: 0.05em;">
            <i class="fa-solid fa-check-circle me-2"></i> <?php echo htmlspecialchars($mensagem); ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-left: 5px solid #ef4444; margin-bottom: 30px; font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: 0.05em;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($erro); ?>
        </div>
    <?php endif; ?>

    <div style="max-width: 800px;">
        <div style="display:inline-flex; align-items:center; gap:8px; padding: 8px 16px; margin-bottom: 24px; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; <?php echo ($clientId && $temCertificado && $temChave) ? 'background:#dcfce7; color:#166534;' : 'background:#f1f5f9; color:#64748b;'; ?>">
            <i class="fa-solid <?php echo ($clientId && $temCertificado && $temChave) ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
            <?php echo ($clientId && $temCertificado && $temChave) ? 'Configurado' : 'Não configurado'; ?>
            <?php if ($ativo): ?> &middot; GATEWAY ATIVO<?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div style="background: white; border: 1px solid var(--border-color); padding: 40px; margin-bottom: 30px;">
                <h3 style="font-size: 14px; font-weight: 900; color: #133080; text-transform: uppercase; margin-bottom: 25px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; display: flex; align-items: center; gap: 12px;">
                    <i class="fa-solid fa-key"></i> Credenciais
                </h3>
                <div class="cora-grid" style="margin-bottom: 25px;">
                    <div class="form-group">
                        <label class="cora-field-label">Client ID (Cora)</label>
                        <input type="text" name="client_id" value="<?php echo htmlspecialchars($clientId); ?>" class="form-control" placeholder="app-xxxxxxxx" required>
                    </div>
                    <div class="form-group">
                        <label class="cora-field-label">Ambiente</label>
                        <select name="ambiente" class="form-control">
                            <option value="sandbox" <?php echo $ambiente == 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testes)</option>
                            <option value="producao" <?php echo $ambiente == 'producao' ? 'selected' : ''; ?>>Produção (Real)</option>
                        </select>
                    </div>
                </div>
                <div class="cora-grid">
                    <div class="form-group">
                        <label class="cora-field-label">Certificado (.pem)</label>
                        <input type="file" name="certificado" accept=".pem,.crt" class="form-control">
                        <div class="cora-file-status" style="color: <?php echo $temCertificado ? '#166534' : '#94a3b8'; ?>;">
                            <i class="fa-solid <?php echo $temCertificado ? 'fa-circle-check' : 'fa-circle-minus'; ?>"></i>
                            <?php echo $temCertificado ? 'Certificado enviado' : 'Nenhum certificado enviado ainda'; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="cora-field-label">Chave Privada (.key)</label>
                        <input type="file" name="chave_privada" accept=".key,.pem" class="form-control">
                        <div class="cora-file-status" style="color: <?php echo $temChave ? '#166534' : '#94a3b8'; ?>;">
                            <i class="fa-solid <?php echo $temChave ? 'fa-circle-check' : 'fa-circle-minus'; ?>"></i>
                            <?php echo $temChave ? 'Chave enviada' : 'Nenhuma chave enviada ainda'; ?>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 25px;">
                    <label style="display:flex; align-items:center; gap:10px; font-weight: 800; font-size: 13px; cursor:pointer;">
                        <input type="checkbox" name="ativo" value="1" <?php echo $ativo ? 'checked' : ''; ?> style="width:18px; height:18px;">
                        Usar Cora como gateway de pagamento ativo desta unidade
                    </label>
                    <p style="font-size: 11px; color: var(--text-muted); margin: 6px 0 0 28px;">Apenas um gateway (Asaas ou Cora) fica ativo por vez. Marcar aqui desativa o Asaas automaticamente.</p>
                </div>
            </div>

            <div style="background: #1e293b; color: #fff; padding: 25px; border-left: 6px solid var(--primary-green); margin-bottom: 30px;">
                <h4 style="font-size: 11px; font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin: 0 0 10px 0; letter-spacing: 0.05em; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-bolt"></i> Webhook (Baixa Automática)
                </h4>
                <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; margin-bottom: 15px;">Cadastre esta URL no painel do Cora como endpoint de notificações (recurso "invoice", evento "paid") para que o SHIAI PRO confirme os pagamentos automaticamente.</p>
                <div class="cora-webhook-row">
                    <code id="webhook_url">https://shiaipro.com.br/Gestor/webhook_cora.php</code>
                    <button type="button" onclick="copyWebhook()" class="btn-sq" style="height: auto; width: auto; padding: 0 20px; font-size: var(--fs-xs); background: #fff; color: #08153a;">COPIAR URL</button>
                </div>
                <button type="button" onclick="registrarWebhook(this)" class="btn-sq" style="width: auto; padding: 10px 25px; margin-top: 15px; background: var(--primary-green);">
                    <i class="fa-solid fa-bolt me-2"></i> Registrar Webhook na Cora
                </button>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-sq" style="width: auto; padding: 12px 30px;"><i class="fa-solid fa-floppy-disk me-2"></i>Salvar Credenciais</button>
                <a href="financeiro.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px; text-decoration: none;">Cancelar</a>
            </div>
        </form>
    </div>
</section>

<script>
    function copyWebhook() {
        const text = document.getElementById('webhook_url').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('URL do Webhook copiada!');
        });
    }

    function registrarWebhook(btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Registrando...';

        const body = new URLSearchParams({ acao: 'registrar_webhook' });
        fetch(window.location.href, { method: 'POST', body })
            .then(() => window.location.reload())
            .catch(() => {
                alert('Falha na requisição. Tente novamente.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bolt me-2"></i> Registrar Webhook na Cora';
            });
    }
</script>

<?php include 'footer.php'; ?>
