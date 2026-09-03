<?php
require_once '../config.php';
require_once '../assets/lib/AsaasClient.php';

$custom_title = "Faturas Recorrentes (Assinaturas)";
include 'header.php';

// Carregar Configuração Asaas do Mestre
$config = $pdo->query("SELECT * FROM configuracoes_pagamento WHERE unidade_id IS NULL AND gateway = 'asaas'")->fetch();
$apiKey = $config['api_key'] ?? null;
$ambiente = $config['ambiente'] ?? 'sandbox';

$asaasClient = null;
if ($apiKey) {
    try {
        $asaasClient = new AsaasClient($apiKey, $ambiente == 'sandbox');
    } catch (Exception $e) {
        $erro = "Erro ao conectar Asaas: " . $e->getMessage();
    }
}

// Ação: Sincronizar Assinaturas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sincronizar_assinaturas'])) {
    if (!$asaasClient) {
        $erro = "Configure o Asaas primeiro!";
    } else {
        $cont_novas = 0;
        $cont_atualizadas = 0;
        $cont_erros = 0;
        $log_erros = '';

        // Buscar unidades ativas que tenham valor manual ou um plano associado
        $unidades = $pdo->query("SELECT u.*, p.valor as plano_valor 
                                 FROM unidades u 
                                 LEFT JOIN planos_mestre p ON u.plano_id = p.id 
                                 WHERE u.status = 'ativo' 
                                 AND (u.valor_mensalidade > 0 OR p.valor > 0)")->fetchAll();

        foreach ($unidades as $uni) {
            try {
                // 1. Validar Documento
                if (empty($uni['documento'])) {
                    throw new Exception("Documento (CPF/CNPJ) não cadastrado.");
                }

                // 2. Garantir Cliente no Asaas
                $customerId = $uni['asaas_customer_id'];
                if (!$customerId) {
                    // Buscar Admin da Unidade para dados
                    $adm = $pdo->prepare("SELECT email FROM usuarios WHERE unidade_id = ? AND nivel = 'admin' LIMIT 1");
                    $adm->execute([$uni['id']]);
                    $adminData = $adm->fetch();

                    $customerId = $asaasClient->createCustomer($uni['nome'], $uni['documento'], $adminData['email'] ?? 'email@teste.com');
                    $pdo->prepare("UPDATE unidades SET asaas_customer_id = ? WHERE id = ?")->execute([$customerId, $uni['id']]);
                }

                // 3. Verificar/Criar Assinatura
                $subscriptionId = $uni['asaas_subscription_id'];
                $diaVenc = $uni['dia_vencimento'];

                // Calcular próxima data de vencimento válida
                $nextDueDate = date('Y-m') . '-' . str_pad($diaVenc, 2, '0', STR_PAD_LEFT);
                if (date('Y-m-d') > $nextDueDate) {
                    $nextDueDate = date('Y-m-d', strtotime('+1 month', strtotime($nextDueDate)));
                }

                if ($subscriptionId) {
                    // Atualizar Assinatura existente (se valor ou dia mudou)
                    // TODO: Poderíamos checar na API antes, mas update é idempotente na maioria.
                    // Para economizar request, vamos assumir que o botão serve para FORÇAR atualização.
                    $valorCobrar = ($uni['valor_mensalidade'] > 0) ? $uni['valor_mensalidade'] : $uni['plano_valor'];
                    try {
                        $asaasClient->updateSubscription($subscriptionId, [
                            'value' => $valorCobrar,
                            'nextDueDate' => $nextDueDate, // Opcional no update, mas bom garantir
                            // 'cycle' => 'MONTHLY'
                        ]);
                        $cont_atualizadas++;
                    } catch (Exception $e) {
                        // Se der 404, id invalido, criar nova?
                        if (strpos($e->getMessage(), '404') !== false) {
                            $valorCobrar = ($uni['valor_mensalidade'] > 0) ? $uni['valor_mensalidade'] : $uni['plano_valor'];
                            $resp = $asaasClient->createSubscription($customerId, $valorCobrar, $nextDueDate, "Mensalidade Sistema SENPIPE");
                            $pdo->prepare("UPDATE unidades SET asaas_subscription_id = ? WHERE id = ?")->execute([$resp['id'], $uni['id']]);
                            $cont_novas++;
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    // Criar Nova Assinatura
                    $valorCobrar = ($uni['valor_mensalidade'] > 0) ? $uni['valor_mensalidade'] : $uni['plano_valor'];
                    $resp = $asaasClient->createSubscription($customerId, $valorCobrar, $nextDueDate, "Mensalidade Sistema SENPIPE");
                    $pdo->prepare("UPDATE unidades SET asaas_subscription_id = ? WHERE id = ?")->execute([$resp['id'], $uni['id']]);
                    $cont_novas++;
                }

            } catch (Exception $e) {
                $cont_erros++;
                $log_erros .= "Unidade " . htmlspecialchars($uni['nome']) . ": " . $e->getMessage() . "<br>";
            }
        }
        $mensagem = "Sincronização concluída: $cont_novas assinaturas criadas, $cont_atualizadas atualizadas. $cont_erros erros.";
        if ($log_erros)
            $erro = $log_erros;
    }
}

// Listar Unidades com Status da Assinatura e Dados do Plano
$unidades_list = $pdo->query("SELECT u.*, p.nome as plano_nome, p.valor as plano_valor 
                              FROM unidades u 
                              LEFT JOIN planos_mestre p ON u.plano_id = p.id 
                              WHERE u.status != 'suspenso' 
                              ORDER BY u.nome ASC")->fetchAll();
?>



<?php if (isset($mensagem) && $mensagem): ?>
    <div class="alert" style="background:#dcfce7; color:#166534; padding:1rem; margin-bottom:1rem; border-radius:0.5rem;">
        <?php echo $mensagem; ?>
    </div>
<?php endif; ?>

<?php if (isset($erro) && $erro): ?>
    <div class="alert" style="background:#fee2e2; color:#991b1b; padding:1rem; margin-bottom:1rem; border-radius:0.5rem;">
        <?php echo $erro; ?>
    </div>
<?php endif; ?>

<div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom:1.5rem;">
    <form method="POST">
        <button type="submit" name="sincronizar_assinaturas" class="btn btn-primary"
            onclick="return confirm('Isso irá criar ou atualizar as assinaturas de TODAS as unidades ativas no Asaas com os valores atuais. Confirmar?');">
            <i class="fa-solid fa-sync"></i> Sincronizar Assinaturas (Asaas)
        </button>
    </form>
</div>

<div class="card">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background:#f8fafc; text-align:left; border-bottom:1px solid var(--border);">
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Unidade</th>
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Documento</th>
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Valor Mensal</th>
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Vencimento</th>
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Status Asaas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($unidades_list as $u): ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:1rem; font-weight:600;">
                        <?php echo htmlspecialchars($u['nome']); ?>
                        <div style="font-size:0.75rem; color:var(--text-muted); font-weight:400;">
                            <?php echo $u['slug']; ?>
                        </div>
                    </td>
                    <td style="padding:1rem; font-size:0.85rem;">
                        <?php echo $u['documento'] ? htmlspecialchars($u['documento']) : '<span style="color:red; font-size:0.75rem;">Pend. Documento</span>'; ?>
                    </td>
                    <td style="padding:1rem; font-weight:600;">
                        <?php
                        $valorExibir = ($u['valor_mensalidade'] > 0) ? $u['valor_mensalidade'] : ($u['plano_valor'] ?? 0);
                        echo "R$ " . number_format($valorExibir, 2, ',', '.');
                        if ($u['plano_nome'])
                            echo "<br><small style='color:var(--text-muted); font-weight:400;'>Plano: {$u['plano_nome']}</small>";
                        ?>
                    </td>
                    <td style="padding:1rem;">Dia <?php echo $u['dia_vencimento']; ?></td>
                    <td style="padding:1rem;">
                        <?php if ($u['asaas_subscription_id']): ?>
                            <span class="badge badge-ativo" style="background:#dcfce7; color:#166534;"><i
                                    class="fa-solid fa-check"></i> Ativa
                                (<?php echo substr($u['asaas_subscription_id'], -6); ?>)</span>
                        <?php else: ?>
                            <span class="badge" style="background:#f1f5f9; color:#64748b;">Nenhuma</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="margin-top:2rem;">
    <h3 style="font-size:1.2rem; font-weight:700; margin-bottom:1rem;">Histórico Recente de Pagamentos (Do
        Asaas)</h3>
    <!-- Aqui poderíamos listar os últimos pagamentos via API -->
    <p style="color:var(--text-muted);">Implementação futura: webhook para listar pagamentos em tempo real.</p>
</div>



<?php include 'footer.php'; ?>