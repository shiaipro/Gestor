<?php
// Endpoint público chamado pelo Asaas quando o status de uma cobrança muda.
// Cadastrar esta URL no painel do Asaas de cada unidade (Configurações > Integrações > Webhooks).
require_once 'config.php';

http_response_code(200); // Sempre responde 200 para o Asaas não ficar reenviando o evento.

$payload = json_decode(file_get_contents('php://input'), true);
$evento = $payload['event'] ?? '';
$asaas_payment_id = $payload['payment']['id'] ?? null;

$eventos_confirmacao = ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'];

if (!$asaas_payment_id || !in_array($evento, $eventos_confirmacao)) {
    echo json_encode(['ok' => true]);
    exit;
}

try {
    // 1) Mensalidades
    $stmt = $pdo->prepare("SELECT * FROM mensalidades WHERE asaas_payment_id = ? LIMIT 1");
    $stmt->execute([$asaas_payment_id]);
    $mensalidade = $stmt->fetch();

    if ($mensalidade && $mensalidade['status'] !== 'pago') {
        // Nunca confia cegamente no corpo do webhook: reconsulta o pagamento direto na API do
        // Asaas usando a chave da própria unidade antes de dar baixa, evitando chamadas forjadas.
        $asaas = getAsaasClientParaUnidade($pdo, $mensalidade['unidade_id']);
        if ($asaas) {
            $pagamento_real = $asaas->getPayment($asaas_payment_id);
            $status_real = $pagamento_real['status'] ?? '';

            if (in_array($status_real, ['CONFIRMED', 'RECEIVED'])) {
                $pdo->prepare("UPDATE mensalidades SET status = 'pago', data_pagamento = CURDATE(), forma_pagamento = 'pix' WHERE id = ? AND unidade_id = ?")
                    ->execute([$mensalidade['id'], $mensalidade['unidade_id']]);
                registrarComissoesMensalidade($mensalidade['id'], $mensalidade['unidade_id']);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // 2) Taxas de exame de faixa
    $stmt = $pdo->prepare("SELECT * FROM eventos_graduacao_inscricoes WHERE asaas_payment_id = ? LIMIT 1");
    $stmt->execute([$asaas_payment_id]);
    $inscricao = $stmt->fetch();

    if ($inscricao && $inscricao['status_pagamento'] !== 'pago') {
        $stmt_evento = $pdo->prepare("SELECT unidade_id FROM eventos_graduacao WHERE id = ?");
        $stmt_evento->execute([$inscricao['evento_id']]);
        $unidade_id_evento = $stmt_evento->fetchColumn();

        $asaas = $unidade_id_evento ? getAsaasClientParaUnidade($pdo, $unidade_id_evento) : null;
        if ($asaas) {
            $pagamento_real = $asaas->getPayment($asaas_payment_id);
            $status_real = $pagamento_real['status'] ?? '';

            if (in_array($status_real, ['CONFIRMED', 'RECEIVED'])) {
                $pdo->prepare("UPDATE eventos_graduacao_inscricoes SET status_pagamento = 'pago' WHERE id = ?")
                    ->execute([$inscricao['id']]);
            }
        }
    }

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    // Não expõe detalhes ao Asaas; loga silenciosamente e responde 200 do mesmo jeito.
    echo json_encode(['ok' => true]);
}
